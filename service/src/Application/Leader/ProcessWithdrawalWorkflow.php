<?php

namespace Fxtm\CopyTrading\Application\Leader;

use Exception;
use Fxtm\CopyTrading\Application\ClientGateway;
use Fxtm\CopyTrading\Application\Common\BaseParentalWorkflow;
use Fxtm\CopyTrading\Application\Common\Workflow\Activity;
use Fxtm\CopyTrading\Application\Common\Workflow\ContextData;
use Fxtm\CopyTrading\Application\Common\Workflow\WorkflowManager;
use Fxtm\CopyTrading\Application\Common\Workflow\WorkflowRepository;
use Fxtm\CopyTrading\Application\Common\Workflow\WorkflowState;
use Fxtm\CopyTrading\Application\NotificationGateway;
use Fxtm\CopyTrading\Application\PluginGateway;
use Fxtm\CopyTrading\Application\PluginGatewayManager;
use Fxtm\CopyTrading\Application\TradeAccountGateway;
use Fxtm\CopyTrading\Application\TransactionGateway;
use Fxtm\CopyTrading\Application\TransactionGatewayExecuteResult;
use Fxtm\CopyTrading\Domain\Common\DateTime;
use Fxtm\CopyTrading\Domain\Model\Client\ClientId;
use Fxtm\CopyTrading\Domain\Model\Follower\FollowerAccountRepository;
use Fxtm\CopyTrading\Domain\Model\Leader\LeaderAccountRepository;
use Fxtm\CopyTrading\Domain\Model\Shared\AccountNumber;
use Fxtm\CopyTrading\Domain\Model\Shared\Currency;
use Fxtm\CopyTrading\Domain\Model\Shared\Money;

class ProcessWithdrawalWorkflow extends BaseParentalWorkflow
{
    const TYPE = "leader.process_withdrawal";

    /**
     * @var TradeAccountGateway
     */
    private $tradeAccGateway = null;

    /**
     * @var LeaderAccountRepository
     */
    private $leadAccRepo;

    /**
     * @var FollowerAccountRepository
     */
    private $follAccRepo;

    private $transGateway    = null;
    private $pluginManager   = null;
    private $notifGateway    = null;
    private $clientGateway   = null;

    /**
     * ProcessWithdrawalWorkflow constructor.
     * @param WorkflowManager $workflowManager
     * @param WorkflowRepository $workflowRepository
     * @param TradeAccountGateway $tradeAccGateway
     * @param LeaderAccountRepository $leadAccRepo
     * @param FollowerAccountRepository $follAccRepo
     * @param TransactionGateway $transGateway
     * @param PluginGatewayManager $pluginManager
     * @param NotificationGateway $notifGateway
     * @param ClientGateway $clientGateway
     */
    public function __construct(
        WorkflowManager $workflowManager,
        WorkflowRepository $workflowRepository,
        TradeAccountGateway $tradeAccGateway,
        LeaderAccountRepository $leadAccRepo,
        FollowerAccountRepository $follAccRepo,
        TransactionGateway $transGateway,
        PluginGatewayManager $pluginManager,
        NotificationGateway $notifGateway,
        ClientGateway $clientGateway
    ) {
        $this->workflowManager      = $workflowManager;
        $this->workflowRepository   = $workflowRepository;
        $this->tradeAccGateway      = $tradeAccGateway;
        $this->leadAccRepo          = $leadAccRepo;
        $this->follAccRepo          = $follAccRepo;
        $this->transGateway         = $transGateway;
        $this->pluginManager        = $pluginManager;
        $this->notifGateway         = $notifGateway;
        $this->clientGateway        = $clientGateway;

        parent::__construct(
            $this->activities([
                "ensureNoOpenPositions",
                "disableTrading",
                "executeTransaction",
                "changeEquity",
                "notifyPluginOfWithdrawal",
                "updateBalance",
                "enableTrading",
                "notifyClient",
                "closeFollowerAccounts",
                "deactivateAccount",
            ])
        );
    }

    protected function doProceed()
    {
        if (empty($leadAcc = $this->leadAccRepo->getLightAccount($this->getAccountNumber()))) {
            $tradeAcc = $this->tradeAccGateway->fetchAccountByNumber($this->getAccountNumber(), $this->getBroker());
            $client = $this->clientGateway->fetchClientByClientId($tradeAcc->ownerId(), $this->getBroker());

            switch ($client->getParam("company_id")) {
                case 1 : $company = 'EU'; break;
                case 50 : $company = 'AINT'; break;
                default : $company = 'FTG';
            }

            $convertAcc = $this->workflowManager->newWorkflow(
                ConvertAccountWorkflow::TYPE,
                new ContextData([
                    "accNo"    => $this->getAccountNumber()->value(),
                    "clientId" => $tradeAcc->ownerId()->value(),
                    "email"    => $client->getParam("email"),
                    "company"  => $company,
                    "accCurr"  => $tradeAcc->currency()->code(),
                    ContextData::KEY_BROKER   => $this->getBroker(),
                ])
            );
            $convertAcc->setParent($this);
            $this->workflowManager->processWorkflow($convertAcc);

            if ($convertAcc->isCompleted()) {
                $leadAcc = $convertAcc->getResult();
            } else {
                return WorkflowState::REJECTED;
            }
        }

        $result = parent::doProceed();
        if($result == WorkflowState::COMPLETED && $this->getContext()->getIfHas('canceled')) {
            return WorkflowState::CANCELLED;
        }

        if ($this->getContext()->getIfHas("transFailed")) {
            return WorkflowState::FAILED;
        }

        return $result;
    }

