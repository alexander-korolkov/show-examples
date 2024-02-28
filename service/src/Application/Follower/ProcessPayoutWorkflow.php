<?php

namespace Fxtm\CopyTrading\Application\Follower;

use Fxtm\CopyTrading\Application\Common\BaseParentalWorkflow;
use Fxtm\CopyTrading\Application\Common\Workflow\Activity;
use Fxtm\CopyTrading\Application\Common\Workflow\ContextData;
use Fxtm\CopyTrading\Application\Common\Workflow\WorkflowManager;
use Fxtm\CopyTrading\Application\Common\Workflow\WorkflowRepository;
use Fxtm\CopyTrading\Application\NotificationGateway;
use Fxtm\CopyTrading\Application\PluginGateway;
use Fxtm\CopyTrading\Application\PluginGatewayManager;
use Fxtm\CopyTrading\Domain\Common\DateTime;
use Fxtm\CopyTrading\Domain\Model\Follower\FollowerAccountRepository;
use Fxtm\CopyTrading\Domain\Model\Follower\Commission;
use Fxtm\CopyTrading\Domain\Model\Shared\AccountNumber;
use Fxtm\CopyTrading\Domain\Model\Shared\Currency;
use Fxtm\CopyTrading\Domain\Model\Shared\Money;

class ProcessPayoutWorkflow extends BaseParentalWorkflow
{
    const TYPE = "follower.process_payout";

    private $pluginManager   = null;

    private $notifGateway    = null;
    private $statementSvc    = null;

    /**
     * ProcessPayoutWorkflow constructor.
     * @param PluginGatewayManager $pluginManager
     * @param FollowerAccountRepository $follAccRepo
     * @param WorkflowManager $workflowManager
     * @param WorkflowRepository $workflowRepo
     * @param NotificationGateway $notifGateway
     * @param StatementService $statementSvc
     */
    public function __construct(
        PluginGatewayManager $pluginManager,
        FollowerAccountRepository $follAccRepo,
        WorkflowManager $workflowManager,
        WorkflowRepository $workflowRepo,
        NotificationGateway $notifGateway,
        StatementService $statementSvc
    ) {
        $this->pluginManager   = $pluginManager;
        $this->follAccRepo     = $follAccRepo;
        $this->workflowManager      = $workflowManager;
        $this->workflowRepository   = $workflowRepo;
        $this->notifGateway    = $notifGateway;
        $this->statementSvc    = $statementSvc;

        parent::__construct(
            $this->activities([
                "settleCommission",
                "notifyPluginOfWithdrawal",
                "updateBalance",
                "sendStatement",
            ])
        );
    }

    protected function doProceed()
    {
        return parent::doProceed();
    }

    protected function settleCommission(Activity $activity): void
    {
        $follAcc = $this->follAccRepo->findOrFail($this->getAccountNumber());
        $this->getContext()->set('prevSettledAt', $follAcc->settledAt()->__toString());


        $accountNumber = $this->getAccountNumber()->value();
        $status = $this->findCreateExecute(
            $accountNumber,
            PayCommissionWorkflow::TYPE,
            function () use ($accountNumber, $follAcc) {
                return $this->createChild(
                    PayCommissionWorkflow::TYPE,
                    new ContextData([
                        'accNo' => $accountNumber,
                        'prevEquity' => $follAcc->equity()->amount(),
                        'currency' => $this->getContext()->get("accCurr"),
                        'commissionType' => Commission::TYPE_PERIODICAL,
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
                $follAcc = $this->follAccRepo->findOrFail($this->getAccountNumber());
                $this->getContext()->set('prevEquity', $innerWorkflow->getContext()->get('prevEquity'));
                $this->getContext()->set('prevFeeLvl', $innerWorkflow->getContext()->get('prevFeeLvl'));
                $this->getContext()->set('feeLvl', $follAcc->settlingEquity()->amount());

                $fee = $innerWorkflow->getContext()->get('fee');
                $this->getContext()->set('fee', $fee);
                if ($fee > 0) {
                    $this->setTransactionId($innerWorkflow->getContext()->get('accountToWalletTransactionId'));
                    $this->setWithdrawalOrder($innerWorkflow->getContext()->get('withdrawalOrder'));
                }

                $activity->succeed();
                break;
        }
    }

    protected function notifyPluginOfWithdrawal(Activity $activity): void
    {
        if (!$this->getContext()->get("fee")) {
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
                $this->getContext()->get("fee")
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

    protected function updateBalance(Activity $activity): void
    {
        if (!$this->getContext()->get("fee")) {
            $activity->skip();
            return;
        }
        $follAcc = $this->follAccRepo->findOrFail($this->getAccountNumber());
        $follAcc->withdrawFunds($this->getFunds(), $this, false);

        try {
            $this->logDebug($activity, __FUNCTION__, 'store');
            $this->follAccRepo->store($follAcc);
        } catch (\Exception $e) {
            $this->logException($e);
            $activity->fail();
            return;
        }

        $this->setNewStoplossEquity($follAcc->stopLossEquity()->amount());
        $activity->succeed();
    }

    protected function sendStatement(Activity $activity): void
    {
        $follAcc = $this->follAccRepo->getLightAccountOrFail($this->getAccountNumber());
        if (!$follAcc->isActivated()) {
            $activity->skip();
            return;
        }

        $this->notifGateway->notifyClient(
            $follAcc->ownerId(),
            $this->getBroker(),
            NotificationGateway::FOLLOWER_STATEMENT,
            $this->statementSvc->prepareStatementData(
                $this->getAccountNumber(),
                DateTime::of($this->getContext()->get("prevSettledAt")),
                $follAcc->settledAt()
            )
        );
        $activity->succeed();
    }

    private function setWithdrawalOrder($order)
    {
        $this->getContext()->set("withdrawalOrder", $order);
    }

    private function getWithdrawalOrder()
    {
        return $this->getContext()->get("withdrawalOrder");
    }

    private function setNewStoplossEquity($stoplossEquity)
    {
        $this->getContext()->set("stoplossEquity", $stoplossEquity);
    }

    private function getNewStoplossEquity()
    {
        return $this->getContext()->get("stoplossEquity");
    }

    private function getTransactionId()
    {
        return $this->getContext()->get("transId");
    }

    private function setTransactionId($transId)
    {
        $this->getContext()->set("transId", $transId);
    }

    private function getAccountNumber()
    {
        return new AccountNumber($this->getContext()->get("accNo"));
    }

    private function getFunds()
    {
        return new Money(
            $this->getContext()->get("fee"),
            Currency::forCode($this->getContext()->get("accCurr"))
        );
    }

    public function getAccountRepository()
    {
        return $this->follAccRepo;
    }

    private function getBroker()
    {
        return $this->getContext()->get('broker');
    }
}
