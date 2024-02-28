<?php

namespace Fxtm\CopyTrading\Domain\Model\Follower;

use Fxtm\CopyTrading\Domain\Model\Shared\AccountNotRegistered;
use Fxtm\CopyTrading\Domain\Model\Shared\AccountNumber;

interface FollowerAccountRepository
{
    /**
     * @param FollowerAccount $acc
     * @return void
     * @throws FollowersRepositoryException
     */
    public function store(FollowerAccount $acc): void;

    /**
     *
     * @param AccountNumber $accNo
     * @return FollowerAccount|null
     * @throws FollowersRepositoryException
     */
    public function find(AccountNumber $accNo): ?FollowerAccount;

    /**
     *
     * @param AccountNumber $accNo
     * @return FollowerAccount
     * @throws AccountNotRegistered
     * @throws FollowersRepositoryException
     */
    public function findOrFail(AccountNumber $accNo): FollowerAccount;

    /**
     *
     * @return FollowerAccount[]
     * @throws FollowersRepositoryException
     */
    public function findWithDuePayoutInterval(): array;

    /**
     *
     * @param AccountNumber $leadAccNo
     * @return integer
     * @throws FollowersRepositoryException
     */
    public function getCountOfCopyingFollowerAccounts(AccountNumber $leadAccNo): int;

    /**
     *
     * @param AccountNumber $leadAccNo
     * @return integer
     * @throws FollowersRepositoryException
     */
    public function getCountOfActivatedFollowerAccounts(AccountNumber $leadAccNo): int;

    /**
     *
     * @param AccountNumber $accNo
     * @return FollowerAccount[]
     * @throws FollowersRepositoryException
     */
    public function findOpenByLeaderAccountNumber(AccountNumber $accNo): array;

    /**
     * @param AccountNumber $accNo
     * @return FollowerAccount|null
     * @throws FollowersRepositoryException
     */
    public function getLightAccount(AccountNumber $accNo): ?FollowerAccount;

    /**
     * @param AccountNumber $accNo
     * @return FollowerAccount
     * @throws AccountNotRegistered
     * @throws FollowersRepositoryException
     */
    public function getLightAccountOrFail(AccountNumber $accNo): FollowerAccount;

    /**
     * Returns array of follower accounts that have been paused for $daysWithoutActivity or longer
     *
     * @param int $daysWithoutActivity
     * @param int|null $limit
     * @param bool $greaterOrEqual
     * @return FollowerAccount[]
     * @throws FollowersRepositoryException
     */
    public function getPausedFollowers(int $daysWithoutActivity, ?int $limit = null, bool $greaterOrEqual = true): array;

    /**
     * @param int $clientId
     * @param int $limit
     * @param int $offset
     * @return array
     * @throws FollowersRepositoryException
     */
    public function getClosedByClient(int $clientId, int $limit = 1, int $offset = 0): array;

    /**
     * @param int $clientId
     * @param array|null $logins
     * @param int|null $limit
     * @param int|null $offset
     * @return array
     * @throws FollowersRepositoryException
     */
    public function getByClient(int $clientId, ?array $logins = null, ?int $limit = null, ?int $offset = null): array;

    /**
     * @param int $accountNumber
     * @return array|null
     * @throws FollowersRepositoryException
     */
    public function getAsArray(int $accountNumber): ?array;

    public function getPaidFees(AccountNumber $accountNumber, ?int $limit = null, ?int $offset = null): array;

    public function getPaidFeesTotalAmount(AccountNumber $accountNumber): float;

    public function getPaidFeesCount(AccountNumber $accountNumber): int;

    /**
     * @param int $clientId
     * @return array
     * @throws FollowersRepositoryException
     */
    public function getReferrable(int $clientId): array;

    /**
     * @param string $accountNumber
     * @return array
     */
    public function getMinDeposit(string $accountNumber): array;

    /**
     * @param string $clientId
     * @return array
     */
    public function getMinDepositsByClientId(string $clientId): array;
}
