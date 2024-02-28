<?php

namespace Fxtm\CopyTrading\Interfaces\Persistence\Follower;

use Fxtm\CopyTrading\Application\Follower\PauseCopyingWorkflow;
use Fxtm\CopyTrading\Application\Follower\StopCopyingWorkflow;
use Fxtm\CopyTrading\Application\TradeAccountGateway;
use Fxtm\CopyTrading\Domain\Common\Event;
use Fxtm\CopyTrading\Application\TradeOrderGatewayFacade;
use Fxtm\CopyTrading\Domain\Common\Objects;
use Fxtm\CopyTrading\Domain\Entity\MetaData\DataSourceFactory;
use Fxtm\CopyTrading\Domain\Model\Event\EventEntity;
use Fxtm\CopyTrading\Domain\Model\Follower\AccountStatus;
use Fxtm\CopyTrading\Domain\Model\Follower\FollowerAccount;
use Fxtm\CopyTrading\Domain\Model\Follower\FollowerAccountRepository;
use Fxtm\CopyTrading\Domain\Model\Follower\FollowersRepositoryException;
use Fxtm\CopyTrading\Domain\Model\Shared\AccountNotRegistered;
use Fxtm\CopyTrading\Domain\Model\Shared\AccountNumber;
use Fxtm\CopyTrading\Interfaces\Repository\EventException;
use Fxtm\CopyTrading\Interfaces\Repository\EventRepository;
use Fxtm\CopyTrading\Interfaces\Repository\MetaDataRepository;
use Psr\Log\LoggerInterface;
use ReflectionException;
use ReflectionObject;
use RuntimeException;
use Throwable;

class MySqlAccountRepository implements FollowerAccountRepository
{

    /**
     * @var DataSourceFactory
     */
    private $factory;

    /**
     * @var TradeOrderGatewayFacade
     */
    private $tradeOrderFacade;

    /**
     * @var TradeAccountGateway
     */
    private $tradeAccGateway;

    /**
     * @var array
     */
    private static $cache = [];

    /**
     * @var EventRepository
     */
    private $eventRepository;

    /**
     * @var MetaDataRepository
     */
    private $metaData;

    /**
     * @var LoggerInterface
     */
    private $logger;

    public function __construct(
        LoggerInterface $logger,
        DataSourceFactory $factory,
        TradeOrderGatewayFacade $tradeOrderFacade,
        TradeAccountGateway $tradeAccGateway,
        EventRepository $eventRepository,
        MetaDataRepository $metaDataRepository
    ) {
        $this->logger           = $logger;
        $this->factory          = $factory;
        $this->tradeOrderFacade = $tradeOrderFacade;
        $this->tradeAccGateway  = $tradeAccGateway;
        $this->eventRepository  = $eventRepository;
        $this->metaData         = $metaDataRepository;
    }

    public function clearCache()
    {
        self::$cache = [];
    }

    /**
     * @param AccountNumber $accNo
     * @return FollowerAccount|null
     * @throws FollowersRepositoryException
     */
    public function getLightAccount(AccountNumber $accNo): ?FollowerAccount
    {
        $accNo = $accNo->value();
        if (empty(self::$cache[$accNo])) {
            if (empty($accs = $this->fetchAccounts("fa.acc_no = ?", $accNo)) || empty($accs[$accNo])) {
                return null;
            }
            self::$cache[$accNo] = $accs[$accNo];
        }

        $acc = self::$cache[$accNo];

        $reflection = new ReflectionObject($acc);

        try {
            $equityVal = $this
                ->tradeOrderFacade
                ->getForAccount($acc)
                ->getAccountEquity($acc->number());


            $equity = $reflection->getProperty("equity");
            $equity->setAccessible(true);
            $equity->setValue($acc, $equityVal);

            return $acc;
        } catch (Throwable $any) {
            $this->logger->error(sprintf(
                "[%s::getLightAccount(%s)] Exception: %s\n%s",
                self::class,
                $accNo,
                $any->getMessage(),
                $any->getTraceAsString()
            ));
            throw new FollowersRepositoryException($any);
        }
    }

