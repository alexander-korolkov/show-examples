<?php

namespace Fxtm\CopyTrading\Domain\Common;

interface Specification
{
    public function isSatisfiedBy($object);
}
