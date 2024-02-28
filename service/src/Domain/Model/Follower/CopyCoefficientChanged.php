<?php

namespace Fxtm\CopyTrading\Domain\Model\Follower;

use Fxtm\CopyTrading\Domain\Common\AbstractEvent;
use Fxtm\CopyTrading\Domain\Common\DateTime;
use Fxtm\CopyTrading\Domain\Common\Identity;
use Fxtm\CopyTrading\Domain\Model\Shared\AccountNumber;

class CopyCoefficientChanged extends AbstractEvent
{

    /**
     * @var AccountNumber
     */
    private $accNo = null;

    private $newCopyCoef = 0.00;
    private $oldCopyCoef = 0.00;

    /**
     * @var Identity
     */
    private $workflowId;

    /**
     * @var DateTime
     */
    private $when;

    public function __construct(AccountNumber $accNo, $copyCoef, Identity $sender)
    {
        parent::__construct();

        $this->accNo = $accNo;
        $this->newCopyCoef = $copyCoef;
        $this->workflowId = $sender;
        $this->when = DateTime::NOW();
    }

    public function getNewCopyCoefficient()
    {
        return $this->newCopyCoef;
    }

    public function setOldCopyCoefficient($copyCoef)
    {
        $this->oldCopyCoef = $copyCoef;
    }

    public function getOldCopyCoefficient()
    {
        return $this->oldCopyCoef;
    }

    public function __toString()
    {
        return sprintf(
            "CopyCoefficientChanged {AccNo: %s, From: %d, To: %d}",
            $this->accNo, $this->oldCopyCoef, $this->newCopyCoef
        );
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
        return sprintf("Coefficient changed: from %s to %s", $this->oldCopyCoef, $this->newCopyCoef);
    }

}
