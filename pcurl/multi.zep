namespace Pcurl;

class Multi
{
    public static function curlMultiSetopt(var multi, int option, var value) -> int
    {
        return setopt_multi(multi, option, value);
    }

    public static function curlMultiSocketAction(var multi, int s, int evBitmask) -> array
    {
        var result;

        let result = socket_action_multi(multi, s, evBitmask);

        return result;
    }

    public static function curlMultiFdset(var multi) -> array
    {
        var result;

        let result = fdset_multi(multi);

        return result;
    }

    public static function curlMultiTimeout(var multi) -> array
    {
        var result;

        let result = timeout_multi(multi);

        return result;
    }

    public static function curlMultiPoll(var multi, array extraFds, int timeoutMs) -> array
    {
        var result;

        let result = poll_multi(multi, extraFds, timeoutMs);

        return result;
    }

    public static function curlMultiWait(var multi, array extraFds, int timeoutMs) -> array
    {
        var result;

        let result = wait_multi(multi, extraFds, timeoutMs);

        return result;
    }

    public static function curlMultiWakeup(var multi) -> int
    {
        return wakeup_multi(multi);
    }

    public static function curlMultiAssign(var multi, int s, var sockp) -> int
    {
        return assign_multi(multi, s, sockp);
    }

    public static function curlMultiStrerror(int code) -> string
    {
        var result;

        let result = strerror_multi(code);

        return result;
    }
}
