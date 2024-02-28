<?php

namespace Fxtm\CopyTrading\Application\Common\Workflow;

use Exception;
use Fxtm\CopyTrading\Application\Services\LoggerTrait;
use Fxtm\CopyTrading\Domain\Common\DateTime;
use Fxtm\CopyTrading\Domain\Common\Identity;
use Fxtm\CopyTrading\Domain\Common\ValueObject;
use Fxtm\CopyTrading\Domain\Model\Follower\FollowerAccountRepository;
use Fxtm\CopyTrading\Domain\Model\Leader\LeaderAccountRepository;

abstract class AbstractWorkflow implements Identity
{
    use LoggerTrait;

    protected const KEY_ID = 'id';
    protected const KEY_PARENT_ID = 'parent_id';
    protected const KEY_NESTING_LEVEL = 'nesting_level';
    protected const KEY_TYPE = 'type';
    protected const KEY_STATE = 'state';
    protected const KEY_TRIES = 'tries';
    protected const KEY_CREATED_AT = 'created_at';
    protected const KEY_SCHEDULED_AT = 'scheduled_at';
    protected const KEY_STARTED_AT = 'started_at';
    protected const KEY_FINISHED_AT = 'finished_at';
    protected const KEY_CONTEXT = 'context';
    protected const KEY_CONTEXT_INIT = 'context_init';
    protected const KEY_BROKER = 'broker';

    const TYPE = null;

    private $id = null;

    private $parentId = null;
    private $nestingLevel = 0;
    private $broker;

    private $state = WorkflowState::UNTRIED;
    private $tries = 0;

    private $createdAt   = null;
    private $scheduledAt = null;
    private $startedAt   = null;
    private $finishedAt  = null;

    /**
     * @var Activity[]
     */
    private $activities = [];

    /**
     * @var ContextData
     */
    private $context = null;

    /**
     * The context which save only one time - when workflow has been created
     *
     * @var ContextData
     */
    private $context_init = null;

    public function __construct(array $activities)
    {
        $this->activities = $activities;
        $this->context = new ContextData();
        $this->context_init = new ContextData();
        $this->createdAt = DateTime::NOW()->__toString();
    }

    /**
     * @param ContextData $context
     */
    public function setContext(ContextData $context): void
    {
        $this->context = $context;

        if ($this->context_init->isEmpty()) {
            $this->context_init = $context;
        }
    }

    public function resetContext(): void
    {
        if ($this->context_init->isEmpty()) {
            return;
        }

        $this->context = $this->context_init;
    }

    public function id()
    {
        return $this->id;
    }

    public function value()
    {
        return $this->id;
    }

    public function isSameValueAs(ValueObject $other)
    {
        if ($other instanceof $this) {
            return $this->id == $other->id() && $this->id != null;
        }
        return false;
    }

    public function setParent(AbstractWorkflow $parent)
    {
        $this->parentId = $parent->id();
        $this->nestingLevel = $parent->getNestingLevel() + 1;
    }

    public function getParentId()
    {
        return $this->parentId;
    }

    public function getNestingLevel()
    {
        return $this->nestingLevel;
    }

    public function setWorkflowBroker(string $broker): AbstractWorkflow
    {
        $this->broker = $broker;
        return $this;
    }

    public function getWorkflowBroker(): string
    {
        return $this->broker;
    }

    public function type()
    {
        return static::TYPE;
    }

    public function createdAt()
    {
        return DateTime::of($this->createdAt);
    }

    public function scheduleAt(DateTime $dt)
    {
        $this->scheduledAt = $dt->__toString();
    }

    public function scheduledAt()
    {
        return DateTime::of($this->scheduledAt);
    }

    public function isScheduled()
    {
        return !empty($this->scheduledAt);
    }

    public function startedAt()
    {
        return DateTime::of($this->startedAt);
    }

    public function finishedAt()
    {
        return DateTime::of($this->finishedAt);
    }

    public function getActivities()
    {
        return $this->activities;
    }

    public function getActivity($name)
    {
        if (empty($this->activities[$name])) {
            return  null;
            //throw new Exception(sprintf("Activity '%s' not registered in '%s' workflow", $name, static::TYPE));
        }
        return $this->activities[$name];
    }

    public function getContext()
    {
        return $this->context;
    }

    public function isNew()
    {
        return $this->state === WorkflowState::UNTRIED;
    }

    public function untried(): void
    {
        $this->state = WorkflowState::UNTRIED;
    }

    public function isInProgress()
    {
        return $this->state === WorkflowState::PROCEEDING;
    }

    public function markAsProceeding()
    {
        $this->state = WorkflowState::PROCEEDING;
    }

