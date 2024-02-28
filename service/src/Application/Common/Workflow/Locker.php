<?php

namespace Fxtm\CopyTrading\Application\Common\Workflow;

interface Locker
{
    public function lock(AbstractWorkflow $workflow, $expire = 0);
    public function unlock(AbstractWorkflow $workflow);
    public function unlockById($workflowId);
}
