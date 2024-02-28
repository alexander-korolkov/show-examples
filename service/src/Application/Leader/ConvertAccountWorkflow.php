<?php

namespace Fxtm\CopyTrading\Application\Leader;

use Fxtm\CopyTrading\Application\ClientGateway;
use Fxtm\CopyTrading\Application\Common\BaseWorkflow;
use Fxtm\CopyTrading\Application\Common\Workflow\Activity;
use Fxtm\CopyTrading\Application\Common\Workflow\WorkflowState;
use Fxtm\CopyTrading\Application\EquityService;
use Fxtm\CopyTrading\Application\StatisticsService;
use Fxtm\CopyTrading\Application\TradeAccountGateway;
use Fxtm\CopyTrading\Domain\Common\DateTime;
use Fxtm\CopyTrading\Domain\Model\Client\ClientId;
use Fxtm\CopyTrading\Domain\Model\Leader\HiddenReason;
use Fxtm\CopyTrading\Domain\Model\Leader\LeaderAccount;
use Fxtm\CopyTrading\Domain\Model\Leader\LeaderAccountRepository;
use Fxtm\CopyTrading\Domain\Model\LeaderProfile\LeaderProfile;
use Fxtm\CopyTrading\Domain\Model\LeaderProfile\LeaderProfileRepository;
use Fxtm\CopyTrading\Domain\Model\Shared\AccountNumber;
use Fxtm\CopyTrading\Domain\Model\Shared\Currency;

class ConvertAccountWorkflow extends BaseWorkflow
{
    const TYPE = "leader.convert_account";

    private $tradeAccGateway = null;

    /**
     * @var LeaderAccountRepository
     */
    private $leadAccRepo;

    /**
     * @var EquityService
     */
    private $equityService;

    private $accNameSvc      = null;
    private $statsSvc        = null;
    private $leadProfRepo    = null;

    /**
     * @var ClientGateway
     */
    private $clientGateway;

    /**
     * ConvertAccountWorkflow constructor.
     * @param TradeAccountGateway $tradeAccGateway
     * @param LeaderAccountRepository $leadAccRepo
     * @param AccountNameService $accNameSvc
     * @param StatisticsService $statsSvc
     * @param LeaderProfileRepository $leadProfRepo
     * @param EquityService $equityService
     * @param ClientGateway $clientGateway
     */
    public function __construct(
        TradeAccountGateway $tradeAccGateway,
        LeaderAccountRepository $leadAccRepo,
        AccountNameService $accNameSvc,
        StatisticsService $statsSvc,
        LeaderProfileRepository $leadProfRepo,
        EquityService  $equityService,
        ClientGateway $clientGateway
    ) {
        $this->tradeAccGateway = $tradeAccGateway;
        $this->leadAccRepo     = $leadAccRepo;
        $this->accNameSvc      = $accNameSvc;
        $this->statsSvc        = $statsSvc;
        $this->leadProfRepo    = $leadProfRepo;
        $this->equityService   = $equityService;
        $this->clientGateway   = $clientGateway;

        parent::__construct(
            $this->activities([
                "registerAccount",
                "registerProfile",
                "importTradeHistory",
                "activateAccount",
            ])
        );
    }

    protected function doProceed()
    {
        if ($this->leadAccRepo->getLightAccount($this->getAccountNumber())) {
            return WorkflowState::REJECTED;
        }

        $client = $this->clientGateway->fetchClientByClientId($this->getClientId(), $this->getBroker());
        if($client == null) {
            $this->getLogger()->error("Client not found for id: {$this->getClientId()}");
            return WorkflowState::FAILED;
        }
        if ($client->getCompany()->isEu()) {
            $this->getLogger()->error(
                "Opening leaders accounts is forbidden for EU clients: Client ID: {$this->getClientId()}"
            );
            return WorkflowState::FAILED;
        }

        return parent::doProceed();
    }

    protected function registerAccount(Activity $activity): void
    {
        $tradeAcc = $this->tradeAccGateway->fetchAccountByNumberWithFreshEquity($this->getAccountNumber(), $this->getBroker());

        $leadAcc = new LeaderAccount(
            $this->getAccountNumber(),
            $this->getBroker(),
            $tradeAcc->accountTypeId(),
            $tradeAcc->server(),
            $this->getAccountCurrency(),
            $this->getClientId(),
            $this->accNameSvc->generateUniqueNameFromEmail($this->getEmail()),
            $this,
            30,
            true
        );

        $leadAcc->depositFunds($tradeAcc->equity(), $this);
        if($leadAcc->isActivated()) {
            //If account just been activated, store current equity as initial deposit
            $this->equityService->saveTransactionEquityChange(
                $this->getAccountNumber(),
                $leadAcc->equity(),
                $leadAcc->equity(),
                null,
                DateTime::NOW()
            );
        }

        // switch off newly converted accounts
        $leadAcc->makePrivate();
        $leadAcc->setHiddenReason(HiddenReason::BY_CLIENT);
        $leadAcc->rejectFollowers();

        try {
            $this->leadAccRepo->store($leadAcc);
        } catch (\Exception $e) {
            $this->logException($e);
            $activity->fail();
            return;
        }
        $activity->succeed();
    }

    protected function registerProfile(Activity $activity): void
    {
        $leaderId = $this->getClientId();
        if (!empty($this->leadProfRepo->find($leaderId))) {
            $activity->skip();
            return;
        }
        $profile = new LeaderProfile($leaderId);
        $profile->showCountry();

        try {
            $this->leadProfRepo->store($profile);
        } catch (\Exception $e) {
            $this->logException($e);
            $activity->fail();
            return;
        }

        $activity->succeed();
    }

    protected function importTradeHistory(Activity $activity): void
    {
        $this->statsSvc->importEquityStatistics($this->leadAccRepo->getLightAccount($this->getAccountNumber()));
        $activity->succeed();
    }

    protected function activateAccount(Activity $activity): void
    {
        $leadAcc = $this->leadAccRepo->findOrFail($this->getAccountNumber());
        $leadAcc->activate($this);

        if (!empty($dt = $this->statsSvc->getFirstDepositDatetime($leadAcc->number()))) {
            $leadAcc->setActivationDatetime($dt);
        }

        try {
            $this->leadAccRepo->store($leadAcc);
        } catch (\Exception $e) {
            $this->logException($e);
            $activity->fail();
            return;
        }

        $activity->succeed();
    }

    public function getResult()
    {
        return $this->leadAccRepo->findOrFail($this->getAccountNumber());
    }

    private function getAccountNumber()
    {
        return new AccountNumber($this->getContext()->get("accNo"));
    }

    private function getClientId()
    {
        return new ClientId($this->getContext()->get("clientId"));
    }

    private function getAccountCurrency()
    {
        return Currency::forCode($this->getContext()->get("accCurr"));
    }

    private function getEmail()
    {
        return $this->getContext()->get("email");
    }

    private function getCompany()
    {
        return $this->getContext()->get("company");
    }

    public function getAccountRepository()
    {
        return $this->leadAccRepo;
    }

    private function getBroker()
    {
        return $this->getContext()->get('broker');
    }
}
