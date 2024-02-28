<?php

namespace Fxtm\CopyTrading\Domain\Common;

abstract class AbstractEntity implements Entity
{
    /**
     *
     * @var AbstractId
     */
    protected $identity = null;

    public function __construct(AbstractId $identity = null)
    {
        $this->identity = $identity;
    }

    /**
     *
     * @return AbstractId
     */
    public function identity()
    {
        return $this->identity;
    }

    public function isSameIdentityAs(Entity $other)
    {
        if ($other instanceof $this) {
            return $this->identity()->isSameValueAs($other->identity());
        }

        return false;
    }
}
