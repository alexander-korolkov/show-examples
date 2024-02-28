<?php

namespace Fxtm\CopyTrading\Application\Leader;

use Fxtm\CopyTrading\Application\Common\BaseParentalWorkflow;
use Fxtm\CopyTrading\Application\Common\Workflow\Activity;
use Fxtm\CopyTrading\Application\Common\Workflow\ContextData;
use Fxtm\CopyTrading\Application\Common\Workflow\WorkflowManager;
use Fxtm\CopyTrading\Application\Common\Workflow\WorkflowRepository;
use Fxtm\CopyTrading\Application\Common\Workflow\WorkflowState;
use Fxtm\CopyTrading\Application\Follower\CloseAccountWorkflow as CloseFollowerAccountWorkflow;
use Fxtm\CopyTrading\Application\PluginGateway;
use Fxtm\CopyTrading\Application\PluginGatewayManager;
use Fxtm\CopyTrading\Domain\Common\DateTime;
use Fxtm\CopyTrading\Domain\Model\Follower\FollowerAccount;
use Fxtm\CopyTrading\Domain\Model\Follower\FollowerAccountRepository;
use Fxtm\CopyTrading\Domain\Model\Leader\LeaderAccountRepository;
use Fxtm\CopyTrading\Domain\Model\Shared\AccountNumber;

class CloseFollowerAccountsWorkflow extends BaseParentalWorkflow
{
    const TYPE = "leader.close_followers";

    /**
     * @var LeaderAccountRepository
     */
    private $leadAccRepo;

    /**
     * @var FollowerAccountRepository
     */
    private $follAccRepo;

    private $pluginManager   = null;

    /**
     * CloseFollowerAccountsWorkflow constructor.
     * @param LeaderAccountRepository $leadAccRepo
     * @param FollowerAccountRepository $follAccRepo
     * @param PluginGatewayManager $pluginManager
     * @param WorkflowManager $workflowManager
     * @param WorkflowRepository $workflowRepo
     */
    public function __construct(
        LeaderAccountRepository $leadAccRepo,
        FollowerAccountRepository $follAccRepo,
        PluginGatewayManager $pluginManager,
        WorkflowManager $workflowManager,
        WorkflowRepository $workflowRepo
    ) {
        $this->leadAccRepo          = $leadAccRepo;
        $this->follAccRepo          = $follAccRepo;
        $this->pluginManager        = $pluginManager;
        $this->workflowManager      = $workflowManager;
        $this->workflowRepository   = $workflowRepo;

        parent::__construct(
            $this->activities([
                'tellPluginToStopFollowerAccounts',
                'updateFollowerCopyingStatus',
                'closeFollowerAccounts',
                'disableCopying',
            ])
        );
    }

    protected function doProceed()
    {
        if ($this->isFirstTry() && empty($this->follAccRepo->findOpenByLeaderAccountNumber($this->getAccountNumber()))) {
            return WorkflowState::REJECTED;
        }
        return parent::doProceed();
    }

    protected function tellPluginToStopFollowerAccounts(Activity $activity): void
    {
        $leadAcc = $this->leadAccRepo->getLightAccountOrFail($this->getAccountNumber());
        if (!$leadAcc->isCopied()) {
            $activity->skip();
            return;
        }

        $pluginGateway = $this->pluginManager->getForAccount($leadAcc);

        $actCtx = $activity->getContext();
        if (!$actCtx->has("msgId")) {
            $actCtx->set('msgId', $pluginGateway->sendMessage($this->getAccountNumber(), $this->id(), PluginGateway::FOLLOWER_COPYING_ALL, 0));
            $actCtx->set('server', $leadAcc->server());
        }

        if ($pluginGateway->isMessageAcknowledged($actCtx->get("msgId"))) {
            $activity->succeed();
            return;
        }

        $activity->keepTrying();
    }

    protected function updateFollowerCopyingStatus(Activity $activity): void
    {
        $follAccs = $this->getFollowersOrSkip($activity);
        if (is_null($follAccs)) {
            return;
        }

        /** @var FollowerAccount $follower */
        foreach ($follAccs as $follower) {
            try {
                $follower->pauseCopying($this);
                $this->follAccRepo->store($follower);
            }
            catch (\Throwable $any) {
                $this->logException($any);
            }
        }
        $activity->succeed();
    }

    protected function closeFollowerAccounts(Activity $activity): void
    {
        $accounts = $this->follAccRepo->findOpenByLeaderAccountNumber($this->getAccountNumber());
        if($this->isFirstTry()) {
            if(empty($accounts)) {
                $activity->skip();
                return;
            }
        }

        if(empty($accounts)) {
            $activity->succeed();
            return;
        }

        $activityCtx = $activity->getContext();
        if(!$activityCtx->has("workflowsCreated")) {
            $delay = 3;
            foreach ($accounts as $accNo => $follAcc) {
                $workflow = $this->createDetached(
                    CloseFollowerAccountWorkflow::TYPE,
                    new ContextData([
                        "accNo" => $accNo,
                        "reason" => CloseFollowerAccountWorkflow::REASON_CLOSED_BY_LEADER,
                        "alreadyPaused" => 1,
                        'doNotDisableTheLeader' => true,
                        'broker' => $follAcc->broker(),
                        'parentId' => $this->id()
                    ]),
                    DateTime::of("+{$delay} seconds")
                );
                if(!$this->workflowManager->enqueueWorkflow($workflow)) {
                    $this->logDebug($activity, __FUNCTION__, "Workflow manager unable to create child workflow; see previous errors");
                    $activity->fail();
                    return;
                }
                $delay += 3;
            }
            $activityCtx->set("workflowsCreated", true);
        }

        // Keep trying this activity until the last of followers is closed
        $activity->keepTrying();
    }

    protected function disableCopying(Activity $activity): void
    {
        $leadAcc = $this->leadAccRepo->getLightAccountOrFail($this->getAccountNumber());
        if (!$leadAcc->isCopied()) {
            $activity->skip();
            return;
        }

        $status = $this->findCreateExecute(
            $this->getCorrelationId(),
            DisableCopyingWorkflow::TYPE,
            function() use ($leadAcc) {
                return $this->createChild(
                    DisableCopyingWorkflow::TYPE,
                    new ContextData([
                        "accNo" => $leadAcc->number()->value(),
                        "broker" => $leadAcc->broker()
                    ])
                );
            }
        );

        $status->updateActivity($activity);
    }

    private function getAccountNumber(): AccountNumber
    {
        return new AccountNumber($this->getContext()->get("accNo"));
    }

    /**
     * Returns array of followers for current leader, or skips activity in case if the leader does not have any and
     *  returns NULL.
     *
     * @param Activity $activity
     *
     * @return FollowerAccount[]|null
     */
    private function getFollowersOrSkip(Activity $activity): ?array
    {
        $follAccs = $this->follAccRepo->findOpenByLeaderAccountNumber($this->getAccountNumber());
        if ($this->isFirstTry() && empty($follAccs)) {
            $activity->skip();
            return null;
        }
        return $follAccs;
    }

    public function getAccountRepository(): LeaderAccountRepository
    {
        return $this->leadAccRepo;
    }

    /**
     * Method should return true if all necessary conditions for
     * creating of this workflow are met
     *
     * @return bool
     */
    public function canBeCreated(): bool
    {
        return !empty($this->leadAccRepo->getLightAccount($this->getAccountNumber()));
    }

    public function getBroker(): string
    {
        return $this->getContext()->get('broker');
    }

}
