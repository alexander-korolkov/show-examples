<?php

namespace Fxtm\CopyTrading\Domain\Common;

interface ValueObject
{
    public function isSameValueAs(ValueObject $other);
}
