<?php

namespace Fxtm\CopyTrading\Application\Follower;

use Fxtm\CopyTrading\Application\Common\BaseParentalWorkflow;
use Fxtm\CopyTrading\Application\Common\Workflow\Activity;
use Fxtm\CopyTrading\Application\Common\Workflow\ContextData;
use Fxtm\CopyTrading\Application\Common\Workflow\WorkflowManager;
use Fxtm\CopyTrading\Application\Common\Workflow\WorkflowRepository;
use Fxtm\CopyTrading\Application\Common\Workflow\WorkflowState;
use Fxtm\CopyTrading\Application\NotificationGateway;
use Fxtm\CopyTrading\Application\PluginGateway;
use Fxtm\CopyTrading\Application\PluginGatewayManager;
use Fxtm\CopyTrading\Application\TransactionGateway;
use Fxtm\CopyTrading\Application\TransactionGatewayExecuteResult;
use Fxtm\CopyTrading\Domain\Common\DateTime;
use Fxtm\CopyTrading\Domain\Model\Client\ClientId;
use Fxtm\CopyTrading\Domain\Model\Follower\FollowerAccountRepository;
use Fxtm\CopyTrading\Domain\Model\Follower\Commission;
use Fxtm\CopyTrading\Domain\Model\Leader\LeaderAccountRepository;
use Fxtm\CopyTrading\Domain\Model\Shared\AccountNumber;
use Fxtm\CopyTrading\Domain\Model\Shared\Currency;
use Fxtm\CopyTrading\Domain\Model\Shared\Money;

class ProcessWithdrawalWorkflow extends BaseParentalWorkflow
{
    const TYPE = "follower.process_withdrawal";

    private $transGateway    = null;
    private $pluginManager   = null;

    /**
     * @var LeaderAccountRepository
     */
    private $leadAccRepo;

    private $notifGateway    = null;

    /**
     * ProcessWithdrawalWorkflow constructor.
     * @param TransactionGateway $transGateway
     * @param PluginGatewayManager $pluginManager
     * @param FollowerAccountRepository $follAccRepo
     * @param LeaderAccountRepository $leadAccRepo
     * @param WorkflowManager $workflowManager
     * @param WorkflowRepository $workflowRepo
     * @param NotificationGateway $notifGateway
     */
    public function __construct(
        TransactionGateway $transGateway,
        PluginGatewayManager $pluginManager,
        FollowerAccountRepository $follAccRepo,
        LeaderAccountRepository $leadAccRepo,
        WorkflowManager $workflowManager,
        WorkflowRepository $workflowRepo,
        NotificationGateway $notifGateway
    ) {
        $this->transGateway    = $transGateway;
        $this->pluginManager   = $pluginManager;
        $this->follAccRepo     = $follAccRepo;
        $this->leadAccRepo     = $leadAccRepo;
        $this->workflowManager      = $workflowManager;
        $this->workflowRepository   = $workflowRepo;
        $this->notifGateway    = $notifGateway;

        parent::__construct(
            $this->activities([
                "createTransaction",
                "notifyPluginOfWithdrawal",
                "executeTransaction",
                "settleCommission",
                "updateBalance",
                "tellPluginToUpdateStoploss",
                "notifyClient",
            ])
        );
    }

    protected function doProceed()
    {
        if ($this->getTriesCount() === 1) {
            $follAcc = $this->follAccRepo->findOrFail($this->getAccountNumber());
            // Should check for the required amount of equity, but temporarily
            // compares to 0 before we clarify this in the interface
            if ($follAcc->equity()->subtract($this->getFunds())->isLessThanOrEqualTo(new Money(0, $follAcc->currency()))) {
                /*
                 * this workflow reborn into new workflow; So no need to spawn a child.
                 */
                $workflow = $this->workflowManager->newWorkflow(
                    CloseAccountWorkflow::TYPE,
                    new ContextData([
                        "accNo" => $this->getAccountNumber()->value(),
                        'broker' => $this->getBroker(),
                        'causedBy' => $this->id()
                    ])
                );
                $this->workflowManager->enqueueWorkflow($workflow);
                return WorkflowState::REJECTED;
            }
        }
        $result = parent::doProceed();
        if($result == WorkflowState::COMPLETED && $this->getContext()->getIfHas('canceled')) {
            return WorkflowState::CANCELLED;
        }
        return $result;
    }

    protected function createTransaction(Activity $activity): void
    {
        $this->setTransactionId(
            $this->transGateway->createSynchronousTransaction(
                $this->getAccountNumber(),
                $this->getBroker(),
                $this->getFunds()->amount()
            )
        );

        $activity->succeed();
    }