    public function isCompleted()
    {
        return $this->state === WorkflowState::COMPLETED;
    }

    public function isFailed()
    {
        return $this->state === WorkflowState::FAILED;
    }

    public function cancel()
    {
        $this->state = WorkflowState::CANCELLED;
    }

    public function isCancelled()
    {
        return $this->state === WorkflowState::CANCELLED;
    }

    public function reject()
    {
        $this->state = WorkflowState::REJECTED;
    }

    public function isRejected()
    {
        return $this->state === WorkflowState::REJECTED;
    }

    public function isDone()
    {
        return $this->isCompleted()
            || $this->isFailed()
            || $this->isRejected()
            || $this->isCancelled();
    }

    public function proceed()
    {
        if ($this->isDone()) {
            return;
        }

        if ($this->tries === 0) {
            $this->startedAt = DateTime::NOW()->__toString();
        }

        $this->tries++;
        $this->state = $this->doProceed();
        if ($this->isInProgress()) {
            return;
        }

        $this->finishedAt = DateTime::NOW()->__toString();
    }

    protected function doProceed()
    {
        foreach ($this->activities as $activity) {
            try {
                $activity->execute($this->context);
            } catch (Exception $e) {
                $this->getLogger()->error(
                    sprintf(
                        "Activity %s#%d failed with %s('%s') in %s on line %d",
                        $activity->name(),
                        $activity->id(),
                        get_class($e),
                        $e->getMessage(),
                        $e->getFile(),
                        $e->getLine()
                    ),
                    $this->toArray()
                );
            }

            if ($this->isRejected()) {
                return WorkflowState::REJECTED;
            }
            if ($activity->isInProgress()) {
                return WorkflowState::PROCEEDING;
            }
            if ($activity->isFailed()) {
                return WorkflowState::FAILED;
            }
        }
        return WorkflowState::COMPLETED;
    }

    public function getState()
    {
        return $this->state;
    }

    public function getTriesCount()
    {
        return $this->tries;
    }

    public function isFirstTry()
    {
        return $this->getTriesCount() === 1;
    }

    public function toArray()
    {
        return [
            self::KEY_ID => $this->id,
            self::KEY_PARENT_ID => $this->parentId,
            self::KEY_NESTING_LEVEL => $this->nestingLevel,
            self::KEY_TYPE => static::TYPE,
            self::KEY_STATE => $this->state,
            self::KEY_TRIES => $this->tries,
            self::KEY_CREATED_AT => $this->createdAt,
            self::KEY_SCHEDULED_AT => $this->scheduledAt,
            self::KEY_STARTED_AT => $this->startedAt,
            self::KEY_FINISHED_AT => $this->finishedAt,
            self::KEY_CONTEXT => $this->context->toArray(),
            self::KEY_CONTEXT_INIT => $this->context_init->toArray(),
            self::KEY_BROKER => $this->broker,
        ];
    }

    public function fromArray(array $array)
    {
        $this->id = intval($array[self::KEY_ID]);
        $this->parentId = intval($array[self::KEY_PARENT_ID]);
        $this->nestingLevel = intval($array[self::KEY_NESTING_LEVEL]);
        $this->state = intval($array[self::KEY_STATE]);
        $this->tries = intval($array[self::KEY_TRIES]);
        $this->createdAt = $array[self::KEY_CREATED_AT];
        $this->scheduledAt = $array[self::KEY_SCHEDULED_AT];
        $this->startedAt = $array[self::KEY_STARTED_AT];
        $this->finishedAt = $array[self::KEY_FINISHED_AT];
        $this->broker = $array[self::KEY_BROKER];

        if (!empty($array[self::KEY_CONTEXT])) {
            $this->context->fromArray($array[self::KEY_CONTEXT]);
        }

        if (!empty($array[self::KEY_CONTEXT_INIT])) {
            $this->context_init->fromArray($array[self::KEY_CONTEXT_INIT]);
        }
    }

    public function getResult()
    {
        return $this->isDone();
    }

    abstract public function getCorrelationId();

    protected function logDebug(Activity $activity, $method, $details) {
        $this->getLogger()->debug(
            sprintf(
                "DebugWorkflow %s#%d Activity %s#%d. Method: %s. Details: %s",
                $this->type(),
                $this->id(),
                $activity->name(),
                $activity->id(),
                $method,
                $details
            ),
            $this->toArray()
        );
    }

    /**
     * Method should return true if all necessary conditions for
     * creating of this workflow are met
     *
     * @return bool
     */
    public function canBeCreated()
    {
        return true;
    }

    /**
     * @return FollowerAccountRepository
     * @return LeaderAccountRepository
     */
    abstract public function getAccountRepository();
}
