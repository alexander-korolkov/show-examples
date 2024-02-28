<?php

namespace Fxtm\CopyTrading\Application\Leader;

use Fxtm\CopyTrading\Application\ClientGateway;
use Fxtm\CopyTrading\Application\Common\BaseWorkflow;
use Fxtm\CopyTrading\Application\Common\Workflow\Activity;
use Fxtm\CopyTrading\Application\Common\Workflow\WorkflowState;
use Fxtm\CopyTrading\Application\TradeAccountGateway;
use Fxtm\CopyTrading\Domain\Model\Client\ClientId;
use Fxtm\CopyTrading\Domain\Model\Leader\LeaderAccount;
use Fxtm\CopyTrading\Domain\Model\Leader\LeaderAccountRepository;
use Fxtm\CopyTrading\Domain\Model\LeaderProfile\LeaderProfile;
use Fxtm\CopyTrading\Domain\Model\LeaderProfile\LeaderProfileRepository;
use Fxtm\CopyTrading\Domain\Model\Shared\AccountNumber;
use Fxtm\CopyTrading\Domain\Model\Shared\Currency;

class RegisterNewAccountWorkflow extends BaseWorkflow
{
    const TYPE = "leader.register_new_account";

    private $tradeAccGateway = null;

    /**
     * @var LeaderAccountRepository
     */
    private $leadAccRepo;

    /**
     * @var AccountNameService
     */
    private $accNameSvc      = null;

    /**
     * @var LeaderProfileRepository
     */
    private $leadProfRepo    = null;

    /**
     * @var ClientGateway
     */
    private $clientGateway;

    /**
     * RegisterNewAccountWorkflow constructor.
     * @param TradeAccountGateway $tradeAccGateway
     * @param LeaderAccountRepository $leadAccRepo
     * @param AccountNameService $accNameSvc
     * @param LeaderProfileRepository $leadProfRepo
     * @param ClientGateway $clientGateway
     */
    public function __construct(
        TradeAccountGateway $tradeAccGateway,
        LeaderAccountRepository $leadAccRepo,
        AccountNameService $accNameSvc,
        LeaderProfileRepository $leadProfRepo,
        ClientGateway $clientGateway
    ) {
        $this->tradeAccGateway = $tradeAccGateway;
        $this->leadAccRepo     = $leadAccRepo;
        $this->accNameSvc      = $accNameSvc;
        $this->leadProfRepo    = $leadProfRepo;
        $this->clientGateway   = $clientGateway;

        parent::__construct(
            $this->activities([
                "registerAccount",
                "registerProfile",
            ])
        );
    }

    protected function doProceed()
    {
        $tradeAcc = $this->tradeAccGateway->fetchAccountByNumber($this->getAccountNumber(), $this->getBroker());
        $this->setServer($tradeAcc->server());
        $this->setAccountType($tradeAcc->accountTypeId());
        $this->setClientId($tradeAcc->ownerId());
        $this->setAccountCurrency($tradeAcc->currency());

        $client = $this->clientGateway->fetchClientByClientId($tradeAcc->ownerId(), $this->getBroker());
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
        $leadAcc = new LeaderAccount(
            $this->getAccountNumber(),
            $this->getBroker(),
            $this->getAccountType(),
            $this->getServer(),
            $this->getAccountCurrency(),
            $this->getClientId(),
            $this->getAccountName() ?: $this->accNameSvc->generateUniqueNameForClient($this->getClientId(), $this->getBroker()),
            $this,
            $this->getRemunerationFee() ?: 30
        );
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
        $this->leadProfRepo->store($profile);

        $activity->succeed();
    }

    public function getResult()
    {
        $acc = $this->leadAccRepo->getLightAccountOrFail($this->getAccountNumber());
        return $acc->number();
    }

    private function getAccountNumber()
    {
        return new AccountNumber($this->getContext()->get("accNo"));
    }

    private function setServer($server)
    {
        $this->getContext()->set("server", $server);
    }

    private function getServer()
    {
        return $this->getContext()->get("server");
    }

    private function setAccountType($type)
    {
        $this->getContext()->set('account_type', $type);
    }

    private function getAccountType()
    {
        return $this->getContext()->get('account_type');
    }

    private function setClientId(ClientId $clientId)
    {
        $this->getContext()->set("clientId", $clientId->value());
    }

    private function getClientId()
    {
        return new ClientId($this->getContext()->get("clientId"));
    }

    private function getBroker()
    {
        return $this->getContext()->get('broker');
    }

    private function setAccountCurrency(Currency $accCurr)
    {
        $this->getContext()->set("accCurr", $accCurr->code());
    }

    private function getAccountCurrency()
    {
        return Currency::forCode($this->getContext()->get("accCurr"));
    }

    private function getAccountName()
    {
        return $this->getContext()->getIfHas("accName");
    }

    private function getRemunerationFee()
    {
        return $this->getContext()->getIfHas("remunFee");
    }

    public function getAccountRepository()
    {
        return $this->leadAccRepo;
    }
}
