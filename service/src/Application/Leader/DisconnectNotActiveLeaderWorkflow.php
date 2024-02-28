<?php

namespace Fxtm\CopyTrading\Application\Leader;

use Fxtm\CopyTrading\Application\Common\BaseParentalWorkflow;
use Fxtm\CopyTrading\Application\Common\Workflow\Activity;
use Fxtm\CopyTrading\Application\Common\Workflow\ContextData;
use Fxtm\CopyTrading\Application\Common\Workflow\WorkflowManager;
use Fxtm\CopyTrading\Application\Common\Workflow\WorkflowRepository;
use Fxtm\CopyTrading\Application\Follower\CloseAccountWorkflow as CloseFollowerAccountWorkflow;
use Fxtm\CopyTrading\Application\NotificationGateway;
use Fxtm\CopyTrading\Application\PluginGateway;
use Fxtm\CopyTrading\Application\PluginGatewayManager;
use Fxtm\CopyTrading\Domain\Common\DateTime;
use Fxtm\CopyTrading\Domain\Model\Follower\FollowerAccountRepository;
use Fxtm\CopyTrading\Domain\Model\Leader\LeaderAccountRepository;
use Fxtm\CopyTrading\Domain\Model\Shared\AccountNumber;

class DisconnectNotActiveLeaderWorkflow extends BaseParentalWorkflow
{
    public const TYPE = "leader.disconnect_not_active";
    public const REASON_LOST_ALL_MONEY = 'lost_all_money';
    public const REASON_NO_TRADING_ACTIVITY = 'no_trading_activity';

    /**
     * @var LeaderAccountRepository
     */
    private $leaderAccountRepository;

    /**
     * @var FollowerAccountRepository
     */
    private $followerAccountRepository;

    /**
     * @var PluginGatewayManager
     */
    private $pluginGatewayManager;

    /**
     * @var NotificationGateway
     */
    private $notificationGateway;

    /**
     * DisconnectNotActiveLeaderWorkflow constructor.
     * @param LeaderAccountRepository $leaderAccountRepository
     * @param FollowerAccountRepository $followerAccountRepository
     * @param PluginGatewayManager $pluginGatewayManager
     * @param WorkflowRepository $workflowRepository
     * @param WorkflowManager $workflowManager
     * @param NotificationGateway $notificationGateway
     */
    public function __construct(
        LeaderAccountRepository $leaderAccountRepository,
        FollowerAccountRepository $followerAccountRepository,
        PluginGatewayManager $pluginGatewayManager,
        WorkflowRepository $workflowRepository,
        WorkflowManager $workflowManager,
        NotificationGateway $notificationGateway
    ) {
        $this->leaderAccountRepository = $leaderAccountRepository;
        $this->followerAccountRepository = $followerAccountRepository;
        $this->pluginGatewayManager = $pluginGatewayManager;
        $this->workflowRepository = $workflowRepository;
        $this->workflowManager = $workflowManager;
        $this->notificationGateway = $notificationGateway;

        parent::__construct(
            $this->activities([
                'tellPluginToStopFollowerAccounts',
                'closeFollowerAccounts',
                'tellPluginToDisableCopying',
                'updateLeaderAccount',
                'notifyClient',
            ])
        );
    }

    protected function doProceed()
    {
        if (
            $this->isFirstTry() &&
            empty($this->followerAccountRepository->findOpenByLeaderAccountNumber($this->getAccountNumber()))
        ) {
            $this->getContext()->set('followersAlreadyClosed', true);
        }

        return parent::doProceed();
    }

    protected function tellPluginToStopFollowerAccounts(Activity $activity): void
    {
        if ($this->getContext()->has('followersAlreadyClosed')) {
            $activity->skip();
            return;
        }

        $leader = $this->leaderAccountRepository->getLightAccountOrFail($this->getAccountNumber());
        if (!$leader->isCopied()) {
            $activity->skip();
            return;
        }

        $pluginGateway = $this->pluginGatewayManager->getForAccount($leader);

        $actCtx = $activity->getContext();
        if (!$actCtx->has('msgId')) {
            $msgId = $pluginGateway->sendMessage($this->getAccountNumber(), $this->id(), PluginGateway::FOLLOWER_COPYING_ALL, 0);
            $actCtx->set('msgId', $msgId);
            $actCtx->set('server', $leader->server());
        }

        if ($pluginGateway->isMessageAcknowledged($actCtx->get('msgId'))) {
            $activity->succeed();
            return;
        }

        $activity->keepTrying();
    }

