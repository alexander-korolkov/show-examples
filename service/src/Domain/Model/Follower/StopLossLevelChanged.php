<?php

namespace Fxtm\CopyTrading\Domain\Model\Follower;

use Fxtm\CopyTrading\Domain\Common\AbstractEvent;
use Fxtm\CopyTrading\Domain\Common\DateTime;
use Fxtm\CopyTrading\Domain\Common\Identity;
use Fxtm\CopyTrading\Domain\Model\Shared\AccountNumber;
use Fxtm\CopyTrading\Domain\Model\Shared\Money;

class StopLossLevelChanged extends AbstractEvent
{
    private $accNo = null;
    private $newStopLossLevel = 0;
    private $oldStopLossLevel = 0;
    private $newStopLossEquity = 0;

    /**
     * @var Identity
     */
    private $workflowId;

    /**
     * @var DateTime
     */
    private $when;

    public function __construct(AccountNumber $accNo, $stopLossLevel, Money $stopLossEquity, Identity $sender)
    {
        parent::__construct();

        $this->accNo = $accNo;
        $this->newStopLossLevel = $stopLossLevel;
        $this->workflowId = $sender;
        $this->newStopLossEquity = $stopLossEquity;
        $this->when = DateTime::NOW();
    }

    public function setOldStopLossLevel($stopLossLevel)
    {
        $this->oldStopLossLevel = $stopLossLevel;
    }

    public function getOldStopLossLevel()
    {
        return $this->oldStopLossLevel;
    }

    public function getNewStopLossLevel()
    {
        return $this->newStopLossLevel;
    }

    public function getNewStopLossEquity()
    {
        return $this->newStopLossEquity;
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
            "SL changed: from %s to %s (%.2f %s)",
            $this->oldStopLossLevel,
            $this->newStopLossLevel,
            $this->newStopLossEquity->amount(),
            $this->newStopLossEquity->currency()
        );
    }

}
