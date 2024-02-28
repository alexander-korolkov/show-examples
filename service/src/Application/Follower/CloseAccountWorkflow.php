<?php

namespace Fxtm\CopyTrading\Application\Follower;

use Fxtm\CopyTrading\Application\Common\Workflow\Activity;
use Fxtm\CopyTrading\Application\Common\Workflow\ContextData;
use Fxtm\CopyTrading\Application\Common\Workflow\WorkflowManager;
use Fxtm\CopyTrading\Application\Common\Workflow\WorkflowRepository;
use Fxtm\CopyTrading\Application\Leader\DisableCopyingWorkflow as DisableCopyingOnLeaderAccountWorkflow;
use Fxtm\CopyTrading\Application\LeverageService;
use Fxtm\CopyTrading\Application\NotificationGateway;
use Fxtm\CopyTrading\Application\PluginGateway;
use Fxtm\CopyTrading\Application\PluginGatewayManager;
use Fxtm\CopyTrading\Application\TradeAccountGateway;
use Fxtm\CopyTrading\Application\TransactionGateway;
use Fxtm\CopyTrading\Application\TransactionGatewayExecuteResult;
use Fxtm\CopyTrading\Domain\Common\DateTime;
use Fxtm\CopyTrading\Domain\Model\Follower\FollowerAccountRepository;
use Fxtm\CopyTrading\Domain\Model\Follower\Commission;
use Fxtm\CopyTrading\Domain\Model\Leader\LeaderAccountRepository;
use Fxtm\CopyTrading\Domain\Model\Shared\AccountNumber;
use Fxtm\CopyTrading\Domain\Model\Shared\Money;

class CloseAccountWorkflow extends BasePauseResumeWorkflow
{
    public const TYPE = "follower.close_account";

    public const REASON_CLOSED_BY_LEADER      = "CLOSED_BY_LEADER";
    public const REASON_CLOSED_BY_FOLLOWER    = "CLOSED_BY_FOLLOWER";
    public const REASON_INCOMPATIBLE_LEVERAGE = "INCOMPATIBLE_LEVERAGE";
    public const REASON_LONG_INACTIVITY = "INACTIVITY_LONG";
    public const REASON_DISCONNECTED_FROM_INACTIVE_LEADER = "REASON_DISCONNECTED_FROM_INACTIVE_LEADER";
    public const REASON_LEADER_LOST_ALL_MONEY = 'REASON_LEADER_LOST_ALL_MONEY';

    private $transGateway    = null;
    private $pluginManager   = null;

    /**
     * @var LeaderAccountRepository
     */
    private $leadAccRepo;

    private $tradeAccGateway = null;
    private $notifGateway    = null;
    private $statementSvc    = null;
    private $leverageSvc     = null;

    /**
     * CloseAccountWorkflow constructor.
     * @param TransactionGateway $transGateway
     * @param PluginGatewayManager $pluginManager
     * @param FollowerAccountRepository $follAccRepo
     * @param LeaderAccountRepository $leadAccRepo
     * @param WorkflowManager $workflowManager
     * @param WorkflowRepository $workflowRepo
     * @param TradeAccountGateway $tradeAccGateway
     * @param NotificationGateway $notifGateway
     * @param StatementService $statementSvc
     * @param LeverageService $leverageSvc
     */
    public function __construct(
        TransactionGateway $transGateway,
        PluginGatewayManager $pluginManager,
        FollowerAccountRepository $follAccRepo,
        LeaderAccountRepository $leadAccRepo,
        WorkflowManager $workflowManager,
        WorkflowRepository $workflowRepo,
        TradeAccountGateway $tradeAccGateway,
        NotificationGateway $notifGateway,
        StatementService $statementSvc,
        LeverageService $leverageSvc
    ) {
        $this->transGateway    = $transGateway;
        $this->pluginManager   = $pluginManager;
        $this->follAccRepo     = $follAccRepo;
        $this->leadAccRepo     = $leadAccRepo;
        $this->workflowManager      = $workflowManager;
        $this->workflowRepository   = $workflowRepo;
        $this->tradeAccGateway = $tradeAccGateway;
        $this->notifGateway    = $notifGateway;
        $this->statementSvc    = $statementSvc;
        $this->leverageSvc     = $leverageSvc;

        parent::__construct(
            $this->activities([
                "stopCopying",
                "withdrawAllFunds",
                "settleCommission",
                "notifyPluginOfWithdrawal",
                "closeAccount",
                "notifyFollower",
                "sendStatement",
                "disableCopyingOnLeaderAccount",
            ])
        );
    }

