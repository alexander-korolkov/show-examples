<?php

namespace Fxtm\CopyTrading\Application\Leader;

use Exception;
use Fxtm\CopyTrading\Application\ClientGateway;
use Fxtm\CopyTrading\Application\Common\BaseParentalWorkflow;
use Fxtm\CopyTrading\Application\Common\BaseWorkflow;
use Fxtm\CopyTrading\Application\Common\Workflow\Activity;
use Fxtm\CopyTrading\Application\Common\Workflow\ContextData;
use Fxtm\CopyTrading\Application\Common\Workflow\WorkflowManager;
use Fxtm\CopyTrading\Application\Common\Workflow\WorkflowRepository;
use Fxtm\CopyTrading\Application\Common\Workflow\WorkflowState;
use Fxtm\CopyTrading\Application\EquityService;
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

class ProcessDepositWorkflow extends BaseParentalWorkflow
{
    const TYPE = "leader.process_deposit";

    private $tradeAccGateway = null;

    /**
     * @var LeaderAccountRepository
     */
    private $leadAccRepo;

    /**
     * @var FollowerAccountRepository
     */
    private $follAccRepo;

    /**
     * @var EquityService
     */
    private $equityService;

    private $transGateway    = null;
    private $pluginManager   = null;
    private $notifGateway    = null;
    private $clientGateway   = null;

