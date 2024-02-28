<?php

namespace Fxtm\CopyTrading\Application\Common\Workflow;

final class ActivityState
{
    const UNTRIED    = 0;
    const TRYING     = 1;
    const RETRYING   = 2;
    const SKIPPED    = 3;
    const SUCCEEDED  = 4;
    const CANCELLED  = 5;
    const FAILED     = 6;

    private static $states = [
        self::UNTRIED    => "UNTRIED",
        self::TRYING     => "TRYING",
        self::RETRYING   => "RETRYING",
        self::SKIPPED    => "SKIPPED",
        self::SUCCEEDED  => "SUCCEEDED",
        self::CANCELLED  => "CANCELLED",
        self::FAILED     => "FAILED",
    ];

    public static function toString($state)
    {
        if (array_key_exists($state, self::$states)) {
            return self::$states[$state];
        }
    }
}