    /**
     * @param AccountNumber $accNo
     * @return FollowerAccount
     * @throws AccountNotRegistered
     * @throws FollowersRepositoryException
     */
    public function getLightAccountOrFail(AccountNumber $accNo): FollowerAccount
    {
        if (empty($account = $this->getLightAccount($accNo))) {
            throw new AccountNotRegistered($accNo->value());
        }
        return $account;
    }

    /**
     * @param AccountNumber $accNo
     * @return FollowerAccount
     * @throws FollowersRepositoryException
     */
    public function find(AccountNumber $accNo): FollowerAccount
    {
        return $this->hydrateFromMt($this->getLightAccount($accNo));
    }

    /**
     * @param AccountNumber $accNo
     * @return FollowerAccount
     * @throws AccountNotRegistered
     * @throws FollowersRepositoryException
     */
    public function findOrFail(AccountNumber $accNo): FollowerAccount
    {
        if (empty($acc = $this->find($accNo))) {
            throw new AccountNotRegistered($accNo->value());
        }
        return $acc;
    }

    /**
     *
     * @param AccountNumber $leadAccNo
     * @return integer
     * @throws FollowersRepositoryException
     */
    public function getCountOfCopyingFollowerAccounts(AccountNumber $leadAccNo): int
    {
        try {
            return (int) $this->factory
                    ->getCTConnection()
                    ->executeQuery(
                        "SELECT COUNT(id) FROM follower_accounts WHERE lead_acc_no = ? AND status = 1 AND is_copying = 1",
                        [$leadAccNo->value()]
                    )->fetchOne();
        } catch (Throwable $any) {
            $this->logger->error(sprintf(
                "[%s::getCountOfCopyingFollowerAccounts(%s)] Exception: %s\n%s",
                self::class,
                $leadAccNo,
                $any->getMessage(),
                $any->getTraceAsString()
            ));
            throw new FollowersRepositoryException($any);
        }
    }

    /**
     *
     * @param AccountNumber $leadAccNo
     * @return integer
     * @throws FollowersRepositoryException
     */
    public function getCountOfActivatedFollowerAccounts(AccountNumber $leadAccNo): int
    {
        try {
            return (int) $this->factory
                    ->getCTConnection()
                    ->executeQuery(
                        "SELECT COUNT(id) FROM follower_accounts WHERE lead_acc_no = ? AND status = 1",
                        [$leadAccNo->value()]
                    )->fetchOne();
        } catch (Throwable $any) {
            $this->logger->error(sprintf(
                "[%s::getCountOfActivatedFollowerAccounts(%s)] Exception: %s\n%s",
                self::class,
                $leadAccNo,
                $any->getMessage(),
                $any->getTraceAsString()
            ));
            throw new FollowersRepositoryException($any);
        }
    }

    /**
     *
     * @param AccountNumber $accNo
     * @return FollowerAccount[]
     * @throws FollowersRepositoryException
     */
    public function findOpenByLeaderAccountNumber(AccountNumber $accNo): array
    {
        return array_map(
            function (FollowerAccount $acc) {
                self::$cache[$acc->number()->value()] = $acc;
                return $acc;
            },
            $this->fetchAccounts("fa.lead_acc_no = ? AND fa.status != 2", $accNo->value())
        );
    }

    /**
     *
     * @return FollowerAccount[]
     * @throws FollowersRepositoryException
     */
    public function findWithDuePayoutInterval(): array
    {
        return array_map(
            function (FollowerAccount $acc) {
                self::$cache[$acc->number()->value()] = $acc;
                return $acc;
            },
            $this->fetchAccounts("fa.next_payout_at < NOW() AND fa.status = 1")
        );
    }

    /**
     *
     * @param string $cond
     * @param mixed[] $args
     * @return FollowerAccount[]
     * @throws FollowersRepositoryException
     */
    private function fetchAccounts(string $cond, ...$args): array
    {
        try {
            $stmt = $this
                ->factory
                ->getCTConnection()
                ->executeQuery("{$this->sqlQuery()} WHERE {$cond}", $args);

            return $this->buildAccountsFromArray($stmt->fetchAllAssociative());
        } catch (Throwable $any) {
            $this->logger->error(sprintf(
                "[%s::fetchAccounts(%s, %s)] Exception: %s\n%s",
                self::class,
                $cond,
                implode(', ', $args),
                $any->getMessage(),
                $any->getTraceAsString()
            ));
            throw new FollowersRepositoryException($any);
        }
    }

