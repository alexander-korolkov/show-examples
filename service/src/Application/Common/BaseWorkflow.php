<?php

namespace Fxtm\CopyTrading\Application\Common;

use Fxtm\CopyTrading\Application\Common\Workflow\AbstractWorkflow;
use Fxtm\CopyTrading\Application\Common\Workflow\WorkflowMethodActivity;
use Fxtm\CopyTrading\Domain\Model\Shared\AccountNumber;

abstract class BaseWorkflow extends AbstractWorkflow
{
    protected function activities(array $names)
    {
        $activities = [];
        foreach ($names as $name) {
            $activities[$name] = new WorkflowMethodActivity($this, $name);
        }
        return $activities;
    }

    public function proceed()
    {
        $account = $this->getAccountRepository()
            ->getLightAccount(new AccountNumber($this->getCorrelationId()));

        if (empty($account) || !$account->isBlocked()) {
            parent::proceed();
        }
    }

    public function getAccount()
    {
        if (empty($corrId = $this->getCorrelationId())) {
            return null;
        }
        return $this->getAccountRepository()->find(new AccountNumber($corrId));
    }

    public function getCorrelationId() : int
    {
        return intval($this->getContext()->getIfHas("accNo"));
    }
}
