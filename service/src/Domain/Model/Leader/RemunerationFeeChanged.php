<?php

namespace Fxtm\CopyTrading\Domain\Model\Leader;

use Fxtm\CopyTrading\Domain\Common\AbstractEvent;
use Fxtm\CopyTrading\Domain\Common\DateTime;
use Fxtm\CopyTrading\Domain\Common\Identity;
use Fxtm\CopyTrading\Domain\Model\Shared\AccountNumber;

class RemunerationFeeChanged extends AbstractEvent
{

    /**
     * @var AccountNumber
     */
    private $accNo = null;
    private $newRemunFee = 0;
    private $oldRemunFee = 0;

    /**
     * @var Identity
     */
    private $workflowId;

    /**
     * @var DateTime
     */
    private $when;

    public function __construct(AccountNumber $accNo, $remunFee, Identity $workflowId)
    {
        parent::__construct();

        $this->accNo = $accNo;
        $this->newRemunFee = $remunFee;
        $this->workflowId = $workflowId;
        $this->when = DateTime::NOW();
    }

    public function getNewRemunerationFee()
    {
        return $this->newRemunFee;
    }

    public function setOldRemunerationFee($remunFee)
    {
        $this->oldRemunFee = $remunFee;
    }

    public function getOldRemunerationFee()
    {
        return $this->oldRemunFee;
    }

    public function getAccountNumber(): AccountNumber
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
        return sprintf("Fee changed: from %s to %s", $this->oldRemunFee, $this->newRemunFee);
    }

}
