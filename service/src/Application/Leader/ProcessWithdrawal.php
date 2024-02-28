<?php

namespace Fxtm\CopyTrading\Application\Leader;

use Fxtm\CopyTrading\Domain\Model\Client\ClientId;
use Fxtm\CopyTrading\Domain\Model\Shared\AccountNumber;
use Fxtm\CopyTrading\Domain\Model\Shared\Money;

class ProcessWithdrawal
{
    private $transId  = 0;
    private $clientId = null;
    private $accNo    = null;
    private $funds    = null;

    public function __construct($transId, ClientId $clientId, AccountNumber $accNo, Money $funds)
    {
        $this->transId  = intval($transId);
        $this->clientId = $clientId;
        $this->accNo    = $accNo;
        $this->funds    = $funds;
    }

    public function getTransactionId()
    {
        return $this->transId;
    }

    public function getClientId()
    {
        return $this->clientId;
    }

    public function getAccountNumber()
    {
        return $this->accNo;
    }

    public function getFunds()
    {
        return $this->funds;
    }
}
