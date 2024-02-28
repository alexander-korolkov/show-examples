<?php

namespace Fxtm\CopyTrading\Application;

use Fxtm\CopyTrading\Domain\Model\Client\ClientId;
use Fxtm\CopyTrading\Domain\Model\Follower\FollowerAccount;
use Fxtm\CopyTrading\Domain\Model\Shared\AccountNumber;

interface LeverageService
{
    public function validateFollowerLeverageAndCopyCoefficient(AccountNumber $leadAccNo, $leaderBroker, ClientId $follId, $followerBroker, $copyCoef = 1.0);
    public function getLeverageRatio(AccountNumber $leadAccNo, $leaderBroker, ClientId $follId, $followerBroker);
    public function isValidFollowerLeverage(AccountNumber $leadAccNo, $leaderBroker, ClientId $follId, $followerBroker);
    public function getUpperCopyCoefficientLimit(AccountNumber $leadAccNo, $leaderBroker, ClientId $follId, $followerBroker);
    public function getMaxAllowedLeverageForClientAndAccountType(ClientId $id, $broker, $accType);
    public function getMaxAllowedLeverageForFollowerAccount(FollowerAccount $acc);
}
