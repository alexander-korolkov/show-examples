<?php

namespace Fxtm\CopyTrading\Application\Follower;

use Fxtm\CopyTrading\Domain\Model\Client\ClientId;
use Fxtm\CopyTrading\Domain\Model\Shared\AccountNumber;
use Fxtm\CopyTrading\Domain\Model\Shared\Money;

class ProcessWithdrawal
{
    private $accNo = null;
    private $funds = null;
    private $clientId = null;
    private $broker;

    public function __construct(AccountNumber $accNo, Money $funds, ClientId $clientId, $broker)
    {
        $this->accNo = $accNo;
        $this->funds = $funds;
        $this->clientId = $clientId;
        $this->broker = $broker;
    }

    /**
     * @return AccountNumber
     */
    public function getAccountNumber()
    {
        return $this->accNo;
    }

    /**
     * @return Money
     */
    public function getFunds()
    {
        return $this->funds;
    }

    /**
     * @return ClientId
     */
    public function getClientId()
    {
        return $this->clientId;
    }

    public function getBroker()
    {
        return $this->broker;
    }
}
