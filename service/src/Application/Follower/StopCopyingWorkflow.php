<?php

namespace Fxtm\CopyTrading\Application\Follower;

use Fxtm\CopyTrading\Application\Common\BaseWorkflow;
use Fxtm\CopyTrading\Application\Common\Workflow\Activity;
use Fxtm\CopyTrading\Application\Common\Workflow\WorkflowState;
use Fxtm\CopyTrading\Application\NotificationGateway;
use Fxtm\CopyTrading\Application\PluginGatewayManager;
use Fxtm\CopyTrading\Domain\Common\DateTime;
use Fxtm\CopyTrading\Domain\Model\Follower\FollowerAccountRepository;
use Fxtm\CopyTrading\Domain\Model\Leader\LeaderAccountRepository;
use Fxtm\CopyTrading\Domain\Model\Shared\AccountNumber;

class StopCopyingWorkflow extends BaseWorkflow
{
    const TYPE = "follower.stop_copying";

    const REASON_UNKNOWN             = "UNKNOWN";
    const REASON_PROTECTION_LEVEL    = "PROTECTION_LEVEL";
    const REASON_INSUFFICIENT_FUNDS  = "INSUFFICIENT_FUNDS";

    /**
     * @var FollowerAccountRepository
     */
    private $follAccRepo;

    /**
     * @var LeaderAccountRepository
     */
    private $leadAccRepo;

    private $notifGateway  = null;
    private $pluginManager = null;

    /**
     * StopCopyingWorkflow constructor.
     * @param FollowerAccountRepository $follAccRepo
     * @param LeaderAccountRepository $leadAccRepo
     * @param NotificationGateway $notifGateway
     * @param PluginGatewayManager $pluginManager
     */
    public function __construct(
        FollowerAccountRepository $follAccRepo,
        LeaderAccountRepository $leadAccRepo,
        NotificationGateway $notifGateway,
        PluginGatewayManager $pluginManager
    ) {
        $this->follAccRepo   = $follAccRepo;
        $this->leadAccRepo   = $leadAccRepo;
        $this->notifGateway  = $notifGateway;
        $this->pluginManager = $pluginManager;

        parent::__construct(
            $this->activities([
                "waitForPlugin",
                "updateDatabase",
                "notifyClient",
            ])
        );
    }

    protected function doProceed()
    {
        if (!$this->getContext()->has("msgId")) {
            return WorkflowState::REJECTED;
        }
        return parent::doProceed();
    }

    protected function waitForPlugin(Activity $activity): void
    {
        $follAcc = $this->follAccRepo->getLightAccountOrFail($this->getAccountNumber());
        $msgId = $this->getContext()->get('msgId');

        if ($activity->isFirstTry()) {
            $activity->getContext()->set('msgId', $msgId);
            $activity->getContext()->set('server', $follAcc->server());
        }

        $pluginGateway = $this->pluginManager->getForAccount($follAcc);
        if ($pluginGateway->isMessageAcknowledged($msgId)) {
            $activity->succeed();
            return;
        }

        $datetime = new DateTime();
        if ($datetime->isWeekend()) {
            $this->scheduleAt(DateTime::of("+5 minutes"));
        }

        $activity->keepTrying();
    }

    protected function updateDatabase(Activity $activity): void
    {
        $follAcc = $this->follAccRepo->getLightAccountOrFail($this->getAccountNumber());
        $follAcc->pauseCopying($this);
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

    protected function notifyClient(Activity $activity): void
    {
        list($notifType, $params) = $this->getNotificationTypeAndParams($this->getContext()->getIfHas("reason"));
        $follAcc = $this->follAccRepo->getLightAccountOrFail($this->getAccountNumber());
        $this->notifGateway->notifyClient($follAcc->ownerId(), $follAcc->broker(), $notifType, $params);
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

    private function getNotificationTypeAndParams($reason)
    {
        $follAcc = $this->follAccRepo->findOrFail($this->getAccountNumber());
        $leadAcc = $this->leadAccRepo->getLightAccount($follAcc->leaderAccountNumber());
        return [
            NotificationGateway::FOLLOWER_COPYING_STOPPED,
            [
                "accNo"       => $follAcc->number()->value(),
                "leadAccNo"   => $leadAcc->number()->value(),
                "leadAccName" => $leadAcc->name(),
                "reason"      => $reason ?: self::REASON_UNKNOWN,
                "accCurr"     => $follAcc->currency()->code(),
                "reqAmount"   => ($reason == self::REASON_INSUFFICIENT_FUNDS)
                    ? $follAcc->requiredEquity()->subtract($follAcc->equity())->amount()
                    : 0,
                "isPublic"    => $leadAcc->isPublic()
            ]
        ];
    }
}
