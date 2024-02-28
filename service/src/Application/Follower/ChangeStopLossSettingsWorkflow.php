<?php

namespace Fxtm\CopyTrading\Application\Follower;

use Fxtm\CopyTrading\Application\Common\BaseWorkflow;
use Fxtm\CopyTrading\Application\Common\Workflow\Activity;
use Fxtm\CopyTrading\Application\PluginGateway;
use Fxtm\CopyTrading\Application\PluginGatewayManager;
use Fxtm\CopyTrading\Domain\Model\Follower\FollowerAccountRepository;
use Fxtm\CopyTrading\Domain\Model\Shared\AccountNumber;
use Fxtm\CopyTrading\Domain\Model\Shared\Money;

class ChangeStopLossSettingsWorkflow extends BaseWorkflow
{
    const TYPE = "follower.change_stoploss_settings";

    /**
     * @var FollowerAccountRepository
     */
    private $follAccRepo;

    private $pluginManager = null;

    /**
     * ChangeStopLossSettingsWorkflow constructor.
     * @param FollowerAccountRepository $follAccRepo
     * @param PluginGatewayManager $pluginManager
     */
    public function __construct(
        FollowerAccountRepository $follAccRepo,
        PluginGatewayManager $pluginManager
    ) {
        $this->follAccRepo = $follAccRepo;
        $this->pluginManager = $pluginManager;

        parent::__construct(
            $this->activities([
                "calculateNewStopLossEquity",
                "tellPluginToUpdateStoploss",
                "updateDatabase",
            ])
        );
    }

    protected function calculateNewStopLossEquity(Activity $activity): void
    {
        $follAcc = $this->follAccRepo->findOrFail($this->getAccountNumber());

        $this->setStopLossEquity($follAcc->calculateStopLossEquity($this->getStopLossLevel()));

        // don't save the changes yet
        $activity->succeed();
    }

    protected function tellPluginToUpdateStoploss(Activity $activity): void
    {
        $follAcc = $this->follAccRepo->getLightAccountOrFail($this->getAccountNumber());
        if (!$follAcc->isActivated()) {
            $activity->skip();
            return;
        }

        $pluginGateway = $this->pluginManager->getForAccount($follAcc);

        $actCtx = $activity->getContext();
        if (!$actCtx->has("msgId")) {
            $msgId = $pluginGateway->sendMessage(
                $this->getAccountNumber(),
                $this->id(),
                PluginGateway::FOLLOWER_STOPLOSS,
                sprintf("%.2f;%d", $this->getContext()->get("stopLossEquityNew"), $this->getStopCopyingOnStopLossLevel())
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

    protected function updateDatabase(Activity $activity): void
    {
        $follAcc = $this->follAccRepo->getLightAccountOrFail($this->getAccountNumber());
        if (!$follAcc->isActivated()) {
            $activity->skip();
            return;
        }

        $newEquity = new Money($this->getStopLossEquity(), $follAcc->currency());
        $newLevel = $this->getStopLossLevel();

        $follAcc->changeStopLossLevel($newLevel, $newEquity, $this);
        $follAcc->stopCopyingOnStopLoss($this->getStopCopyingOnStopLossLevel());
        try {
            $this->follAccRepo->store($follAcc);
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

    private function getStopLossLevel()
    {
        return $this->getContext()->get("stopLossLevel");
    }

    private function getStopLossEquity()
    {
        return floatval($this->getContext()->get("stopLossEquityNew"));
    }

    private function setStopLossEquity(Money $equity)
    {
        $this->getContext()->set("stopLossEquityNew", $equity->amount());
    }

    private function getStopCopyingOnStopLossLevel()
    {
        return intval($this->getContext()->get("stopCopyingOnStopLoss"));
    }

    public function getAccountRepository()
    {
        return $this->follAccRepo;
    }
}
