<?php

namespace Fxtm\CopyTrading\Application\Leader;

use Fxtm\CopyTrading\Application\Common\BaseWorkflow;
use Fxtm\CopyTrading\Application\Common\Workflow\Activity;
use Fxtm\CopyTrading\Application\Common\Workflow\ContextData;
use Fxtm\CopyTrading\Application\Common\Workflow\WorkflowManager;
use Fxtm\CopyTrading\Application\Common\Workflow\WorkflowState;
use Fxtm\CopyTrading\Application\TradeAccountGateway;
use Fxtm\CopyTrading\Domain\Model\Leader\LeaderAccountRepository;
use Fxtm\CopyTrading\Domain\Model\Shared\AccountNotRegistered;
use Fxtm\CopyTrading\Domain\Model\Shared\AccountNumber;

class ChangeShowEquityWorkflow extends BaseWorkflow
{
    public const TYPE = 'leader.change_show_equity';

    /**
     * @var LeaderAccountRepository
     */
    private $leaderRepository;

    /**
     * @var TradeAccountGateway
     */
    private $tradeAccountGateway;

    /**
     * @var WorkflowManager
     */
    private $workflowManager;

    public function __construct(
        LeaderAccountRepository $leaderRepository,
        TradeAccountGateway $tradeAccountGateway,
        WorkflowManager $workflowManager
    ) {
        $this->leaderRepository = $leaderRepository;
        $this->tradeAccountGateway = $tradeAccountGateway;
        $this->workflowManager = $workflowManager;

        parent::__construct(
            $this->activities([
                "changeShowEquity",
            ])
        );
    }

    /**
     * @return int
     */
    protected function doProceed()
    {
        try {
            $leadAcc = $this->leaderRepository->getLightAccountOrFail($this->getAccountNumber());
        } catch (AccountNotRegistered $exception) {
            return WorkflowState::FAILED;
        }

        if ($leadAcc->getShowEquity() == $this->getShowEquity()) {
            $this->getContext()->override(
                'message',
                'Rejected because the leader\'s show_equity is already in requested state.'
            );
            return WorkflowState::REJECTED;
        }

        return parent::doProceed();
    }

    /**
     * @param Activity $activity
     */
    protected function changeShowEquity(Activity $activity)
    {
        try {
            $leadAcc = $this->leaderRepository->getLightAccountOrFail($this->getAccountNumber());
            $leadAcc->changeShowEquity($this->getShowEquity(), $this);

            $this->leaderRepository->store($leadAcc);
        } catch (\Throwable $e) {
            $this->logException($e);
            $activity->fail();
            return;
        }

        $activity->succeed();
    }

    private function getAccountNumber()
    {
        return new AccountNumber($this->getContext()->get(ContextData::KEY_ACC_NO));
    }

    private function getBroker()
    {
        return $this->getContext()->get(ContextData::KEY_BROKER);
    }

    private function getShowEquity()
    {
        return $this->getContext()->get('show_equity');
    }

    public function getAccountRepository()
    {
        return $this->leaderRepository;
    }
}
