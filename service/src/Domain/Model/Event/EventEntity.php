<?php


namespace Fxtm\CopyTrading\Domain\Model\Event;


use Fxtm\CopyTrading\Domain\Common\DateTime;

class EventEntity
{

    /**
     * @var int
     */
    private $accountId;

    /**
     * @var int
     */
    private $workflowId;

    /**
     * @var string
     */
    private $eventType;

    /**
     * @var DateTime
     */
    private $timeStamp;

    /**
     * @var string
     */
    private $message;

    /**
     * @return int
     */
    public function getAccountId(): int
    {
        return $this->accountId;
    }

    /**
     * @param int $accountId
     */
    public function setAccountId(int $accountId): void
    {
        $this->accountId = $accountId;
    }

    /**
     * @return int
     */
    public function getWorkflowId(): int
    {
        return $this->workflowId;
    }

    /**
     * @param int $workflowId
     */
    public function setWorkflowId(int $workflowId): void
    {
        $this->workflowId = $workflowId;
    }

    /**
     * @return string
     */
    public function getEventType(): string
    {
        return $this->eventType;
    }

    /**
     * @param string $eventType
     */
    public function setEventType(string $eventType): void
    {
        $this->eventType = $eventType;
    }

    /**
     * @return DateTime
     */
    public function getTimeStamp(): DateTime
    {
        return $this->timeStamp;
    }

    /**
     * @param DateTime $timeStamp
     */
    public function setTimeStamp(DateTime $timeStamp): void
    {
        $this->timeStamp = $timeStamp;
    }

    /**
     * @return string
     */
    public function getMessage(): string
    {
        return $this->message;
    }

    /**
     * @param string $message
     */
    public function setMessage(string $message): void
    {
        $this->message = $message;
    }

}