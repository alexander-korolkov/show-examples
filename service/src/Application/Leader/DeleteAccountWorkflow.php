<?php

namespace Fxtm\CopyTrading\Application\Leader;

use Fxtm\CopyTrading\Application\Common\BaseParentalWorkflow;
use Fxtm\CopyTrading\Application\Common\Workflow\Activity;
use Fxtm\CopyTrading\Application\Common\Workflow\ContextData;
use Fxtm\CopyTrading\Application\Common\Workflow\WorkflowManager;
use Fxtm\CopyTrading\Application\Common\Workflow\WorkflowRepository;
use Fxtm\CopyTrading\Application\NotificationGateway;
use Fxtm\CopyTrading\Application\PluginGateway;
use Fxtm\CopyTrading\Application\PluginGatewayManager;
use Fxtm\CopyTrading\Application\TradeAccountGateway;
use Fxtm\CopyTrading\Application\TransactionGateway;
use Fxtm\CopyTrading\Application\TransactionGatewayExecuteResult;
use Fxtm\CopyTrading\Domain\Common\DateTime;
use Fxtm\CopyTrading\Domain\Model\Leader\LeaderAccountRepository;
use Fxtm\CopyTrading\Domain\Model\Follower\FollowerAccountRepository;
use Fxtm\CopyTrading\Domain\Model\Shared\AccountNumber;
use Fxtm\CopyTrading\Domain\Model\Shared\Money;

class DeleteAccountWorkflow extends BaseParentalWorkflow
{
    const TYPE = "leader.delete_account";

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
    private $notifGateway    = null;

    private $pluginManager;

    /**
     * CloseAccountWorkflow constructor.
     * @param TradeAccountGateway $tradeAccGateway
     * @param LeaderAccountRepository $leadAccRepo
     * @param FollowerAccountRepository $follAccRepo
     * @param WorkflowManager $workflowManager
     * @param WorkflowRepository $workflowRepo
     * @param TransactionGateway $transGateway
     * @param PluginGatewayManager $pluginManager
     * @param NotificationGateway $notifGateway
     */
    public function __construct(
        TradeAccountGateway $tradeAccGateway,
        LeaderAccountRepository $leadAccRepo,
        FollowerAccountRepository $follAccRepo,
        WorkflowManager $workflowManager,
        WorkflowRepository $workflowRepo,
        TransactionGateway $transGateway,
        PluginGatewayManager $pluginManager,
        NotificationGateway $notifGateway
    ) {
        $this->tradeAccGateway      = $tradeAccGateway;
        $this->leadAccRepo          = $leadAccRepo;
        $this->follAccRepo          = $follAccRepo;
        $this->workflowManager      = $workflowManager;
        $this->workflowRepository   = $workflowRepo;
        $this->transGateway         = $transGateway;
        $this->pluginManager        = $pluginManager;
        $this->notifGateway         = $notifGateway;


        parent::__construct(
            $this->activities([
                "ensureNoOpenPositions",
                "disableTrading",
                "withdrawAllFunds",
                "changeEquity",
                "notifyPluginOfWithdrawal",
                "closeFollowerAccounts",
                "deleteAccount",
                "notifyLeader",
            ])
        );
    }

    protected function ensureNoOpenPositions(Activity $activity): void
    {
        $leadAcc = $this->leadAccRepo->findOrFail($this->getAccountNumber());
        if ($leadAcc->hasOpenPositions()) {
            $this->scheduleAt(DateTime::of("+2 minutes"));
            $activity->keepTrying();
            return;
        }
        $activity->succeed();
    }

    protected function disableTrading(Activity $activity): void
    {
        $this->tradeAccGateway->changeAccountReadOnly($this->getAccountNumber(), $this->getBroker());
        $activity->succeed();
    }

