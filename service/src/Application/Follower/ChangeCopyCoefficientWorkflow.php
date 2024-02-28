<?php

namespace Fxtm\CopyTrading\Application\Follower;

use Fxtm\CopyTrading\Application\Common\Workflow\Activity;
use Fxtm\CopyTrading\Application\Common\Workflow\WorkflowManager;
use Fxtm\CopyTrading\Application\Common\Workflow\WorkflowRepository;
use Fxtm\CopyTrading\Domain\Model\Follower\FollowerAccountRepository;
use Fxtm\CopyTrading\Domain\Model\Shared\AccountNumber;

class ChangeCopyCoefficientWorkflow extends BasePauseResumeWorkflow
{
    const TYPE = "follower.change_copy_coef";

    /**
     * ChangeCopyCoefficientWorkflow constructor.
     * @param WorkflowManager $workflowManager
     * @param WorkflowRepository $workflowRepo
     * @param FollowerAccountRepository $follAccRepo
     */
    public function __construct(
        WorkflowManager $workflowManager,
        WorkflowRepository $workflowRepo,
        FollowerAccountRepository $follAccRepo
    ) {
        $this->workflowManager      = $workflowManager;
        $this->workflowRepository   = $workflowRepo;
        $this->follAccRepo          = $follAccRepo;

        parent::__construct(
            $this->activities([
                "stopCopying",
                "changeCopyCoefficient",
                "startCopying",
            ])
        );
    }

    protected function changeCopyCoefficient(Activity $activity): void
    {
        $follAcc = $this->follAccRepo->getLightAccountOrFail($this->getAccountNumber());
        $follAcc->changeCopyCoefficient($this->getCopyCoefficient(), $this);

        try {
            $this->follAccRepo->store($follAcc);
        } catch (\Exception $e) {
            $this->logException($e);
            $activity->fail();
            return;
        }
        $activity->succeed();
    }

    private function getAccountNumber()
    {
        return new AccountNumber($this->getContext()->get("accNo"));
    }

    private function getCopyCoefficient()
    {
        return $this->getContext()->get("copyCoef");
    }

    public function getAccountRepository()
    {
        return $this->follAccRepo;
    }
}
