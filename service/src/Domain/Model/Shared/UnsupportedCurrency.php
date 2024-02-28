<?php

namespace Fxtm\CopyTrading\Domain\Model\Shared;

class UnsupportedCurrency extends DomainException
{
    private $currCode = "";

    public function __construct($currCode)
    {
        $this->currCode = $currCode;
        parent::__construct($this, 0, null);
    }

    public function getCurrencyCode()
    {
        return $this->currCode;
    }

    public function __toString()
    {
        return sprintf("Unsupported currency %s", $this->currCode);
    }
}