    protected function notifyPluginOfWithdrawal(Activity $activity): void
    {
        if ($this->getContext()->getIfHas('canceled')) {
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
                $this->getFunds()->amount()
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

    protected function executeTransaction(Activity $activity): void
    {
        $follAcc = $this->follAccRepo->findOrFail($this->getAccountNumber());
        $this->getContext()->set("prevEquity", $follAcc->equity()->amount());

        try {
            //Save time when we start executeTransaction
            $this->getContext()->set("orderTime", DateTime::NOW()->format('Y-m-d H:i:s'));

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

            $activity->succeed();
        } catch (\Exception $e) {
            $this->getContext()->set("errorMessage", $e->getMessage());
            $activity->fail();
        }
    }

    protected function settleCommission(Activity $activity): void
    {
        if ($this->getContext()->getIfHas('canceled')) {
            $activity->skip();
            return;
        }

        $accountNumber = $this->getAccountNumber()->value();
        $status = $this->findCreateExecute(
            $accountNumber,
            PayCommissionWorkflow::TYPE,
            function () use ($accountNumber) {
                return $this->createChild(
                    PayCommissionWorkflow::TYPE,
                    new ContextData([
                        'accNo' => $accountNumber,
                        'prevEquity' => $this->getContext()->get('prevEquity'),
                        'settlingFunds' => $this->getFunds()->amount(),
                        'currency' => $this->getContext()->get("accCurr"),
                        'settlingEquity' => $this->getContext()->get('prevEquity'),
                        'commissionType' => Commission::TYPE_WITHDRAWAL,
                        'accountToWalletTransactionId' => $this->getTransactionId(),
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
                $follAcc = $this->follAccRepo->getLightAccountOrFail($this->getAccountNumber());
                $fee = $innerWorkflow->getContext()->get('fee');

                $feeStr = sprintf('%01.2f', $fee);
                $netAmountStr = sprintf('%01.2f', $this->getContext()->get('amount') - $fee);

                $this->getContext()->set('prevFeeLvl', $innerWorkflow->getContext()->get('prevFeeLvl'));
                $this->getContext()->set('feeLvl', $follAcc->settlingEquity()->amount());
                $this->getContext()->set('fee', $feeStr);
                $this->getContext()->set('netAmount', $netAmountStr);

                $activity->succeed();
                break;
        }
    }

    protected function updateBalance(Activity $activity): void
    {
        if ($this->getContext()->getIfHas('canceled')) {
            $activity->skip();
            return;
        }

        $follAcc = $this->follAccRepo->findOrFail($this->getAccountNumber());
        $follAcc->withdrawFunds($this->getFunds(), $this);
        try {
            $this->logDebug($activity, __FUNCTION__, 'store');
            $this->follAccRepo->store($follAcc);
        } catch (\Exception $e) {
            $this->logException($e);
            $activity->fail();
            return;
        }

        $this->getContext()->set("stopLossEquityNew", $follAcc->stopLossEquity()->truncate()->amount());

        $leadAcc = $this->leadAccRepo->getLightAccountOrFail($follAcc->leaderAccountNumber());
        $this->setLeaderAccountName($leadAcc->name()); // for "notifyClient"
        $this->setLeaderIsPublic($leadAcc->isPublic()); // for "notifyClient"
        $activity->succeed();
    }

    protected function tellPluginToUpdateStoploss(Activity $activity): void
    {
        if ($this->getContext()->getIfHas('canceled')) {
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

    protected function notifyClient(Activity $activity): void
    {
        if ($this->getContext()->getIfHas('canceled')) {
            $activity->skip();
            return;
        }

        $this->notifGateway->notifyClient(
            $this->getClientId(),
            $this->getBroker(),
            NotificationGateway::FOLLOWER_FUNDS_WITHDRAWN,
            $this->getContext()->toArray()
        );
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

    private function setTransactionId($transId)
    {
        $this->getContext()->set("transId", $transId);
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

    public function canBeCreated()
    {
        // TODO It's a temp hotfix
        return true;

        $followerAccount = $this->follAccRepo->findOrFail($this->getAccountNumber());
        $minEquity = $followerAccount->requiredEquity();

        return $followerAccount->equity()
            ->subtract($this->getFunds())
            ->isGreaterThanOrEqualTo($minEquity);
    }

    private function getBroker()
    {
        return $this->getContext()->get('broker');
    }
}
