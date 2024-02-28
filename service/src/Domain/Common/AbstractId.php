<?php

namespace Fxtm\CopyTrading\Domain\Common;

abstract class AbstractId implements Identity
{
    private $id = 0;

    public function __construct($id)
    {
        $this->id = $id;
    }

    public function isSameValueAs(ValueObject $other)
    {
        if ($other instanceof $this) {
            return $this->value() === $other->value();
        }

        return false;
    }

    public function value()
    {
        return $this->id;
    }

    public function __toString()
    {
        return strval($this->value());
    }
}
