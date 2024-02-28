<?php

namespace Fxtm\CopyTrading\Domain\Model\Follower;

use Fxtm\CopyTrading\Domain\Common\DateTime;

class Commission
{
    const TYPE_PERIODICAL    = 0;
    const TYPE_WITHDRAWAL    = 1;
    const TYPE_CLOSE_ACCOUNT = 2;

    private $id           = null;
    private $type         = 0;
    private $accNo        = null;
    private $amount       = 0.00;
    private $prevEquity   = 0;
    private $prevFeeLevel = 0;
    private $workflowId   = null;
    private $comment      = '';
    private $createdAt    = null;
    private $transId      = null;
    private $broker;

    public function __construct(
        $type,
        $accNo,
        $amount,
        $prevEquity,
        $prevFeeLevel,
        $workflowId,
        $comment = '',
        DateTime $createdAt = null
    ) {
        $this->type         = $type;
        $this->accNo        = $accNo;
        $this->amount       = $amount;
        $this->prevEquity   = $prevEquity;
        $this->prevFeeLevel = $prevFeeLevel;
        $this->workflowId   = $workflowId;
        $this->comment      = $comment;
        $this->createdAt    = null === $createdAt ? DateTime::NOW()->__toString() : $createdAt->__toString();
    }

    public function toArray()
    {
        return array(
            'id'             => $this->id,
            'workflow_id'    => $this->workflowId,
            'trans_id'       => $this->transId,
            'broker'         => $this->broker,
            'acc_no'         => $this->accNo,
            'created_at'     => $this->createdAt,
            'amount'         => $this->amount,
            'type'           => $this->type,
            'prev_equity'    => $this->prevEquity,
            'prev_fee_level' => $this->prevFeeLevel,
            'comment'        => $this->comment,
        );
    }

    public function fromArray(array $array)
    {
        $this->id           = intval($array['id']);
        $this->transId      = intval($array['trans_id']);
        $this->broker       = intval($array['broker']);
        $this->workflowId   = intval($array['workflow_id']);
        $this->accNo        = intval($array['acc_no']);
        $this->createdAt    = $array['created_at'];
        $this->amount       = (float) $array['amount'];
        $this->type         = intval($array['type']);
        $this->prevEquity   = $array['prev_equity'];
        $this->prevFeeLevel = $array['prev_fee_level'];
        $this->comment      = $array['comment'];
    }

    public function getAmount()
    {
        return $this->amount;
    }

    public function getAccNo()
    {
        return $this->accNo;
    }

    public function setTransId($transId)
    {
        $this->transId = $transId;
    }

    public function setBroker($broker)
    {
        $this->broker = $broker;
    }
}
