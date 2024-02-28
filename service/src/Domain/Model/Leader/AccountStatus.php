<?php

namespace Fxtm\CopyTrading\Domain\Model\Leader;

final class AccountStatus
{
    const PASSIVE = 0;
    const ACTIVE  = 1;
    const DELETED = 4;

    /**
     * @deprecated
     */
    const CLOSED  = 2;

    private static $statuses = [
        self::PASSIVE => "PASSIVE",
        self::ACTIVE  => "ACTIVE",
        self::CLOSED  => "CLOSED",
        self::DELETED  => "DELETED",
    ];

    public static function toString($status)
    {
        if (array_key_exists($status, self::$statuses)) {
            return self::$statuses[$status];
        }
    }
}
