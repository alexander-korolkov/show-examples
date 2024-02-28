<?php

namespace Fxtm\CopyTrading\Domain\Model\Leader;

use Fxtm\CopyTrading\Domain\Common\AbstractEvent;
use Fxtm\CopyTrading\Domain\Common\DateTime;
use Fxtm\CopyTrading\Domain\Common\Identity;
use Fxtm\CopyTrading\Domain\Model\Client\ClientId;
use Fxtm\CopyTrading\Domain\Model\Shared\AccountNumber;
use Fxtm\CopyTrading\Domain\Model\Shared\Currency;

class AccountOpened extends AbstractEvent
{

    /**
     * @var AccountNumber
     */
    private $accNo       = null;
    private $accountType = null;
    private $server      = null;
    private $accCurr     = null;
    private $ownerId     = null;
    private $accName     = "";
    private $remunFee    = 0;
    private $broker;

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
        $accountType,
        $server,
        Currency $accCurr,
        ClientId $ownerId,
        $accName,
        $remunFee,
        Identity $workflowId
    ) {
        parent::__construct();

        $this->accNo       = $accNo;
        $this->broker      = $broker;
        $this->accountType = $accountType;
        $this->server      = $server;
        $this->accCurr     = $accCurr;
        $this->ownerId     = $ownerId;
        $this->accName     = $accName;
        $this->remunFee    = $remunFee;
        $this->workflowId  = $workflowId;
        $this->when        = DateTime::NOW();
    }

    public function getAccountNumber() : AccountNumber
    {
        return $this->accNo;
    }

    public function getAccountType()
    {
        return $this->accountType;
    }

    public function getServer()
    {
        return $this->server;
    }

    public function getAccountCurrency()
    {
        return $this->accCurr;
    }

    public function getAccountOwnerId()
    {
        return $this->ownerId;
    }

    public function getAccountName()
    {
        return $this->accName;
    }

    public function getRemunerationFee()
    {
        return $this->remunFee;
    }

    public function getBroker()
    {
        return $this->broker;
    }

    public function getWorkflowId() : Identity
    {
        return $this->workflowId;
    }

    public function getTime() : DateTime
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
            "Opened: Account = %s; Currency = %s; Type = %s; Server = %s; Broker = %s; Client = %s; Name = %s; Fee = %s",
            $this->accNo->value(),
            $this->accCurr->code(),
            $this->accountType,
            $this->server,
            $this->broker,
            $this->ownerId->value(),
            $this->accName,
            $this->remunFee
        );
    }
}
