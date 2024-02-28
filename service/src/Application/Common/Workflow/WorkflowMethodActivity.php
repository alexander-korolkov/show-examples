<?php

namespace Fxtm\CopyTrading\Application\Common\Workflow;

class WorkflowMethodActivity extends Activity
{
    public function __construct(AbstractWorkflow $workflow, $name)
    {
        $callback = function (Activity $activity) use ($workflow, $name) {
            $workflow->$name($activity);
        };
        parent::__construct($name, $callback->bindTo($workflow, $workflow));
    }
}
