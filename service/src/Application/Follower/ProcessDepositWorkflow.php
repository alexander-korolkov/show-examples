<?php

namespace Fxtm\CopyTrading\Application\Follower;

use Fxtm\CopyTrading\Application\Common\Environment;
use Fxtm\CopyTrading\Application\Common\Workflow\Activity;
use Fxtm\CopyTrading\Application\Common\Workflow\ContextData;
use Fxtm\CopyTrading\Application\Common\Workflow\WorkflowManager;
use Fxtm\CopyTrading\Application\Common\Workflow\WorkflowRepository;
use Fxtm\CopyTrading\Application\Common\Workflow\WorkflowState;
use Fxtm\CopyTrading\Application\EquityService;
use Fxtm\CopyTrading\Application\Leader\EnableCopyingWorkflow as EnableCopyingOnLeaderAccountWorkflow;
use Fxtm\CopyTrading\Application\NotificationGateway;
use Fxtm\CopyTrading\Application\PluginGateway;
use Fxtm\CopyTrading\Application\PluginGatewayManager;
use Fxtm\CopyTrading\Application\SettingsRegistry;
use Fxtm\CopyTrading\Application\TransactionGateway;
use Fxtm\CopyTrading\Application\ClientGateway;
use Fxtm\CopyTrading\Application\TransactionGatewayExecuteResult;
use Fxtm\CopyTrading\Domain\Common\DateTime;
use Fxtm\CopyTrading\Domain\Model\Client\ClientId;
use Fxtm\CopyTrading\Domain\Model\Follower\FollowerAccountRepository;
use Fxtm\CopyTrading\Domain\Model\Leader\LeaderAccountRepository;
use Fxtm\CopyTrading\Domain\Model\Shared\AccountNumber;
use Fxtm\CopyTrading\Domain\Model\Shared\Currency;
use Fxtm\CopyTrading\Domain\Model\Shared\Money;

class ProcessDepositWorkflow extends BasePauseResumeWorkflow
{
    const TYPE = "follower.process_deposit";

    private $transGateway    = null;
    private $pluginManager   = null;

    /**
     * @var LeaderAccountRepository
     */
    private $leadAccRepo;

    /**
     * @var SettingsRegistry
     */
    private $settingsRegistry;

    /**
     * @var EquityService
     */
    private $equityService;

    private $notifGateway    = null;
    private $clientGateway   = null;

    /**
     * ProcessDepositWorkflow constructor.
     * @param TransactionGateway $transGateway
     * @param PluginGatewayManager $pluginManager
     * @param FollowerAccountRepository $follAccRepo
     * @param LeaderAccountRepository $leadAccRepo
     * @param WorkflowManager $workflowManager
     * @param WorkflowRepository $workflowRepo
     * @param NotificationGateway $notifGateway
     * @param ClientGateway $clientGateway
     * @param SettingsRegistry $settingsRegistry
     * @param EquityService $equityService
     */
    public function __construct(
        TransactionGateway $transGateway,
        PluginGatewayManager $pluginManager,
        FollowerAccountRepository $follAccRepo,
        LeaderAccountRepository $leadAccRepo,
        WorkflowManager $workflowManager,
        WorkflowRepository $workflowRepo,
        NotificationGateway $notifGateway,
        ClientGateway $clientGateway,
        SettingsRegistry $settingsRegistry,
        EquityService $equityService
    ) {
        $this->transGateway    = $transGateway;
        $this->pluginManager   = $pluginManager;
        $this->follAccRepo     = $follAccRepo;
        $this->leadAccRepo     = $leadAccRepo;
        $this->workflowManager      = $workflowManager;
        $this->workflowRepository   = $workflowRepo;
        $this->notifGateway    = $notifGateway;
        $this->clientGateway   = $clientGateway;
        $this->settingsRegistry = $settingsRegistry;
        $this->equityService   = $equityService;

        parent::__construct(
            $this->activities([
                "enableCopyingOnLeaderAccount",
                "executeTransaction",
                "notifyPluginOfDeposit",
                "updateBalance",
                "tellPluginToUpdateStoploss",
                "startCopyingDetached",
                "notifyOnDeposit",
                "notifyOnInsufficientDeposit",
            ])
        );
    }