    /**
     * @param array $rows
     * @return FollowerAccount[]
     * @throws ReflectionException
     */
    private function buildAccountsFromArray(array $rows): array
    {
        $accounts = [];
        foreach ($rows as $row) {
            /* @var $acc FollowerAccount */
            $account = Objects::newInstance(FollowerAccount::CLASS, $row);

            $reflection = new ReflectionObject($account);

            $properties = [
                "req_equity" => "requiredEquity",
                "req_equity_in_safety_mode" => "requiredEquityInSafetyMode",
            ];

            foreach ($properties as $key => $property) {
                if (isset($row[$key])) {
                    $value = $row[$key];
                    unset($row[$key]);

                    $prop = $reflection->getProperty($property);
                    $prop->setAccessible(true);
                    $prop->setValue($account, $value);
                }
            }

            $accounts[$account->number()->value()] = $account;
        }

        return $accounts;
    }

    /**
     * @param FollowerAccount $acc
     * @return FollowerAccount
     */
    protected function hydrateFromMt(FollowerAccount $acc): FollowerAccount
    {
        try {
            $tradeAcc = $this
                ->tradeAccGateway
                ->fetchAccountByNumberWithFreshEquity($acc->number(), $acc->broker());

            $reflection = new ReflectionObject($acc);

            $equity = $reflection->getProperty("equity");
            $equity->setAccessible(true);
            $equity->setValue($acc, $tradeAcc->equity()->amount());

            return $acc;
        } catch (Throwable $any) {
            $this->logger->error(sprintf(
                "[%s::hydrateFromMt(%s)] Exception: %s\n%s",
                self::class,
                $acc->number(),
                $any->getMessage(),
                $any->getTraceAsString()
            ));
            throw new FollowersRepositoryException($any);
        }
    }

    /**
     * @param FollowerAccount $acc
     * @return void
     * @throws FollowersRepositoryException
     */
    public function store(FollowerAccount $acc): void
    {
        $sql = $this->getStoreSql();
        $data = $acc->toArray();

        $metaData = $this
            ->metaData
            ->getMetaData($acc->number())
        ;

        $pluginDBConnection = $metaData
            ->getDataSourceComponent()
            ->getPluginConnection()
        ;

        $dbConn = $this->factory->getCTConnection();

        $dbConn->beginTransaction();
        try {
            $stmt = $dbConn->prepare($sql);
            if (!$stmt->execute($data)) {
                throw new RuntimeException("Coudn't store Follower Account #{$acc->number()}");
            }
            self::$cache[$acc->number()->value()] = $acc;

            $sql = $this->getStoreSqlForPlugin();
            unset($data['server']);
            unset($data['broker']);
            unset($data['next_payout_at']);

            try {
                $pluginDBConnection->beginTransaction();
                $pluginDBConnection->prepare($sql)->execute($data);
                $pluginDBConnection->commit();
            } catch (Throwable $throwable) {
                $pluginDBConnection->rollBack();
                throw $throwable;
            }

            $this->updateEventHistory($acc);

            $dbConn->commit();
        } catch (Throwable $any) {
            $this->logger->error(sprintf(
                "[%s::store(%s)] Exception: %s\n%s",
                self::class,
                $acc,
                $any->getMessage(),
                $any->getTraceAsString()
            ));
            try {
                $dbConn->rollBack();
            } catch (Throwable $ignore) {
                $this->logger->critical(sprintf(
                    "[%s::store(%s)] Exception during rolling back transaction: %s\n%s",
                    self::class,
                    $acc,
                    $ignore->getMessage(),
                    $ignore->getTraceAsString()
                ));
            }
            throw new FollowersRepositoryException($any);
        }
    }

    /**
     * @param FollowerAccount $acc
     * @throws EventException
     */
    private function updateEventHistory(FollowerAccount $acc): void
    {
        /** @var Event $event */
        foreach ($acc->history() as $event) {
            $eventEntity = new EventEntity();
            $eventEntity->setAccountId($event->getAccountNumber()->value());
            $eventEntity->setEventType($event->getType());
            $eventEntity->setWorkflowId($event->getWorkflowId()->value());
            $eventEntity->setMessage($event->getMessage());
            $eventEntity->setTimeStamp($event->getTime());
            $this
                ->eventRepository
                ->store($eventEntity);
        }
        $acc->disposeHistory();
    }

