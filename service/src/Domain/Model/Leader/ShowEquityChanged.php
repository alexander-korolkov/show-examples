<?php

namespace Fxtm\CopyTrading\Domain\Model\Leader;

use Fxtm\CopyTrading\Domain\Common\AbstractEvent;
use Fxtm\CopyTrading\Domain\Common\DateTime;
use Fxtm\CopyTrading\Domain\Common\Identity;
use Fxtm\CopyTrading\Domain\Model\Shared\AccountNumber;

class ShowEquityChanged extends AbstractEvent
{
    /**
     * @var AccountNumber
     */
    private $accNo = null;
    private $newShowEquity;
    private $oldShowEquity;

    /**
     * @var Identity
     */
    private $workflowId;

    /**
     * @var DateTime
     */
    private $when;

    public function __construct(AccountNumber $accNo, $showEquity, Identity $workflowId)
    {
        parent::__construct();

        $this->accNo = $accNo;
        $this->newShowEquity = $showEquity;
        $this->workflowId = $workflowId;
        $this->when = DateTime::NOW();
    }

    public function getNewShowEquity()
    {
        return $this->newShowEquity;
    }

    public function setOldShowEquity($showEquity)
    {
        $this->oldShowEquity = $showEquity;
    }

    public function getOldShowEquity()
    {
        return $this->oldShowEquity;
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

    public function getType(): string
    {
        return self::type();
    }

    public function getMessage(): string
    {
        return sprintf("Fee changed: from %s to %s", $this->oldShowEquity, $this->newShowEquity);
    }
}
