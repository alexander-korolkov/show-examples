<?php

namespace Fxtm\CopyTrading\Application\Common\Workflow;

interface Scheduler
{
    public function chooseTime(AbstractWorkflow $workflow);
}
