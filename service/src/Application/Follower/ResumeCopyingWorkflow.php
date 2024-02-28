<?php

namespace Fxtm\CopyTrading\Application\Follower;

use Fxtm\CopyTrading\Application\ClientGateway;
use Fxtm\CopyTrading\Application\Common\BaseWorkflow;
use Fxtm\CopyTrading\Application\Common\Workflow\Activity;
use Fxtm\CopyTrading\Application\Common\Workflow\ContextData;
use Fxtm\CopyTrading\Application\Common\Workflow\WorkflowManager;
use Fxtm\CopyTrading\Application\Common\Workflow\WorkflowState;
use Fxtm\CopyTrading\Application\NotificationGateway;
use Fxtm\CopyTrading\Application\PluginGateway;
use Fxtm\CopyTrading\Application\PluginGatewayManager;
use Fxtm\CopyTrading\Application\SettingsRegistry;
use Fxtm\CopyTrading\Application\Utils\FloatUtils;
use Fxtm\CopyTrading\Domain\Common\DateTime;
use Fxtm\CopyTrading\Domain\Model\Follower\FollowerAccount;
use Fxtm\CopyTrading\Domain\Model\Follower\FollowerAccountRepository;
use Fxtm\CopyTrading\Domain\Model\Leader\LeaderAccountRepository;
use Fxtm\CopyTrading\Domain\Model\Shared\AccountNumber;
use Fxtm\CopyTrading\Interfaces\Gateway\Plugin\PluginException;

class ResumeCopyingWorkflow extends BaseWorkflow
{
    const TYPE = "follower.resume_copying";
    const ALLOWED_ATTEMPTS_COUNT = 3;

    /**
     * @var PluginGatewayManager $pluginManager
     */
    private $pluginManager = null;

    /**
     * @var FollowerAccountRepository
     */
    private $follAccRepo;

    /**
     * @var LeaderAccountRepository
     */
    private $leadAccRepo;

    /**
     * @var NotificationGateway $notifGateway
     */
    private $notifGateway = null;

    /**
     * @var ClientGateway $clientGateway
     */
    private $clientGateway   = null;

    /**
     * @var SettingsRegistry
     */
    private $settingsRegistry;

    /**
     * @var WorkflowManager
     */
    private $workflowManager = null;

    /**
     * ResumeCopyingWorkflow constructor.
     * @param PluginGatewayManager $pluginManager
     * @param FollowerAccountRepository $follAccRepo
     * @param LeaderAccountRepository $leadAccRepo
     * @param NotificationGateway $notifGateway
     * @param ClientGateway $clientGateway
     * @param SettingsRegistry $settingsRegistry
     * @param WorkflowManager $workflowManager
     */
    public function __construct(
        PluginGatewayManager $pluginManager,
        FollowerAccountRepository $follAccRepo,
        LeaderAccountRepository $leadAccRepo,
        NotificationGateway $notifGateway,
        ClientGateway $clientGateway,
        SettingsRegistry $settingsRegistry,
        WorkflowManager $workflowManager
    ) {
        $this->pluginManager = $pluginManager;
        $this->follAccRepo   = $follAccRepo;
        $this->leadAccRepo   = $leadAccRepo;
        $this->notifGateway  = $notifGateway;
        $this->clientGateway = $clientGateway;
        $this->settingsRegistry = $settingsRegistry;
        $this->workflowManager = $workflowManager;

        parent::__construct(
            $this->activities([
                "tellPluginToResumeCopying",
                "updateDatabase",
            ])
        );
    }

    protected function doProceed(): int
    {
        $this->log(sprintf("start %s", __FUNCTION__));

        $follAcc = $this->follAccRepo->findOrFail($this->getAccountNumber());
        $client = $this->clientGateway->fetchClientByClientId($follAcc->ownerId(), $this->getBroker());

        if (
            $this->isFirstTry() && (
                !$follAcc->isActivated() ||
                $follAcc->isCopying() ||
                $follAcc->isCopyingLocked() ||
                $client->isLockedSourceWealth()  ||
                !$this->hasEnoughEquityToResume($follAcc)
            )
        ) {
            $this->log(sprintf(
                "end %s, workflow rejected (activated: %s, copying: %s, lock_copying: %s, has_required_equity: %s)",
                __FUNCTION__,
                var_export(boolval($follAcc->isActivated()), true),
                var_export(boolval($follAcc->isCopying()), true),
                var_export(boolval($follAcc->isCopyingLocked()), true),
                var_export(boolval($follAcc->hasRequiredEquity()), true)
            ));

            return WorkflowState::REJECTED;
        }
        $result = parent::doProceed();

        $this->log(sprintf("end %s, workflow %s", __FUNCTION__, strtolower(WorkflowState::toString($result))));

        return $result;
    }

