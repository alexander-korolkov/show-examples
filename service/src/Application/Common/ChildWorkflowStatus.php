<?php


namespace Fxtm\CopyTrading\Application\Common;


use Fxtm\CopyTrading\Application\Common\Workflow\AbstractWorkflow;
use Fxtm\CopyTrading\Application\Common\Workflow\Activity;

final class ChildWorkflowStatus
{

    /**
     * @var AbstractWorkflow|null
     */
    private $workflow;

    /**
     * @var boolean
     */
    private $interrupted;

    /**
     * Null value means workflow manager failure
     * @param AbstractWorkflow|null $workflow
     */
    public function __construct(?AbstractWorkflow $workflow = null)
    {
        $this->workflow = $workflow;
        if($workflow == null) {
            $this->interrupted = true;
        }
        else {
            $this->interrupted = $workflow->isFailed();
        }
    }


    /**
     * Signalize that multiple execution of children workflows list must be interrupted
     * (because of failure on processing of current workflow or child workflow cannot be created due to previous errors)
     *
     * @return bool
     */
    public function isInterrupted() : bool
    {
        return $this->interrupted;
    }

    public function updateActivity(Activity $activity) : void
    {

        switch(true) {
            case $this->interrupted:
                $activity->fail();
                break;

            case $this->workflow->isRejected():
            case $this->workflow->isCompleted():
                $activity->succeed();
                break;

            case $this->workflow->isCancelled():
                $activity->skip();
                break;

            default:
                $activity->keepTrying();
                break;
        }

    }

    public function getChild() : ?AbstractWorkflow
    {
        return $this->workflow;
    }

}