    protected function ensureNoOpenPositions(Activity $activity): void
    {
        $activeFollowers = $this->follAccRepo->getCountOfCopyingFollowerAccounts($this->getAccountNumber());
        if (empty($activeFollowers)) {
            $this->getContext()->set('keepTrading', 1);
            $activity->skip();
            return;
        }

        $leadAcc = $this->leadAccRepo->findOrFail($this->getAccountNumber());
        if (!$leadAcc->hasOpenPositions()) {
            $activity->succeed();
            return;
        }

        if ($activity->getTriesCount() === 1) {
            $this->notifGateway->notifyClient(
                $leadAcc->ownerId(),
                $this->getBroker(),
                NotificationGateway::LEADER_WITHDRAWAL_CLOSE_POSITIONS,
                $this->getContext()->toArray()
            );
        }

        $this->scheduleAt(DateTime::of("+2 minutes"));
        $activity->keepTrying();
    }

    protected function disableTrading(Activity $activity): void
    {
        if ($this->getContext()->getIfHas("keepTrading")) {
            $activity->skip();
            return;
        }

        $accNo = $this->getAccountNumber();
        $tradeAcc = $this->tradeAccGateway->fetchAccountByNumber($accNo, $this->getBroker());
        if ($tradeAcc->isReadOnly()) {
            $activity->skip();
            return;
        }
        $this->tradeAccGateway->changeAccountReadOnly($accNo, $this->getBroker());
        $this->getContext()->set("tradingDisabled", true);
        $activity->succeed();
    }

