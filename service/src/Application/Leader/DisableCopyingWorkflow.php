<?php

namespace Fxtm\CopyTrading\Application\Leader;

use Fxtm\CopyTrading\Application\Common\BaseWorkflow;
use Fxtm\CopyTrading\Application\Common\Workflow\Activity;
use Fxtm\CopyTrading\Application\Common\Workflow\WorkflowRepository;
use Fxtm\CopyTrading\Application\Common\Workflow\WorkflowState;
use Fxtm\CopyTrading\Application\PluginGateway;
use Fxtm\CopyTrading\Application\PluginGatewayManager;
use Fxtm\CopyTrading\Domain\Common\DateTime;
use Fxtm\CopyTrading\Domain\Model\Leader\LeaderAccountRepository;
use Fxtm\CopyTrading\Domain\Model\Shared\AccountNumber;

class DisableCopyingWorkflow extends BaseWorkflow
{
    const TYPE = "leader.disable_copying";

    /**
     * @var LeaderAccountRepository
     */
    private $leadAccRepo;

    /**
     * @var WorkflowRepository
     */
    private $workflowRepo;

    private $pluginManager = null;

    /**
     * DisableCopyingWorkflow constructor.
     * @param LeaderAccountRepository $leadAccRepo
     * @param PluginGatewayManager $pluginManager
     */
    public function __construct(
        LeaderAccountRepository $leadAccRepo,
        PluginGatewayManager $pluginManager,
        WorkflowRepository $workflowRepo
    ) {
        $this->leadAccRepo   = $leadAccRepo;
        $this->pluginManager = $pluginManager;
        $this->workflowRepo = $workflowRepo;

        parent::__construct(
            $this->activities([
                "tellPluginToDisableCopying",
                "updateCopyingStatus"
            ])
        );
    }

    protected function doProceed()
    {
        $leadAcc = $this->leadAccRepo->getLightAccountOrFail($this->getAccountNumber());
        $latestEnableCopyingWorkflow = $this->workflowRepo->findLatestStartedByCorrelationIdAndType(
            $this->getAccountNumber()->value(),
            EnableCopyingWorkflow::TYPE
        );
        if (
            ($this->isFirstTry() && !$leadAcc->isCopied()) ||
            (
                !is_null($latestEnableCopyingWorkflow) &&
                $latestEnableCopyingWorkflow->startedAt() > DateTime::of('-1 min')
            )
        ) {
            return WorkflowState::REJECTED;
        }
        return parent::doProceed();
    }

    public function tellPluginToDisableCopying(Activity $activity): void
    {
        $leadAcc = $this->leadAccRepo->getLightAccountOrFail($this->getAccountNumber());
        $pluginGateway = $this->pluginManager->getForAccount($leadAcc);

        $actCtx = $activity->getContext();
        if (!$actCtx->has("msgId")) {
            $msgId = $pluginGateway->sendMessage(
                $this->getAccountNumber(),
                $this->id(),
                PluginGateway::LEADER_COPIED_NOT,
                0
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

    public function updateCopyingStatus(Activity $activity): void
    {
        try {
            $leadAcc = $this->leadAccRepo->getLightAccountOrFail($this->getAccountNumber());
            $leadAcc->disableCopying();

            $this->leadAccRepo->store($leadAcc);
        } catch (\Exception $e) {
            $this->logException($e);
            $activity->fail();
            return;
        }
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
}
