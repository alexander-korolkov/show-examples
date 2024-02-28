<?php

namespace Fxtm\CopyTrading\Application\Leader;

use Fxtm\CopyTrading\Application\Common\BaseWorkflow;
use Fxtm\CopyTrading\Application\Common\Workflow\Activity;
use Fxtm\CopyTrading\Application\Common\Workflow\ContextData;
use Fxtm\CopyTrading\Application\TradeAccountGateway;
use Fxtm\CopyTrading\Domain\Model\Client\ClientId;
use Fxtm\CopyTrading\Domain\Model\Leader\LeaderAccount;
use Fxtm\CopyTrading\Domain\Model\Leader\LeaderAccountRepository;
use Fxtm\CopyTrading\Domain\Model\LeaderProfile\LeaderProfile;
use Fxtm\CopyTrading\Domain\Model\LeaderProfile\LeaderProfileRepository;
use Fxtm\CopyTrading\Domain\Model\Shared\AccountNumber;
use Fxtm\CopyTrading\Domain\Model\Shared\Currency;
use Fxtm\CopyTrading\Interfaces\Statistics\StatisticsFromPammServiceImpl;

class ConvertFromPammAccountWorkflow extends BaseWorkflow
{
    const TYPE = "leader.convert_from_pamm";

    private $tradeAccGateway = null;
    private $leadAccRepo = null;
    private $accNameSvc = null;
    private $leadProfRepo = null;
    private $statFromPammSvc;

    public function __construct(
        ContextData $context,
        TradeAccountGateway $tradeAccGateway,
        LeaderAccountRepository $leadAccRepo,
        AccountNameService $accNameSvc,
        LeaderProfileRepository $leadProfRepo,
        StatisticsFromPammServiceImpl $statFromPammSvc
    )
    {
        $this->tradeAccGateway = $tradeAccGateway;
        $this->leadAccRepo = $leadAccRepo;
        $this->accNameSvc = $accNameSvc;
        $this->leadProfRepo = $leadProfRepo;
        $this->statFromPammSvc = $statFromPammSvc;

        parent::__construct(
            $this->activities([
                "registerAccount",
                "registerProfile",
                "importTradeHistory",
                "updateActivationDate"
            ]),
            $context
        );
    }

    protected function doProceed()
    {
        $tradeAcc = $this->tradeAccGateway->fetchAccountByNumber($this->getAccountNumber(), $this->getBroker());
        $this->setServer($tradeAcc->server());
        $this->setAccountType($tradeAcc->accountTypeId());
        $this->setClientId($tradeAcc->ownerId());
        $this->setAccountCurrency($tradeAcc->currency());

        return parent::doProceed();
    }

    protected function registerAccount(Activity $activity)
    {
        $leadAcc = new LeaderAccount(
            $this->getAccountNumber(),
            $this->getBroker(),
            $this->getAccountType(),
            $this->getServer(),
            $this->getAccountCurrency(),
            $this->getClientId(),
            preg_replace('#\s+#', '_', $this->getAccountName()) ?: $this->accNameSvc->generateUniqueNameForClient(
                $this->getClientId(),
                $this->getBroker()
            ),
            $this,
            $this->getRemunerationFee() ?: 30
        );
        $leadAcc->activate($this);
        $leadAcc->makePublic();
        $leadAcc->setHiddenReason(null);
        $arrData = $leadAcc->toArray();
        $arrData['prepare_stats'] = 1;
        $leadAcc->fromArray($arrData);

        try {
            $this->leadAccRepo->store($leadAcc);
        } catch (\Exception $e) {
            $this->logException($e);
            $activity->fail();
            return;
        }
        $activity->succeed();
    }

    protected function registerProfile(Activity $activity)
    {
        $leaderId = $this->getClientId();
        if (!empty($this->leadProfRepo->find($leaderId))) {
            return $activity->skip();
        }
        $profile = new LeaderProfile($leaderId);
        $profile->showCountry();
        $this->leadProfRepo->store($profile);
        return $activity->succeed();
    }

    protected function importTradeHistory(Activity $activity)
    {
        $activationDate = $this->statFromPammSvc->importEquityStatistics($this->leadAccRepo->getLightAccount($this->getAccountNumber()));
        $this->getContext()->set('activationDate', $activationDate);
        return $activity->succeed();
    }

    protected function updateActivationDate(Activity $activity)
    {
        $leadAcc = $this->leadAccRepo->getLightAccount($this->getAccountNumber());

        $leadAcc->setActivationDatetime($this->getContext()->get('activationDate'));
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