    /**
     * @return string
     */
    private function getStoreSql(): string
    {
        return "
            INSERT INTO follower_accounts (
                `acc_no`,
                `lead_acc_no`,
                `owner_id`,
                `broker`,
                `server`,
                `acc_curr`,
                `copy_coef`,
                `lock_copy_coef`,
                `stoploss_level`,
                `stoploss_equity`,
                `stoploss_action`,
                `pay_fee`,
                `balance`,
                `status`,
                `state`,
                `is_copying`,
                `lock_copying`,
                `opened_at`,
                `closed_at`,
                `activated_at`,
                `settled_at`,
                `next_payout_at`,
                `settling_equity`
            ) VALUES (
                :acc_no,
                :lead_acc_no,
                :owner_id,
                :broker,
                :server,
                :acc_curr,
                :copy_coef,
                :lock_copy_coef,
                :stoploss_level,
                :stoploss_equity,
                :stoploss_action,
                :pay_fee,
                :balance,
                :status,
                :state,
                :is_copying,
                :lock_copying,
                :opened_at,
                :closed_at,
                :activated_at,
                :settled_at,
                :next_payout_at,
                :settling_equity
            ) ON DUPLICATE KEY UPDATE
                `copy_coef`        = VALUES(`copy_coef`),
                `lock_copy_coef`   = VALUES(`lock_copy_coef`),
                `stoploss_level`   = VALUES(`stoploss_level`),
                `stoploss_equity`  = VALUES(`stoploss_equity`),
                `stoploss_action`  = VALUES(`stoploss_action`),
                `balance`          = VALUES(`balance`),
                `status`           = VALUES(`status`),
                `state`            = VALUES(`state`),
                `is_copying`       = VALUES(`is_copying`),
                `lock_copying`     = VALUES(`lock_copying`),
                `closed_at`        = VALUES(`closed_at`),
                `activated_at`     = VALUES(`activated_at`),
                `settled_at`       = VALUES(`settled_at`),
                `next_payout_at`   = VALUES(`next_payout_at`),
                `settling_equity`  = VALUES(`settling_equity`)
        ";
    }

    /**
     * @return string
     */
    private function getStoreSqlForPlugin(): string
    {
        return "
            INSERT INTO follower_accounts (
                `acc_no`,
                `lead_acc_no`,
                `owner_id`,
                `acc_curr`,
                `copy_coef`,
                `lock_copy_coef`,
                `stoploss_level`,
                `stoploss_equity`,
                `stoploss_action`,
                `pay_fee`,
                `balance`,
                `status`,
                `state`,
                `is_copying`,
                `lock_copying`,
                `opened_at`,
                `closed_at`,
                `activated_at`,
                `settled_at`,
                `settling_equity`
            ) VALUES (
                :acc_no,
                :lead_acc_no,
                :owner_id,
                :acc_curr,
                :copy_coef,
                :lock_copy_coef,
                :stoploss_level,
                :stoploss_equity,
                :stoploss_action,
                :pay_fee,
                :balance,
                :status,
                :state,
                :is_copying,
                :lock_copying,
                :opened_at,
                :closed_at,
                :activated_at,
                :settled_at,
                :settling_equity
            ) ON DUPLICATE KEY UPDATE
                `copy_coef`        = VALUES(`copy_coef`),
                `lock_copy_coef`   = VALUES(`lock_copy_coef`),
                `stoploss_level`   = VALUES(`stoploss_level`),
                `stoploss_equity`  = VALUES(`stoploss_equity`),
                `stoploss_action`  = VALUES(`stoploss_action`),
                `balance`          = VALUES(`balance`),
                `status`           = VALUES(`status`),
                `state`            = VALUES(`state`),
                `is_copying`       = VALUES(`is_copying`),
                `lock_copying`     = VALUES(`lock_copying`),
                `closed_at`        = VALUES(`closed_at`),
                `activated_at`     = VALUES(`activated_at`),
                `settled_at`       = VALUES(`settled_at`),
                `settling_equity`  = VALUES(`settling_equity`)
        ";
    }

