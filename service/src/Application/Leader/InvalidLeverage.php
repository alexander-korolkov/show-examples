<?php

namespace Fxtm\CopyTrading\Application\Leader;

use RuntimeException;

class InvalidLeverage extends RuntimeException
{
    public function __construct($expected, $actual)
    {
        parent::__construct("Expected <={$expected}, actually got {$actual}");
    }
}
