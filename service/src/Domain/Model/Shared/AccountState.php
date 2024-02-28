<?php

namespace Fxtm\CopyTrading\Domain\Model\Shared;

final class AccountState
{
    const NORMAL  = 0;
    const BLOCKED = 1;

    private static $states = [
        self::NORMAL  => "NORMAL",
        self::BLOCKED => "BLOCKED",
    ];

    public static function toString($status)
    {
        if (array_key_exists($status, self::$states)) {
            return self::$states[$status];
        }
    }
}
