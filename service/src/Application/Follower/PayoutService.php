<?php

namespace Fxtm\CopyTrading\Application\Follower;

use Fxtm\CopyTrading\Application\Common\Workflow\WorkflowRepository;
use Fxtm\CopyTrading\Domain\Common\DateTime;
use Fxtm\CopyTrading\Domain\Common\DateTimeInterval;
use Fxtm\CopyTrading\Domain\Model\Shared\AccountNumber;

class PayoutService
{
    private $workflowRepo = null;

    public function __construct(WorkflowRepository $workflowRepo)
    {
        $this->workflowRepo = $workflowRepo;
    }

    public function getLastPayoutInterval(AccountNumber $accNo)
    {
        if (empty($workflow = $this->workflowRepo->findLastCompletedByCorrelationIdAndType($accNo->value(), ProcessClosingPeriodWorkflow::TYPE))) {
            return null;
        }
        return new DateTimeInterval(DateTime::of($workflow->getContext()->get("prevSettledAt")), $workflow->startedAt());
    }
}