    /**
     * @return string
     */
    private function sqlQuery(): string
    {
        return "
            SELECT
                fa.*,
                la.server leader_server,
                la.min_deposit req_equity,
                la.min_deposit_in_safety_mode  req_equity_in_safety_mode,
                la.account_type leader_account_type
            FROM follower_accounts fa
            JOIN leader_accounts la ON la.acc_no = fa.lead_acc_no
        ";
    }

    /**
     * Returns array of follower accounts that have been paused for $daysWithoutActivity or longer
     *
     * @param int $daysWithoutActivity
     * @param int $limit
     * @param bool $greaterOrEqual
     * @return FollowerAccount[]
     * @throws FollowersRepositoryException
     */
    public function getPausedFollowers(int $daysWithoutActivity, ?int $limit = null, bool $greaterOrEqual = true): array
    {
        $expression = $greaterOrEqual ? '>=' : '=';

        $sql = "
          SELECT 
                 fa.*, 
                 la.server as `leader_server`, 
                 la.min_deposit req_equity,
                 la.min_deposit_in_safety_mode  req_equity_in_safety_mode,
                 la.account_type leader_account_type,
                 DATEDIFF(CURDATE(), COALESCE(wf.finished_at, fa.opened_at)) AS days_paused 
            FROM follower_accounts fa 
            LEFT OUTER JOIN leader_accounts as la ON la.acc_no = fa.lead_acc_no
            LEFT OUTER JOIN (
              SELECT w.corr_id, w.id, w.finished_at
              FROM workflows w
              INNER JOIN (
                  SELECT corr_id, MAX(id) AS id
                  FROM workflows
                  WHERE type IN (:pause_copying_type, :stop_copying_type)
                  GROUP BY corr_id
              ) w2 ON w.id = w2.id
            ) wf ON fa.acc_no = wf.corr_id
            WHERE fa.is_copying = :is_copying AND fa.status != :status
            HAVING days_paused {$expression} :days_without_activity
        ";

        if ($limit > 0) {
            $sql .= " LIMIT $limit";
        }

        try {
            $stmt = $this
                ->factory
                ->getCTConnection()
                ->prepare($sql);

            $stmt->execute([
                'pause_copying_type' => PauseCopyingWorkflow::TYPE,
                'stop_copying_type' => StopCopyingWorkflow::TYPE,
                'is_copying' => 0,
                'status' => AccountStatus::CLOSED,
                'days_without_activity' => $daysWithoutActivity,
            ]);

            return $this->buildAccountsFromArray($stmt->fetchAllAssociative());
        } catch (Throwable $any) {
            $this->logger->error(sprintf(
                "[%s::getPausedFollowers(%s, %s, %s)] Exception: %s\n%s",
                self::class,
                $daysWithoutActivity,
                $limit,
                $greaterOrEqual,
                $any->getMessage(),
                $any->getTraceAsString()
            ));
            throw new FollowersRepositoryException($any);
        }
    }

    /**
     * @param int  $clientId
     * @param int $limit
     * @param int $offset
     * @return array
     * @throws FollowersRepositoryException
     */
    public function getClosedByClient(int $clientId, int $limit = 1, int $offset = 0): array
    {
        $sql = "
              SELECT fa.acc_no, la.acc_name, fa.opened_at, fa.activated_at, fa.closed_at
              FROM follower_accounts fa
              JOIN leader_accounts la ON la.acc_no = fa.lead_acc_no
              WHERE fa.owner_id = :owner_id
                AND fa.status = 2
              ORDER BY fa.acc_no
        ";

        if ($limit) {
            $sql .= " LIMIT $limit";
        }

        if ($offset) {
            $sql .= " OFFSET $offset";
        }

        try {
            $results = $this
                ->factory
                ->getCTConnection()
                ->executeQuery($sql, ['owner_id' => $clientId])
                ->fetchAllAssociative();

            return array_map(function (array $item) {
                return [
                    'accNo' => $item['acc_no'],
                    'strategyName' => $item['acc_name'],
                    'openedAt' => $item['opened_at'],
                    'activatedAt' => $item['activated_at'],
                    'closedAt' => $item['closed_at'],
                ];
            }, $results);
        } catch (Throwable $any) {
            $this->logger->error(sprintf(
                "[%s::getClosedByClient(%s, %s, %s)] Exception: %s\n%s",
                self::class,
                $clientId,
                $limit,
                $offset,
                $any->getMessage(),
                $any->getTraceAsString()
            ));
            throw new FollowersRepositoryException($any);
        }
    }

