<?php

declare(strict_types=1);

use Pcurl\Multi;

if (! extension_loaded('pcurl')) {
    throw new RuntimeException('The pcurl extension is not loaded; run pest with -d extension=/path/to/pcurl.so (ext-curl must load first)');
}

/*
 * libcurl constants pcurl leaves to its callers. ext-curl defines some CURLM_* codes already;
 * a guard keeps these valid should a later PHP add the rest.
 */
foreach ([
    'CURLMOPT_SOCKETDATA' => 10002,
    'CURLMOPT_TIMERDATA' => 10005,
    'CURLMOPT_SOCKETFUNCTION' => 20001,
    'CURLMOPT_TIMERFUNCTION' => 20004,
    'CURL_SOCKET_TIMEOUT' => -1,
    'CURL_POLL_IN' => 1,
    'CURL_POLL_OUT' => 2,
    'CURL_POLL_INOUT' => 3,
    'CURL_POLL_REMOVE' => 4,
    'CURL_CSELECT_IN' => 1,
    'CURL_CSELECT_OUT' => 2,
    'CURL_CSELECT_ERR' => 4,
    'CURL_WAIT_POLLIN' => 1,
    'CURL_WAIT_POLLOUT' => 4,
    'CURLM_OK' => 0,
    'CURLM_BAD_HANDLE' => 1,
    'CURLM_BAD_SOCKET' => 5,
    'CURLM_UNKNOWN_OPTION' => 6,
    'CURLM_RECURSIVE_API_CALL' => 8,
    'CURLM_BAD_FUNCTION_ARGUMENT' => 10,
    'CURLM_ABORTED_BY_CALLBACK' => 11,
] as $name => $value) {
    defined($name) || define($name, $value);
}

/**
 * Base URL of a PHP built-in server on tests/fixtures, started once per run and
 * terminated at shutdown. Transfers go to "$base/<path>" and get back "hello /<path>".
 */
