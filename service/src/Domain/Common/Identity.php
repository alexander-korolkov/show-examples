<?php

namespace Fxtm\CopyTrading\Domain\Common;

interface Identity extends ValueObject
{
    public function value();
}
