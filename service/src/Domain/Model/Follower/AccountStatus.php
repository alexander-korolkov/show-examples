<?php

namespace Fxtm\CopyTrading\Domain\Model\Follower;

final class AccountStatus
{
    const PASSIVE = 0;
    const ACTIVE  = 1;
    const CLOSED  = 2;

    private static $statuses = [
        self::PASSIVE => "PASSIVE",
        self::ACTIVE  => "ACTIVE",
        self::CLOSED  => "CLOSED",
    ];

    public static function toString($status)
    {
        if (array_key_exists($status, self::$statuses)) {
            return self::$statuses[$status];
        }
    }
}
