<?php

namespace Fxtm\CopyTrading\Domain\Model\News;

final class NewsStatus
{
    const UNDER_REVIEW = 1;
    const APPROVED     = 2;
    const REJECTED     = 3;

    private static $statuses = [
        self::UNDER_REVIEW => "UNDER_REVIEW",
        self::APPROVED     => "APPROVED",
        self::REJECTED     => "REJECTED",
    ];

    public static function toString($status)
    {
        if (array_key_exists($status, self::$statuses)) {
            return self::$statuses[$status];
        }
    }
}
