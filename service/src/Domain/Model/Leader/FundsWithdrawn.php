<?php

namespace Fxtm\CopyTrading\Domain\Model\Leader;

use Fxtm\CopyTrading\Domain\Common\AbstractEvent;
use Fxtm\CopyTrading\Domain\Common\DateTime;
use Fxtm\CopyTrading\Domain\Common\Identity;
use Fxtm\CopyTrading\Domain\Model\Shared\AccountNumber;
use Fxtm\CopyTrading\Domain\Model\Shared\Money;

class FundsWithdrawn extends AbstractEvent
{

    /**
     * @var AccountNumber
     */
    private $accNo = null;
    private $funds = null;

    /**
     * @var Identity
     */
    private $workflowId;

    /**
     * @var DateTime
     */
    private $when;

    public function __construct(AccountNumber $accNo, Money $funds, Identity $workflowId)
    {
        parent::__construct();

        $this->accNo = $accNo;
        $this->funds = $funds;
        $this->workflowId = $workflowId;
        $this->when = DateTime::NOW();
    }

    public function getFunds() : Money
    {
        return $this->funds;
    }

    public function getAccountNumber() : AccountNumber
    {
        return $this->accNo;
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
        return sprintf("Withdrawn: %s", $this->funds);
    }

}