    protected function withdrawAllFunds(Activity $activity): void
    {
        $leadAcc = $this->leadAccRepo->findOrFail($this->getAccountNumber());
        if ($leadAcc->equity()->amount() <= 0) {
            $activity->skip();
            return;
        }
        try {
            // Update balance by the amount of the equity
            $leadAcc->withdrawFunds($leadAcc->equity(), $this);

            $this->leadAccRepo->store($leadAcc);
        } catch (\Exception $e) {
            $this->logException($e);
            $activity->fail();
            return;
        }

        $this->getContext()->set("in_out", $leadAcc->equity()->amount());

        $transId = $this->transGateway->createTransaction($leadAcc->number(), $this->getBroker(), $leadAcc->equity()->amount());
        try {
            //Save time when we start executeTransaction
            $this->getContext()->set("orderTime", DateTime::NOW()->format('Y-m-d H:i:s'));

            if (!$this->getContext()->has('prevEquity')) {
                $this->getContext()->set('prevEquity', $leadAcc->equity()->amount());
            }

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

            // populate context for logging purposes
            $this->getContext()->set("transId", $transId);
            $this->getContext()->set("order", $order);

            $activity->succeed();
        } catch (\Exception $e) {
            $this->getContext()->set("errorMessage", $e->getMessage());
            $activity->fail();
        }
    }

    protected function changeEquity(Activity $activity): void
    {
        if ($this->getContext()->getIfHas('transFailed')) {
            $activity->skip();
            return;
        }

        try {
            $leadAcc = $this->leadAccRepo->findOrFail($this->getAccountNumber());
            if ($leadAcc->equity()->amount() <= 0) {
                $activity->skip();
                return;
            }

            $transAmt =  new Money($this->getContext()->get("in_out"), $leadAcc->currency());
            //Get time when we start executeTransaction
            $prevEquity = $this->getContext()->getIfHas("prevEquity");
            $prevEquityMoney =  new Money($prevEquity, $leadAcc->currency());
            $equity = $prevEquityMoney->subtract($transAmt);

            $this->getContext()->set("equity", $equity->amount());

            $activity->succeed();
        } catch (\Exception $e) {
            $this->getContext()->set("errorMessage", $e->getMessage());
            $activity->fail();
        }
    }

    protected function notifyPluginOfWithdrawal(Activity $activity): void
    {
        $leadAcc = $this->leadAccRepo->getLightAccountOrFail($this->getAccountNumber());
        if (!$leadAcc->isCopied()) {
            $activity->skip();
            return;
        }

        if (!$this->getContext()->has("order")) {
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
                $this->getContext()->get('prevEquity')
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

    protected function closeFollowerAccounts(Activity $activity): void
    {
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

        $actCtx = $activity->getContext();
        if (empty($actCtx->getIfHas("workflowId")) && $status->getChild() != null) {
            $actCtx->set("workflowId", $status->getChild()->id());
        }
    }

    protected function deleteAccount(Activity $activity): void
    {
        try {
            $accNo = $this->getAccountNumber();
            $leadAcc = $this->leadAccRepo->findOrFail($accNo);
            $leadAcc->delete($this);
            $this->tradeAccGateway->destroyAccount($accNo, $this->getBroker());

            $this->leadAccRepo->store($leadAcc);
        } catch (\Exception $e) {
            $this->logException($e);
            $activity->fail();
            return;
        }
        $activity->succeed();
    }

    protected function notifyLeader(Activity $activity): void
    {
        $leadAcc = $this->leadAccRepo->getLightAccountOrFail($this->getAccountNumber());
        $this->notifGateway->notifyClient(
            $leadAcc->ownerId(),
            $leadAcc->broker(),
            NotificationGateway::LEADER_ACC_CLOSED,
            [
                'accNo'   => $leadAcc->number()->value(),
                'accName' => $leadAcc->name(),
                'accCurr' => $leadAcc->currency()->code(),
            ]
        );
        $activity->succeed();
    }

    private function getAccountNumber()
    {
        return new AccountNumber($this->getContext()->get("accNo"));
    }

    public function getAccountRepository()
    {
        return $this->leadAccRepo;
    }

    /**
     * Method should return true if all necessary conditions for
     * creating of this workflow are met
     *
     * @return bool
     */
    public function canBeCreated()
    {
        return !empty($this->leadAccRepo->getLightAccount($this->getAccountNumber()));
    }

    private function getBroker()
    {
        return $this->getContext()->get('broker');
    }
}
