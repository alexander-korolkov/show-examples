<?php

namespace Fxtm\CopyTrading\Domain\Model\Leader;

final class HiddenReason
{
    const BY_CLIENT    = 1;
    const LOW_PROFIT   = 2;
    const NO_ACTIVITY  = 3;
    const LOW_EQUITY  = 4;
    const BY_COMPANY  = 5;
    const HIGH_LEVERAGE = 6;

    private static $reasons = [
        self::BY_CLIENT   => "BY_CLIENT",
        self::LOW_PROFIT  => "LOW_PROFIT",
        self::NO_ACTIVITY => "NO_ACTIVITY",
        self::LOW_EQUITY => "LOW_EQUITY",
        self::BY_COMPANY => "BY_COMPANY",
        self::HIGH_LEVERAGE => "HIGH_LEVERAGE"
    ];

    public static function toString($reason)
    {
        if (array_key_exists($reason, self::$reasons)) {
            return self::$reasons[$reason];
        }
        return $reason;
    }
}
