<?php

namespace Fxtm\CopyTrading\Domain\Model\Leader;

final class PrivacyMode
{
    const PUBLIK  = 1;
    const PRYVATE = 2;
    const OFF     = 3;

    private static $modes = [
        self::PUBLIK  => "PUBLIC",
        self::PRYVATE => "PRIVATE",
        self::OFF     => "SWITCHED_OFF",
    ];

    public static function toString($mode)
    {
        if (array_key_exists($mode, self::$modes)) {
            return self::$modes[$mode];
        }
    }
}
