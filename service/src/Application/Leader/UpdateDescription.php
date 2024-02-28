<?php

namespace Fxtm\CopyTrading\Application\Leader;

use Fxtm\CopyTrading\Domain\Model\Shared\AccountNumber;

class UpdateDescription
{
    private $accNo    = null;
    private $accDescr = null;

    public function __construct(AccountNumber $accNo, $accDescr)
    {
        $this->accNo    = $accNo;
        $this->accDescr = $accDescr;
    }

    public function getAccountNumber()
    {
        return $this->accNo;
    }

    public function getAccountDescription()
    {
        return $this->accDescr;
    }
}