    protected function executeTransaction(Activity $activity): void
    {
        try {
            $leadAcc = $this->leadAccRepo->findOrFail($this->getAccountNumber());

            //Save time when we start executeTransaction
            $this->getContext()->set("orderTime", DateTime::NOW()->format('Y-m-d H:i:s'));
            $this->getContext()->set("prevEquity", $leadAcc->equity()->amount());

            $result = $this->transGateway->executeTransaction(
                $this->getTransactionId(),
                $this->getBroker(),
                TransactionGateway::TK_WITHDRAWAL,
                $this->getAccountNumber()
            );

            switch ($result->getStatus()) {
                case TransactionGatewayExecuteResult::STATUS_NOT_ENOUGH_BALANCE:
                case TransactionGatewayExecuteResult::STATUS_DECLINED_BY_USER:
                    $this->getContext()->set("canceled", true);
                    $activity->cancel();
                    return;

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
            $this->setWithdrawalOrder($order);

            if (empty($this->getContext()->get("amount"))) {
                $this->getContext()->set(
                    "amount",
                    $this->transGateway->getWithdrawalAmount($this->getTransactionId(), $this->getBroker())
                );
            }

            $activity->succeed();
        } catch (Exception $e) {
            $this->getContext()->set("transFailed", true);
            $this->getContext()->set("errorMessage", $e->getMessage());
            $activity->cancel();
        }
    }

    protected function changeEquity(Activity $activity): void
    {
        if ($this->getContext()->getIfHas('transFailed')) {
            $activity->skip();
            return;
        }

        if ($this->getContext()->getIfHas('canceled')) {
            $activity->skip();
            return;
        }


        try {
            $leadAcc = $this->leadAccRepo->getLightAccount($this->getAccountNumber());

            $prevEquity = $this->getContext()->getIfHas("prevEquity");
            $prevEquityMoney =  new Money($prevEquity, $leadAcc->currency());
            $equity = $prevEquityMoney->subtract($this->getFunds());

            $this->getContext()->set("equity", $equity->amount());

            $activity->succeed();
        } catch (\Exception $e) {
            $this->getContext()->set("errorMessage", $e->getMessage());
            $activity->fail();
        }
    }

    protected function notifyPluginOfWithdrawal(Activity $activity): void
    {
        if ($this->getContext()->getIfHas("transFailed")) {
            $activity->skip();
            return;
        }

        if ($this->getContext()->getIfHas('canceled')) {
            $activity->skip();
            return;
        }

        $leadAcc = $this->leadAccRepo->getLightAccountOrFail($this->getAccountNumber());
        if (!$leadAcc->isCopied()) {
            $activity->skip();
            return;
        }

        $pluginGateway = $this->pluginManager->getForAccount($leadAcc);

        $actCtx = $activity->getContext();
        if (!$actCtx->has("msgId")) {
            $msgId = $pluginGateway->sendMessage(
                $this->getAccountNumber(),
                $this->id(),
                PluginGateway::LEADER_WITHDRAWAL,
                $this->getFunds()->amount()
            );
            $actCtx->set('msgId', $msgId);
            $actCtx->set('server', $leadAcc->server());
        }
        if ($pluginGateway->isMessageAcknowledged($actCtx->get("msgId"))) {
            $activity->succeed();
            return;
        }
        $activity->keepTrying();
    }

    protected function updateBalance(Activity $activity): void
    {
        if ($this->getContext()->getIfHas("transFailed")) {
            $activity->skip();
            return;
        }

        if ($this->getContext()->getIfHas('canceled')) {
            $activity->skip();
            return;
        }

        $acc = $this->leadAccRepo->findOrFail($this->getAccountNumber());
        $acc->withdrawFunds($this->getFunds(), $this);

        try {
            $this->leadAccRepo->store($acc);
        } catch (\Exception $e) {
            $this->logException($e);
            $activity->fail();
            return;
        }

        $this->setAccountName($acc->name()); // for "notifyClient"
        $activity->succeed();
    }

    protected function enableTrading(Activity $activity): void
    {
        if (!$this->getContext()->has("tradingDisabled")) {
            $activity->skip();
            return;
        }

        $this->tradeAccGateway->changeAccountReadOnly($this->getAccountNumber(), $this->getBroker(), false);
        $activity->succeed();
    }

    protected function notifyClient(Activity $activity): void
    {
        if ($this->getContext()->getIfHas("transFailed")) {
            $activity->skip();
            return;
        }

        if ($this->getContext()->getIfHas('canceled')) {
            $activity->skip();
            return;
        }

        $this->notifGateway->notifyClient(
            $this->getClientId(),
            $this->getBroker(),
            $this->transGateway->isNoActivityWithdrawal($this->getTransactionId(), $this->getBroker())
                ? NotificationGateway::LEADER_FUNDS_WITHDRAWN_NO_ACTIVITY_FEE
                : NotificationGateway::LEADER_FUNDS_WITHDRAWN,
            $this->getContext()->toArray()
        );
        $activity->succeed();
    }

    protected function closeFollowerAccounts(Activity $activity): void
    {
        if ($this->getContext()->getIfHas("transFailed")) {
            $activity->skip();
            return;
        }

        if ($this->getContext()->getIfHas('canceled')) {
            $activity->skip();
            return;
        }

        $leadAcc = $this->leadAccRepo->findOrFail($this->getAccountNumber());
        // Should check for the required amount of equity, but temporarily
        // compares to 0 before we clarify this in the interface
        if (!$leadAcc->equity()->isLessThanOrEqualTo(new Money(0, $leadAcc->currency()))) {
            $activity->skip();
            return;
        }

        if ($activity->isFirstTry() && empty($this->follAccRepo->findOpenByLeaderAccountNumber($this->getAccountNumber()))) {
            $activity->skip();
            return;
        }

        $status = $this->findCreateExecute(
            $this->getAccountNumber()->value(),
            CloseFollowerAccountsWorkflow::TYPE,
            function() {
                return $this->createChild(
                    CloseFollowerAccountsWorkflow::TYPE,
                    new ContextData([
                        "accNo" => $this->getAccountNumber()->value(),
                        'broker' => $this->getBroker(),
                    ])
                );
            }
        );

        $status->updateActivity($activity);
    }

    protected function deactivateAccount(Activity $activity): void
    {
        if ($this->getContext()->getIfHas("transFailed")) {
            $activity->skip();
            return;
        }

        if ($this->getContext()->getIfHas('canceled')) {
            $activity->skip();
            return;
        }

        $leadAcc = $this->leadAccRepo->findOrFail($this->getAccountNumber());
        if (!$leadAcc->equity()->isLessThanOrEqualTo(new Money(0, $leadAcc->currency()))) {
            $activity->skip();
            return;
        }

        $leadAcc->deactivate();
        try {
            $this->leadAccRepo->store($leadAcc);
        } catch (\Exception $e) {
            $this->logException($e);
            $activity->fail();
            return;
        }

        $this->notifGateway->notifyClient(
            $this->getClientId(),
            $this->getBroker(),
            NotificationGateway::LEADER_ACC_DEACTIVATED,
            $this->getContext()->toArray() + [
                "reqEquity" => $leadAcc->requiredEquity()->amount()
            ]
        );

        $activity->succeed();
    }

    private function setAccountName($accName)
    {
        $this->getContext()->set("accName", $accName);
    }

    private function setWithdrawalOrder($order)
    {
        $this->getContext()->set("withdrawalOrder", $order);
    }

    private function getWithdrawalOrder()
    {
        return $this->getContext()->get("withdrawalOrder");
    }

    private function getTransactionId()
    {
        return $this->getContext()->get("transId");
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
        return $this->leadAccRepo;
    }

    /**
     * TODO commented as temporarily resolving of issue
     * Method should return true if all necessary conditions for
     * creating of this workflow are met
     *
     * @return bool
     */
    //public function canBeCreated()
    //{
    //   return !empty($this->leadAccRepo->find($this->getAccountNumber()));
    //}

    public function getBroker()
    {
        return $this->getContext()->get('broker');
    }
}
