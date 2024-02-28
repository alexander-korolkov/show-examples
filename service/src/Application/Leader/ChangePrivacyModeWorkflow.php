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
use Fxtm\CopyTrading\Domain\Common\DateTime;
use Fxtm\CopyTrading\Domain\Model\Follower\FollowerAccountRepository;
use Fxtm\CopyTrading\Domain\Model\Leader\HiddenReason;
use Fxtm\CopyTrading\Domain\Model\Leader\LeaderAccountRepository;
use Fxtm\CopyTrading\Domain\Model\Leader\PrivacyMode;
use Fxtm\CopyTrading\Domain\Model\Shared\AccountNumber;

class ChangePrivacyModeWorkflow extends BaseParentalWorkflow
{
    const TYPE = "leader.change_privacy_mode";

    /**
     * @var LeaderAccountRepository
     */
    private $leadAccRepo;

    /**
     * @var FollowerAccountRepository
     */
    private $follAccRepo;

    /**
     * @var TradeAccountGateway
     */
    private $tradeAccountGateway;

    /**
     * @var ClientGateway
     */
    private $clientGateway;

    /**
     * ChangePrivacyModeWorkflow constructor.
     * @param LeaderAccountRepository $leadAccRepo
     * @param FollowerAccountRepository $follAccRepo
     * @param WorkflowManager $workflowManager
     * @param WorkflowRepository $workflowRepository
     * @param TradeAccountGateway $tradeAccountGateway
     * @param ClientGateway $clientGateway
     */
    public function __construct(
        LeaderAccountRepository $leadAccRepo,
        FollowerAccountRepository $follAccRepo,
        WorkflowManager $workflowManager,
        WorkflowRepository $workflowRepository,
        TradeAccountGateway $tradeAccountGateway,
        ClientGateway $clientGateway
    ) {
        $this->leadAccRepo          = $leadAccRepo;
        $this->follAccRepo          = $follAccRepo;
        $this->workflowManager      = $workflowManager;
        $this->workflowRepository   = $workflowRepository;
        $this->tradeAccountGateway  = $tradeAccountGateway;
        $this->clientGateway        = $clientGateway;

        parent::__construct(
            $this->activities([
                'changePrivacyMode'
            ])
        );
    }

    /**
     * @return int
     */
    protected function doProceed()
    {
        $leadAcc = $this->leadAccRepo->getLightAccount($this->getAccountNumber());

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

        if ($this->getPrivacyMode() != PrivacyMode::OFF &&
            $leadAcc->getHiddenReason() &&
            $leadAcc->getHiddenReason() != HiddenReason::BY_CLIENT
        ) {
            $this->getContext()->set('message', 'Rejected because the client is not allowed to change his privacy mode.');
            return WorkflowState::REJECTED;
        }

        return parent::doProceed();
    }

    protected function changePrivacyMode(Activity $activity): void
    {
        $leadAcc = $this->leadAccRepo->getLightAccountOrFail($this->getAccountNumber());
        $oldPrivacyMode = $leadAcc->getPrivacyMode();
        $newPrivacyMode = $this->getPrivacyMode();

        if ($oldPrivacyMode == $newPrivacyMode && $oldPrivacyMode != PrivacyMode::OFF) {
            $activity->skip();
            return;
        }

        $ctx = $this->getContext();
        $ctx->set("oldValue", PrivacyMode::toString($oldPrivacyMode));
        $ctx->set("newValue", PrivacyMode::toString($newPrivacyMode));

        if ($newPrivacyMode == PrivacyMode::PUBLIK) {
            $leadAcc->makePublic();
            $leadAcc->setHiddenReason(null);
            $leadAcc->acceptFollowers();
        }
        else {
            $leadAcc->makePrivate();
            if (!$leadAcc->getHiddenReason()) {
                $leadAcc->setHiddenReason(HiddenReason::BY_CLIENT);
            }
            if ($newPrivacyMode == PrivacyMode::PRYVATE) {
                $leadAcc->acceptFollowers();
            }
            else {
                $leadAcc->rejectFollowers();
                if ($this->needsCloseFollowerAccounts() && !empty($this->follAccRepo->findOpenByLeaderAccountNumber($this->getAccountNumber()))) {
                    $workflow = $this->createDetached(
                        CloseFollowerAccountsWorkflow::TYPE,
                        new ContextData([
                            "accNo" => $this->getAccountNumber()->value(),
                            'broker' => $this->getBroker(),
                            'parentId' => $this->id()
                        ]),
                        DateTime::of("+1 second")
                    );
                    if(!$this->workflowManager->enqueueWorkflow($workflow)) {
                        $this->logDebug($activity, __FUNCTION__, "Workflow manager unable to create child workflow; see previous errors");
                        $activity->fail();
                        return;
                    }
                }
            }
        }
        try {
            $this->leadAccRepo->store($leadAcc);
        }
        catch (\Exception $e) {
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

    private function getPrivacyMode()
    {
        return $this->getContext()->get("privacyMode");
    }

    private function needsCloseFollowerAccounts()
    {
        return $this->getContext()->getIfHas("closeFollowerAccounts");
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
