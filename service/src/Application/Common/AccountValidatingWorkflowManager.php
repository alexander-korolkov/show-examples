<?php

namespace Fxtm\CopyTrading\Application\Common;

use Fxtm\CopyTrading\Application\Common\Workflow\AbstractWorkflow;
use Fxtm\CopyTrading\Application\Common\Workflow\WorkflowManager;
use Fxtm\CopyTrading\Domain\Model\Shared\AccountNumber;
use Fxtm\CopyTrading\Domain\Model\Shared\BlockedAccount;
use Fxtm\CopyTrading\Domain\Model\Shared\ClosedAccount;

class AccountValidatingWorkflowManager extends WorkflowManager
{
    public function processWorkflow(AbstractWorkflow $workflow)
    {
        $this->checkAccount($workflow);
        return parent::processWorkflow($workflow);
    }

    public function enqueueWorkflow(AbstractWorkflow $workflow)
    {
        $this->checkAccount($workflow);
        return parent::enqueueWorkflow($workflow);
    }

    private function checkAccount(AbstractWorkflow $workflow)
    {
        $account = $workflow->getAccountRepository()
            ->getLightAccount(new AccountNumber($workflow->getCorrelationId()));
        if (empty($account)) {
            return;
        }

        $context = $workflow->getContext();
        if($context->has('forced') && $context->get('forced') === true) {
            return;
        }

        if ($workflow->getTriesCount() === 0 && $account->isClosed()) {
            if (!empty($workflow->id())) {
                $workflow->reject();
                $workflow->getContext()->set('message', 'Rejected because the account is closed.');
                parent::enqueueWorkflow($workflow);
            }
            throw new ClosedAccount();
        }
        if ($account->isBlocked()) {
            if (!empty($workflow->id())) {
                $workflow->reject();
                $workflow->getContext()->set('message', 'Rejected because the account is blocked.');
                parent::enqueueWorkflow($workflow);
            }
            throw new BlockedAccount();
        }
    }
}