function localServer(): string
{
    static $base = null;
    if ($base !== null) {
        return $base;
    }

    $probe = stream_socket_server('tcp://127.0.0.1:0') ?: throw new RuntimeException('no free port');
    $address = stream_socket_get_name($probe, false);
    fclose($probe);

    $server = proc_open(
        [PHP_BINARY, '-n', '-S', $address, '-t', __DIR__ . '/fixtures'],
        [1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']],
        $pipes,
    ) ?: throw new RuntimeException('could not start the built-in server');
    register_shutdown_function(static function () use ($server): void {
        proc_terminate($server);
        proc_close($server);
    });

    // Refused connects while the server boots are expected; Pest reports even @-suppressed warnings.
    set_error_handler(static fn (): bool => true);
    try {
        for ($deadline = microtime(true) + 5; microtime(true) < $deadline; usleep(10_000)) {
            $socket = stream_socket_client("tcp://{$address}", $errno, $errstr, 0.1);
            if ($socket) {
                fclose($socket);

                return $base = "http://{$address}";
            }
        }
    } finally {
        restore_error_handler();
    }

    throw new RuntimeException("built-in server never listened on {$address}");
}

/** An easy handle for "$path" on the local server that keeps its body for curl_multi_getcontent(). */
function easyFor(string $path): CurlHandle
{
    $easy = curl_init(localServer() . $path);
    curl_setopt($easy, CURLOPT_RETURNTRANSFER, true);

    return $easy;
}

/**
 * Drives $multi with the socket API until no transfer is running: installs CURLMOPT_SOCKETFUNCTION and
 * CURLMOPT_TIMERFUNCTION, adds $easies (after the callbacks, so libcurl reports the add's timer too),
 * waits on curlSocketStream() duplicates with stream_select, and feeds readiness back through
 * curlMultiSocketAction. $onSocket and $onTimer see every callback's arguments first; $onTick runs once
 * per loop pass, outside any callback, where multi calls are allowed. Both options are set back to null
 * on return. Throws when $seconds pass with a transfer still running.
 *
 * @param list<CurlHandle> $easies
 */
function drive(
    CurlMultiHandle $multi,
    array $easies = [],
    ?callable $onSocket = null,
    ?callable $onTimer = null,
    ?callable $onTick = null,
    float $seconds = 5.0,
): void {
    /** @var array<int, array{stream: resource, what: int}> $watched */
    $watched = [];
    $timerAt = null;

    Multi::curlMultiSetopt($multi, CURLMOPT_TIMERFUNCTION, function (CurlMultiHandle $m, int $timeoutMs, mixed $clientp) use (&$timerAt, $onTimer): int {
        $timerAt = $timeoutMs < 0 ? null : microtime(true) + $timeoutMs / 1000;
        if ($onTimer) {
            $onTimer($m, $timeoutMs, $clientp);
        }

        return 0;
    });
    Multi::curlMultiSetopt($multi, CURLMOPT_SOCKETFUNCTION, function (?CurlHandle $easy, int $s, int $what, mixed $clientp, mixed $socketp) use (&$watched, $onSocket): int {
        if ($onSocket) {
            $onSocket($easy, $s, $what, $clientp, $socketp);
        }
        if ($what === CURL_POLL_REMOVE) {
            if (isset($watched[$s])) {
                fclose($watched[$s]['stream']);
                unset($watched[$s]);
            }

            return 0;
        }
        $watched[$s] ??= ['stream' => Multi::curlSocketStream($s), 'what' => 0];
        $watched[$s]['what'] = $what;

        return 0;
    });

    try {
        foreach ($easies as $easy) {
            curl_multi_add_handle($multi, $easy) === CURLM_OK || throw new RuntimeException('curl_multi_add_handle failed');
        }
        $result = Multi::curlMultiSocketAction($multi, CURL_SOCKET_TIMEOUT, 0);
        $deadline = microtime(true) + $seconds;
        while ($result['running_handles'] > 0) {
            if (microtime(true) > $deadline) {
                throw new RuntimeException("{$result['running_handles']} transfer(s) still running after {$seconds}s");
            }
            if ($onTick) {
                $onTick();
            }
            $read = $write = [];
            foreach ($watched as $s => $entry) {
                if ($entry['what'] & CURL_POLL_IN) {
                    $read[$s] = $entry['stream'];
                }
                if ($entry['what'] & CURL_POLL_OUT) {
                    $write[$s] = $entry['stream'];
                }
            }
            $except = null;
            if ($read || $write) {
                // stream_select keeps the arrays' keys: the socket numbers come back with the ready streams.
                stream_select($read, $write, $except, 0, 20_000);
            } else {
                usleep(20_000);
            }
            $ready = [];
            foreach ($read as $s => $stream) {
                $ready[$s] = CURL_CSELECT_IN;
            }
            foreach ($write as $s => $stream) {
                $ready[$s] = ($ready[$s] ?? 0) | CURL_CSELECT_OUT;
            }
            foreach ($ready as $s => $bitmask) {
                $result = Multi::curlMultiSocketAction($multi, $s, $bitmask);
            }
            if ($timerAt !== null && microtime(true) >= $timerAt) {
                $timerAt = null;
                $result = Multi::curlMultiSocketAction($multi, CURL_SOCKET_TIMEOUT, 0);
            }
        }
    } finally {
        Multi::curlMultiSetopt($multi, CURLMOPT_SOCKETFUNCTION, null);
        Multi::curlMultiSetopt($multi, CURLMOPT_TIMERFUNCTION, null);
        foreach ($watched as $entry) {
            fclose($entry['stream']);
        }
    }
}

/**
 * The descriptor number behind a stream. Linux: the /proc/self/fd link that names the stream's inode
 * ("socket:[ino]" for a socket). Elsewhere: the /dev/fd entry whose inode and mode match its fstat().
 *
 * @param resource $stream
 */
function fdOf($stream): int
{
    // Descriptor entries are reused across descriptors; cached stats go stale.
    clearstatcache();
    $want = fstat($stream);
    // Entries that cannot be read (the descriptor scandir() itself used) cannot be the stream.
    set_error_handler(static fn (): bool => true);
    try {
        if (PHP_OS_FAMILY === 'Linux') {
            foreach (scandir('/proc/self/fd') as $entry) {
                $link = ctype_digit($entry) ? readlink("/proc/self/fd/{$entry}") : false;
                if ($link === "socket:[{$want['ino']}]") {
                    return (int) $entry;
                }
            }
        } else {
            foreach (scandir('/dev/fd') as $entry) {
                $st = ctype_digit($entry) ? stat("/dev/fd/{$entry}") : false;
                if ($st !== false && $st['ino'] === $want['ino'] && $st['mode'] === $want['mode']) {
                    return (int) $entry;
                }
            }
        }
    } finally {
        restore_error_handler();
    }

    throw new RuntimeException('No descriptor entry matches the stream');
}

/**
 * A connected UNIX socket pair plus each end's descriptor number.
 *
 * @return array{0: resource, 1: resource, 2: int, 3: int}
 */
function socketPairWithFds(): array
{
    [$a, $b] = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, 0);

    return [$a, $b, fdOf($a), fdOf($b)];
}