    /**
     * ProcessDepositWorkflow constructor.
     * @param TradeAccountGateway $tradeAccGateway
     * @param LeaderAccountRepository $leadAccRepo
     * @param FollowerAccountRepository $follAccRepo
     * @param TransactionGateway $transGateway
     * @param PluginGatewayManager $pluginManager
     * @param NotificationGateway $notifGateway
     * @param WorkflowManager $workflowManager
     * @param WorkflowRepository $workflowRepository
     * @param ClientGateway $clientGateway
     * @param EquityService $equityService
     */
    public function __construct(
        TradeAccountGateway $tradeAccGateway,
        LeaderAccountRepository $leadAccRepo,
        FollowerAccountRepository $follAccRepo,
        TransactionGateway $transGateway,
        PluginGatewayManager $pluginManager,
        NotificationGateway $notifGateway,
        WorkflowManager $workflowManager,
        WorkflowRepository $workflowRepository,
        ClientGateway $clientGateway,
        EquityService $equityService
    ) {
        $this->tradeAccGateway      = $tradeAccGateway;
        $this->leadAccRepo          = $leadAccRepo;
        $this->follAccRepo          = $follAccRepo;
        $this->transGateway         = $transGateway;
        $this->pluginManager        = $pluginManager;
        $this->notifGateway         = $notifGateway;
        $this->workflowManager      = $workflowManager;
        $this->workflowRepository   = $workflowRepository;
        $this->clientGateway        = $clientGateway;$this->equityService   = $equityService;

        parent::__construct(
            $this->activities([
                "ensureNoOpenPositions",
                "disableTrading",
                "executeTransaction",
                "changeEquity",
                "notifyPluginOfDeposit",
                "updateBalance",
                "enableTrading",
                "notifyOnDeposit",
                "notifyOnInsufficientDeposit"
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

            $status = $this->findCreateExecute(
                $this->getAccountNumber()->value(),
                ConvertAccountWorkflow::TYPE,
                function () use ($tradeAcc, $client, $company) {
                    return $this->createChild(
                        ConvertAccountWorkflow::TYPE,
                        new ContextData([
                            "accNo"    => $this->getAccountNumber()->value(),
                            "clientId" => $tradeAcc->ownerId()->value(),
                            "email"    => $client->getParam("email"),
                            "company"  => $company,
                            "accCurr"  => $tradeAcc->currency()->code(),
                            'broker'   => $this->getBroker(),
                        ])
                    );
                }
            );

            if($status->isInterrupted()) {
                return WorkflowState::FAILED;
            }

            if(!$status->getChild()->isCompleted()) {
                return WorkflowState::REJECTED;
            }

        }

        $state = parent::doProceed();

        if ($this->getContext()->getIfHas("transFailed")) {
            return WorkflowState::FAILED;
        }
        if($state == WorkflowState::COMPLETED && $this->getContext()->getIfHas('canceled')) {
            return WorkflowState::CANCELLED;
        }
        return $state;
    }

    protected function ensureNoOpenPositions(Activity $activity): void
    {
        $this->logDebug($activity, __FUNCTION__, 'findOpenByLeaderAccountNumber');
        $activeFollowers = $this->follAccRepo->getCountOfCopyingFollowerAccounts($this->getAccountNumber());
        $this->logDebug($activity, __FUNCTION__, sprintf('Found followers: %d', $activeFollowers));
        if (empty($activeFollowers)) {
            $this->getContext()->set("keepTrading", 1);
            $activity->skip();
            return;
        }

        $this->logDebug($activity, __FUNCTION__, 'get leader account');
        $leadAcc = $this->leadAccRepo->findOrFail($this->getAccountNumber());
        $this->logDebug($activity, __FUNCTION__, sprintf('leader has open positions: %s', $leadAcc->hasOpenPositions() ? 'Yes' : 'No'));

        if (!$leadAcc->hasOpenPositions()) {
            $this->logDebug($activity, __FUNCTION__, 'succeed');
            $activity->succeed();
            return;
        }

        if (!$this->isInternal()) {
            $this->transGateway->changeDestinationToWallet($this->getTransactionId(), $this->getBroker());
            $this->getContext()->set('reason', 'leader has open orders and active followers, external deposit - destination changed to the client\'s wallet');
            $activity->succeed();
            $this->reject();
            $this->notifGateway->notifyClient(
                $leadAcc->ownerId(),
                $this->getBroker(),
                NotificationGateway::LEADER_DEPOSIT_HAS_BEEN_TRANSFERRED_TO_WALLET,
                $this->getContext()->toArray()
            );
            return;
        }

        if ($activity->getTriesCount() === 1) {
            $this->logDebug($activity, __FUNCTION__, 'Notify client about open positions');
            $this->notifGateway->notifyClient(
                $leadAcc->ownerId(),
                $this->getBroker(),
                NotificationGateway::LEADER_DEPOSIT_CLOSE_POSITIONS,
                $this->getContext()->toArray()
            );
        }

        $this->logDebug($activity, __FUNCTION__, 'Schedule 2 minutes later');
        $this->scheduleAt(DateTime::of("+2 minutes"));
        $this->logDebug($activity, __FUNCTION__, 'keep trying');
        $activity->keepTrying();
    }

    protected function disableTrading(Activity $activity): void
    {
        if ($this->getContext()->getIfHas("keepTrading")) {
            $this->logDebug($activity, __FUNCTION__, 'skip due to flag keepTrading');
            $activity->skip();
            return;
        }

        $accNo = $this->getAccountNumber();

        $this->logDebug($activity, __FUNCTION__, 'get leader account');
        $tradeAcc = $this->tradeAccGateway->fetchAccountByNumber($accNo, $this->getBroker());
        if ($tradeAcc->isReadOnly()) {
            $this->logDebug($activity, __FUNCTION__, 'skip due to acc.isReadOnly=true already');
            $activity->skip();
            return;
        }

        $this->logDebug($activity, __FUNCTION__, 'make account readonly');
        $this->tradeAccGateway->changeAccountReadOnly($accNo, $this->getBroker());
        $this->getContext()->set("tradingDisabled", true);

        $this->logDebug($activity, __FUNCTION__, 'succeed');
        $activity->succeed();
    }

    protected function executeTransaction(Activity $activity): void
    {
        try {
            $this->logDebug($activity, __FUNCTION__, sprintf('execute transaction: %d', $this->getTransactionId()));

            $leadAcc = $this->leadAccRepo->findOrFail($this->getAccountNumber());
            //Save time when we start executeTransaction
            $this->getContext()->set("orderTime", DateTime::NOW()->format('Y-m-d H:i:s'));
            $this->getContext()->set("prevEquity", $leadAcc->equity()->amount());

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

            $activity->succeed();
        } catch (Exception $e) {
            $this->logDebug($activity, __FUNCTION__, sprintf('transaction %d failed, %s', $this->getTransactionId(), $e));
            $this->getContext()->set("transFailed", true);
            $this->getContext()->set("errorMessage", $e->getMessage());
            $this->logDebug($activity, __FUNCTION__, 'cancel due to failed transaction');
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
            $this->logDebug($activity, __FUNCTION__, 'get leader account');
            $leadAcc = $this->leadAccRepo->getLightAccount($this->getAccountNumber());
            $prevEquity = $this->getContext()->getIfHas("prevEquity");
            $prevEquityMoney =  new Money($prevEquity, $leadAcc->currency());
            $equity = $this->getFunds()->add($prevEquityMoney);

            $this->getContext()->set("equity", $equity->amount());

            $activity->succeed();
        } catch (\Exception $e) {
            $this->getContext()->set("errorMessage", $e->getMessage());
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
        if ($this->getContext()->getIfHas('canceled')) {
            $activity->skip();
            return;
        }

        $this->logDebug($activity, __FUNCTION__, 'get leader account');
        $leadAcc = $this->leadAccRepo->getLightAccountOrFail($this->getAccountNumber());
        if (!$leadAcc->isCopied()) {
            $this->logDebug($activity, __FUNCTION__, 'skip because leader not copied');
            $activity->skip();
            return;
        }

        $pluginGateway = $this->pluginManager->getForAccount($leadAcc);

        $actCtx = $activity->getContext();
        if (!$actCtx->has("msgId")) {

            $this->logDebug($activity, __FUNCTION__, 'Send message to plugin');
            $msgId = $pluginGateway->sendMessage(
                $this->getAccountNumber(),
                $this->id(),
                PluginGateway::LEADER_DEPOSIT,
                $this->getFunds()->amount()
            );
            $this->logDebug($activity, __FUNCTION__, sprintf('Plugin returned message#%d', $msgId));
            $actCtx->set('msgId', $msgId);
            $actCtx->set('server', $leadAcc->server());
        }
        $this->logDebug($activity, __FUNCTION__, 'isMessageAcknowledged');
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
        if ($this->getContext()->getIfHas('canceled')) {
            $activity->skip();
            return;
        }

        $this->logDebug($activity, __FUNCTION__, 'get leader account');
        $acc = $this->leadAccRepo->findOrFail($this->getAccountNumber());

        $this->logDebug($activity, __FUNCTION__, 'depositFunds');
        $wasPassive = !$acc->isActivated();
        $acc->depositFunds($this->getFunds(), $this);
        if($wasPassive && $acc->isActivated()) {
            //If account just been activated, store current equity as initial deposit
            $this->equityService->saveTransactionEquityChange(
                $this->getAccountNumber(),
                $acc->equity(),
                $acc->equity(),
                null,
                DateTime::NOW()
            );
        }

        try {
            $this->logDebug($activity, __FUNCTION__, 'leadAccRepo.store()');
            $this->leadAccRepo->store($acc);
        } catch (\Exception $e) {
            $this->logException($e);
            $activity->fail();
            return;
        }

        $this->setAccountName($acc->name()); // for "notifyClient"

        $this->logDebug($activity, __FUNCTION__, 'succeed');
        $activity->succeed();
    }

    protected function enableTrading(Activity $activity): void
    {
        if (!$this->getContext()->has("tradingDisabled")) {
            $this->logDebug($activity, __FUNCTION__, 'skip due to enabled trading');
            $activity->skip();
            return;
        }

        $this->logDebug($activity, __FUNCTION__, 'makeAccountReadOnly');
        $this->tradeAccGateway->changeAccountReadOnly($this->getAccountNumber(), $this->getBroker(), false);

        $this->logDebug($activity, __FUNCTION__, 'succeed');
        $activity->succeed();
    }

    protected function notifyOnDeposit(Activity $activity): void
    {
        if ($this->getContext()->getIfHas("transFailed")) {
            $this->logDebug($activity, __FUNCTION__, 'skip due to transFailed');
            $activity->skip();
            return;
        }
        if ($this->getContext()->getIfHas('canceled')) {
            $activity->skip();
            return;
        }

        $this->logDebug($activity, __FUNCTION__, 'notifyClient');
        $this->notifGateway->notifyClient(
            $this->getClientId(),
            $this->getBroker(),
            NotificationGateway::LEADER_FUNDS_DEPOSITED,
            $this->getContext()->toArray()
        );
        $this->logDebug($activity, __FUNCTION__, 'succeed');
        $activity->succeed();
    }

    protected function notifyOnInsufficientDeposit(Activity $activity): void
    {
        if ($this->getContext()->getIfHas("transFailed")) {
            $this->logDebug($activity, __FUNCTION__, 'skip due to transFailed');
            $activity->skip();
            return;
        }
        if ($this->getContext()->getIfHas('canceled')) {
            $activity->skip();
            return;
        }

        $this->logDebug($activity, __FUNCTION__, 'get leader account');
        $leadAcc = $this->leadAccRepo->findOrFail($this->getAccountNumber());
        if ($leadAcc->isActivated()) {
            $this->logDebug($activity, __FUNCTION__, 'skip due leadAcc.isActivated');
            $activity->skip();
            return;
        }
        $this->getContext()->set("accEquity", $leadAcc->equity()->amount());
        $this->getContext()->set("reqEquity", $leadAcc->requiredEquity()->amount());
        $this->getContext()->set("missingAmount", $leadAcc->requiredEquity()->subtract($leadAcc->equity())->amount());
        $this->notifGateway->notifyClient(
            $this->getClientId(),
            $this->getBroker(),
            NotificationGateway::LEADER_FUNDS_DEPOSITED_INSUFFICIENT,
            $this->getContext()->toArray()
        );
        $activity->succeed();
    }

    private function setAccountName($accName)
    {
        $this->getContext()->set("accName", $accName);
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

    private function getBroker()
    {
        return $this->getContext()->get('broker');
    }

    private function isInternal()
    {
        return $this->getContext()->get('internal');
    }
}