    protected function tellPluginToResumeCopying(Activity $activity): void
    {
        $this->log(sprintf("start %s:%d", __FUNCTION__, $activity->id()));

        $account = $this->follAccRepo->getLightAccountOrFail($this->getAccountNumber());
        $pluginGateway = $this->pluginManager->getForAccount($account);

        $actCtx = $activity->getContext();
        if (!$actCtx->has("msgId")) {
            $msgId = $pluginGateway->sendMessage(
                $this->getAccountNumber(),
                $this->id(),
                PluginGateway::FOLLOWER_COPYING,
                1
            );
            $actCtx->set('msgId', $msgId);
            $actCtx->set('server', $account->server());

            $this->log(sprintf("%s:%d, message %d sent", __FUNCTION__, $activity->id(), $actCtx->get("msgId")));
        }

        try {

            if ($pluginGateway->isMessageAcknowledged($actCtx->get("msgId"))) {
                $this->log(sprintf("end %s:%d, succeeded", __FUNCTION__, $activity->id()));
                $activity->succeed();
                return;
            }

            $this->log(sprintf("end %s:%d, trying", __FUNCTION__, $activity->id()));

            $activity->keepTrying();
            return;
        }
        catch (PluginException $e) {
            $msgId = $actCtx->get('msgId');
            $message = $pluginGateway->getMessageById($msgId);

            $this->log(sprintf("%s:%d, result %d", __FUNCTION__, $activity->id(), $message['result']));

            $wfCtx = $this->getContext();

            if ($message['result'] == 14) { // Plugin receives reject from dealer
                $attemptsCount = $wfCtx->has('attemptsCount') ? $wfCtx->get('attemptsCount') + 1 : 1;
                $this->log(sprintf("%s:%d, attempt %d rejected by dealing", __FUNCTION__, $activity->id(), $attemptsCount));
                if ($attemptsCount < self::ALLOWED_ATTEMPTS_COUNT) {
                    $actCtx->remove('msgId');
                    $actCtx->set("attempt{$attemptsCount}_MsgId", $msgId);
                    $actCtx->set('attemptsCount', $attemptsCount);
                    $delay = intval($this->settingsRegistry->get('follower.plugin_copying_attempt.delay', 20));
                    $this->scheduleAt(DateTime::of("+{$delay} minutes"));
                    $pluginGateway->messageCanceled($msgId);
                    $activity->keepTrying();
                    return;
                }
            }

            if ($message['result'] == 50) {
                $this->getContext()->set('reason', $message['comment']);
                $this->log(sprintf("end %s:%d, cancel activity, but continue workflow", __FUNCTION__, $activity->id()));

                $pluginGateway->messageCanceled($msgId);

                $activity->cancel();
                return;
            }

            $follAcc = $this->follAccRepo->findOrFail($this->getAccountNumber());
            $leadAcc = $this->leadAccRepo->getLightAccountOrFail($follAcc->leaderAccountNumber());
            $this->notifGateway->notifyClient(
                $follAcc->ownerId(),
                $follAcc->broker(),
                ($message['result'] == 53) ? NotificationGateway::FOLLOWER_CANNOT_RESUME_INSUFFICIENT_FUNDS : NotificationGateway::FOLLOWER_CANNOT_RESUME,
                [
                    "accNo"       => $follAcc->number()->value(),
                    "leadAccName" => $leadAcc->name(),
                    "accCurr"     => $follAcc->currency()->code(),
                    "reqAmount"   => ($message['result'] == 53)
                        ? $follAcc->requiredEquity()->subtract($follAcc->equity())->amount()
                        : 0,
                ]
            );

            if ($message['result'] == 53 || $message['result'] == 51) {
                $this->getContext()->set('reason', $message['comment']);
                $this->log(sprintf("end %s:%d, workflow rejected, activity cancelled", __FUNCTION__, $activity->id()));

                $pluginGateway->messageCanceled($msgId);

                $this->reject();
                $activity->cancel();
                return;
            }

            $this->log(sprintf("end %s:%d, everything failed", __FUNCTION__, $activity->id()));

            throw $e;
        }
    }