    protected function closeFollowerAccounts(Activity $activity): void
    {
        if ($this->getContext()->has('followersAlreadyClosed')) {
            $this->logDebug($activity, __FUNCTION__, 'followers already closed');
            $activity->skip();
            return;
        }

        $accounts = $this->followerAccountRepository->findOpenByLeaderAccountNumber($this->getAccountNumber());
        if ($this->isFirstTry() && empty($accounts)) {
            $this->logDebug($activity, __FUNCTION__, 'there are no open follower accounts, skipping');
            $activity->skip();
            return;
        }

        if (empty($accounts)) {
            $this->logDebug($activity, __FUNCTION__, 'there are no more open follower accounts, succeeded');
            $activity->succeed();
            return;
        }

        $activityCtx = $activity->getContext();
        if (!$activityCtx->has("workflowsCreated")) {
            $delay = 3;
            $reason = $this->getReason() === self::REASON_LOST_ALL_MONEY ?
                CloseFollowerAccountWorkflow::REASON_LEADER_LOST_ALL_MONEY :
                CloseFollowerAccountWorkflow::REASON_DISCONNECTED_FROM_INACTIVE_LEADER;
            foreach ($accounts as $accNo => $follower) {
                $workflow = $this->createDetached(
                    CloseFollowerAccountWorkflow::TYPE,
                    new ContextData([
                        'accNo' => $accNo,
                        ContextData::REASON => $reason,
                        'alreadyPaused' => 1,
                        'doNotDisableTheLeader' => true,
                        'broker' => $follower->broker(),
                        'parentId' => $this->id()
                    ]),
                    DateTime::of("+{$delay} seconds")
                );
                if (!$this->workflowManager->enqueueWorkflow($workflow)) {
                    $this->logDebug(
                        $activity,
                        __FUNCTION__,
                        "Workflow manager unable to create child workflow; see previous errors"
                    );
                    $activity->fail();
                    return;
                }
                $delay += 3;
            }
            $activityCtx->set("workflowsCreated", true);
        }

        $this->logDebug($activity, __FUNCTION__, 'not yet finished, keep trying');
        $activity->keepTrying();
    }

    protected function tellPluginToDisableCopying(Activity $activity): void
    {
        $leader = $this->leaderAccountRepository->getLightAccountOrFail($this->getAccountNumber());
        if (!$leader->isCopied()) {
            $activity->skip();
            return;
        }

        $pluginGateway = $this->pluginGatewayManager->getForAccount($leader);

        $actCtx = $activity->getContext();
        if (!$actCtx->has('msgId')) {
            $msgId = $pluginGateway->sendMessage(
                $this->getAccountNumber(),
                $this->id(),
                PluginGateway::LEADER_COPIED_NOT,
                0
            );
            $actCtx->set('msgId', $msgId);
            $actCtx->set('server', $leader->server());
        }

        if ($pluginGateway->isMessageAcknowledged($actCtx->get("msgId"))) {
            $activity->succeed();
            return;
        }

        $activity->keepTrying();
    }

    protected function updateLeaderAccount(Activity $activity): void
    {
        try {
            $leader = $this->leaderAccountRepository->getLightAccountOrFail($this->getAccountNumber());

            $leader->disableCopying();
            $leader->deactivate();
            $this->leaderAccountRepository->store($leader);
        } catch (\Exception $e) {
            $this->logException($e);
            $activity->fail();
            return;
        }
        $activity->succeed();
    }

    protected function notifyClient(Activity $activity): void
    {
        $leader = $this->leaderAccountRepository->getLightAccountOrFail($this->getAccountNumber());

        $msgType = $this->getReason() === self::REASON_LOST_ALL_MONEY ?
            NotificationGateway::COPY_TRADING_LEADER_LOST_ALL_MONEY : NotificationGateway::LEADER_ACC_INACTIVE_CLOSED;
        $this->notificationGateway->notifyClient(
            $leader->ownerId(),
            $leader->broker(),
            $msgType,
            [
                'accNo'   => $leader->number()->value(),
                'accName' => $leader->name(),
                'accCurr' => $leader->currency()->code(),
            ]
        );

        $activity->succeed();
    }

    private function getReason()
    {
        return $this->getContext()->get(ContextData::REASON);
    }

    private function getAccountNumber()
    {
        return new AccountNumber($this->getContext()->get('accNo'));
    }

    public function getAccountRepository()
    {
        return $this->leaderAccountRepository;
    }
}
