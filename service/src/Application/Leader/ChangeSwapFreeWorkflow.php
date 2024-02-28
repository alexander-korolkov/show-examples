<?php

namespace Fxtm\CopyTrading\Application\Leader;

use Fxtm\CopyTrading\Application\ClientGateway;
use Fxtm\CopyTrading\Application\Common\BaseParentalWorkflow;
use Fxtm\CopyTrading\Application\Common\Workflow\Activity;
use Fxtm\CopyTrading\Application\Common\Workflow\ContextData;
use Fxtm\CopyTrading\Application\Common\Workflow\WorkflowManager;
use Fxtm\CopyTrading\Application\Common\Workflow\WorkflowRepository;
use Fxtm\CopyTrading\Application\Common\Workflow\WorkflowState;
use Fxtm\CopyTrading\Application\TradeAccountGateway;
use Fxtm\CopyTrading\Domain\Model\Follower\FollowerAccount;
use Fxtm\CopyTrading\Domain\Model\Follower\FollowerAccountRepository;
use Fxtm\CopyTrading\Domain\Model\Leader\LeaderAccountRepository;
use Fxtm\CopyTrading\Domain\Model\Shared\AccountNumber;

class ChangeSwapFreeWorkflow extends BaseParentalWorkflow
{
    const TYPE = 'leader.change_swap_free';

    /**
     * @var FollowerAccountRepository
     */
    private $followerRepository;

    /**
     * @var LeaderAccountRepository
     */
    private $leaderRepository;

    /**
     * @var TradeAccountGateway
     */
    private $tradeAccountGateway;

    /**
     * @var ClientGateway
     */
    private $clientGateway;

    /**
     * ChangeSwapFreeWorkflow constructor.
     *
     * @param FollowerAccountRepository $followerRepository
     * @param LeaderAccountRepository $leaderRepository
     * @param TradeAccountGateway $tradeAccountGateway
     * @param ClientGateway $clientGateway
     * @param WorkflowManager $workflowManager
     * @param WorkflowRepository $workflowRepository
     */
    public function __construct(
        FollowerAccountRepository $followerRepository,
        LeaderAccountRepository $leaderRepository,
        TradeAccountGateway $tradeAccountGateway,
        ClientGateway $clientGateway,
        WorkflowManager $workflowManager,
        WorkflowRepository $workflowRepository
    ) {
        $this->followerRepository = $followerRepository;
        $this->leaderRepository = $leaderRepository;
        $this->tradeAccountGateway = $tradeAccountGateway;
        $this->clientGateway = $clientGateway;
        $this->workflowManager = $workflowManager;
        $this->workflowRepository = $workflowRepository;

        parent::__construct(
            $this->activities([
                "changeSwapFree",
                "changeAggregateAccount",
                "changeFollowers",
            ])
        );
    }

    /**
     * @return int
     */
    protected function doProceed()
    {
        $leadAcc = $this->leaderRepository->find($this->getAccountNumber());

        if (empty($leadAcc)) {
            $tradeAcc = $this->tradeAccountGateway->fetchAccountByNumber($this->getAccountNumber(), $this->getBroker());
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

            $workflow = $status->getChild();
            if(!$workflow->isCompleted()) {
                return WorkflowState::REJECTED;
            }

            $leadAcc = $workflow->getResult();
        }

        if ($leadAcc->hasOpenPositions()) {
            $this->getContext()->set('message', 'Rejected because the leader has open trade positions.');
            return WorkflowState::REJECTED;
        }

        if ($leadAcc->isSwapFree() == $this->getSwapFree()) {
            $this->getContext()->set('message', 'Rejected because the leader\'s swap_free already in requested state.');
            return WorkflowState::REJECTED;
        }

        return parent::doProceed();
    }

    /**
     * @param Activity $activity
     */
    protected function changeSwapFree(Activity $activity): void
    {
        $result = $this
            ->tradeAccountGateway
            ->changeAccountSwapFree($this->getAccountNumber(), $this->getBroker(), $this->getSwapFree());

        if ($result) {
            $activity->succeed();
        }
        else {
            $activity->fail();
        }
    }

    /**
     * @param Activity $activity
     */
    protected function changeAggregateAccount(Activity $activity): void
    {
        $leadAcc = $this
            ->leaderRepository
            ->getLightAccount($this->getAccountNumber());

        $aggAccNo = $leadAcc->aggregateAccountNumber();

        if(empty($aggAccNo)) {
            $activity->skip();
            return;
        }

        $result = $this
            ->tradeAccountGateway
            ->changeAccountSwapFree($aggAccNo, $this->getBroker(), $this->getSwapFree());

        if ($result) {
            $activity->succeed();
        }
        else {
            $activity->fail();
        }

    }

    /**
     * @param Activity $activity
     */
    protected function changeFollowers(Activity $activity): void
    {
        /** @var FollowerAccount[] $followers */
        $followers = $this->followerRepository->findOpenByLeaderAccountNumber($this->getAccountNumber());

        foreach ($followers as $follower) {
            try {
                $this->tradeAccountGateway->changeAccountSwapFree(
                    $follower->number(),
                    $follower->broker(),
                    $this->getSwapFree()
                );
            } catch (\Exception $e) {
                $this->getLogger()->error(sprintf(
                    'Change swap free for follower %s error: %s. Stack trace: %s',
                    $follower->number(),
                    $e->getMessage(),
                    $e->getTraceAsString()
                ));
            }
        }

        $activity->succeed();
    }

    /**
     * @return FollowerAccountRepository
     * @return LeaderAccountRepository
     */
    public function getAccountRepository()
    {
        return $this->followerRepository;
    }

    private function getAccountNumber()
    {
        return new AccountNumber($this->getContext()->get("accNo"));
    }

    private function getBroker()
    {
        return $this->getContext()->get('broker');
    }

    private function getSwapFree()
    {
        return $this->getContext()->get('is_swap_free');
    }
}
