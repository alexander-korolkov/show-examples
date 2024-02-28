<?php

namespace Fxtm\CopyTrading\Application\Common;

use Fxtm\CopyTrading\Application\Common\Workflow\AbstractWorkflow;
use Fxtm\CopyTrading\Application\Common\Workflow\Locker;
use Memcache;

class MemcacheLocker implements Locker
{
    private $memcache = null;

    public function __construct($host, $port = 11211)
    {
        $this->memcache = new Memcache();
        $this->memcache->addserver($host, $port);
    }

    public function lock(AbstractWorkflow $workflow, $expire = 0)
    {
        return $this->memcache->add($this->key($workflow->id()), 1, 0, $expire);
    }

    public function unlock(AbstractWorkflow $workflow)
    {
        return $this->memcache->delete($this->key($workflow->id()));
    }

    private function key($workflowId)
    {
        return "workflow_{$workflowId}";
    }

    public function unlockById($workflowId)
    {
        return $this->memcache->delete($this->key($workflowId));
    }
}
