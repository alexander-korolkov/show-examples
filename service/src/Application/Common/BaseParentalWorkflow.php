<?php


namespace Fxtm\CopyTrading\Application\Common;


use Closure;
use Fxtm\CopyTrading\Application\Common\Workflow\AbstractWorkflow;
use Fxtm\CopyTrading\Application\Common\Workflow\ContextData;
use Fxtm\CopyTrading\Application\Common\Workflow\WorkflowManager;
use Fxtm\CopyTrading\Application\Common\Workflow\WorkflowRepository;
use Fxtm\CopyTrading\Domain\Common\DateTime;
use LogicException;

abstract class BaseParentalWorkflow extends BaseWorkflow
{

    /**
     * @var WorkflowRepository
     */
    protected $workflowRepository;

    /**
     * @var WorkflowManager
     */
    protected $workflowManager;

    protected function findCreateExecute(int $corrId, string $type, Closure $initializer) : ChildWorkflowStatus
    {

        /**
         * @var BaseWorkflow[]|BaseWorkflow $workflow
         */
        $workflow = $this->workflowRepository->findByCorrelationIdAndType($corrId, $type, $this->id());

        if(empty($workflow)) {
            /** @var AbstractWorkflow $workflow */
            $workflow = $initializer->call($this);
            if(!$this->workflowManager->enqueueWorkflow($workflow)) {
                $this->getLogger()->error("BaseParentalWorkflow: Workflow manager unable to create child workflow; see previous errors");
                return new ChildWorkflowStatus();
            }
        }
        else {
            if(count($workflow) != 1) {
                throw new LogicException('Impossible state');
            }
            $workflow = $workflow[0];
            if ($workflow->isDone()) {
                return new ChildWorkflowStatus($workflow);
            }
        }

        $this->workflowManager->processWorkflow($workflow);

        return new ChildWorkflowStatus($workflow);
    }

    public function createChild($type, ContextData $context, ?DateTime $schedule = null) : AbstractWorkflow
    {
        $childWorkflow = $this->createDetached($type, $context, $schedule);
        $childWorkflow->setParent($this);
        return $childWorkflow;
    }

    public function createDetached($type, ContextData $context, ?DateTime $schedule) : AbstractWorkflow {
        if(!$context->has("parentId")) {
            $context->set("parentId", $this->id());
        }
        $workflow = $this->workflowManager->newWorkflow($type, $context);
        if($schedule) {
            $workflow->scheduleAt($schedule);
        }
        return $workflow;
    }

}