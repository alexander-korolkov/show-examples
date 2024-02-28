<?php

namespace Fxtm\CopyTrading\Application\Metrix\Utils;

class TimeUtils
{
    public static function getCurrentNanotime()
    {
        return (int) (microtime(TRUE) * 1000000000);
    }

    public static function nanoTimeToMicro($nano)
    {
        return (int) $nano / 1000000;
    }
}
