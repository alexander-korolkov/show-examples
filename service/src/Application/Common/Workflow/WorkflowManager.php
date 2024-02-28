<?php

namespace Fxtm\CopyTrading\Application\Common\Workflow;

use Fxtm\CopyTrading\Domain\Common\DateTime;
use Psr\Log\LoggerInterface;

class WorkflowManager
{
    /**
     * @var WorkflowFactory
     */
    private $factory;

    /**
     * @var WorkflowRepository
     */
    private $repo;

    /**
     * @var Scheduler
     */
    private $scheduler;

    /**
     * @var Locker
     */
    private $locker;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * WorkflowManager constructor.
     * @param WorkflowFactory $factory
     * @param WorkflowRepository $repo
     * @param Scheduler $scheduler
     * @param Locker $locker
     * @param LoggerInterface $logger
     */
    public function __construct(
        /*WorkflowFactory*/ $factory,
        WorkflowRepository $repo,
        Scheduler $scheduler,
        Locker $locker,
        LoggerInterface $logger
    ) {
        $this->factory = $factory;
        $this->repo      = $repo;
        $this->scheduler = $scheduler;
        $this->locker    = $locker;
        $this->logger    = $logger;
    }

    /**
     *
     * @param string $type
     * @param ContextData $context
     * @return AbstractWorkflow
     */
    public function newWorkflow($type, ContextData $context)
    {
        return $this->factory->createNewOfType($type, $context);
    }

    public function processWorkflow(AbstractWorkflow $workflow)
    {
        $startTime = microtime(true);
        $this->log($workflow, "start processWorkflow");

        if (empty($workflow->id())) {
            if (!$this->enqueueWorkflow($workflow)) {
                $workflow->reject();
                $this->log($workflow, "rejected");

                return false;
            }

            $this->log($workflow, "enqueued");
        }

        if (!$this->locker->lock($workflow)) {

            $this->log($workflow, "couldn't lock");

            return false;
        }

        if ($workflow->scheduledAt() > DateTime::NOW()) {

            $this->log($workflow, "scheduled for later");

            $ul = $this->locker->unlock($workflow);

            if (!$ul) {
                $this->log($workflow, "couldn't unlock");
            }

            return false;
        }

        // Refresh the state after we got the lock (required when processed by several workers)
        $refreshed = $this->repo->findById($workflow->id());
        if ($refreshed->isDone() || $workflow->toArray() != $refreshed->toArray()) {

            $this->log($workflow, "processed by another process");

            $ul = $this->locker->unlock($workflow);

            if (!$ul) {
                $this->log($workflow, "couldn't unlock");
            }

            return false;
        }

        try {
            $dt = $this->scheduler->chooseTime($workflow);
            if ($dt > DateTime::NOW()) {

                $this->log($workflow, "scheduled for {$dt}");

                $workflow->scheduleAt($dt);
                return false;
            }

            // a kludge
            if ($workflow->isNew()) {
                // https://tw.fxtm.com/servicedesk/view/48687
                $this->repo->markAsProceeding($workflow->id());

                $this->log($workflow, "marked as proceeding");
            }

            $workflow->proceed();

            $this->log($workflow, "processed");

            return true;
        } catch (\Throwable $exception) {
            $this->log($workflow, sprintf(
                'failed because of exception %s with message %s, Stack trace: %s',
                get_class($exception),
                $exception->getMessage(),
                $exception->getTraceAsString()
            ));
        } finally {
            try {
                $this->repo->store($workflow);

                $this->log($workflow, "stored");
            }
            catch (\Throwable $throwable) {
                $this->log($workflow, sprintf(
                    'Workflow repository failed to store the workflow because of exception %s with message %s, Stack trace: %s',
                    get_class($throwable),
                    $throwable->getMessage(),
                    $throwable->getTraceAsString()
                ));
            }
            try {
                $ul = $this->locker->unlock($workflow);

                if (!$ul) {
                    $this->log($workflow, "couldn't unlock");
                }

                $this->log($workflow, sprintf("end processWorkflow, time: %f s", microtime(true) - $startTime));
            }
            catch (\Throwable $throwable) {
                $this->log($workflow, sprintf(
                    'Locker failed to unlock the workflow because of exception %s with message %s, Stack trace: %s',
                    get_class($throwable),
                    $throwable->getMessage(),
                    $throwable->getTraceAsString()
                ));
            }
        }

        return false;
    }

    public function enqueueWorkflow(AbstractWorkflow $workflow)
    {
        if (!$workflow->canBeCreated()) {
            return false;
        }

        if (!$workflow->isScheduled()) {
            $workflow->scheduleAt($this->scheduler->chooseTime($workflow));
        }
        try {
            $this->repo->store($workflow);

            $this->log($workflow, "stored");
        }
        catch (\Throwable $throwable) {
            $this->log($workflow, sprintf(
                'Workflow repository failed to store the workflow on enqueueWorkflow step because of exception %s with message %s, Stack trace: %s',
                get_class($throwable),
                $throwable->getMessage(),
                $throwable->getTraceAsString()
            ));
            return false;
        }
        return true;
    }

    private function log(AbstractWorkflow $workflow, $message)
    {
        $this->logger->debug(sprintf("pid %d, %s:%d, %s", getmypid(), $workflow->type(), $workflow->id(), $message));
    }
}
