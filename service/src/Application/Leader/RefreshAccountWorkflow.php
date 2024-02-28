<?php

namespace Fxtm\CopyTrading\Application\Leader;

use Fxtm\CopyTrading\Application\Common\BaseWorkflow;
use Fxtm\CopyTrading\Application\Common\Workflow\Activity;
use Fxtm\CopyTrading\Application\PluginGateway;
use Fxtm\CopyTrading\Application\PluginGatewayManager;
use Fxtm\CopyTrading\Domain\Model\Leader\LeaderAccountRepository;
use Fxtm\CopyTrading\Domain\Model\Shared\AccountNumber;
use Fxtm\CopyTrading\Domain\Model\Shared\Server;

class RefreshAccountWorkflow extends BaseWorkflow
{
    const TYPE = "leader.refresh_account";

    /**
     * @var LeaderAccountRepository
     */
    private $leadAccRepo;

    private $pluginManager = null;

    /**
     * RefreshAccountWorkflow constructor.
     * Internal service workflow type
     *
     * @param LeaderAccountRepository $leadAccRepo
     * @param PluginGatewayManager $pluginManager
     */
    public function __construct(
        LeaderAccountRepository $leadAccRepo,
        PluginGatewayManager $pluginManager
    ) {
        $this->leadAccRepo   = $leadAccRepo;
        $this->pluginManager = $pluginManager;

        parent::__construct(
            $this->activities([
                'tellPluginToRefreshAccount',
            ])
        );
    }

    public function tellPluginToRefreshAccount(Activity $activity): void
    {
        $leadAcc = $this->leadAccRepo->getLightAccountOrFail($this->getAccountNumber());
        $pluginGateway = $this->pluginManager->getForAccount($leadAcc);

        $actCtx = $activity->getContext();
        if (!$actCtx->has("msgId")) {
            $msgId = $pluginGateway->sendMessage(
                $this->getAccountNumber(),
                $this->id(),
                PluginGateway::LEADER_REFRESH,
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
