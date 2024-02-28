<?php

namespace Fxtm\CopyTrading\Interfaces\Repository;

use Doctrine\DBAL\Connection;

class BrokerRepository implements BrokerRepositoryInterface
{
    /**
     * @var \PDO
     */
    private $connection;

    /**
     * BrokerRepository constructor.
     * @param Connection $connection
     */
    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    /**
     * Returns broker of leader with given account number
     *
     * @param string $accountNumber
     * @return string|null
     */
    public function getByLeader(string $accountNumber)
    {
        $stmt = $this->connection->prepare('SELECT broker FROM leader_accounts WHERE acc_no = ?');
        $stmt->execute([$accountNumber]);

        return $stmt->fetchColumn();
    }

    /**
     * Returns broker of follower with given account number
     *
     * @param string $accountNumber
     * @return string|null
     */
    public function getByFollower(string $accountNumber)
    {
        $stmt = $this->connection->prepare('SELECT broker FROM follower_accounts WHERE acc_no = ?');
        $stmt->execute([$accountNumber]);

        return $stmt->fetchColumn();
    }

    /**
     * Returns broker for given account, when type of account (leader or follower) is not known
     *
     * @param string $accountNumber
     * @return string|null
     */
    public function getByTradeAccount(string $accountNumber)
    {
        $sql1 = 'SELECT broker FROM leader_accounts WHERE acc_no = :acc_no';
        $sql2 = 'SELECT broker FROM follower_accounts WHERE acc_no = :acc_no';
        $stmt = $this->connection->prepare(implode(' UNION ALL ', [$sql1, $sql2]));
        $stmt->execute(['acc_no' => $accountNumber]);

        return $stmt->fetchColumn();
    }

    /**
     * Returns broker of leader by his owner's id
     *
     * @param string $clientId
     * @return string|null
     */
    public function getByLeaderClientId(string $clientId)
    {
        $stmt = $this->connection->prepare('SELECT acc_no FROM leader_accounts WHERE owner_id = ?');
        $stmt->execute([$clientId]);
        $accountNumber = $stmt->fetchColumn();

        return $accountNumber ? $this->getByLeader($accountNumber) : null;
    }

    /**
     * Returns broker of follower by his owner's id
     *
     * @param string $clientId
     * @return string|null
     */
    public function getByFollowerClientId(string $clientId)
    {
        $stmt = $this->connection->prepare('SELECT acc_no FROM follower_accounts WHERE owner_id = ?');
        $stmt->execute([$clientId]);
        $accountNumber = $stmt->fetchColumn();

        return $accountNumber ? $this->getByFollower($accountNumber) : null;
    }
}
