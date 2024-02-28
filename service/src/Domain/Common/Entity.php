<?php

namespace Fxtm\CopyTrading\Domain\Common;

interface Entity
{
    public function identity();
    public function isSameIdentityAs(Entity $other);
}
