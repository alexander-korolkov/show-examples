<?php

namespace Fxtm\CopyTrading\Application\Follower;

use Fxtm\CopyTrading\Application\Common\BaseWorkflow;
use Fxtm\CopyTrading\Application\Common\Workflow\Activity;
use Fxtm\CopyTrading\Application\Common\Workflow\WorkflowState;
use Fxtm\CopyTrading\Application\PluginGateway;
use Fxtm\CopyTrading\Application\PluginGatewayManager;
use Fxtm\CopyTrading\Application\SettingsRegistry;
use Fxtm\CopyTrading\Domain\Common\DateTime;
use Fxtm\CopyTrading\Domain\Model\Follower\FollowerAccountRepository;
use Fxtm\CopyTrading\Domain\Model\Shared\AccountNumber;
use Fxtm\CopyTrading\Interfaces\Gateway\Plugin\PluginException;

class PauseCopyingWorkflow extends BaseWorkflow
{
    const TYPE = "follower.pause_copying";
    const ALLOWED_ATTEMPTS_COUNT = 3;

    const REASON_CLIENT_FROZEN   = "FROZEN";
    const REASON_CLIENT_APPRTEST = "APPRTEST";
    const REASON_LEADER_LEVERAGE = "LEVERAGE";

    private $pluginManager = null;

    /**
     * @var FollowerAccountRepository
     */
    private $follAccRepo;

    /**
     * @var SettingsRegistry
     */
    private $settingsRegistry;

    /**
     * PauseCopyingWorkflow constructor.
     * @param PluginGatewayManager $pluginManager
     * @param FollowerAccountRepository $follAccRepo
     * @param SettingsRegistry $settingsRegistry
     */
    public function __construct(
        PluginGatewayManager $pluginManager,
        FollowerAccountRepository $follAccRepo,
        SettingsRegistry $settingsRegistry
    ) {
        $this->pluginManager = $pluginManager;
        $this->follAccRepo = $follAccRepo;
        $this->settingsRegistry = $settingsRegistry;

        parent::__construct(
            $this->activities([
                "tellPluginToPauseCopying",
                "updateDatabase",
            ])
        );
    }

    protected function doProceed()
    {
        $this->log(sprintf("start %s", __FUNCTION__));

        $follAcc = $this->follAccRepo->getLightAccountOrFail($this->getAccountNumber());
        if (!$follAcc->isCopying()) {

            $this->log(sprintf("end %s, workflow rejected", __FUNCTION__));

            return WorkflowState::REJECTED;
        }
        $result = parent::doProceed();

        $this->log(sprintf("end %s, workflow %s", __FUNCTION__, strtolower(WorkflowState::toString($result))));

        return $result;
    }

    protected function tellPluginToPauseCopying(Activity $activity): void
    {
        if ($this->getContext()->getIfHas("alreadyPaused")) {
            $activity->skip();
            return;
        }

        $this->log(sprintf("start %s:%d", __FUNCTION__, $activity->id()));

        $account = $this->follAccRepo->getLightAccountOrFail($this->getAccountNumber());
        $pluginGateway = $this->pluginManager->getForAccount($account);

        $actCtx = $activity->getContext();
        if (!$actCtx->has("msgId")) {
            $msgId = $pluginGateway->sendMessage(
                $this->getAccountNumber(),
                $this->id(),
                PluginGateway::FOLLOWER_COPYING,
                0
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
        } catch (PluginException $e) {
            $msgId = $actCtx->get('msgId');
            $result = $pluginGateway->getMessageResult($msgId);

            if ($result == 14) { // Plugin receives reject from dealer
                $attemptsCount = $actCtx->has('attemptsCount') ? $actCtx->get('attemptsCount') + 1 : 1;
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

            if ($result == 60) { // Follower already paused
                $this->log(sprintf(
                    "Follower %s already paused. Activity %d rejected, message %d canceled",
                    $this->getAccountNumber(),
                    $activity->id(),
                    $msgId
                ));
                $pluginGateway->messageCanceled($msgId);
                $activity->cancel();
                return;
            }

            throw $e;
        }
    }

    protected function updateDatabase(Activity $activity): void
    {
        $this->log(sprintf("start %s:%d", __FUNCTION__, $activity->id()));

        $acc = $this->follAccRepo->getLightAccountOrFail($this->getAccountNumber());
        $acc->pauseCopying($this);
        $acc->lockCopying($this->getContext()->getIfHas("reason") == self::REASON_CLIENT_APPRTEST);
        try {
            $this->follAccRepo->store($acc);
        } catch (\Exception $e) {
            $this->logException($e);
            $activity->fail();
            return;
        }

        $this->log(sprintf("end %s:%d, succeeded (copying: %s)", __FUNCTION__, $activity->id(), var_export(boolval($acc->isCopying()), true)));

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

    private function log($message, array $context = [])
    {
        $this->getLogger()->debug(sprintf("%s:%d, %s", self::TYPE, $this->id(), $message), $context);
    }
}