    /**
     * @param int $clientId
     * @param array|null $logins
     * @param int|null $limit
     * @param int|null $offset
     * @return array
     * @throws FollowersRepositoryException
     */
    public function getByClient(int $clientId, ?array $logins = null, ?int $limit = null, ?int $offset = null): array
    {
        $sql = "
            SELECT 
              fa.acc_no as accountNumber,
              fa.acc_curr as accountCurrency,
              fa.status,
              fa.is_copying as isCopying,
              fa.lock_copying as lockCopying,
              fa.copy_coef as copyCoefficient,
              fa.lock_copy_coef as lockCopyCoefficient,
              fa.stoploss_level as protectionLevel,
              IF (fa.copy_coef = 1, 0, 1) as safetyMode,
              fa.pay_fee as profitShare,
              fa.activated_at as activatedAt,
              fa.stoploss_action as protectionAction,
              fa.stoploss_equity as protectionEquity,
              fa.settling_equity as settlingEquity,
              fa.settled_at as settledAt,
              DATE_FORMAT(fa.next_payout_at, '%d.%m.%Y %H:%i') as nextPayoutDate,
              les.acc_no as leaderAccountNumber,
              les.acc_name as accountName,
              les.manager_name as managerName,
              les.avatar as avatar,
              les.country as country,
              les.acc_curr as currency,
              les.volatility as volatility,
              les.risk_level as riskLevel,
              les.is_veteran as isVeteran,
              les.chart as chart,
              les.min_deposit as minDeposit,
              les.min_deposit_in_safety_mode as minDepositInSafetyMode,
              la.server
            FROM follower_accounts fa
            LEFT JOIN leader_accounts AS la ON la.acc_no = fa.lead_acc_no
            LEFT JOIN leader_equity_stats les on les.acc_no = fa.lead_acc_no
            WHERE fa.owner_id = :client_id AND fa.status != :status
        ";

        $logins = implode(',', $logins ?? []);
        if (!empty($logins)) {
            $sql .= " AND fa.acc_no IN ({$logins})";
        }

        if ($limit != null) {
            $sql .= " LIMIT $limit";
            if ($offset != null) {
                $sql .= " OFFSET $offset";
            }
        }

        try {
            $accounts = $this
                ->factory
                ->getCTConnection()
                ->executeQuery($sql, ['client_id' => $clientId, 'status' => AccountStatus::CLOSED])
                ->fetchAllAssociative();

            if (!empty($accounts)) {
                foreach ($accounts as &$account) {
                    $pluginData = $this->getFollowerPluginData(intval($account['accountNumber']));
                    $account["pluginRelCoefficient"] = $pluginData["foll_rel_coef"];
                    $account["pluginCopyCoefficientCurrent"] = $pluginData["copy_coef_curr"];
                }
            }

            return $accounts;
        } catch (Throwable $any) {
            $this->logger->error(sprintf(
                "[%s::getByClient(%s, [%s], %s, %s)] Exception: %s\n%s",
                self::class,
                $clientId,
                is_array($logins) ? implode(', ', $logins) : $logins,
                $limit,
                $offset,
                $any->getMessage(),
                $any->getTraceAsString()
            ));
            throw new FollowersRepositoryException($any);
        }
    }

