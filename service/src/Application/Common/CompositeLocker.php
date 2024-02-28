<?php

namespace Fxtm\CopyTrading\Application\Common;

use Fxtm\CopyTrading\Application\Common\Workflow\AbstractWorkflow;
use Fxtm\CopyTrading\Application\Common\Workflow\Locker;

class CompositeLocker implements Locker
{
    /**
     * @var Locker[]
     */
    private $lockers = [];

    public function __construct(array $lockers)
    {
        $this->lockers = $lockers;
    }

    public function lock(AbstractWorkflow $workflow, $expire = 0)
    {
        foreach ($this->lockers as $locker) {
            if (!$locker->lock($workflow, $expire)) {
                return false;
            }
        }
        return true;
    }

    public function unlock(AbstractWorkflow $workflow)
    {
        foreach (array_reverse($this->lockers) as $locker) {
            if (!$locker->unlock($workflow)) {
                return false;
            }
        }
        return true;
    }

    public function unlockById($workflowId)
    {
        foreach (array_reverse($this->lockers) as $locker) {
            if (!$locker->unlockById($workflowId)) {
                return false;
            }
        }
        return true;
    }
}