    protected function updateDatabase(Activity $activity): void
    {
        if ($this->isRejected()) {
            $activity->skip();
            return;
        }

        $this->log(sprintf("start %s:%d", __FUNCTION__, $activity->id()));

        $acc = $this->follAccRepo->getLightAccountOrFail($this->getAccountNumber());
        $acc->resumeCopying($this);
        try {
            $this->log(sprintf("Stored account:%s, %s:%d", $this->getContext()->get("accNo"),__FUNCTION__, $activity->id()));
            $this->follAccRepo->store($acc);
        } catch (\Exception $e) {
            $this->logException($e);
            $activity->fail();
            return;
        }

        $acc = $this->follAccRepo->getLightAccountOrFail($this->getAccountNumber());
        $this->log(sprintf("end %s:%d, succeeded (copying: %s)", __FUNCTION__, $activity->id(), var_export(boolval($acc->isCopying()), true)));

        $activity->succeed();
    }

    private function hasEnoughEquityToResume(FollowerAccount $account): bool
    {
        $justActivated = $this->getContext()->getIfHas('justActivated') == 1;
        if($justActivated) {
            if(!$account->hasRequiredEquity()) {
                $this->log(sprintf("%s: Not enough equity by first rule %s", __FUNCTION__, $account));
                $this->notifGateway->notifyClient(
                    $account->ownerId(),
                    $account->broker(),
                    NotificationGateway::FOLLOWER_CANNOT_RESUME_INSUFFICIENT_FUNDS,
                    [
                        'accNo' => $account->number()->value(),
                        'accCurr' => $account->currency()->code(),
                        'leadAccName' => $this->leadAccRepo->getLightAccountOrFail($account->leaderAccountNumber())->name(),
                        'reqAmount' => sprintf("%01.2f", $account->requiredEquity()->subtract($account->equity())->amount()),
                    ]
                );
                return false;
            }
            return true;
        }

        $followerEquity = $account->equity()->amount();
        if(intval(floor($followerEquity * 100.0)) == 0) {
            $this->log(sprintf("%s: Equity is zero %s", __FUNCTION__, $account));
            return false;
        }
        $leaderAccount = $this->leadAccRepo->find($account->leaderAccountNumber());
        $leaderEquity  = $leaderAccount->equity()->amount();
        $minEquityRatio = $account->isInSafeMode() ? 50.0 : 100.0;
        if(($leaderEquity / $followerEquity) > $minEquityRatio) {
            $minCurrentlyRequired = $leaderEquity / $minEquityRatio;
            $this->log(sprintf("%s: Not enough equity by second rule %s", __FUNCTION__, $account));
            $this->notifGateway->notifyClient(
                $account->ownerId(),
                $account->broker(),
                NotificationGateway::FOLLOWER_CANNOT_RESUME_INSUFFICIENT_FUNDS,
                [
                    'accNo' => $account->number()->value(),
                    'accCurr' => $account->currency()->code(),
                    'leadAccName' => $leaderAccount->name(),
                    'reqAmount' => sprintf("%01.2f", $minCurrentlyRequired - $followerEquity),
                ]
            );
            return false;
        }
        return true;
    }

    /**
     * @return AccountNumber
     */
    private function getAccountNumber(): AccountNumber
    {
        return new AccountNumber($this->getContext()->get("accNo"));
    }

    /**
     * @return FollowerAccountRepository
     */
    public function getAccountRepository(): FollowerAccountRepository
    {
        return $this->follAccRepo;
    }

    private function log($message, array $context = []): void
    {
        $this->getLogger()->debug(sprintf("%s:%d, %s", self::TYPE, $this->id(), $message), $context);
    }

    private function getBroker(): string
    {
        return $this->getContext()->get('broker');
    }

}