    /**
     * @param int $accountNumber
     * @return array|null
     * @throws FollowersRepositoryException
     */
    public function getAsArray(int $accountNumber): ?array
    {
        $sql = "
            SELECT 
              fa.acc_no as accountNumber,
              fa.acc_curr as accountCurrency,
              fa.status,
              fa.is_copying as isCopying,
              fa.lock_copying as lockCopying,
              fa.copy_coef as copyCoefficient,
              fa.lock_copy_coef as lockCopyCoefficient,
              fa.stoploss_level as protectionLevel,
              IF (fa.copy_coef = 1, 0, 1) as safetyMode,
              fa.pay_fee as profitShare,
              fa.activated_at as activatedAt,
              fa.stoploss_action as protectionAction,
              fa.stoploss_equity as protectionEquity,
              fa.settling_equity as settlingEquity,
              fa.settled_at as settledAt,
              DATE_FORMAT(fa.next_payout_at, '%d.%m.%Y %H:%i') as nextPayoutDate,
              les.acc_no as leaderAccountNumber,
              les.acc_name as accountName,
              les.manager_name as managerName,
              les.avatar as avatar,
              les.country as country,
              la.acc_curr as currency,
              les.volatility as volatility,
              les.risk_level as riskLevel,
              les.is_veteran as isVeteran,
              les.chart as chart,
              les.min_deposit as minDeposit,
              les.min_deposit_in_safety_mode as minDepositInSafetyMode,
              la.server
            FROM follower_accounts fa
            LEFT JOIN leader_accounts AS la ON la.acc_no = fa.lead_acc_no
            LEFT JOIN leader_equity_stats les on les.acc_no = fa.lead_acc_no
            WHERE fa.acc_no = :acc_no
        ";

        try {
            $account = $this
                ->factory
                ->getCTConnection()
                ->executeQuery($sql, ['acc_no' => $accountNumber])
                ->fetchAssociative();

            if (!empty($account)) {
                $pluginData = $this->getFollowerPluginData($accountNumber);
                $account["pluginRelCoefficient"] = $pluginData["foll_rel_coef"];
                $account["pluginCopyCoefficientCurrent"] = $pluginData["copy_coef_curr"];
                return $account;
            }

            return null;
        }
        catch (Throwable $any) {
            $this->logger->error(sprintf(
                "[%s::getAsArray(%s)] Exception: %s\n%s",
                self::class,
                $accountNumber,
                $any->getMessage(),
                $any->getTraceAsString()
            ));
            throw new FollowersRepositoryException($any);
        }
    }

    public function getPaidFees(AccountNumber $accountNumber, ?int $limit = null, ?int $offset = null): array
    {
        $accNo = $accountNumber->value();
        $sql = '
            SELECT amount, type, created_at
            FROM commission
            WHERE acc_no = :acc_no
            ORDER BY id DESC
        ';

        if (!is_null($limit)){
            $sql .= " LIMIT $limit";
        }

        if (!is_null($offset)){
            $sql .= " OFFSET $offset";
        }

        try {
             return $this
                ->factory
                ->getCTConnection()
                ->executeQuery($sql, ['acc_no' => $accNo])
                ->fetchAllAssociative();
        } catch (Throwable $any) {
            $this->logger->error(sprintf(
                "[%s::getAsArray(%s)] Exception: %s\n%s",
                self::class,
                $accNo,
                $any->getMessage(),
                $any->getTraceAsString()
            ));
            throw new FollowersRepositoryException($any);
        }
    }

    public function getPaidFeesTotalAmount(AccountNumber $accountNumber): float
    {
        $accNo = $accountNumber->value();

        $sql = '
            SELECT SUM(amount) FROM commission
            WHERE acc_no = :acc_no
        ';

        try {
            return floatval($this
                ->factory
                ->getCTConnection()
                ->executeQuery($sql, [
                  'acc_no' => $accNo,
                ])
                ->fetchOne());
        } catch (Throwable $any) {
            $this->logger->error(sprintf(
                "[%s::getAsArray(%s)] Exception: %s\n%s",
                self::class,
                $accNo,
                $any->getMessage(),
                $any->getTraceAsString()
            ));
            throw new FollowersRepositoryException($any);
        }
    }

    public function getPaidFeesCount(AccountNumber $accountNumber): int
    {
        $accNo = $accountNumber->value();

        $sql = '
            SELECT COUNT(*) FROM commission
            WHERE acc_no = :acc_no
        ';

        try {
            return intval($this
                ->factory
                ->getCTConnection()
                ->executeQuery($sql, [
                    'acc_no' => $accNo,
                ])
                ->fetchOne());
        } catch (Throwable $any) {
            $this->logger->error(sprintf(
                "[%s::getAsArray(%s)] Exception: %s\n%s",
                self::class,
                $accNo,
                $any->getMessage(),
                $any->getTraceAsString()
            ));
            throw new FollowersRepositoryException($any);
        }
    }