    protected function withdrawAllFunds(Activity $activity): void
    {
        $follAcc = $this->follAccRepo->findOrFail($this->getAccountNumber());

        if (!$this->getContext()->has('prevEquity')) {
            $this->getContext()->set('prevEquity', $follAcc->equity()->amount());
        }

        try {
            if ($follAcc->equity()->amount() > 0) {
                // Update balance by the amount of the equity
                if (!$this->getContext()->has('balanceUpdated')) {
                    $follAcc->withdrawFunds($follAcc->equity(), $this);
                    $this->follAccRepo->store($follAcc);
                    $this->getContext()->set('balanceUpdated', true);
                }

                $this->getContext()->set("in_out", $follAcc->equity()->amount());
                // Create transaction
                if (!$this->getContext()->has('transId')) {
                    $transId = $this->transGateway->createSynchronousTransaction($this->getAccountNumber(), $this->getBroker(), $follAcc->equity()->amount());
                    $this->getContext()->set('transId', $transId);
                } else {
                    $transId = $this->getContext()->get('transId');
                }

                // Execute transaction
                if (!$this->transGateway->transferWasExecuted($transId, $this->getBroker())) {

                    //Save time when we start executeTransaction
                    $this->getContext()->set("orderTime", DateTime::NOW()->format('Y-m-d H:i:s'));

                    $result = $this->transGateway->executeTransaction(
                        $transId,
                        $this->getBroker(),
                        TransactionGateway::TK_WITHDRAWAL,
                        $this->getAccountNumber()
                    );

                    if($result->getStatus() != TransactionGatewayExecuteResult::STATUS_OK) {
                        throw new \Exception(
                            sprintf(
                                "TransferGateway::executeTransaction() have returned invalid status (expected %d but got %d)",
                                TransactionGatewayExecuteResult::STATUS_OK,
                                $result->getStatus()
                            )
                        );
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

                    $this->setWithdrawalOrder($order);
                    $this->getContext()->set('order', $order);
                }
            }

            $activity->succeed();
        } catch (\Exception $e) {
            $this->getContext()->set("errorMessage", $e->getMessage());
            $activity->fail();
        }
    }

    protected function settleCommission(Activity $activity): void
    {
        if ($this->getContext()->getIfHas("transFailed")) {
            $this->logDebug($activity, __FUNCTION__, 'skip due to transFailed flag');
            $activity->skip();
            return;
        }

        $accountNumber = $this->getAccountNumber()->value();
        $follAcc = $this->follAccRepo->getLightAccountOrFail($this->getAccountNumber());
        $status = $this->findCreateExecute(
            $accountNumber,
            PayCommissionWorkflow::TYPE,
            function () use ($accountNumber, $follAcc) {
                return $this->createChild(
                    PayCommissionWorkflow::TYPE,
                    new ContextData([
                        'accNo' => $accountNumber,
                        'prevEquity' => $this->getContext()->get('prevEquity'),
                        'currency' => $follAcc->currency()->code(),
                        'settlingEquity' => $this->getContext()->get('prevEquity'),
                        'commissionType' => Commission::TYPE_CLOSE_ACCOUNT,
                        'accountToWalletTransactionId' => $this->getContext()->getIfHas('transId'),
                        'broker' => $this->getBroker(),
                    ])
                );
            }
        );

        $innerWorkflow = $status->getChild();

        switch (true) {
            case $status->isInterrupted():
                $activity->fail();
                break;

            case !$innerWorkflow->isDone():
                $activity->keepTrying();
                break;
            case $innerWorkflow->isRejected():
                $activity->skip();
                break;

            case $innerWorkflow->isCompleted():
                $this->getContext()->set('fee', $innerWorkflow->getContext()->get('fee'));
                $activity->succeed();
                break;
        }
    }

    protected function notifyPluginOfWithdrawal(Activity $activity): void
    {
        if (!$this->hasWithdrawalOrder()) {
            $activity->skip();
            return;
        }

        $account = $this->follAccRepo->getLightAccountOrFail($this->getAccountNumber());
        $pluginGateway = $this->pluginManager->getForAccount($account);

        $actCtx = $activity->getContext();
        if (!$actCtx->has("msgId")) {
            $msgId = $pluginGateway->sendMessage(
                $this->getAccountNumber(),
                $this->id(),
                PluginGateway::FOLLOWER_WITHDRAWAL,
                $this->getContext()->get('prevEquity')
            );
            $actCtx->set('msgId', $msgId);
            $actCtx->set('server', $account->server());
        }
        if ($pluginGateway->isMessageAcknowledged($actCtx->get("msgId"))) {
            $activity->succeed();
            return;
        }
        $activity->keepTrying();
    }

    protected function closeAccount(Activity $activity): void
    {
        $accNo = $this->getAccountNumber();
        $this->tradeAccGateway->destroyAccount($accNo, $this->getBroker());
        $follAcc = $this->follAccRepo->getLightAccountOrFail($accNo);
        $this->getContext()->set("wasActivated", $follAcc->isActivated());
        $follAcc->close($this);

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
        $ctx = $this->getContext();
        $follAcc = $this->follAccRepo->getLightAccountOrFail($this->getAccountNumber());
        $ctx->set("walletId", $follAcc->currency()->code() . $follAcc->ownerId()->value());

        $leadAcc = $this->leadAccRepo->getLightAccountOrFail($follAcc->leaderAccountNumber());
        $ctx->set("leadAccName", $leadAcc->name());
        $ctx->set("isPublic", $leadAcc->isPublic());

        list($notifType, $params) = $this->getNotificationTypeAndParams(
            $this->getContext()->getIfHas(ContextData::REASON),
            $follAcc->isInSafeMode()
        );
        $this->notifGateway->notifyClient($follAcc->ownerId(), $follAcc->broker(), $notifType, $params);
        $activity->succeed();
    }

    protected function sendStatement(Activity $activity): void
    {
        if (!$this->getContext()->get("wasActivated")) {
            $activity->skip();
            return;
        }
        $follAcc = $this->follAccRepo->getLightAccountOrFail($this->getAccountNumber());
        $this->notifGateway->notifyClient(
            $follAcc->ownerId(),
            $follAcc->broker(),
            NotificationGateway::FOLLOWER_STATEMENT,
            $this->statementSvc->prepareStatementData(
                $this->getAccountNumber(),
                $follAcc->settledAt(),
                DateTime::NOW()
            )
        );
        $activity->succeed();
    }

    protected function disableCopyingOnLeaderAccount(Activity $activity): void
    {
        if ($this->getContext()->has('doNotDisableTheLeader')) {
            $activity->skip();
            return;
        }

        $follAcc = $this->follAccRepo->getLightAccountOrFail($this->getAccountNumber());
        $leadAcc = $this->leadAccRepo->getLightAccountOrFail($follAcc->leaderAccountNumber());
        if (!$leadAcc->isCopied()) {
            $activity->skip();
            return;
        }

        if (!empty($this->follAccRepo->getCountOfActivatedFollowerAccounts($leadAcc->number()))) {
            $activity->skip();
            return;
        }

        $status = $this->findCreateExecute(
            $leadAcc->number()->value(),
            DisableCopyingOnLeaderAccountWorkflow::TYPE,
            function () use ($leadAcc) {
                return $this->createChild(
                    DisableCopyingOnLeaderAccountWorkflow::TYPE,
                    new ContextData([
                        "accNo" => $leadAcc->number()->value(),
                        "broker" => $leadAcc->broker()
                    ])
                );
            }
        );

        $status->updateActivity($activity);
    }

    private function getAccountNumber()
    {
        return new AccountNumber($this->getContext()->get("accNo"));
    }

    private function setWithdrawalOrder($order)
    {
        $this->getContext()->set("withdrawalOrder", $order);
    }

    private function hasWithdrawalOrder()
    {
        return $this->getContext()->has("withdrawalOrder");
    }

    private function getWithdrawalOrder()
    {
        return $this->getContext()->get("withdrawalOrder");
    }

    public function getAccountRepository()
    {
        return $this->follAccRepo;
    }

    private function getNotificationTypeAndParams(?string $reason, bool $isInSafeMode)
    {
        $ctx = $this->getContext()->toArray();
        switch ($reason) {
            case self::REASON_LONG_INACTIVITY:
                return [NotificationGateway::FOLLOWER_ACC_INACTIVE_CLOSED, $ctx];
            case self::REASON_DISCONNECTED_FROM_INACTIVE_LEADER:
                return [NotificationGateway::FOLLOWER_ACC_DISCONNECTED_FROM_INACTIVE_LEADER, $ctx];
            case self::REASON_CLOSED_BY_LEADER:
                return [NotificationGateway::FOLLOWER_ACC_CLOSED_BY_LEADER, $ctx];
            case self::REASON_INCOMPATIBLE_LEVERAGE:
                $leverage = $this->leverageSvc->getMaxAllowedLeverageForFollowerAccount(
                    $this->follAccRepo->getLightAccountOrFail($this->getAccountNumber())
                );
                return [
                    NotificationGateway::FOLLOWER_ACC_CLOSED_INCOMPATIBLE_LEVERAGE,
                    $ctx + [
                        "accounts"  => [$this->getContext()->get("accNo")],
                        "leverage"  => $leverage,
                        "leverage2" => $leverage * 2,
                    ]
                ];
            case self::REASON_LEADER_LOST_ALL_MONEY:
                return [
                    $isInSafeMode ?
                        NotificationGateway::COPY_TRADING_INVESTOR_YOUR_LEADER_LOST_ALL_MONEY :
                        NotificationGateway::COPY_TRADING_INVESTOR_LOST_ALL_MONEY,
                    $ctx
                ];
            case self::REASON_CLOSED_BY_FOLLOWER:
            default:
                return [NotificationGateway::FOLLOWER_ACC_CLOSED, $ctx];
        }
    }

    private function getBroker()
    {
        return $this->getContext()->get('broker');
    }
}
