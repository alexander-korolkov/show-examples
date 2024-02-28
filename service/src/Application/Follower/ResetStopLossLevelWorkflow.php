<?php

namespace Fxtm\CopyTrading\Application\Follower;

use Fxtm\CopyTrading\Application\Common\Workflow\Activity;
use Fxtm\CopyTrading\Application\Common\Workflow\WorkflowManager;
use Fxtm\CopyTrading\Application\Common\Workflow\WorkflowRepository;
use Fxtm\CopyTrading\Application\NotificationGateway;
use Fxtm\CopyTrading\Application\PluginGateway;
use Fxtm\CopyTrading\Application\PluginGatewayManager;
use Fxtm\CopyTrading\Domain\Model\Follower\FollowerAccountRepository;
use Fxtm\CopyTrading\Domain\Model\Leader\LeaderAccountRepository;
use Fxtm\CopyTrading\Domain\Model\Shared\AccountNumber;
use Fxtm\CopyTrading\Domain\Model\Shared\Money;

class ResetStopLossLevelWorkflow extends BasePauseResumeWorkflow
{
    const TYPE = "follower.reset_stoploss_level";

    /**
     * @var LeaderAccountRepository
     */
    private $leadAccRepo;

    private $pluginManager = null;
    private $notifGateway  = null;

    /**
     * ResetStopLossLevelWorkflow constructor.
     * @param FollowerAccountRepository $follAccRepo
     * @param LeaderAccountRepository $leadAccRepo
     * @param PluginGatewayManager $pluginManager
     * @param NotificationGateway $notifGateway
     * @param WorkflowManager $workflowManager
     * @param WorkflowRepository $workflowRepo
     */
    public function __construct(
        FollowerAccountRepository $follAccRepo,
        LeaderAccountRepository $leadAccRepo,
        PluginGatewayManager $pluginManager,
        NotificationGateway $notifGateway,
        WorkflowManager $workflowManager,
        WorkflowRepository $workflowRepo
    ) {
        $this->leadAccRepo   = $leadAccRepo;
        $this->pluginManager = $pluginManager;
        $this->notifGateway  = $notifGateway;
        $this->follAccRepo   = $follAccRepo;
        $this->workflowManager      = $workflowManager;
        $this->workflowRepository   = $workflowRepo;

        parent::__construct(
            $this->activities([
                "calculateNewStopLossEquity",
                "stopCopying",
                "tellPluginToUpdateStoploss",
                "updateDatabase",
                "notifyFollower",
            ])
        );
    }

    protected function calculateNewStopLossEquity(Activity $activity): void
    {
        $follAcc = $this->follAccRepo->findOrFail($this->getAccountNumber());

        if ($follAcc->equity()->amount() <= 0.0) {
            $this->getContext()->set("pauseReason", $follAcc->equity()->amount());
            $activity->skip();
            return;
        }

        $this->getContext()->set(self::SKIP_STOP_COPYING_FLAG, true);

        $stopLossPercent = $follAcc->stopLossLevel();
        $currStopLossEquity = $follAcc->stopLossEquity();

        $this->getContext()->set("stopLossEquityPercent", $stopLossPercent);
        $this->getContext()->set("stopLossEquityPrev", $currStopLossEquity->amount());

        $currStopLossEquity =  $follAcc->equity()->divide(100)->multiply($stopLossPercent);

        $follAcc->setStopLossEquity($currStopLossEquity);

        $this->getContext()->set("stopLossEquityNew", $follAcc->stopLossEquity()->truncate()->amount());

        // don't save the changes yet
        $activity->succeed();
    }

    protected function tellPluginToUpdateStoploss(Activity $activity): void
    {
        if($this->getContext()->getIfHas("stopLossEquityNew") == null) {
            $activity->skip();
            return;
        }

        $follAcc = $this->follAccRepo->getLightAccountOrFail($this->getAccountNumber());
        $pluginGateway = $this->pluginManager->getForAccount($follAcc);

        $actCtx = $activity->getContext();
        if (!$actCtx->has("msgId")) {
            $msgId = $pluginGateway->sendMessage(
                $this->getAccountNumber(),
                $this->id(),
                PluginGateway::FOLLOWER_STOPLOSS,
                sprintf("%.2f;%d", $this->getContext()->get("stopLossEquityNew"), $follAcc->isCopyingStoppedOnStopLoss())
            );
            $actCtx->set('msgId', $msgId);
            $actCtx->set('server', $follAcc->server());
        }
        if ($pluginGateway->isMessageAcknowledged($actCtx->get("msgId"))) {
            $activity->succeed();
            return;
        }
        $activity->keepTrying();
    }

    protected function updateDatabase(Activity $activity): void
    {
        if($this->getContext()->getIfHas("stopLossEquityNew") == null) {
            $activity->skip();
            return;
        }

        $follAcc = $this->follAccRepo->getLightAccountOrFail($this->getAccountNumber());
        $follAcc->setStopLossEquity(new Money($this->getContext()->get("stopLossEquityNew"), $follAcc->currency()));

        try {
            $this->logDebug($activity, __FUNCTION__, 'store');
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
        $ctx = $this->getContext();
        $follAcc = $this->follAccRepo->getLightAccountOrFail($this->getAccountNumber());
        $ctx->set("accCurr", $follAcc->currency()->code());
        $leadAcc = $this->leadAccRepo->getLightAccountOrFail($follAcc->leaderAccountNumber());
        $ctx->set("leadAccName", $leadAcc->name());
        $ctx->set("isPublic", $leadAcc->isPublic());
        $this->notifGateway->notifyClient(
            $follAcc->ownerId(),
            $follAcc->broker(),
            NotificationGateway::FOLLOWER_STOPLOSS_REACHED,
            $ctx->toArray()
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
