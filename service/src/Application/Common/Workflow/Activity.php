<?php

namespace Fxtm\CopyTrading\Application\Common\Workflow;

use Exception;
use Fxtm\CopyTrading\Domain\Common\DateTime;

class Activity
{
    private $id = null;

    private $state = ActivityState::UNTRIED;
    private $tries = 0;

    private $startedAt = null;
    private $finishedAt = null;

    private $name = "";
    private $callback = null;

    /**
     * @var ContextData
     */
    private $context = null;

    public function __construct($name, callable $callback)
    {
        $this->name = $name;
        $this->callback = $callback;
        $this->context = new ContextData();
    }

    public function id()
    {
        return $this->id;
    }

    public function name()
    {
        return $this->name;
    }

    public function isNew()
    {
        return $this->state === ActivityState::UNTRIED;
    }

    public function untried(): void
    {
        $this->state = ActivityState::UNTRIED;
    }

    public function keepTrying()
    {
        $this->state = ActivityState::TRYING;
    }

    public function isTrying()
    {
        return $this->state === ActivityState::TRYING;
    }

    public function retry()
    {
        $this->state = ActivityState::RETRYING;
    }

    public function isRetrying()
    {
        return $this->state === ActivityState::RETRYING;
    }

    public function isInProgress()
    {
        return $this->isTrying() || $this->isRetrying();
    }

    public function skip()
    {
        $this->state = ActivityState::SKIPPED;
    }

    public function isSkipped()
    {
        return $this->state === ActivityState::SKIPPED;
    }

    public function succeed()
    {
        $this->state = ActivityState::SUCCEEDED;
    }

    public function isSucceeded()
    {
        return $this->state === ActivityState::SUCCEEDED;
    }

    public function isCompleted()
    {
        return $this->isSkipped() || $this->isSucceeded();
    }

    public function cancel()
    {
        $this->state = ActivityState::CANCELLED;
    }

    public function isCancelled()
    {
        return $this->state === ActivityState::CANCELLED;
    }

    public function fail()
    {
        $this->state = ActivityState::FAILED;
    }

    public function isFailed()
    {
        return $this->state === ActivityState::FAILED;
    }

    public function isTerminated()
    {
        return $this->isCancelled() || $this->isFailed();
    }

    public function isDone()
    {
        return $this->isCompleted() || $this->isTerminated();
    }

    public function execute(ContextData $context)
    {
        if ($this->isDone()) {
            return;
        }

        if ($this->tries === 0) {
            $this->startedAt = DateTime::NOW()->__toString();
        }
        $this->tries++;

        try {
            call_user_func($this->callback, $this);
        } catch (Exception $e) {
            $this->fail();
            throw $e;
        } finally {
            if ($this->isDone()) {
                $this->finishedAt = DateTime::NOW()->__toString();
            }
        }
    }

    public function startedAt()
    {
        return DateTime::of($this->startedAt);
    }

    public function finishedAt()
    {
        return DateTime::of($this->finishedAt);
    }

    public function getTriesCount()
    {
        return $this->tries;
    }

    public function isFirstTry()
    {
        return $this->getTriesCount() === 1;
    }

    public function getContext()
    {
        return $this->context;
    }

    public function clearContext(): void
    {
        $this->context = new ContextData();
    }

    public function toArray()
    {
        return [
            "id"           => $this->id,
            "name"         => $this->name,
            "state"        => $this->state,
            "tries"        => $this->tries,
            "started_at"   => $this->startedAt,
            "finished_at"  => $this->finishedAt,
            "context"      => $this->context->toArray(),
        ];
    }

    public function fromArray(array $array)
    {
        $this->id          = intval($array["id"]);
        $this->name        = $array["name"];
        $this->state       = intval($array["state"]);
        $this->tries       = intval($array["tries"]);
        $this->startedAt   = $array["started_at"];
        $this->finishedAt  = $array["finished_at"];
        $this->context->fromArray($array["context"]);
    }
}
