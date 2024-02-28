<?php

namespace Fxtm\CopyTrading\Domain\Model\Follower;

use Fxtm\CopyTrading\Domain\Common\AbstractEvent;
use Fxtm\CopyTrading\Domain\Common\DateTime;
use Fxtm\CopyTrading\Domain\Common\Identity;
use Fxtm\CopyTrading\Domain\Model\Client\ClientId;
use Fxtm\CopyTrading\Domain\Model\Shared\AccountNumber;
use Fxtm\CopyTrading\Domain\Model\Shared\Currency;

class AccountOpened extends AbstractEvent
{
    private $accNo = null;
    private $leadAccNo = null;
    private $ownerId = null;
    private $accCurr = null;
    private $payFee = 0;
    private $copyCoef = 1.00;
    private $stopLossLevel = 0;
    private $broker;
    private $server;

    /**
     * @var Identity
     */
    private $workflowId;

    /**
     * @var DateTime
     */
    private $when;

    public function __construct(
        AccountNumber $accNo,
        $broker,
        $server,
        AccountNumber $leadAccNo,
        ClientId $ownerId,
        Currency $accCurr,
        $payFee,
        $copyCoef,
        $stopLossLevel,
        Identity $sender
    ) {
        parent::__construct();

        $this->accNo         = $accNo;
        $this->broker        = $broker;
        $this->server        = $server;
        $this->leadAccNo     = $leadAccNo;
        $this->ownerId       = $ownerId;
        $this->accCurr       = $accCurr;
        $this->payFee        = $payFee;
        $this->copyCoef      = $copyCoef;
        $this->stopLossLevel = $stopLossLevel;
        $this->workflowId    = $sender;
        $this->when          = DateTime::NOW();
    }

    public function getLeaderAccountNumber()
    {
        return $this->leadAccNo;
    }

    public function getAccountOwnerId()
    {
        return $this->ownerId;
    }

    public function getAccountCurrency()
    {
        return $this->accCurr;
    }

    public function getPayableFee()
    {
        return $this->payFee;
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

    public function getServer()
    {
        return $this->server;
    }

    public function getAccountNumber(): AccountNumber
    {
        return $this->accNo;
    }

    public function getWorkflowId(): Identity
    {
        return $this->workflowId;
    }

    public function getTime(): DateTime
    {
        return $this->when;
    }

    public function getType() : string
    {
        return self::type();
    }

    public function getMessage() : string
    {
        return sprintf(
            "Opened: Account = %s; Currency = %s; Leader = %s; Broker = %s; Client = %s; Fee = %s; Coefficient = %s; SL = %s",
            $this->accNo->value(),
            $this->accCurr->code(),
            $this->leadAccNo->value(),
            $this->broker,
            $this->ownerId->value(),
            $this->payFee,
            $this->copyCoef,
            $this->stopLossLevel
        );
    }

}
