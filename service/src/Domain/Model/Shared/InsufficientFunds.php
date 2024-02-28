<?php

namespace Fxtm\CopyTrading\Domain\Model\Shared;

use Fxtm\CopyTrading\Domain\Model\Shared\Money;

class InsufficientFunds extends DomainException
{
    private $funds = null;

    public function __construct(Money $funds)
    {
        $this->funds = $funds;
        parent::__construct($this, 0, null);
    }

    public function getFunds()
    {
        return $this->funds;
    }

    public function __toString()
    {
        return sprintf("Insufficient funds %s", $this->funds);
    }
}
