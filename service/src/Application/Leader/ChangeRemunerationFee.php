<?php

namespace Fxtm\CopyTrading\Application\Leader;

use Fxtm\CopyTrading\Domain\Model\Shared\AccountNumber;

class ChangeRemunerationFee
{
    private $accNo    = null;
    private $remunFee = null;

    public function __construct(AccountNumber $accNo, $remunFee)
    {
        $this->accNo    = $accNo;
        $this->remunFee = intval($remunFee);
    }

    public function getAccountNumber()
    {
        return $this->accNo;
    }

    public function getRemunerationFee()
    {
        return $this->remunFee;
    }
}