    protected function doProceed()
    {
        $followerAccount = $this->follAccRepo->findOrFail($this->getAccountNumber());
        $leaderAccount = $this->leadAccRepo->getLightAccountOrFail($followerAccount->leaderAccountNumber());
        $selfFollowingLimit = $this->settingsRegistry->get('leader.self_following.deposit_limit', 0);

        if (
            $this->isFirstTry() &&
            $followerAccount->ownerId()->value() == $leaderAccount->ownerId()->value() &&
            $selfFollowingLimit > 0 &&
            ($followerAccount->equity()->amount() + $this->getFunds()->amount()) >$selfFollowingLimit &&
            Environment::isProd()
        ) {
            $this->transGateway->changeExecutorToBackOffice(
                $this->getTransactionId(),
                $this->getBroker(),
                sprintf(
                    'The client is only allowed one personal investment account up to %d that follows his own strategy manager account.',
                    $selfFollowingLimit
                )
            );
            return WorkflowState::REJECTED;
        }

        if (!$this->isInternal()) {
            $this->resumeCopyingSchedule = DateTime::of("+3 minutes");
        }

        $status = parent::doProceed();
        if($status == WorkflowState::COMPLETED && $this->getContext()->getIfHas('canceled')) {
            return WorkflowState::CANCELLED;
        }
        return $status;
    }

    protected function enableCopyingOnLeaderAccount(Activity $activity): void
    {
        $this->logDebug($activity, __FUNCTION__, 'get follower account');
        $follAcc = $this->follAccRepo->getLightAccountOrFail($this->getAccountNumber());
        $leadAcc = $this->leadAccRepo->getLightAccountOrFail($follAcc->leaderAccountNumber());
        if (!empty($this->follAccRepo->getCountOfActivatedFollowerAccounts($leadAcc->number()))) {
            $this->logDebug($activity, __FUNCTION__, 'skip due to no activated follower accounts');
            $activity->skip();
            return;
        }

        $broker = $this->leadAccRepo->getLightAccountOrFail($follAcc->leaderAccountNumber())->broker();
        $leaderAccountNumber = $follAcc->leaderAccountNumber()->value();

        $status = $this->findCreateExecute(
            $leaderAccountNumber,
            EnableCopyingOnLeaderAccountWorkflow::TYPE,
            function () use ($leaderAccountNumber, $broker) {
                return $this->createChild(
                    EnableCopyingOnLeaderAccountWorkflow::TYPE,
                    new ContextData(["accNo" => $leaderAccountNumber, "broker" => $broker])
                );
            }
        );

        $status->updateActivity($activity);
    }

    protected function executeTransaction(Activity $activity): void
    {
        $this->logDebug($activity, __FUNCTION__, 'get follower account');
        $follAcc = $this->follAccRepo->findOrFail($this->getAccountNumber());
        $this->getContext()->set("prevEquity", $follAcc->equity()->amount());

        try {
            $this->logDebug($activity, __FUNCTION__, sprintf('execute transaction %d', $this->getTransactionId()));

            //Save time when we start executeTransaction
            $this->getContext()->set("orderTime", DateTime::NOW()->format('Y-m-d H:i:s'));

            $result = $this->transGateway->executeTransaction(
                $this->getTransactionId(),
                $this->getBroker(),
                TransactionGateway::TK_DEPOSIT,
                $this->getAccountNumber()
            );

            switch ($result->getStatus()) {
                case TransactionGatewayExecuteResult::STATUS_DECLINED_BY_USER:
                    $this->getContext()->set("canceled", true);
                    $activity->cancel();
                    return;

                case TransactionGatewayExecuteResult::STATUS_NOT_ENOUGH_BALANCE:
                    throw new \Exception(
                        sprintf(
                            "TransferGateway::executeTransaction() have returned invalid status (expected %d or %d but got %d)",
                            TransactionGatewayExecuteResult::STATUS_OK,
                            TransactionGatewayExecuteResult::STATUS_DECLINED_BY_USER,
                            $result->getStatus()
                        )
                    );

                case TransactionGatewayExecuteResult::STATUS_OK:
                    break;
            }

            $order = $result->getOrder();
            if($order < 0) {
                throw new \Exception(
                    sprintf(
                        "TransferGateway::executeTransaction() have returned invalid order number %d",
                        $order
                    )
                );
            }

            $this->logDebug($activity, __FUNCTION__, sprintf('transaction %d executed, order is %d', $this->getTransactionId(), $order));
            $this->setDepositOrder($order);

            $this->logDebug($activity, __FUNCTION__, 'succeed');
            $activity->succeed();
        } catch (\Exception $e) {
            $this->getContext()->set("errorMessage", $e->getMessage());
            $this->getContext()->set("transFailed", true);
            $activity->fail();
        }
    }

