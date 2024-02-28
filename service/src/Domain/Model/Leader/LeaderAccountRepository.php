<?php

namespace Fxtm\CopyTrading\Domain\Model\Leader;

use Fxtm\CopyTrading\Domain\Model\Shared\AccountNotRegistered;
use Fxtm\CopyTrading\Domain\Model\Shared\AccountNumber;

interface LeaderAccountRepository
{
    public const KEY_SERVER = 'server';
    public const KEY_BROKER = 'broker';
    public const KEY_ACC_NO = 'acc_no';
    public const KEY_TYPE = 'type';

    public const ACCOUNT_TYPE_LEADER = 1;
    public const ACCOUNT_TYPE_AGGREGATE = 2;
    public const ACCOUNT_TYPE_FOLLOWER = 3;

    /**
     * @param LeaderAccount $acc
     * @return void
     * @throws LeaderRepositoryException
     */
    public function store(LeaderAccount $acc): void;

    /**
     *
     * @param AccountNumber $accNo
     * @return LeaderAccount|null
     * @throws LeaderRepositoryException
     */
    public function find(AccountNumber $accNo): ?LeaderAccount;

    /**
     *
     * @param AccountNumber $accNo
     * @return LeaderAccount
     * @throws AccountNotRegistered
     * @throws LeaderRepositoryException
     */
    public function findOrFail(AccountNumber $accNo): LeaderAccount;

    /**
     *
     * @param string $accName
     * @return LeaderAccount|null
     * @throws LeaderRepositoryException
     */
    public function findByAccountName(string $accName): ?LeaderAccount;

    /**
     *
     * @param string $accName
     * @return bool true if name doesn't exists, otherwise false
     * @throws LeaderRepositoryException
     */
    public function isUniqueAccountName(string $accName): bool;

    /**
     * Returns array of active leader accounts, aggregate accounts and follower accounts
     * @return array
     * @throws LeaderRepositoryException
     */
    public function getActiveWithAggregatesAndFollowers(): array;

    /**
     * Returns light array of account ids
     *
     * @param array $onlyAccounts
     * @return array
     */
    public function getAccountsIds(array $onlyAccounts = []): array;

    /**
     * Returns array of data for calculating
     * equity statistics
     * @param array $onlyAccounts
     * @return array
     * @throws LeaderRepositoryException
     */
    public function getForCalculatingStats(array $onlyAccounts = []) : array;

    public function getTotalFundsForAccount(AccountNumber $accNo): float;

    /**
     * @param AccountNumber $accNo
     * @return LeaderAccount|null
     * @throws LeaderRepositoryException
     */
    public function getLightAccount(AccountNumber $accNo): ?LeaderAccount;

    /**
     * @param AccountNumber $accNo
     * @return LeaderAccount
     * @throws AccountNotRegistered
     * @throws LeaderRepositoryException
     */
    public function getLightAccountOrFail(AccountNumber $accNo): LeaderAccount;

    /**
     * Returns array with logins that did't trade after date interval
     * @param string $days
     * @return array
     * @throws LeaderRepositoryException
     */
    public function getAccountsWithoutTradingAfterDateInterval(string $days): array;

    /**
     * Returns array of leader logins which hasn't
     * any trade activity for last $daysWithoutActivity days
     * @param int $daysWithoutActivity
     * @param int|null $limit
     * @return LeaderAccount[]
     * @throws LeaderRepositoryException
     */
    public function getNotActive(int $daysWithoutActivity, ?int $limit = null) : array;

    /**
     * @param int $accountNumber
     * @return array|null
     * @throws LeaderRepositoryException
     */
    public function getArray(int $accountNumber): ?array;

    /**
     * @param int $accountNumber
     * @return array
     * @throws LeaderRepositoryException
     */
    public function getTradeMonthlyStats(int $accountNumber): array;

    /**
     * @param int $clientId
     * @return array
     * @throws LeaderRepositoryException
     */
    public function getReferrable(int $clientId): array;

    /**
     * @param int|null $accNumber
     * @param string|null $accName
     * @param int|null $limit
     * @param int|null $offset
     * @return array
     * @throws LeaderRepositoryException
     */
    public function getInvestors(?int $accNumber = null, ?string $accName = null, ?int $limit = null, ?int $offset = null): array;

    /**
     * @param string $accName
     * @return int
     * @throws LeaderRepositoryException
     */
    public function getAccountNumberByName(string $accName): int;

    /**
     * @return LeaderAccount[]
     * @throws LeaderRepositoryException
     */
    public function findInconsistentAccounts(): array;

}
