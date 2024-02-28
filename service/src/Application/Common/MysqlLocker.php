<?php

namespace Fxtm\CopyTrading\Application\Common;

use Doctrine\DBAL\Connection;
use Fxtm\CopyTrading\Application\Common\Workflow\AbstractWorkflow;
use Fxtm\CopyTrading\Application\Common\Workflow\Locker;

class MysqlLocker implements Locker
{
    private $dbConn = null;

    public function __construct(Connection $dbConn)
    {
        $this->dbConn = $dbConn;
    }

    public function lock(AbstractWorkflow $workflow, $expire = 0)
    {
        return boolval($this->dbConn->query("SELECT GET_LOCK('{$this->key($workflow->id())}', 0)")->fetchColumn());
    }

    public function unlock(AbstractWorkflow $workflow)
    {
        return boolval($this->dbConn->query("SELECT RELEASE_LOCK('{$this->key($workflow->id())}')")->fetchColumn());
    }

    private function key($workflowId)
    {
        return "workflow_{$workflowId}";
    }

    public function unlockById($workflowId)
    {
        return boolval($this->dbConn->query("SELECT RELEASE_LOCK('{$this->key($workflowId)}')")->fetchColumn());
    }
}
