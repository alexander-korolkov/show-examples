<?php

namespace Fxtm\CopyTrading\Application\Follower;

use Fxtm\CopyTrading\Application\ClientGateway;
use Fxtm\CopyTrading\Application\Common\BaseWorkflow;
use Fxtm\CopyTrading\Application\Common\Workflow\Activity;
use Fxtm\CopyTrading\Application\Common\Workflow\WorkflowState;
use Fxtm\CopyTrading\Application\LeverageService;
use Fxtm\CopyTrading\Application\NotificationGateway;
use Fxtm\CopyTrading\Application\TradeAccountGateway;
use Fxtm\CopyTrading\Domain\Model\Account\AccountType;
use Fxtm\CopyTrading\Domain\Model\Client\ClientId;
use Fxtm\CopyTrading\Domain\Model\Follower\FollowerAccount;
use Fxtm\CopyTrading\Domain\Model\Follower\FollowerAccountRepository;
use Fxtm\CopyTrading\Domain\Model\Leader\LeaderAccountRepository;
use Fxtm\CopyTrading\Domain\Model\Shared\AccountNumber;
use Fxtm\CopyTrading\Domain\Model\Shared\Server;
use Fxtm\CopyTrading\Interfaces\Gateway\Account\ServerAccountTypes;

class OpenAccountWorkflow extends BaseWorkflow
{
    const TYPE = "follower.open_account";

    private $tradeAccGateway = null;

    /**
     * @var LeaderAccountRepository
     */
    private $leadAccRepo;

    /**
     * @var FollowerAccountRepository
     */
    private $follAccRepo;
    /**
     * @var ClientGateway
     */
    private $clientGateway;

    private $notifGateway = null;
    private $leverageSvc = null;

    /**
     * OpenAccountWorkflow constructor.
     * @param TradeAccountGateway $tradeAccGateway
     * @param LeaderAccountRepository $leadAccRepo
     * @param FollowerAccountRepository $follAccRepo
     * @param NotificationGateway $notifGateway
     * @param LeverageService $leverageSvc
     * @param ClientGateway $clientGateway
     */
    public function __construct(
        TradeAccountGateway $tradeAccGateway,
        LeaderAccountRepository $leadAccRepo,
        FollowerAccountRepository $follAccRepo,
        NotificationGateway $notifGateway,
        LeverageService $leverageSvc,
        ClientGateway $clientGateway
    ) {
        $this->tradeAccGateway = $tradeAccGateway;
        $this->leadAccRepo     = $leadAccRepo;
        $this->follAccRepo     = $follAccRepo;
        $this->notifGateway    = $notifGateway;
        $this->leverageSvc     = $leverageSvc;
        $this->clientGateway   = $clientGateway;

        parent::__construct(
            $this->activities([
                "createFollowerAccount",
                "registerAccount",
                "notifyClient",
            ])
        );
    }

    protected function doProceed()
    {
        $client = $this->clientGateway->fetchClientByClientId($this->getClientId(), $this->getBroker());
        if($client == null) {
            $this->getLogger()->error("Client not found for id: {$this->getClientId()}");
            return WorkflowState::FAILED;
        }
        if ($client->getCompany()->isEu()) {
            $this->getLogger()->error(
                "Opening follower accounts is forbidden for EU clients: Client ID: {$this->getClientId()}"
            );
            return WorkflowState::FAILED;
        }
        $leadAcc = $this->leadAccRepo->getLightAccountOrFail($this->getLeaderAccountNumber());
        if (!$this->leverageSvc->isValidFollowerLeverage(
            $leadAcc->number(),
            $leadAcc->broker(),
            $this->getClientId(),
            $this->getBroker()
        )) {
            return WorkflowState::REJECTED;
        }
        return parent::doProceed();
    }

    protected function createFollowerAccount(Activity $activity): void
    {
        $leadAcc = $this->leadAccRepo->getLightAccountOrFail($this->getLeaderAccountNumber());
        $tradeAcc = $this->tradeAccGateway->createFollowerAccount($this->getClientId(), $this->getBroker(), $leadAcc->broker(), $leadAcc);
        $this->setAccountNumber($tradeAcc->number());
        $activity->succeed();
    }

    protected function registerAccount(Activity $activity): void
    {
        $leadAcc = $this->leadAccRepo->getLightAccountOrFail($this->getLeaderAccountNumber());
        $follAcc = new FollowerAccount(
            $this->getAccountNumber(),
            $this->getBroker(),
            Server::byAccountType(AccountType::GetFollowerTypeByLeaderType($leadAcc->accountType(), $this->getBroker())),
            $this->getClientId(),
            $leadAcc,
            $this,
            $this->getCopyCoefficient(),
            $this->getStopLossLevel()
        );

        $upperLimit = $this->leverageSvc->getUpperCopyCoefficientLimit(
            $leadAcc->number(),
            $leadAcc->broker(),
            $this->getClientId(),
            $this->getBroker()
        );
        if ($upperLimit == FollowerAccount::SAFE_MODE_COPY_COEFFICIENT && $this->getCopyCoefficient() <= FollowerAccount::SAFE_MODE_COPY_COEFFICIENT) {
            $follAcc->lockCopyCoefficient();
        }

        try {
            $this->follAccRepo->store($follAcc);
        } catch (\Exception $e) {
            $this->logException($e);
            $activity->fail();
            return;
        }

        $this->setLeaderAccountName($leadAcc->name()); // for "notifyClient"
        $this->setLeaderIsPublic($leadAcc->isPublic()); // for "notifyClient"
        $this->setPayableFee($follAcc->payableFee()); // for "notifyClient"
        $activity->succeed();
    }

    protected function notifyClient(Activity $activity): void
    {
        $this->notifGateway->notifyClient(
            $this->getClientId(),
            $this->getBroker(),
            NotificationGateway::FOLLOWER_ACC_OPENED,
            $this->getContext()->toArray()
        );
        $activity->succeed();
    }

    public function getResult()
    {
        $acc = $this->follAccRepo->getLightAccountOrFail($this->getAccountNumber());
        return $acc->number();
    }

    private function getLeaderAccountNumber()
    {
        return new AccountNumber($this->getContext()->get("leadAccNo"));
    }

    private function setLeaderAccountName($leadAccName)
    {
        $this->getContext()->set("leadAccName", $leadAccName);
    }

    private function setLeaderIsPublic($isPublic)
    {
        $this->getContext()->set("isPublic", $isPublic);
    }

    private function setPayableFee($payFee)
    {
        $this->getContext()->set("payFee", $payFee);
    }

    private function getClientId()
    {
        return new ClientId($this->getContext()->get("clientId"));
    }

    private function setAccountNumber(AccountNumber $accNo)
    {
        $this->getContext()->set("accNo", $accNo->value());
    }

    private function getAccountNumber()
    {
        return new AccountNumber($this->getContext()->get("accNo"));
    }

    private function getCopyCoefficient()
    {
        return $this->getContext()->get("copyCoef");
    }

    private function getStopLossLevel()
    {
        return $this->getContext()->get("stopLossPercent");
    }

    public function getAccountRepository()
    {
        return $this->follAccRepo;
    }

    private function getBroker()
    {
        return $this->getContext()->get('broker');
    }
}