    protected function notifyPluginOfDeposit(Activity $activity): void
    {
        if ($this->getContext()->getIfHas("transFailed")) {
            $this->logDebug($activity, __FUNCTION__, 'skip due to transFailed flag');
            $activity->skip();
            return;
        }
        if ($this->getContext()->getIfHas("canceled")) {
            $this->logDebug($activity, __FUNCTION__, 'skip due to canceled flag');
            $activity->skip();
            return;
        }

        $account = $this->follAccRepo->getLightAccountOrFail($this->getAccountNumber());
        $pluginGateway = $this->pluginManager->getForAccount($account);

        $actCtx = $activity->getContext();
        if (!$actCtx->has("msgId")) {
            $this->logDebug($activity, __FUNCTION__, 'sendMessage to plugin');
            $msgId = $pluginGateway->sendMessage(
                $this->getAccountNumber(),
                $this->id(),
                PluginGateway::FOLLOWER_DEPOSIT,
                $this->getFunds()->amount()
            );
            $actCtx->set('msgId', $msgId);
            $actCtx->set('server', $account->server());
        }
        if ($pluginGateway->isMessageAcknowledged($actCtx->get("msgId"))) {
            $this->logDebug($activity, __FUNCTION__, 'succeed');
            $activity->succeed();
            return;
        }
        $this->logDebug($activity, __FUNCTION__, 'keepTrying');
        $activity->keepTrying();
    }

