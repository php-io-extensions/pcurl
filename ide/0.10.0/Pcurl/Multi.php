<?php

namespace Pcurl;

class Multi
{


    /**
     * @param mixed $multi
     * @param int $option
     * @param mixed $value
     * @return int
     */
    public static function curlMultiSetopt($multi, int $option, $value): int
    {
    }

    /**
     * @param mixed $multi
     * @param int $s
     * @param int $evBitmask
     * @return array
     */
    public static function curlMultiSocketAction($multi, int $s, int $evBitmask): array
    {
    }

    /**
     * @param mixed $multi
     * @return array
     */
    public static function curlMultiFdset($multi): array
    {
    }

    /**
     * @param mixed $multi
     * @return array
     */
    public static function curlMultiTimeout($multi): array
    {
    }

    /**
     * @param mixed $multi
     * @param array $extraFds
     * @param int $timeoutMs
     * @return array
     */
    public static function curlMultiPoll($multi, array $extraFds, int $timeoutMs): array
    {
    }

    /**
     * @param mixed $multi
     * @param array $extraFds
     * @param int $timeoutMs
     * @return array
     */
    public static function curlMultiWait($multi, array $extraFds, int $timeoutMs): array
    {
    }

    /**
     * @param mixed $multi
     * @return int
     */
    public static function curlMultiWakeup($multi): int
    {
    }

    /**
     * @param mixed $multi
     * @param int $s
     * @param mixed $sockp
     * @return int
     */
    public static function curlMultiAssign($multi, int $s, $sockp): int
    {
    }

    /**
     * @param int $code
     * @return string
     */
    public static function curlMultiStrerror(int $code): string
    {
    }
}
