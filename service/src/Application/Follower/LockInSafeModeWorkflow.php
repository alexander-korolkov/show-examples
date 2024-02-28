<?php

namespace Fxtm\CopyTrading\Application\Follower;

use Fxtm\CopyTrading\Application\Common\BaseParentalWorkflow;
use Fxtm\CopyTrading\Application\Common\Workflow\Activity;
use Fxtm\CopyTrading\Application\Common\Workflow\ContextData;
use Fxtm\CopyTrading\Application\Common\Workflow\WorkflowManager;
use Fxtm\CopyTrading\Application\Common\Workflow\WorkflowRepository;
use Fxtm\CopyTrading\Application\LeverageService;
use Fxtm\CopyTrading\Application\NotificationGateway;
use Fxtm\CopyTrading\Domain\Model\Follower\FollowerAccount;
use Fxtm\CopyTrading\Domain\Model\Follower\FollowerAccountRepository;
use Fxtm\CopyTrading\Domain\Model\Shared\AccountNumber;

class LockInSafeModeWorkflow extends BaseParentalWorkflow
{
    const TYPE = "follower.lock_in_safe_mode";

    /**
     * @var FollowerAccountRepository
     */
    private $follAccRepo;

    private $notifGateway    = null;
    private $leverageSvc     = null;

    /**
     * LockInSafeModeWorkflow constructor.
     * @param WorkflowManager $workflowManager
     * @param WorkflowRepository $workflowRepo
     * @param FollowerAccountRepository $follAccRepo
     * @param NotificationGateway $notifGateway
     * @param LeverageService $leverageSvc
     */
    public function __construct(
        WorkflowManager $workflowManager,
        WorkflowRepository $workflowRepo,
        FollowerAccountRepository $follAccRepo,
        NotificationGateway $notifGateway,
        LeverageService $leverageSvc
    ) {
        $this->workflowManager      = $workflowManager;
        $this->workflowRepository   = $workflowRepo;
        $this->follAccRepo     = $follAccRepo;
        $this->notifGateway    = $notifGateway;
        $this->leverageSvc     = $leverageSvc;

        parent::__construct(
            $this->activities([
                "pauseCopying",
                "lockInSafeMode",
                "notifyFollower",
            ])
        );
    }

    protected function pauseCopying(Activity $activity): void
    {
        $follAcc = $this->follAccRepo->getLightAccountOrFail($this->getAccountNumber());
        if (!$follAcc->isCopying()) {
            $activity->skip();
            return;
        }

        $status = $this->findCreateExecute(
            PauseCopyingWorkflow::TYPE,
            $this->getCorrelationId(),
            function () use ($follAcc) {
                return $this->createChild(
                    PauseCopyingWorkflow::TYPE,
                    new ContextData([
                        "accNo" => $this->getCorrelationId(),
                        "reason" => PauseCopyingWorkflow::REASON_LEADER_LEVERAGE,
                        "broker" => $follAcc->broker(),
                    ])
                );
            }
        );

        $status->updateActivity($activity);
    }

    protected function lockInSafeMode(Activity $activity): void
    {
        $follAcc = $this->follAccRepo->getLightAccountOrFail($this->getAccountNumber());
        $follAcc->changeCopyCoefficient(FollowerAccount::SAFE_MODE_COPY_COEFFICIENT, $this);
        $follAcc->lockCopyCoefficient();
        try {
            $this->follAccRepo->store($follAcc);
        } catch (\Exception $e) {
            $this->logException($e);
            $activity->fail();
            return;
        }
        $activity->succeed();
    }

    protected function notifyFollower(Activity $activity): void
    {
        $follAcc = $this->follAccRepo->getLightAccountOrFail($this->getAccountNumber());
        $leverage = $this->leverageSvc->getMaxAllowedLeverageForFollowerAccount($follAcc);
        $this->notifGateway->notifyClient(
            $follAcc->ownerId(),
            $follAcc->broker(),
            NotificationGateway::FOLLOWER_LOCKED_IN_SAFE_MODE,
            $this->getContext()->toArray() + [
                "accounts"  => [$this->getContext()->get("accNo")],
                "leverage"  => $leverage,
                "leverage2" => $leverage * 2,
            ]
        );
        $activity->succeed();
    }

    private function getAccountNumber()
    {
        return new AccountNumber($this->getContext()->get("accNo"));
    }

    public function getAccountRepository()
    {
        return $this->follAccRepo;
    }
}
