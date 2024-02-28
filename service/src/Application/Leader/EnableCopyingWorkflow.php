<?php

namespace Fxtm\CopyTrading\Application\Leader;

use Fxtm\CopyTrading\Application\Common\BaseWorkflow;
use Fxtm\CopyTrading\Application\Common\Workflow\Activity;
use Fxtm\CopyTrading\Application\Common\Workflow\WorkflowState;
use Fxtm\CopyTrading\Application\PluginGateway;
use Fxtm\CopyTrading\Application\PluginGatewayManager;
use Fxtm\CopyTrading\Application\TradeAccountGateway;
use Fxtm\CopyTrading\Domain\Model\Leader\LeaderAccountRepository;
use Fxtm\CopyTrading\Domain\Model\Shared\AccountNumber;
use Fxtm\CopyTrading\Domain\Model\Shared\Server;

class EnableCopyingWorkflow extends BaseWorkflow
{
    const TYPE = "leader.enable_copying";

    /**
     * @var LeaderAccountRepository
     */
    private $leadAccRepo;

    private $tradeAccGateway = null;
    private $pluginManager   = null;

    /**
     * EnableCopyingWorkflow constructor.
     * @param LeaderAccountRepository $leadAccRepo
     * @param TradeAccountGateway $tradeAccGateway
     * @param PluginGatewayManager $pluginManager
     */
    public function __construct(
        LeaderAccountRepository $leadAccRepo,
        TradeAccountGateway $tradeAccGateway,
        PluginGatewayManager $pluginManager
    ) {
        $this->leadAccRepo     = $leadAccRepo;
        $this->tradeAccGateway = $tradeAccGateway;
        $this->pluginManager   = $pluginManager;

        parent::__construct(
            $this->activities([
                "updateCopyingStatus",
                "createAggregateAccount",
                "tellPluginToEnableCopying"
            ])
        );
    }

    protected function doProceed()
    {
        $leadAcc = $this->leadAccRepo->getLightAccountOrFail($this->getAccountNumber());
        if ($this->isFirstTry() && $leadAcc->isCopied()) {
            return WorkflowState::REJECTED;
        }
        return parent::doProceed();
    }

    public function updateCopyingStatus(Activity $activity): void
    {
        try {
            $leadAcc = $this->leadAccRepo->getLightAccountOrFail($this->getAccountNumber());
            $leadAcc->enableCopying();
            $this->leadAccRepo->store($leadAcc);
        } catch (\Exception $e) {
            $this->logException($e);
            $activity->fail();
            return;
        }
        $activity->succeed();
    }

    protected function createAggregateAccount(Activity $activity): void
    {
        try {
            $leadAcc = $this->leadAccRepo->getLightAccountOrFail($this->getAccountNumber());
            if ($leadAcc->aggregateAccountNumber()) {
                $activity->skip();
                return;
            }

            $aggrAcc = $this->tradeAccGateway->createAggregateAccount($leadAcc, $leadAcc->broker());
            $leadAcc->assignAggregateAccountNumber($aggrAcc->number());
            $this->setAggregateAccountNumber($aggrAcc->number());

            $this->leadAccRepo->store($leadAcc);
        } catch (\Exception $e) {
            $this->logException($e);
            $activity->fail();
            return;
        }
        $activity->succeed();
    }

    public function tellPluginToEnableCopying(Activity $activity): void
    {
        $leadAcc = $this->leadAccRepo->getLightAccountOrFail($this->getAccountNumber());
        $pluginGateway = $this->pluginManager->getForAccount($leadAcc);

        $actCtx = $activity->getContext();
        if (!$actCtx->has("msgId")) {
            $msgId = $pluginGateway->sendMessage(
                $this->getAccountNumber(),
                $this->id(),
                PluginGateway::LEADER_COPIED,
                1
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

    private function setAggregateAccountNumber(AccountNumber $aggrAccNo)
    {
        $this->getContext()->set("aggrAccNo", $aggrAccNo->value());
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
}
