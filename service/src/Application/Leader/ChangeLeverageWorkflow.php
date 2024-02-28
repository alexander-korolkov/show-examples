<?php

namespace Fxtm\CopyTrading\Application\Leader;

use Fxtm\CopyTrading\Application\Common\BaseParentalWorkflow;
use Fxtm\CopyTrading\Application\Common\Workflow\Activity;
use Fxtm\CopyTrading\Application\Common\Workflow\ContextData;
use Fxtm\CopyTrading\Application\Common\Workflow\WorkflowManager;
use Fxtm\CopyTrading\Application\Common\Workflow\WorkflowRepository;
use Fxtm\CopyTrading\Application\Common\Workflow\WorkflowState;
use Fxtm\CopyTrading\Application\Follower\CloseAccountWorkflow;
use Fxtm\CopyTrading\Application\Follower\LockInSafeModeWorkflow;
use Fxtm\CopyTrading\Application\LeverageService;
use Fxtm\CopyTrading\Application\TradeAccountGateway;
use Fxtm\CopyTrading\Domain\Model\Follower\FollowerAccountRepository;
use Fxtm\CopyTrading\Domain\Model\Leader\LeaderAccountRepository;
use Fxtm\CopyTrading\Domain\Model\Shared\AccountNumber;

class ChangeLeverageWorkflow extends BaseParentalWorkflow
{
    const TYPE = "leader.change_leverage";

    private $tradeAccGateway = null;

    /**
     * @var LeaderAccountRepository
     */
    private $leadAccRepo;

    /**
     * @var FollowerAccountRepository
     */
    private $follAccRepo;

    private $leverageSvc     = null;

    /**
     * ChangeLeverageWorkflow constructor.
     * @param TradeAccountGateway $tradeAccGateway
     * @param LeaderAccountRepository $leadAccRepo
     * @param FollowerAccountRepository $follAccRepo
     * @param LeverageService $leverageSvc
     * @param WorkflowManager $workflowManager
     * @param WorkflowRepository $workflowRepository
     */
    public function __construct(
        TradeAccountGateway $tradeAccGateway,
        LeaderAccountRepository $leadAccRepo,
        FollowerAccountRepository $follAccRepo,
        LeverageService $leverageSvc,
        WorkflowManager $workflowManager,
        WorkflowRepository $workflowRepository
    ) {
        $this->tradeAccGateway = $tradeAccGateway;
        $this->leadAccRepo     = $leadAccRepo;
        $this->follAccRepo     = $follAccRepo;
        $this->leverageSvc     = $leverageSvc;
        $this->workflowManager      = $workflowManager;
        $this->workflowRepository   = $workflowRepository;

        parent::__construct(
            $this->activities([
                "changeLeverage",
                "updateFollowers",
            ])
        );
    }

    protected function doProceed()
    {
        $leadAcc = $this->leadAccRepo->findOrFail($this->getAccountNumber());
        if ($leadAcc->hasOpenPositions()) {
            return WorkflowState::REJECTED;
        }
        return parent::doProceed();
    }

    protected function changeLeverage(Activity $activity): void
    {
        if (!$this->tradeAccGateway->changeAccountLeverage($this->getAccountNumber(), $this->getBroker(), $this->getLeverage())) {
            $activity->fail();
            return;
        }
        $activity->succeed();
    }