    /**
     * @param int $accountNumber
     * @return array
     * @throws Throwable
     */
    private function getFollowerPluginData(int $accountNumber): array
    {
        $result = $this
            ->metaData
            ->getMetaData(new AccountNumber(intval($accountNumber)))
            ->getDataSourceComponent()
            ->getPluginConnection()
            ->executeQuery("SELECT foll_rel_coef, copy_coef_curr FROM plugin_logins WHERE foll_login = ?", [$accountNumber])
            ->fetchAssociative()
        ;
        if($result === false) {
            return [
                'foll_rel_coef' => null,
                'copy_coef_curr' => null
            ];
        }
        return $result;
    }

    /**
     * @param int $clientId
     * @return array
     * @throws FollowersRepositoryException
     */
    public function getReferrable(int $clientId): array
    {
        try {
            return $this
                ->factory
                ->getCTConnection()
                ->executeQuery("
                SELECT
                    fa.acc_no   foll_acc_no,
                    la.acc_no   lead_acc_no,
                    la.acc_name lead_acc_name,
                    les.profit  lead_acc_profit
                FROM follower_accounts fa
                JOIN leader_accounts la ON la.acc_no = fa.lead_acc_no AND la.is_public = 1 AND la.is_followable = 1
                JOIN leader_equity_stats les ON les.acc_no = la.acc_no
                WHERE fa.owner_id = ? AND fa.status = 1
            ", [$clientId])
                ->fetchAllAssociative();
        } catch (Throwable $any) {
            $this->logger->error(sprintf(
                "[%s::getReferrable(%s)] Exception: %s\n%s",
                self::class,
                $clientId,
                $any->getMessage(),
                $any->getTraceAsString()
            ));
            throw new FollowersRepositoryException($any);
        }
    }

    /**
     * @param string $accountNumber
     * @return array
     */
    public function getMinDeposit(string $accountNumber): array
    {
        $sql = "
            SELECT 
                fa.acc_no as login,
                IF(fa.status = :status_new, IF(fa.copy_coef = 1, les.min_deposit, les.min_deposit_in_safety_mode), 0) as minDeposit
            FROM leader_equity_stats les
            JOIN follower_accounts fa ON fa.lead_acc_no = les.acc_no
            WHERE fa.acc_no = :acc_no;
        ";

        try {
            return $this
                ->factory
                ->getCTConnection()
                ->executeQuery($sql, ['acc_no' => $accountNumber, 'status_new' => AccountStatus::PASSIVE,])
                ->fetchAssociative();
        }
        catch (Throwable $any) {
            $this->logger->error(sprintf(
                "[%s::getMinDeposit(%s)] Exception: %s\n%s",
                self::class,
                $accountNumber,
                $any->getMessage(),
                $any->getTraceAsString()
            ));
            throw new FollowersRepositoryException($any);
        }
    }

    /**
     * Returns min deposits for not closed invest accounts of the client
     *
     * @param string $clientId
     * @return array
     */
    public function getMinDepositsByClientId(string $clientId): array
    {
        $sql = "
            SELECT 
                fa.acc_no as login,
                IF(fa.status = :status_new, IF(fa.copy_coef = 1, les.min_deposit, les.min_deposit_in_safety_mode), 0) as minDeposit
            FROM leader_equity_stats les
            JOIN follower_accounts fa ON fa.lead_acc_no = les.acc_no
            WHERE fa.owner_id = :client_id AND fa.status != :status_closed;
        ";

        try {
            return $this
                ->factory
                ->getCTConnection()
                ->executeQuery($sql, [
                    'client_id' => $clientId,
                    'status_new' => AccountStatus::PASSIVE,
                    'status_closed' => AccountStatus::CLOSED,
                ])
                ->fetchAllAssociative();
        }
        catch (Throwable $any) {
            $this->logger->error(sprintf(
                "[%s::getMinDepositsByClientId(%s)] Exception: %s\n%s",
                self::class,
                $clientId,
                $any->getMessage(),
                $any->getTraceAsString()
            ));
            throw new FollowersRepositoryException($any);
        }
    }
}
