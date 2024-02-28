<?php

namespace Fxtm\CopyTrading\Interfaces\Repository;

interface BrokerRepositoryInterface
{
    public function getByLeader(string $accountNumber);
    public function getByFollower(string $accountNumber);
    public function getByTradeAccount(string $accountNumber);
    public function getByLeaderClientId(string $clientId);
    public function getByFollowerClientId(string $clientId);
}