    protected function updateFollowers(Activity $activity): void
    {
        $follAccs = $this->follAccRepo->findOpenByLeaderAccountNumber($this->getAccountNumber());
        if (empty($follAccs)) {
            $activity->skip();
            return;
        }

        $allAreDone = true;
        foreach ($follAccs as $follAcc) {

            try {
                $this->log(sprintf("%s:%d, acc_no #%d", __FUNCTION__, $activity->id(), $follAcc->number()->value()));

                $ratio = $this->leverageSvc->getLeverageRatio($this->getAccountNumber(), $this->getBroker(), $follAcc->ownerId(), $follAcc->broker());
                $this->log(sprintf("%s:%d, ratio %.2f", __FUNCTION__, $activity->id(), $ratio));

                if ($ratio <= 1) {
                    $this->log(sprintf("%s:%d, ratio <= 1", __FUNCTION__, $activity->id()));

                    if ($follAcc->isCopyingLocked() || $follAcc->isCopyCoefficientLocked()) {
                        if ($follAcc->isCopyingLocked()) {
                            $follAcc->lockCopying(false);

                            $this->log(sprintf("%s:%d, copying unlocked", __FUNCTION__, $activity->id()));
                        }

                        if ($follAcc->isCopyCoefficientLocked()) {
                            $follAcc->lockCopyCoefficient(false);

                            $this->log(sprintf("%s:%d, copy coefficient unlocked", __FUNCTION__, $activity->id()));
                        }
                        $this->follAccRepo->store($follAcc);
                    }
                } else if ($ratio > 1 && $ratio <= 2) {
                    $this->log(sprintf("%s:%d, 1 < ratio <= 2", __FUNCTION__, $activity->id()));

                    if ($follAcc->isCopyingLocked()) {
                        $follAcc->lockCopying(false);
                        $this->follAccRepo->store($follAcc);

                        $this->log(sprintf("%s:%d, copying unlocked", __FUNCTION__, $activity->id()));
                    }

                    $status = $this->findCreateExecute(
                        LockInSafeModeWorkflow::TYPE,
                        $follAcc->number()->value(),
                        function () use ($follAcc) {
                            return $this->createChild(
                                LockInSafeModeWorkflow::TYPE,
                                new ContextData(["accNo" => $follAcc->number()->value(), "broker" => $follAcc->broker()])
                            );
                        }
                    );

                    if($status->isInterrupted()) {
                        $activity->fail();
                        return;
                    }

                    $lockInSafeMode = $status->getChild();
                    $allAreDone &= $lockInSafeMode->isCompleted();

                    $this->log(sprintf("%s:%d, follower.lock_in_safe_mode #%d", __FUNCTION__, $activity->id(), $lockInSafeMode->id()));
                } else if ($ratio > 2) {
                    $this->log(sprintf("%s:%d, ratio > 2", __FUNCTION__, $activity->id()));

                    $status = $this->findCreateExecute(
                        $follAcc->number()->value(),
                        CloseAccountWorkflow::TYPE,
                        function() use ($follAcc) {
                            return $this->createChild(
                                CloseAccountWorkflow::TYPE,
                                new ContextData([
                                    "accNo" => $follAcc->number()->value(),
                                    "reason" => CloseAccountWorkflow::REASON_INCOMPATIBLE_LEVERAGE,
                                    'broker' => $follAcc->broker(),
                                ])
                            );
                        }
                    );

                    if($status->isInterrupted()) {
                        $activity->fail();
                        return;
                    }

                    $closeAccount = $status->getChild();

                    $allAreDone &= $closeAccount->isCompleted();

                    $this->log(sprintf("%s:%d, follower.close_account #%d", __FUNCTION__, $activity->id(), $closeAccount->id()));
                } else {
                    $this->log(sprintf("%s:%d, ratio <=> ?", __FUNCTION__, $activity->id()));
                }
            } catch (\Exception $e) {
                $this->log(sprintf("%s:%d, %s", __FUNCTION__, $activity->id(), json_encode($follAcc->toArray())));
                $this->logException($e);
                continue;
            }
        }

        if($allAreDone) {
            $activity->succeed();
        }
        else {
            $activity->keepTrying();
        }
    }

    private function getAccountNumber()
    {
        return new AccountNumber($this->getContext()->get("accNo"));
    }

    private function getLeverage()
    {
        return $this->getContext()->get("leverage");
    }

    public function getAccountRepository()
    {
        return $this->leadAccRepo;
    }

    private function log($message, array $context = [])
    {
        $this->getLogger()->debug(sprintf("%s:%d, %s", self::TYPE, $this->id(), $message), $context);
    }

    /**
     * Method should return true if all necessary conditions for
     * creating of this workflow are met
     *
     * @return bool
     */
    public function canBeCreated()
    {
        return !empty($this->leadAccRepo->getLightAccount($this->getAccountNumber()));
    }

    private function getBroker()
    {
        return $this->getContext()->get('broker');
    }
}
