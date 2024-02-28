<?php

namespace Fxtm\CopyTrading\Application\Common\Workflow;

final class WorkflowState
{
    const UNTRIED     = 0;
    const PROCEEDING  = 1;
    const COMPLETED   = 2;
    const FAILED      = 3;
    const REJECTED    = 4;
    const CANCELLED   = 5;

    private static $states = [
        self::UNTRIED     => "UNTRIED",
        self::PROCEEDING  => "PROCEEDING",
        self::COMPLETED   => "COMPLETED",
        self::FAILED      => "FAILED",
        self::REJECTED    => "REJECTED",
        self::CANCELLED   => "CANCELLED",
    ];

    public static function toString($state)
    {
        if (array_key_exists($state, self::$states)) {
            return self::$states[$state];
        }
    }
}
