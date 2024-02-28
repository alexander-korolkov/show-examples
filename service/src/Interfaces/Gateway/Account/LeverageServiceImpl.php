<?php

namespace Fxtm\CopyTrading\Interfaces\Gateway\Account;

use Fxtm\CopyTrading\Application\ClientGateway;
use Fxtm\CopyTrading\Application\Follower\IncompatibleCopyCoefficient;
use Fxtm\CopyTrading\Application\Follower\IncompatibleMaxAllowedLeverage;
use Fxtm\CopyTrading\Application\LeverageService;
use Fxtm\CopyTrading\Application\TradeAccountGateway;
use Fxtm\CopyTrading\Domain\Model\Account\AccountType;
use Fxtm\CopyTrading\Domain\Model\Client\ClientId;
use Fxtm\CopyTrading\Domain\Model\Follower\FollowerAccount;
use Fxtm\CopyTrading\Domain\Model\Shared\AccountNumber;

class LeverageServiceImpl implements LeverageService
{
    private $clientGateway;
    private $tradeAccGateway;

    public function __construct(ClientGateway $clientGateway, TradeAccountGateway $tradeAccGateway)
    {
        $this->clientGateway = $clientGateway;
        $this->tradeAccGateway = $tradeAccGateway;
    }

    public function validateFollowerLeverageAndCopyCoefficient(AccountNumber $leadAccNo, $leaderBroker, ClientId $follId, $followerBroker, $copyCoef = 1.0)
    {
        $leadAcc = $this->tradeAccGateway->fetchAccountByNumber($leadAccNo, $leaderBroker);
        $client = $this->clientGateway->fetchClientByClientId($follId, $followerBroker);

        $followerMaxAllowedLeverage = $client->getMaxAllowedLeverageForAccountType(
            AccountType::GetFollowerTypeByLeaderType($leadAcc->accountTypeId(), $followerBroker)
        );

        if ($client->isProfessional()) {
            $followerLeverage = $followerMaxAllowedLeverage;
        } else {
            $followerLeverage = min($client->getAppropriatenessLeverage(), $followerMaxAllowedLeverage);
        }

        $ratio = $leadAcc->leverage() / $followerLeverage;

        if ($ratio > 1) {
            if ($ratio > 2) {
                throw new IncompatibleMaxAllowedLeverage($ratio);
            } elseif ($copyCoef > FollowerAccount::SAFE_MODE_COPY_COEFFICIENT) {
                throw new IncompatibleCopyCoefficient($copyCoef);
            }
        }
    }

    public function getLeverageRatio(AccountNumber $leadAccNo, $leaderBroker, ClientId $follId, $followerBroker)
    {
        $leadAcc = $this->tradeAccGateway->fetchAccountByNumber($leadAccNo, $leaderBroker);
        $client = $this->clientGateway->fetchClientByClientId($follId, $followerBroker);
        $follAppropLvr = $client->getAppropriatenessLeverage();
        $follMaxAllowedLvr = $client->getMaxAllowedLeverageForAccountType(AccountType::GetFollowerTypeByLeaderType($leadAcc->accountTypeId(), $followerBroker));

        return $leadAcc->leverage() / min($follAppropLvr, $follMaxAllowedLvr);
    }

    public function isValidFollowerLeverage(AccountNumber $leadAccNo, $leaderBroker, ClientId $follId, $followerBroker)
    {
        return $this->calculateLeaderAccountAndFollowerLeverageRatio($leadAccNo, $leaderBroker, $follId, $followerBroker) <= 2;
    }

    public function getUpperCopyCoefficientLimit(AccountNumber $leadAccNo, $leaderBroker, ClientId $follId, $followerBroker)
    {
        $leadAcc = $this->tradeAccGateway->fetchAccountByNumber($leadAccNo, $leaderBroker);
        $maxAllowedLvr = $this->getMaxAllowedLeverageForClientAndAccountType(
            $follId,
            $followerBroker,
            AccountType::GetFollowerTypeByLeaderType($leadAcc->accountTypeId(), $followerBroker)
        );
        $ratio = $leadAcc->leverage() / $maxAllowedLvr;

        if ($ratio <= 1) {
            return 1.0;
        } else if ($ratio > 1 && $ratio <= 2) {
            return FollowerAccount::SAFE_MODE_COPY_COEFFICIENT;
        } else { //$ratio > 2
            throw new IncompatibleMaxAllowedLeverage($ratio);
        }
    }

    /**
     * @param ClientId $id
     * @param string $broker
     * @param $accType
     * @return int
     */
    public function getMaxAllowedLeverageForClientAndAccountType(ClientId $id, $broker, $accType)
    {
        $client = $this->clientGateway->fetchClientByClientId($id, $broker);
        return min($client->getAppropriatenessLeverage(), $client->getMaxAllowedLeverageForAccountType($accType));
    }

    public function getMaxAllowedLeverageForFollowerAccount(FollowerAccount $acc)
    {
        return $this->getMaxAllowedLeverageForClientAndAccountType(
            $acc->ownerId(),
            $acc->broker(),
            AccountType::GetFollowerTypeByLeaderType($acc->leaderAccountType(), $acc->broker())
        );
    }

    private function calculateLeaderAccountAndFollowerLeverageRatio(AccountNumber $leadAccNo, $leaderBroker, ClientId $follId, $followerBroker)
    {
        $leadAcc = $this->tradeAccGateway->fetchAccountByNumber($leadAccNo, $leaderBroker);
        $maxAllowedLvr = $this->getMaxAllowedLeverageForClientAndAccountType(
            $follId,
            $followerBroker,
            AccountType::GetFollowerTypeByLeaderType($leadAcc->accountTypeId(), $followerBroker)
        );

        return $leadAcc->leverage() / $maxAllowedLvr;
    }
}