    protected function updateBalance(Activity $activity): void
    {
        if ($this->getContext()->getIfHas("transFailed")) {
            $this->logDebug($activity, __FUNCTION__, 'skip due to transFailed flag');
            $activity->skip();
            return;
        }
        if ($this->getContext()->getIfHas("canceled")) {
            $this->logDebug($activity, __FUNCTION__, 'skip due to canceled flag');
            $activity->skip();
            return;
        }

        $this->logDebug($activity, __FUNCTION__, 'get follower account');
        $follAcc = $this->follAccRepo->findOrFail($this->getAccountNumber());

        if (!$this->getContext()->has('prevFeeLvl'))
        {
            $this->getContext()->set('prevFeeLvl', $follAcc->settlingEquity()->amount());
        }

        $wasActivated = $follAcc->isActivated();

        $this->logDebug($activity, __FUNCTION__, 'depositFunds');
        $follAcc->depositFunds($this->getFunds(), $this);

        $minEquitySetting = $this->settingsRegistry->get('follower_acc.min_equity', 100);
        $minEquity = new Money($minEquitySetting, $follAcc->currency());

        if ($follAcc->equity()->isGreaterThanOrEqualTo($minEquity) && $follAcc->isPassive()) {
            $follAcc->activate();
            //If account just been activated, store current equity as initial deposit
            $this->equityService->saveTransactionEquityChange(
                $this->getAccountNumber(),
                $follAcc->equity(),
                $follAcc->equity(),
                null,
                DateTime::NOW()
            );
        }

        if (!$this->getContext()->has('feeLvl'))
        {
            $this->getContext()->set('feeLvl', $follAcc->settlingEquity()->amount());
        }

        try {
            $this->logDebug($activity, __FUNCTION__, 'store');
            $this->follAccRepo->store($follAcc);
        } catch (\Exception $e) {
            $this->logException($e);
            $activity->fail();
            return;
        }

        $this->logDebug($activity, __FUNCTION__, 'get leader account');
        $leadAcc = $this->leadAccRepo->getLightAccountOrFail($follAcc->leaderAccountNumber());

        if (!$wasActivated && $follAcc->isActivated()) {

            if ($this->clientGateway->clientInInactiveStatus($this->getClientId(), $this->getBroker())) {
                $this->getContext()->set(self::CTX_FIELD_JUST_ACTIVATED, 1);

                if (1 == $this->follAccRepo->getCountOfActivatedFollowerAccounts($follAcc->leaderAccountNumber())) {
                    $this->logDebug($activity, __FUNCTION__, 'notify client');
                    $this->notifGateway->notifyClient(
                        $leadAcc->ownerId(),
                        $leadAcc->broker(),
                        NotificationGateway::LEADER_ACC_FIRST_FOLLOWER,
                        [
                            "accNo"   => $leadAcc->number()->value(),
                            "accName" => $leadAcc->name(),
                        ]
                    );
                }
            } else {
                $this->getContext()->set("inactiveClient", 1);
            }
        }

        $this->getContext()->set("stopLossEquityNew", $follAcc->stopLossEquity()->truncate()->amount());

        $this->setLeaderAccountName($leadAcc->name()); // for "notifyClient"
        $this->setLeaderIsPublic($leadAcc->isPublic()); // for "notifyClient"

        $this->logDebug($activity, __FUNCTION__, 'succeed');
        $activity->succeed();
    }

    protected function tellPluginToUpdateStoploss(Activity $activity): void
    {
        if ($this->getContext()->getIfHas("transFailed")) {
            $this->logDebug($activity, __FUNCTION__, 'skip due to transFailed flag');
            $activity->skip();
            return;
        }
        if ($this->getContext()->getIfHas("canceled")) {
            $this->logDebug($activity, __FUNCTION__, 'skip due to canceled flag');
            $activity->skip();
            return;
        }

        $this->logDebug($activity, __FUNCTION__, 'get follower account');
        $follAcc = $this->follAccRepo->getLightAccountOrFail($this->getAccountNumber());
        $pluginGateway = $this->pluginManager->getForAccount($follAcc);

        $actCtx = $activity->getContext();
        if (!$actCtx->has("msgId")) {
            $this->logDebug($activity, __FUNCTION__, 'sendMessage to plugin');
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
            $this->logDebug($activity, __FUNCTION__, 'succeed');
            $activity->succeed();
            return;
        }
        $this->logDebug($activity, __FUNCTION__, 'keepTrying');
        $activity->keepTrying();
    }


    protected function startCopyingDetached(Activity $activity): void {
        if ($this->needSkipResume()) {
            $this->logDebug($activity, __FUNCTION__, 'skipped');
            $activity->skip();
            return;
        }
        $activityContext = $activity->getContext();
        // this is for case if resuming workflow already exists
        if($activityContext->has("resumeWorkflowId")) {
            $resumeCopying = $this
                ->workflowRepository
                ->findById($activityContext->get("resumeWorkflowId"));
            if($resumeCopying->isNew()) {  // reschedule if untried or skip this otherwise
                $resumeCopying->scheduleAt($this->resumeCopyingSchedule);
                $this->workflowRepository->store($resumeCopying);
                $activity->succeed();
                $this->logDebug($activity, __FUNCTION__, 'Reschedule existing resuming workflow');
            }
            $this->logDebug($activity, __FUNCTION__, 'Skipping activity because no action defined for such cases');
            $activity->skip();
            return;
        }
        $contextData = $this->getContext()->toArray();
        $contextData['depositWorkflowId'] = $this->id();
        $resumeCopying = $this->createDetached(
            ResumeCopyingWorkflow::TYPE,
            new ContextData($contextData),
            $this->resumeCopyingSchedule
        );
        if(!$this->workflowManager->enqueueWorkflow($resumeCopying)) {
            $this->logDebug($activity, __FUNCTION__, "Workflow manager unable to create child workflow; see previous errors");
            $activity->fail();
            return;
        }
        $activityContext->set("resumeWorkflowId", $resumeCopying->id());
        $activity->succeed();
    }

