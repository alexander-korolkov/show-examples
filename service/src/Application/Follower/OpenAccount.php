<?php

namespace Fxtm\CopyTrading\Application\Follower;

use Fxtm\CopyTrading\Domain\Model\Client\ClientId;
use Fxtm\CopyTrading\Domain\Model\Shared\AccountNumber;

class OpenAccount
{
    private $leadAccNo     = null;
    private $clientId      = null;
    private $copyCoef      = 1.00;
    private $stopLossLevel = 0;
    private $broker;
    private $leaderBroker;

    public function __construct(
        AccountNumber $leadAccNo,
        ClientId $clientId,
        $broker,
        $leaderBroker,
        $copyCoef = 1.00,
        $stopLossLevel = null
    ) {
        $this->leadAccNo     = $leadAccNo;
        $this->clientId      = $clientId;
        $this->copyCoef      = $copyCoef;
        $this->broker        = $broker;
        $this->leaderBroker  = $leaderBroker;
        $this->stopLossLevel = is_null($stopLossLevel) ? null : intval($stopLossLevel);
    }

    public function getLeaderAccountNumber()
    {
        return $this->leadAccNo;
    }

    public function getClientId()
    {
        return $this->clientId;
    }

    public function getCopyCoefficient()
    {
        return $this->copyCoef;
    }

    public function getStopLossLevel()
    {
        return $this->stopLossLevel;
    }

    public function getBroker()
    {
        return $this->broker;
    }

    public function getLeaderBroker()
    {
        return $this->leaderBroker;
    }
}