    protected function notifyOnDeposit(Activity $activity): void
    {
        if ($this->getContext()->getIfHas("transFailed")) {
            $this->logDebug($activity, __FUNCTION__, 'skip due to transFailed flag');
            $activity->skip();
            return;
        }
        if ($this->getContext()->getIfHas("canceled")) {
            $this->logDebug($activity, __FUNCTION__, 'skip due to canceled flag');
            $activity->skip();
            return;
        }

        $this->logDebug($activity, __FUNCTION__,'notifyClient');
        $this->notifGateway->notifyClient(
            $this->getClientId(),
            $this->getBroker(),
            NotificationGateway::FOLLOWER_FUNDS_DEPOSITED,
            $this->getContext()->toArray()
        );

        $this->logDebug($activity, __FUNCTION__,'succeeded');
        $activity->succeed();
    }

    protected function notifyOnInsufficientDeposit(Activity $activity): void
    {
        if ($this->getContext()->getIfHas("transFailed")) {
            $this->logDebug($activity, __FUNCTION__, 'skip due to transFailed flag');
            $activity->skip();
            return;
        }
        if ($this->getContext()->getIfHas("canceled")) {
            $this->logDebug($activity, __FUNCTION__, 'skip due to canceled flag');
            $activity->skip();
            return;
        }

        $follAcc = $this->follAccRepo->findOrFail($this->getAccountNumber());
        if ($follAcc->isActivated()) {
            $this->logDebug($activity, __FUNCTION__,'skipped');
            $activity->skip();
            return;
        }

        $this->getContext()->set("accEquity", $follAcc->equity()->amount());
        $this->getContext()->set("reqEquity", $follAcc->requiredEquity()->amount());
        $this->getContext()->set("missingAmount", $follAcc->requiredEquity()->subtract($follAcc->equity())->amount());


        $this->logDebug($activity, __FUNCTION__,'notifyClient');
        $this->notifGateway->notifyClient(
            $this->getClientId(),
            $this->getBroker(),
            NotificationGateway::FOLLOWER_FUNDS_DEPOSITED_INSUFFICIENT,
            $this->getContext()->toArray()
        );

        $this->logDebug($activity, __FUNCTION__,'succeeded');
        $activity->succeed();
    }

    private function setLeaderAccountName($leadAccName)
    {
        $this->getContext()->set("leadAccName", $leadAccName);
    }

    private function setLeaderIsPublic($isPublic)
    {
        $this->getContext()->set("isPublic", $isPublic);
    }

    private function setDepositOrder($order)
    {
        $this->getContext()->set("depositOrder", $order);
    }

    private function getDepositOrder()
    {
        return $this->getContext()->get("depositOrder");
    }

    private function getTransactionId()
    {
        return $this->getContext()->get("transId");
    }

    private function isInternal()
    {
        return $this->getContext()->get("internal");
    }

    private function getClientId()
    {
        return new ClientId($this->getContext()->get("clientId"));
    }

    private function getAccountNumber()
    {
        return new AccountNumber($this->getContext()->get("accNo"));
    }

    private function getFunds()
    {
        return new Money(
            $this->getContext()->get("amount"),
            Currency::forCode($this->getContext()->get("accCurr"))
        );
    }

    public function getAccountRepository()
    {
        return $this->follAccRepo;
    }

    public function getBroker()
    {
        return $this->getContext()->get('broker');
    }
}
