<?php

namespace Fxtm\CopyTrading\Interfaces\Persistence\Leader;

use Fxtm\CopyTrading\Application\Leader\Statistics\LeaderTopSuitabilityService;
use Fxtm\CopyTrading\Application\Leader\UpdateAccountNameWorkflow;
use Fxtm\CopyTrading\Application\TradeAccountGateway;
use Fxtm\CopyTrading\Application\TradeOrderGatewayFacade;
use Fxtm\CopyTrading\Domain\Common\DateTime;
use Fxtm\CopyTrading\Domain\Common\Event;
use Fxtm\CopyTrading\Domain\Common\Objects;
use Fxtm\CopyTrading\Domain\Entity\Broker;
use Fxtm\CopyTrading\Domain\Entity\MetaData\DataSourceFactory;
use Fxtm\CopyTrading\Domain\Model\Company\Company;
use Fxtm\CopyTrading\Domain\Model\Event\EventEntity;
use Fxtm\CopyTrading\Domain\Model\Leader\AccountActivated;
use Fxtm\CopyTrading\Domain\Model\Leader\AccountStatus;
use Fxtm\CopyTrading\Domain\Model\Leader\LeaderAccount;
use Fxtm\CopyTrading\Domain\Model\Leader\LeaderAccountRepository;
use Fxtm\CopyTrading\Domain\Model\Leader\LeaderRepositoryException;
use Fxtm\CopyTrading\Domain\Model\Shared\AccountNotRegistered;
use Fxtm\CopyTrading\Domain\Model\Shared\AccountNumber;
use Fxtm\CopyTrading\Interfaces\Repository\EventException;
use Fxtm\CopyTrading\Interfaces\Repository\EventRepository;
use Fxtm\CopyTrading\Interfaces\Repository\MetaDataRepository;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use ReflectionObject;
use RuntimeException;
use Fxtm\CopyTrading\Domain\Model\Shared\Server;
use Throwable;

class MySqlAccountRepository implements LeaderAccountRepository
{

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var DataSourceFactory
     */
    private $factory;

    /**
     * @var TradeOrderGatewayFacade
     */
    private $tradeOrderGatewayFacade;

    /**
     * @var TradeAccountGateway
     */
    private $tradeAccGateway;

    private static $cache = [];

    /**
     * @var LeaderTopSuitabilityService
     */
    private $topSuitabilityService;

    /**
     * @var EventRepository
     */
    private $eventRepository;

    /**
     * @var MetaDataRepository
     */
    private $metaData;

    public function __construct(
        LoggerInterface $logger,
        DataSourceFactory $dsFactory,
        TradeOrderGatewayFacade $tradeOrderGatewayFacade,
        TradeAccountGateway $tradeAccGateway,
        LeaderTopSuitabilityService $topSuitabilityService,
        EventRepository $eventRepository,
        MetaDataRepository $metaDataRepository
    ) {
        $this->logger                   = $logger;
        $this->factory                  = $dsFactory;
        $this->tradeOrderGatewayFacade  = $tradeOrderGatewayFacade;
        $this->tradeAccGateway          = $tradeAccGateway;
        $this->topSuitabilityService    = $topSuitabilityService;
        $this->eventRepository          = $eventRepository;
        $this->metaData                 = $metaDataRepository;
    }

    public function clearCache()
    {
        self::$cache = [];
    }

    /**
     * @param AccountNumber $accNo
     * @return LeaderAccount|null
     * @throws LeaderRepositoryException
     */
    public function getLightAccount(AccountNumber $accNo): ?LeaderAccount
    {
        $accNo = $accNo->value();
        if (empty(self::$cache[$accNo])) {
            if (empty($accs = $this->fetchAccounts("acc_no = ?", $accNo)) || empty($accs[$accNo])) {
                return null;
            }
            self::$cache[$accNo] = $accs[$accNo];
        }

        try {
            $acc = self::$cache[$accNo];

            $reflection = new ReflectionObject($acc);

            $gateway = $this
                ->tradeOrderGatewayFacade
                ->getForAccount($acc);

            $equity = $reflection->getProperty('equity');
            $equity->setAccessible(true);
            $equity->setValue($acc, $gateway->getAccountEquity($acc->number()));

            $hasOpenPoses = $reflection->getProperty('hasOpenPositions');
            $hasOpenPoses->setAccessible(true);
            $hasOpenPoses->setValue($acc, $gateway->hasOpenPositions($acc->number()));
        } catch (Throwable $any) {
            $this->logger->error(sprintf(
                "[%s::getLightAccount(%s)] Exception: %s\n%s",
                self::class,
                $accNo,
                $any->getMessage(),
                $any->getTraceAsString()
            ));
            throw new LeaderRepositoryException($any);
        }
        return $acc;
    }

    /**
     * @param AccountNumber $accNo
     * @return LeaderAccount
     * @throws AccountNotRegistered
     * @throws LeaderRepositoryException
     */
    public function getLightAccountOrFail(AccountNumber $accNo): LeaderAccount
    {
        $account = $this->getLightAccount($accNo);
        if (empty($account)) {
            throw new AccountNotRegistered($accNo->value());
        }

        return $account;
    }

    /**
     * @param AccountNumber $accNo
     * @return LeaderAccount|null
     * @throws LeaderRepositoryException
     */
    public function find(AccountNumber $accNo): ?LeaderAccount
    {
        $acc = $this->getLightAccount($accNo);
        if (!$acc) {
            return null;
        }
        return $this->hydrateFromMt($acc);
    }

    /**
     * @param AccountNumber $accNo
     * @return LeaderAccount
     * @throws AccountNotRegistered
     * @throws LeaderRepositoryException
     */
    public function findOrFail(AccountNumber $accNo): LeaderAccount
    {
        if (empty($acc = $this->find($accNo))) {
            throw new AccountNotRegistered($accNo->value());
        }
        return $acc;
    }

    /**
     * @param string $accName
     * @return LeaderAccount|null
     * @throws LeaderRepositoryException
     */
    public function findByAccountName(string $accName): ?LeaderAccount
    {
        if (empty($accName)) {
            throw new LeaderRepositoryException(new InvalidArgumentException('Account name cannot be empty'));
        }
        $found = array_filter(self::$cache, function (LeaderAccount $acc) use ($accName) {
            return $acc->name() === $accName;
        });
        if (empty($accs = array_values($found)) || empty($accs[0])) {
            if (empty($accs = array_values($this->fetchAccounts("acc_name = ?", $accName))) || empty($accs[0])) {
                return null;
            }
            self::$cache[$accs[0]->number()->value()] = $accs[0];
        }
        return $this->hydrateFromMt($accs[0]);
    }

    /**
     * @param string $accName
     * @return bool
     * @throws LeaderRepositoryException
     */
    public function isUniqueAccountName(string $accName): bool
    {
        if (empty($accName)) {
            throw new LeaderRepositoryException(new InvalidArgumentException('Account name cannot be empty'));
        }
        return empty($this->fetchAccounts("acc_name = :acc_name OR prev_acc_name = :acc_name", ["acc_name" => $accName]));
    }

    /**
     * @param string $cond
     * @param mixed[] $args
     * @return LeaderAccount[]
     * @throws LeaderRepositoryException
     */
    private function fetchAccounts(string $cond, ...$args): array
    {
        $params = array_reduce(
            $args,
            function ($tmp, $item) {
                return array_merge($tmp, (array) $item);
            },
            []
        );

        try {
            $stmt = $this
                ->factory
                ->getCTConnection()
                ->executeQuery("{$this->sqlQuery()} WHERE {$cond}", $params);

            $accs = [];
            while (($row = $stmt->fetchAssociative()) != false) {
                $reqEquity = $row["req_equity"];
                unset($row["req_equity"]);

                /* @var $acc LeaderAccount */
                $acc = Objects::newInstance(LeaderAccount::CLASS, $row);

                $refl = new ReflectionObject($acc);
                $prop = $refl->getProperty("requiredEquity");
                $prop->setAccessible(true);
                $prop->setValue($acc, floatval($reqEquity));

                $accs[$acc->number()->value()] = $acc;
            }

            return $accs;
        } catch (Throwable $any) {
            $this->logger->error(sprintf(
                "[%s::fetchAccounts(%s, %s)] Exception: %s\n%s",
                self::class,
                $cond,
                json_encode($args),
                $any->getMessage(),
                $any->getTraceAsString()
            ));
            throw new LeaderRepositoryException($any);
        }
    }

    /**
     * @param LeaderAccount $acc
     * @return LeaderAccount
     */
    protected function hydrateFromMt(LeaderAccount $acc): LeaderAccount
    {
        try {
            $tradeAcc = $this->tradeAccGateway->fetchAccountByNumberWithFreshEquity($acc->number(), $acc->broker());

            $reflection = new ReflectionObject($acc);

            $equity = $reflection->getProperty("equity");
            $equity->setAccessible(true);
            $equity->setValue($acc, $tradeAcc->equity()->amount());

            $isSwapFree = $reflection->getProperty('isSwapFree');
            $isSwapFree->setAccessible(true);
            $isSwapFree->setValue($acc, $tradeAcc->isSwapFree());

            return $acc;
        } catch (Throwable $any) {
            $this->logger->error(sprintf(
                "[%s::hydrateFromMt(%s)] Exception: %s\n%s",
                self::class,
                $acc->number(),
                $any->getMessage(),
                $any->getTraceAsString()
            ));
            throw new LeaderRepositoryException($any);
        }
    }

    /**
     * @param LeaderAccount $acc
     * @throws LeaderRepositoryException
     */
    public function store(LeaderAccount $acc): void
    {
        $sql = $this->getStoreSql();
        $data = $acc->toArray();

        $dataSources = $this
            ->metaData
            ->getMetaData($acc->number())
            ->getDataSourceComponent()
        ;

        $ctConnection = $dataSources->getCTConnection();

        $ctConnection->beginTransaction();
        try {
            $stmt = $ctConnection->prepare($sql);
            if (!$stmt->execute($data)) {
                throw new RuntimeException("Coudn't store Leader Account #{$acc->number()}");
            }
            self::$cache[$acc->number()->value()] = $acc;

            $pluginDbConn = $dataSources->getPluginConnection();

            $pluginDbConn->beginTransaction();

            unset($data['broker']);
            unset($data['account_type']);
            unset($data['show_equity']);

            try {
                $pluginDbConn->prepare($this->getPluginStoreSql())->execute($data);
                $pluginDbConn->commit();
            } catch (Throwable $throwable) {
                $pluginDbConn->rollBack();
                throw $throwable;
            }

            $this->updateEventHistory($acc);

            $ctConnection->commit();
        } catch (Throwable $any) {
            $this->logger->error(sprintf(
                "[%s::store(%s)] Exception: %s\n%s",
                self::class,
                $acc,
                $any->getMessage(),
                $any->getTraceAsString()
            ));

            try {
                $ctConnection->rollBack();
            } catch (Throwable $ignore) {
                $this->logger->critical(sprintf(
                    "[%s::store(%s)] Exception during rolling back transaction: %s\n%s",
                    self::class,
                    $acc,
                    $ignore->getMessage(),
                    $ignore->getTraceAsString()
                ));
            }
            throw new LeaderRepositoryException($any);
        }
    }

    /**
     * @param LeaderAccount $acc
     * @return void
     * @throws EventException
     */
    private function updateEventHistory(LeaderAccount $acc): void
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
            INSERT INTO leader_accounts (
                `acc_no`,
                `broker`,
                `account_type`,
                `server`,
                `aggr_acc_no`,
                `owner_id`,
                `acc_name`,
                `prev_acc_name`,
                `acc_curr`,
                `acc_descr`,
                `remun_fee`,
                `balance`,
                `status`,
                `state`,
                `is_copied`,
                `opened_at`,
                `activated_at`,
                `closed_at`,
                `prepare_stats`,
                `is_public`,
                `hidden_reason`,
                `is_followable`,
                `show_equity`
            ) VALUES (
                :acc_no,
                :broker,
                :account_type,
                :server,
                :aggr_acc_no,
                :owner_id,
                :acc_name,
                :prev_acc_name,
                :acc_curr,
                :acc_descr,
                :remun_fee,
                :balance,
                :status,
                :state,
                :is_copied,
                :opened_at,
                :activated_at,
                :closed_at,
                :prepare_stats,
                :is_public,
                :hidden_reason,
                :is_followable,
                :show_equity
            ) ON DUPLICATE KEY UPDATE
                `aggr_acc_no`   = VALUES(`aggr_acc_no`),
                `acc_name`      = VALUES(`acc_name`),
                `prev_acc_name` = VALUES(`prev_acc_name`),
                `acc_descr`     = VALUES(`acc_descr`),
                `remun_fee`     = VALUES(`remun_fee`),
                `balance`       = VALUES(`balance`),
                `is_copied`     = VALUES(`is_copied`),
                `status`        = VALUES(`status`),
                `state`         = VALUES(`state`),
                `activated_at`  = VALUES(`activated_at`),
                `closed_at`     = VALUES(`closed_at`),
                `prepare_stats` = VALUES(`prepare_stats`),
                `is_public`     = VALUES(`is_public`),
                `hidden_reason` = VALUES(`hidden_reason`),
                `is_followable` = VALUES(`is_followable`),
                `show_equity`   = VALUES(`show_equity`)
        ";
    }

    /**
     * @return string
     */
    private function getPluginStoreSql(): string
    {
        return "
            INSERT INTO leader_accounts (
                `acc_no`,
                `server`,
                `aggr_acc_no`,
                `owner_id`,
                `acc_name`,
                `prev_acc_name`,
                `acc_curr`,
                `acc_descr`,
                `remun_fee`,
                `balance`,
                `status`,
                `state`,
                `is_copied`,
                `opened_at`,
                `activated_at`,
                `closed_at`,
                `prepare_stats`,
                `is_public`,
                `hidden_reason`,
                `is_followable`
            ) VALUES (
                :acc_no,
                :server,
                :aggr_acc_no,
                :owner_id,
                :acc_name,
                :prev_acc_name,
                :acc_curr,
                :acc_descr,
                :remun_fee,
                :balance,
                :status,
                :state,
                :is_copied,
                :opened_at,
                :activated_at,
                :closed_at,
                :prepare_stats,
                :is_public,
                :hidden_reason,
                :is_followable
            ) ON DUPLICATE KEY UPDATE
                `aggr_acc_no`   = VALUES(`aggr_acc_no`),
                `acc_name`      = VALUES(`acc_name`),
                `prev_acc_name` = VALUES(`prev_acc_name`),
                `acc_descr`     = VALUES(`acc_descr`),
                `remun_fee`     = VALUES(`remun_fee`),
                `balance`       = VALUES(`balance`),
                `is_copied`     = VALUES(`is_copied`),
                `status`        = VALUES(`status`),
                `state`         = VALUES(`state`),
                `activated_at`  = VALUES(`activated_at`),
                `closed_at`     = VALUES(`closed_at`),
                `prepare_stats` = VALUES(`prepare_stats`),
                `is_public`     = VALUES(`is_public`),
                `hidden_reason` = VALUES(`hidden_reason`),
                `is_followable` = VALUES(`is_followable`)
        ";
    }

    /**
     * @return string
     */
    private function sqlQuery(): string
    {
        return "
            SELECT
                la.*,
                ss.value req_equity
            FROM leader_accounts la
            LEFT JOIN service_settings ss ON ss.setting = CONCAT('leader.min_equity.',(
            CASE
            WHEN la.server = 1 THEN 'ecn_zero'
            WHEN la.server = 2 THEN 'ecn'
            ELSE 'ai_ecn'
            END 
            ))
        ";
    }

    /**
     * Returns array of active leader accounts, aggregate accounts and follower accounts
     * @return array
     * @throws LeaderRepositoryException
     */
    public function getActiveWithAggregatesAndFollowers(): array
    {
        try {
            $stmt = $this
                ->factory
                ->getCTConnection()
                ->executeQuery(sprintf("
                    SELECT server, acc_no, broker, %d AS type
                    FROM leader_accounts
                    WHERE status = 1
                    UNION ALL
                    SELECT IF(broker = '" . Broker::FXTM . "', " . Server::MT5_FXTM . ", " . Server::MT5_AINT . ") AS server, aggr_acc_no, broker, %d AS type
                    FROM leader_accounts
                    WHERE status = 1 AND aggr_acc_no IS NOT NULL
                    UNION ALL
                    SELECT fa.server, fa.acc_no, fa.broker, %d AS type
                    FROM follower_accounts fa
                    JOIN leader_accounts la ON la.acc_no = fa.lead_acc_no
                    WHERE fa.status = 1
                ", self::ACCOUNT_TYPE_LEADER, self::ACCOUNT_TYPE_AGGREGATE, self::ACCOUNT_TYPE_FOLLOWER));

            $accounts = [];
            foreach ($stmt->fetchAllAssociative() as $row) {
                $accounts[$row[self::KEY_SERVER]][$row[self::KEY_ACC_NO]] = [
                    self::KEY_TYPE => $row[self::KEY_TYPE],
                    self::KEY_BROKER => $row[self::KEY_BROKER],
                ];
            }

            return $accounts;
        } catch (Throwable $any) {
            $this->logger->error(sprintf(
                "[%s::getActiveWithAggregatesAndFollowers()] Exception: %s\n%s",
                self::class,
                $any->getMessage(),
                $any->getTraceAsString()
            ));
            throw new LeaderRepositoryException($any);
        }
    }

    public function getAccountsIds(array $onlyAccounts = []): array
    {
        try {
            $onlyAccountsCondition = $this->getOnlyAccountsCondition($onlyAccounts, 'la');

            $stmt = $this
                ->factory
                ->getCTConnection()
                ->executeQuery("
                SELECT
                    la.acc_no as id
                FROM leader_accounts la
                WHERE (
                  la.status = 1 OR (la.activated_at IS NOT NULL AND la.closed_at > DATE_SUB(NOW(), INTERVAL 5 DAY))
                )
                {$onlyAccountsCondition}
            ");

            return $stmt->fetchFirstColumn();
        } catch (Throwable $any) {
            $this->logger->error(sprintf(
                "[%s::getAccountsIds()] Exception: %s\n%s",
                self::class,
                $any->getMessage(),
                $any->getTraceAsString()
            ));
            throw new LeaderRepositoryException($any);
        }
    }

    /**
     * Returns array of data for calculating
     * equity statistics
     * @param array $onlyAccounts
     * @return array
     * @throws LeaderRepositoryException
     */
    public function getForCalculatingStats(array $onlyAccounts = []): array
    {
        $accounts = $this->getAccountsForStatistics($onlyAccounts);
        if (empty($accounts)) {
            return [];
        }

        $result = [];
        foreach ($accounts as $broker => $serverAccounts) {
            foreach ($serverAccounts as $server => $accounts) {
                $accountNumbers = array_column($accounts, 'acc_no');

                $leverages = $this->getLeveragesForStatistics($broker, $accountNumbers);
                $profiles = $this->getProfilesForStatistics($broker, $accountNumbers);
                $testAccounts = $this->findTestAccounts($broker, array_column($accounts, 'owner_id'));
                $closedOrdersCounts = $this->getClosedOrdersCounts($server, $accountNumbers);
                $lastFiveDaysOpenedOrdersCounts = $this->getLastOpenedOrdersCounts($server, '-5 days', $accountNumbers);
                $lastMonthOpenedOrdersCounts = $this->getLastOpenedOrdersCounts($server, '-30 days', $accountNumbers);

                foreach ($accounts as &$account) {
                    $account['profile'] = empty($profiles[$account['owner_id']]) ? [] : $profiles[$account['owner_id']];
                    $account['profile']['acc_description'] = $account['acc_descr'];
                    $account['leverage'] = empty($leverages[$account['acc_no']]['leverage']) ? 0 : $leverages[$account['acc_no']]['leverage'];
                    $account['swap_free'] = empty($leverages[$account['acc_no']]['is_swap_free']) ? 0 : (int) $leverages[$account['acc_no']]['is_swap_free'];
                    $account['is_test'] = empty($testAccounts[$account['owner_id']]) ? 0 : (int) $testAccounts[$account['owner_id']];

                    $account['flags'] = [
                        'eu' => $profiles[$account['owner_id']]['company_id'] == Company::ID_EU,
                        'aby' => $profiles[$account['owner_id']]['company_id'] == Company::ID_ABY,
                    ];

                    $account['closed_orders_count'] = empty($closedOrdersCounts[$account['acc_no']]) ? 0 : $closedOrdersCounts[$account['acc_no']];
                    $account['last_opened_orders_count_5'] = empty($lastFiveDaysOpenedOrdersCounts[$account['acc_no']]) ? 0 : $lastFiveDaysOpenedOrdersCounts[$account['acc_no']];
                    $account['last_opened_orders_count_30'] = empty($lastMonthOpenedOrdersCounts[$account['acc_no']]) ? 0 : $lastMonthOpenedOrdersCounts[$account['acc_no']];
                }

                $result = array_merge($result, $accounts);
            }
        }

        return $result;
    }

    public function getTotalFundsForAccount(AccountNumber $accNo): float
    {
        $accNo = $accNo->value();
        try {
            $stmt = $this
                ->factory
                ->getCTConnection()
                ->prepare('SELECT total_funds_raw FROM leader_equity_stats WHERE acc_no = ?');

            $stmt->execute([$accNo]);

            return floatval($stmt->fetchOne());
        } catch (Throwable $any) {
            $this->logger->error(sprintf(
                "[%s::getArray(%s)] Exception: %s\n%s",
                self::class,
                $accNo,
                $any->getMessage(),
                $any->getTraceAsString()
            ));
            throw new LeaderRepositoryException($any);
        }
    }

    /**
     * Returns array of accounts
     * which should be used to calculating statistics
     *
     * @param array $onlyAccounts
     * @return array
     * @throws LeaderRepositoryException
     */
    private function getAccountsForStatistics(array $onlyAccounts = []): array
    {
        try {
            $onlyAccountsCondition = $this->getOnlyAccountsCondition($onlyAccounts, 'la');

            $stmt = $this
                ->factory
                ->getCTConnection()
                ->query("
                SELECT
                    la.acc_no,
                    la.acc_no,
                    les.max_drawdown as prev_max_draw_down, 
                    la.owner_id,
                    la.acc_name,
                    la.prev_acc_name,
                    la.acc_curr,
                    la.acc_descr,
                    la.remun_fee,
                    la.is_public,
                    la.hidden_reason,
                    la.inact_notice,
                    la.is_followable,
                    la.broker,
                    la.activated_at,
                    la.server,
                    la.min_deposit,
                    la.min_deposit_in_safety_mode,
                    la.show_equity,
                    la.show_trading_details,
                    DATEDIFF(NOW(), la.activated_at) + 1 age_in_days,
                    IFNULL(fa.old_followers, 0) old_followers,
                    IFNULL(fa.old_funds, 0) old_funds,
                    IFNULL(fa.new_followers, 0) new_followers,
                    IFNULL(fa.new_funds, 0) new_funds,
                    IFNULL(fa.total_funds, 0) total_funds_raw,
                    lsd.followers,
                    ac_fs.active_followers_count active_followers_count
                FROM leader_accounts la
                LEFT JOIN leader_equity_stats les ON les.acc_no = la.acc_no
                LEFT JOIN (
                    SELECT
                        fa.lead_acc_no,
                        SUM(IF(fa.activated_at >= DATE_SUB(NOW(), INTERVAL 30 DAY), 1, 0)) new_followers,
                        SUM(IF(fa.activated_at >= DATE_SUB(NOW(), INTERVAL 30 DAY), fa.equity, 0)) new_funds,
                        SUM(IF(fa.activated_at <  DATE_SUB(NOW(), INTERVAL 30 DAY), 1, 0)) old_followers,
                        SUM(IF(fa.activated_at <  DATE_SUB(NOW(), INTERVAL 30 DAY), fa.equity, 0)) old_funds,
                        SUM(fa.equity) as total_funds
                    FROM follower_accounts fa
                    JOIN leader_accounts la ON la.acc_no = fa.lead_acc_no AND la.owner_id != fa.owner_id
                    WHERE fa.status = 1
                    GROUP BY fa.lead_acc_no
                ) AS fa ON fa.lead_acc_no = la.acc_no
                LEFT JOIN (
                    SELECT fa.lead_acc_no, COUNT(DISTINCT fa.owner_id) followers
                    FROM follower_accounts fa
                    JOIN leader_accounts la ON la.acc_no = fa.lead_acc_no AND la.owner_id != fa.owner_id
                    WHERE fa.status = 1
                    GROUP BY fa.lead_acc_no
                ) lsd ON lsd.lead_acc_no = la.acc_no
                LEFT JOIN (
                    SELECT
                        fa.lead_acc_no,
                        COUNT(fa.id) active_followers_count
                    FROM follower_accounts fa
                    WHERE fa.status = 1 AND fa.is_copying = 1
                    GROUP BY fa.lead_acc_no
                ) ac_fs ON ac_fs.lead_acc_no = la.acc_no
                WHERE (
                  la.status = 1 OR (la.activated_at IS NOT NULL AND la.closed_at > DATE_SUB(NOW(), INTERVAL 5 DAY))
                )
                {$onlyAccountsCondition}
            ");

            $accounts = $stmt->fetchAll(\PDO::FETCH_GROUP | \PDO::FETCH_UNIQUE | \PDO::FETCH_ASSOC);

            return $this->splitByBrokerAndServer($accounts);
        } catch (Throwable $any) {
            $this->logger->error(sprintf(
                "[%s::getAccountsForStatistics(...)] Exception: %s\n%s",
                self::class,
                $any->getMessage(),
                $any->getTraceAsString()
            ));
            throw new LeaderRepositoryException($any);
        }
    }

    /**
     * Returns array of accounts
     * which should be used to send Notifications
     *
     * @param array $onlyAccounts
     * @return array
     * @throws LeaderRepositoryException
     */
    private function getAccountsForNotification(array $onlyAccounts): array
    {
        try {
            $onlyAccountsCondition = $this->getOnlyAccountsCondition($onlyAccounts, 'la');

            $stmt = $this
                ->factory
                ->getCTConnection()
                ->query("
                    SELECT
                        la.acc_no,
                        la.acc_no,
                        la.owner_id,
                        la.acc_name,
                        la.broker
                    FROM leader_accounts la
                    WHERE la.status = 1 AND la.is_public = 1 
                    {$onlyAccountsCondition}
                ");

            return $stmt->fetchAll(\PDO::FETCH_GROUP | \PDO::FETCH_UNIQUE | \PDO::FETCH_ASSOC);
        } catch (Throwable $any) {
            $this->logger->error(sprintf(
                "[%s::getAccountsForNotification(...)] Exception: %s\n%s",
                self::class,
                $any->getMessage(),
                $any->getTraceAsString()
            ));
            throw new LeaderRepositoryException($any);
        }
    }

    /**
     * Transforms onlyAccounts condition
     * to part of sql statement
     *
     * @param array $onlyAccounts
     * @param string $alias
     * @return string
     */
    private function getOnlyAccountsCondition(array $onlyAccounts, string $alias): string
    {
        return !empty($onlyAccounts) ? sprintf(' AND %s.acc_no in (%s)', $alias, implode(',', $onlyAccounts)) : '';
    }

    /**
     * @param array $accounts
     * @return array
     */
    private function splitByBrokerAndServer(array $accounts): array
    {
        $result = [];
        foreach ($accounts as $account) {
            $result[$account['broker']][$account['server']][] = $account;
        }

        return $result;
    }

    /**
     * Returns array of leverages
     * for given account numbers
     *
     * @param string $broker
     * @param array $accountNumbers
     * @return array
     * @throws LeaderRepositoryException
     */
    private function getLeveragesForStatistics(string $broker, array $accountNumbers): array
    {
        try {
            $stmt = $this
                ->factory
                ->getMyConnection($broker)
                ->query(
                    'SELECT login, leverage, is_swap_free FROM account WHERE login IN (' . implode(',', $accountNumbers) . ')'
                );

            return $stmt->fetchAll(\PDO::FETCH_ASSOC | \PDO::FETCH_UNIQUE | \PDO::FETCH_GROUP);
        } catch (Throwable $any) {
            $this->logger->error(sprintf(
                "[%s::getLeveragesForStatistics(...)] Exception: %s\n%s",
                self::class,
                $any->getMessage(),
                $any->getTraceAsString()
            ));
            throw new LeaderRepositoryException($any);
        }
    }

    /**
     * Returns account profiles
     * for given account numbers
     *
     * @param string $broker
     * @param array $accountNumbers
     * @return array
     * @throws LeaderRepositoryException
     */
    private function getProfilesForStatistics(string $broker, array $accountNumbers): array
    {
        try {
            if (empty($accountNumbers)) {
                return [];
            }
            $stmt = $this
                ->factory
                ->getCTConnection()
                ->query('
                SELECT DISTINCT lp.leader_id, lp.*, la.acc_no
                FROM leader_profiles lp
                JOIN leader_accounts la ON la.owner_id = lp.leader_id AND la.status = 1
                WHERE la.acc_no IN (' . implode(',', $accountNumbers) . ')
            ');
            $profiles = $stmt->fetchAll(\PDO::FETCH_GROUP | \PDO::FETCH_UNIQUE | \PDO::FETCH_ASSOC);
            if (empty($profiles)) {
                return [];
            }

            $stmt = $this
                ->factory
                ->getMyConnection($broker)
                ->query('
                    SELECT
                        cl.id,
                        CONCAT(cl.name, \' \', cl.forename) fullname,
                        co.code_alpha2 country,
                        cl.company_id
                    FROM client cl
                    LEFT JOIN country co ON co.id = cl.country_reg_id
                    WHERE cl.id IN (' . implode(', ', array_keys($profiles)) . ')
                ');
            $clients = $stmt->fetchAll(\PDO::FETCH_GROUP | \PDO::FETCH_UNIQUE | \PDO::FETCH_ASSOC);

            foreach ($profiles as $leaderId => &$profile) {
                // w/o this check it false into "Incompatible arguments type" on trunk
                if (is_array($clients[$leaderId])) {
                    $profile += $clients[$leaderId];
                }
            }

            return $profiles;
        } catch (Throwable $any) {
            $this->logger->error(sprintf(
                "[%s::getProfilesForStatistics(...)] Exception: %s\n%s",
                self::class,
                $any->getMessage(),
                $any->getTraceAsString()
            ));
            throw new LeaderRepositoryException($any);
        }
    }

    /**
     * @param string $broker
     * @param array $clientIds
     * @return array
     * @throws LeaderRepositoryException
     */
    private function findTestAccounts(string $broker, array $clientIds): array
    {
        try {
            $stmt = $this
                ->factory
                ->getMyConnection($broker)
                ->query('
                    SELECT
                        cl.id,
                        cl.email
                    FROM client cl
                    WHERE cl.id IN (' . implode(', ', $clientIds) . ')
                ');

            $clients = $stmt->fetchAll(\PDO::FETCH_KEY_PAIR);

            $result = [];
            foreach ($clients as $id => $email) {
                $result[$id] = $this->isEmailOfTestClient($email);
            }

            return $result;
        } catch (Throwable $any) {
            $this->logger->error(sprintf(
                "[%s::findTestAccounts(...)] Exception: %s\n%s",
                self::class,
                $any->getMessage(),
                $any->getTraceAsString()
            ));
            throw new LeaderRepositoryException($any);
        }
    }

    /**
     * Returns array with logins and dates of last opened trade positions
     * of the accounts with this logins
     *
     * @param int $server
     * @param array $accountNumbers
     * @param string $days
     * @return array
     * @throws LeaderRepositoryException
     */
    private function getLastOpenedOrdersCounts(int $server, string $days, array $accountNumbers = []): array
    {
        try {
            return $this
                ->tradeOrderGatewayFacade
                ->getForServer($server)
                ->getOrdersCountForLastDays($days, $accountNumbers);
        } catch (Throwable $any) {
            $this->logger->error(sprintf(
                "[%s::getLastOpenedOrdersCounts(...)] Exception: %s\n%s",
                self::class,
                $any->getMessage(),
                $any->getTraceAsString()
            ));
            throw new LeaderRepositoryException($any);
        }
    }

    /**
     * Returns array of logins and counts of closed trade positions
     *
     * @param int $server
     * @param array $accountNumbers
     * @return array
     */
    private function getClosedOrdersCounts(int $server, array $accountNumbers): array
    {
        try {
            return $this
                ->tradeOrderGatewayFacade
                ->getForServer($server)
                ->getClosedOrdersCountForAccounts($accountNumbers);
        } catch (Throwable $any) {
            $this->logger->error(sprintf(
                "[%s::getClosedOrdersCounts(...)] Exception: %s\n%s",
                self::class,
                $any->getMessage(),
                $any->getTraceAsString()
            ));
            throw new LeaderRepositoryException($any);
        }
    }

    /**
     * @param string $email
     * @return bool
     */
    private function isEmailOfTestClient(string $email): bool
    {
        foreach (
            [
                    '@forextime.com',
                    '@fxtm.com',
                    '@forextime.co.uk',
                    '@alpari.org',
                ] as $emailRoot
        ) {
            if (substr($email, -1 * strlen($emailRoot)) === $emailRoot) {
                return true;
            }
        }
        return false;
    }

    /**
     * @param array $data
     * @return bool
     */
    private function isTestAccount(array $data): bool
    {
        return !isset($data['acc_no']) || $data['acc_no'] < 10000;
    }

    /**
     * Returns array with logins that did't trade after date interval
     * @param string $days
     * @return array
     * @throws LeaderRepositoryException
     */
    public function getAccountsWithoutTradingAfterDateInterval(string $days): array
    {
        try {
            $accounts = [];
            foreach (Server::list() as $server) {
                if (!Server::containsLeaders($server)) {
                    continue;
                }

                $logins = $this->tradeOrderGatewayFacade->getForServer($server)->getLoginsWithoutTradingInLastDays($days);
                if (!empty($logins)) {
                    $leadersAccounts = $this->getAccountsForNotification($logins);
                    $accounts = array_merge($accounts, $leadersAccounts);
                }
            }

            return $accounts;
        } catch (Throwable $any) {
            $this->logger->error(sprintf(
                "[%s::getAccountsWithoutTradingAfterDateInterval(%s)] Exception: %s\n%s",
                self::class,
                $days,
                $any->getMessage(),
                $any->getTraceAsString()
            ));
            throw new LeaderRepositoryException($any);
        }
    }

    /**
     * Returns array of leader logins which hasn't
     * any trade activity for last $daysWithoutActivity days
     * @param int $daysWithoutActivity
     * @param int|null $limit
     * @return LeaderAccount[]
     * @throws LeaderRepositoryException
     */
    public function getNotActive(int $daysWithoutActivity, ?int $limit = null): array
    {
        try {
            $date = DateTime::NOW()->modify("-$daysWithoutActivity day");

            $leadersList = $this
                ->factory
                ->getCTConnection()
                ->executeQuery(
                    'SELECT acc_no, activated_at FROM leader_accounts WHERE status = ? AND DATE(activated_at) <= ?',
                    [AccountStatus::ACTIVE, $date->__toString()]
                )
                ->fetchAllAssociative();

            if (count($leadersList) <= 0) {
                return [];
            }

            $activeLeaderLogins = [];
            foreach ($leadersList as $row) {
                $journal = $this
                    ->eventRepository
                    ->findByAccountAndType(new AccountNumber(intval($row['acc_no'])), AccountActivated::type());

                if (
                    !empty(array_filter($journal, function (EventEntity $item) use ($date) {
                        return $date->getTimestamp() < $item->getTimeStamp()->getTimestamp();
                    }))
                ) {
                    continue;
                }
                $activeLeaderLogins[] = intval($row['acc_no']);
            }
            // Logins with trading activities should be excluded
            $tobeExcluded = $this
                ->tradeOrderGatewayFacade
                ->getLoginsWithTradingSince($activeLeaderLogins, $date);

            $logins = array_filter(
                $activeLeaderLogins,
                function ($login) use ($tobeExcluded) {
                    return !in_array($login, $tobeExcluded);
                }
            );

            // Limiting array
            $logins = array_slice($logins, 0, $limit);

            $logins = implode(',', $logins);
        } catch (Throwable $any) {
            $this->logger->error(sprintf(
                "[%s::getNotActive(%s, %s)] Exception: %s\n%s",
                self::class,
                $daysWithoutActivity,
                $limit == null ? 'null' : $limit,
                $any->getMessage(),
                $any->getTraceAsString()
            ));
            throw new LeaderRepositoryException($any);
        }

        return $this->fetchAccounts("acc_no IN ({$logins})", []);
    }

    /**
     * @param int $accountNumber
     * @return array|null
     * @throws LeaderRepositoryException
     */
    public function getArray(int $accountNumber): ?array
    {
        try {
            $dbConn = $this->factory->getCTConnection();

            $getAccountData = function ($accNo) use ($dbConn) {
                $result = $dbConn
                    ->executeQuery(
                        "
                        SELECT
                            la.*,
                            CASE
                                WHEN la.is_public = 1 AND la.is_followable = 1 THEN 1
                                WHEN la.is_public = 0 AND la.is_followable = 1 THEN 2
                                WHEN la.is_public = 0 AND la.is_followable = 0 THEN 3
                                ELSE ''
                            END AS privacy_mode,
                            DATEDIFF(NOW(), la.activated_at) + 1 age_in_days,
                            les.max_drawdown,
                            les.volatility,
                            les.risk_level,
                            les.rank,
                            ifnull((select rank from leader_ranks where acc_no=la.acc_no and type='global'), les.rank) as rank_global,
                            (select rank from leader_ranks where acc_no=la.acc_no and type='eu') as rank_eu,
                            les.rank_points,
                            les.pop,
                            les.pop_points,
                            les.profit,
                            les.profit_1d,
                            les.profit_1m,
                            les.profit_days,
                            les.loss_days,
                            les.avg_equity,
                            les.min_deposit,
                            les.min_deposit_in_safety_mode,
                            i.income,
                            IFNULL(itd.income_td, 0.0) income_td,
                            iyd.income_yd,
                            i1w.income_1w,
                            i1m.income_1m,
                            n.foll_now,
                            n.foll_funds_now,
                            t.foll_today,
                            t.foll_funds_today,
                            ni.next_payout,
                            IF(ni.next_income > 0, ni.next_income, 0) AS next_income,
                            IF(t_name_updated.id IS NULL, 0, 1) is_name_updated,
                            la.broker
                        FROM leader_accounts la
                        LEFT JOIN leader_equity_stats les ON les.acc_no = la.acc_no
                        LEFT JOIN (SELECT acc_no, SUM(income) income_yd FROM leader_stats_daily WHERE `date` = DATE(SUBDATE(NOW(), 1)) AND `acc_no` = ? GROUP BY acc_no) AS iyd ON iyd.acc_no = la.acc_no
                        LEFT JOIN (SELECT acc_no, SUM(income) income_1w FROM leader_stats_daily WHERE `date` BETWEEN DATE(SUBDATE(NOW(), 7)) AND DATE(NOW()) AND `acc_no` = ? GROUP BY acc_no) AS i1w ON i1w.acc_no = la.acc_no
                        LEFT JOIN (SELECT acc_no, SUM(income) income_1m FROM leader_stats_daily WHERE `date` BETWEEN DATE(SUBDATE(NOW(), 30)) AND DATE(NOW()) AND `acc_no` = ? GROUP BY acc_no) AS i1m ON i1m.acc_no = la.acc_no
                        LEFT JOIN (
                            SELECT fa.lead_acc_no, SUM(c.amount) income
                            FROM commission c
                            JOIN follower_accounts fa ON fa.acc_no = c.acc_no
                            WHERE fa.lead_acc_no = ?
                            GROUP BY fa.lead_acc_no
                        ) AS i ON i.lead_acc_no = la.acc_no
                        LEFT JOIN (
                            SELECT fa.lead_acc_no, SUM(c.amount) income_td
                            FROM commission c
                            JOIN follower_accounts fa ON fa.acc_no = c.acc_no
                            WHERE DATE(c.created_at) = DATE(NOW())
                            AND fa.lead_acc_no = ?
                            GROUP BY fa.lead_acc_no
                        ) AS itd ON itd.lead_acc_no = la.acc_no
                        LEFT JOIN (
                            SELECT la.acc_no AS acc_no, COUNT(fa.id) AS foll_now, TRUNCATE(SUM(fa.equity), 2) AS foll_funds_now
                            FROM leader_accounts la
                            JOIN follower_accounts fa ON fa.lead_acc_no = la.acc_no AND fa.status = 1
                            WHERE la.acc_no = ?
                            GROUP BY la.acc_no
                        ) AS n ON n.acc_no = la.acc_no
                        LEFT JOIN (
                            SELECT la.acc_no AS acc_no, COUNT(fa.id) AS foll_today, TRUNCATE(SUM(fa.equity), 2) AS foll_funds_today
                            FROM leader_accounts la
                            JOIN follower_accounts fa ON fa.lead_acc_no = la.acc_no AND fa.status = 1 AND DATE(fa.opened_at) = DATE(NOW())
                            WHERE la.acc_no = ?
                            GROUP BY la.acc_no
                        ) AS t ON t.acc_no = la.acc_no
                        LEFT JOIN (
                            SELECT
                                DATE_FORMAT(fa.next_payout_at, '%d.%m.%Y %H:%i') next_payout,
                                TRUNCATE((fa.equity - fa.settling_equity) / 100 * fa.pay_fee, 2) next_income
                            FROM follower_accounts AS fa
                            WHERE fa.status = 1 AND fa.lead_acc_no = ?
                            ORDER BY fa.settled_at
                            LIMIT 1
                        ) AS ni ON 1
                        LEFT JOIN (
                            SELECT id
                            FROM workflows
                            WHERE corr_id = ? AND `type` = ?
                            LIMIT 1
                        ) AS t_name_updated ON 1
                        WHERE la.acc_no = ?
                    ",
                        [$accNo, $accNo, $accNo, $accNo, $accNo, $accNo, $accNo, $accNo, $accNo, UpdateAccountNameWorkflow::TYPE, $accNo]
                    )
                    ->fetchAssociative();
                if ($result) {
                    return [
                        "acc_no" => intval($result["acc_no"]),
                        "acc_curr" => $result["acc_curr"],
                        "owner_id" => $result["owner_id"],
                        "opened_at" => $result["opened_at"],
                        "activated_at" => $result["activated_at"],
                        "acc_name" => $result["acc_name"],
                        "status" => intval($result["status"]),
                        "remun_fee" => intval($result["remun_fee"]),
                        "is_public" => boolval($result["is_public"]),
                        "hidden_reason" => intval($result["hidden_reason"]),
                        "is_followable" => boolval($result["is_followable"]),
                        "volatility" => floatval($result["volatility"]),
                        "profit" => floatval($result["profit"]),
                        "pop" => intval($result["pop"]),
                        "income" => floatval($result["income"]),
                        "income_td" => floatval($result["income_td"]),
                        "income_yd" => floatval($result["income_yd"]),
                        "income_1w" => floatval($result["income_1w"]),
                        "income_1m" => floatval($result["income_1m"]),
                        "foll_now" => intval($result["foll_now"]),
                        "foll_funds_now" => floatval($result["foll_funds_now"]),
                        "foll_today" => intval($result["foll_today"]),
                        "foll_funds_today" => floatval($result["foll_funds_today"]),
                        "next_payout" => $result["next_payout"],
                        "next_income" => floatval($result["next_income"]),
                        "is_name_updated" => boolval($result["is_name_updated"]),
                        'server' => $result['server'],
                        'avg_equity' => $result['avg_equity'],
                        'profit_days' => $result['profit_days'],
                        'loss_days' => $result['loss_days'],
                        'equity' => $result['equity'],
                        'broker' => $result['broker'],
                        'acc_descr' => $result['acc_descr'],
                        'show_equity' => $result['show_equity'],
                    ];
                }

                return [];
            };

            $getLastMonthDailyStats = function ($accNo, $data) use ($dbConn) {
                $stmt = $dbConn->prepare("SELECT `date`, followers, funds, income FROM leader_stats_daily WHERE acc_no = ? AND `date` > DATE(SUBDATE(NOW(), 30))");
                $stmt->execute([$accNo]);
                $stats = [];
                foreach ($stmt->fetchAllAssociative() as $row) {
                    $stats[$row["date"]] = [
                        "date" => $row["date"],
                        "followers" => intval($row["followers"]),
                        "funds" => floatval($row["funds"]),
                        "income" => floatval($row["income"]),
                    ];
                }
                $stats[strftime("%Y-%m-%d")] = [
                    "date" => strftime("%Y-%m-%d"),
                    "followers" => intval($data["foll_now"]),
                    "funds" => floatval($data["foll_funds_now"]),
                    "income" => floatval($data["income_td"]),
                ];
                return $stats;
            };

            $getAllTimeMonthlyStats = function ($accNo) use ($dbConn) {
                $stmt = $dbConn->prepare("
                SELECT DATE_FORMAT(DATE_SUB(m.first_day, INTERVAL 1 MONTH), '%Y-%m') date, lsd1.followers, lsd1.funds, SUM(lsd2.income) AS income
                FROM leader_stats_daily lsd1
                JOIN (SELECT MIN(date) first_day FROM leader_stats_daily WHERE acc_no = ? GROUP BY DATE_FORMAT(date, '%Y-%m')) AS m ON m.first_day = lsd1.date
                LEFT JOIN leader_stats_daily lsd2 ON lsd2.acc_no = lsd1.acc_no AND lsd2.date <= m.first_day AND lsd2.date > DATE_FORMAT(DATE_SUB(m.first_day, INTERVAL 1 MONTH), '%Y-%m-01')
                WHERE lsd1.acc_no = ?
                GROUP BY date
                HAVING date >= DATE_FORMAT((SELECT MIN(date) FROM leader_stats_daily WHERE acc_no = ?), '%Y-%m')
                ORDER BY lsd1.date
            ");
                $stmt->execute([$accNo, $accNo, $accNo]);

                $stats = [];
                foreach ($stmt->fetchAllAssociative() as $row) {
                    $stats[$row["date"]] = [
                        "date" => $row["date"],
                        "followers" => intval($row["followers"]),
                        "funds" => floatval($row["funds"]),
                        "income" => floatval($row["income"]),
                    ];
                }
                return $stats;
            };

            $getCommissions = function ($accNo) use ($dbConn) {
                $stmt = $dbConn->prepare("
                SELECT
                    workflow_id,
                    acc_no,
                    DATE_FORMAT(created_at, '%d.%m.%Y %H:%i') date,
                    amount,
                    type reason,
                    comment
                FROM commission
                WHERE amount > 0 AND acc_no IN (SELECT acc_no FROM follower_accounts WHERE lead_acc_no = ?)
                ORDER BY created_at
            ");
                $stmt->execute([$accNo]);

                $commissions = [];
                foreach ($stmt->fetchAllAssociative() as $row) {
                    $commissions[$row["workflow_id"]] = [
                        "acc_no" => $row["acc_no"],
                        "date" => $row["date"],
                        "amount" => floatval($row["amount"]),
                        "reason" => intval($row["reason"]),
                        "comment" => $row["comment"],
                    ];
                }
                return $commissions;
            };

            $getLeverage = function ($accNo, $broker) {
                $stmt = $this
                    ->factory
                    ->getMyConnection($broker)
                    ->prepare('SELECT leverage FROM account WHERE login = ?');

                $stmt->execute([$accNo]);

                return intval($stmt->fetchOne());
            };

            $getLeaderProfile = function ($accNo, $broker) {

                $stmt = $this
                    ->factory
                    ->getCTConnection()
                    ->prepare("
                       SELECT lp.*, la.acc_no
                       FROM leader_profiles lp
                       JOIN leader_accounts la ON la.owner_id = lp.leader_id
                       WHERE la.acc_no = ?
                ");

                if (!$stmt->execute([$accNo])) {
                    return [];
                }

                $profile = $stmt->fetchAssociative();
                if (empty($profile)) {
                    return [];
                }

                $stmt = $this
                    ->factory
                    ->getMyConnection($broker)
                    ->prepare("
                   SELECT
                       cl.id,
                       CONCAT(cl.name, ' ', cl.forename) fullname,
                       co.code_alpha2 country,
                       cl.company_id
                   FROM client cl
                   LEFT JOIN country co ON co.id = cl.country_reg_id
                   WHERE cl.id = ?
               ");
                $stmt->execute([$profile['leader_id']]);
                $client = $stmt->fetchAssociative();
                if (empty($client)) {
                    $client = [];
                }
                return $profile + $client;
            };

            if (empty($data = $getAccountData($accountNumber))) {
                return null;
            }

            $data['closed_orders_count'] = $this->tradeOrderGatewayFacade
                ->getForServer($data['server'])
                ->getClosedOrdersCountForAccounts([$accountNumber])[$accountNumber];
            $data['last_opened_orders_count_5'] = $this->tradeOrderGatewayFacade
                ->getForServer($data['server'])
                ->getOrdersCountForLastDays('-5 days', [$accountNumber])[$accountNumber];
            $data['leverage'] = $getLeverage($accountNumber, $data['broker']);
            $data['profile'] = $getLeaderProfile($accountNumber, $data['broker']);
            $data['profile']['acc_description'] = $data['acc_descr'];

            $data["daily_stats"]["last_month"] = $getLastMonthDailyStats($accountNumber, $data);
            $data["daily_stats"]["all_time"] = $getAllTimeMonthlyStats($accountNumber);
            $data["commissions"] = $getCommissions($accountNumber);
            $data["investors"] = $this->getInvestors($accountNumber);
            $data['is_test'] = $this->isTestAccount($data);

            $data['top_checkpoints'] = $this->topSuitabilityService->getTopCheckpoints($data);

            return $data;
        } catch (Throwable $any) {
            $this->logger->error(sprintf(
                "[%s::getArray(%s)] Exception: %s\n%s",
                self::class,
                $accountNumber,
                $any->getMessage(),
                $any->getTraceAsString()
            ));
            throw new LeaderRepositoryException($any);
        }
    }

    /**
     * @param int $accountNumber
     * @return array
     * @throws LeaderRepositoryException
     */
    public function getTradeMonthlyStats(int $accountNumber): array
    {
        try {
            return $this
                ->factory
                ->getCTConnection()
                ->executeQuery('SELECT quantity, date FROM site_review_quantity WHERE acc_id = ? ORDER BY date ASC', [$accountNumber])
                ->fetchAllAssociative();
        } catch (Throwable $any) {
            $this->logger->error(sprintf(
                "[%s::getTradeMonthlyStats(%s)] Exception: %s\n%s",
                self::class,
                $accountNumber,
                $any->getMessage(),
                $any->getTraceAsString()
            ));
            throw new LeaderRepositoryException($any);
        }
    }

    /**
     * @param int $clientId
     * @return array
     * @throws LeaderRepositoryException
     */
    public function getReferrable(int $clientId): array
    {
        try {
            return $this
                ->factory
                ->getCTConnection()
                ->executeQuery("
                SELECT
                    la.acc_no   lead_acc_no,
                    la.acc_name lead_acc_name,
                    les.profit  lead_acc_profit
                FROM leader_accounts la
                JOIN leader_equity_stats les ON les.acc_no = la.acc_no
                WHERE la.owner_id = ? AND la.status = 1 AND la.is_public = 1 AND la.is_followable = 1
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
            throw new LeaderRepositoryException($any);
        }
    }

    /**
     * @param int|null $accNumber
     * @param string|null $accName
     * @param int|null $limit
     * @param int|null $offset
     * @return array
     * @throws LeaderRepositoryException
     */
    public function getInvestors(?int $accNumber = null, ?string $accName = null, ?int $limit = null, ?int $offset = null): array
    {
        $where = "WHERE fa.status = ? ";
        $executeArgs = [AccountStatus::ACTIVE];

        if ($accNumber) {
            $where .= " AND fa.lead_acc_no = ?";
            $executeArgs[] = $accNumber;
        }
        if ($accName) {
            $where .= " AND la.acc_name = ?";
            $executeArgs[] = $accName;
        }

        $limitOffset = "";
        if ($limit) {
            $limitOffset = "LIMIT $limit ";
            if ($offset) {
                $limitOffset .= "OFFSET $offset ";
            }
        }

        try {
            $stmt = $this
                ->factory
                ->getCTConnection()
                ->prepare("
                SELECT
                    fa.acc_no,
                    fa.is_copying,
                    fa.equity funds,
                    DATE_FORMAT(fa.next_payout_at, '%d.%m.%Y %H:%i') next_payout,
                    fa.pay_fee,
                    (fa.equity - fa.settling_equity) AS profit
                FROM follower_accounts AS fa
                JOIN leader_accounts la ON la.acc_no = fa.lead_acc_no
                {$where}
                ORDER BY fa.settled_at
                {$limitOffset}
            ");
            $stmt->execute($executeArgs);

            $investors = [];
            foreach ($stmt->fetchAllAssociative() as $row) {
                $investors[$row["acc_no"]] = [
                    "acc_no" => $row["acc_no"],
                    "is_copying" => boolval($row["is_copying"]),
                    "funds" => floatval($row["funds"]),
                    "next_payout" => $row["next_payout"],
                    "pay_fee" => intval($row["pay_fee"]),
                    "profit" => floatval($row["profit"]),
                ];
            }

            return $investors;
        } catch (Throwable $any) {
            $this->logger->error(sprintf(
                "[%s::getInvestors(%s, %s, %s, %s)] Exception: %s\n%s",
                self::class,
                $accNumber == null ? 'null' : $accNumber,
                $accName == null ? 'null' : $accName,
                $limit == null ? 'null' : $limit,
                $offset == null ? 'null' : $offset,
                $any->getMessage(),
                $any->getTraceAsString()
            ));
            throw new LeaderRepositoryException($any);
        }
    }

    /**
     * @param string $accName
     * @return int
     *
     * @throws LeaderRepositoryException
     */
    public function getAccountNumberByName(string $accName): int
    {
        try {
            return intval(
                $this->factory
                    ->getCTConnection()
                    ->executeQuery("
                        SELECT
                            la.acc_no
                        FROM leader_accounts AS la
                        WHERE la.acc_name = ? 
                    ", [$accName])
                    ->fetchOne()
            );
        } catch (Throwable $any) {
            $this->logger->error(sprintf(
                "[%s::getAccountNumberByName(%s)] Exception: %s\n%s",
                self::class,
                $accName,
                $any->getMessage(),
                $any->getTraceAsString()
            ));
            throw new LeaderRepositoryException($any);
        }
    }

    /**
     * @return LeaderAccount[]
     * @throws LeaderRepositoryException
     */
    public function findInconsistentAccounts(): array
    {
        $logins = [];
        try {
            foreach ($this->factory->getAllPluginConnections() as $pluginDbConn) {
                $statement = $pluginDbConn->prepare('SELECT login FROM plugin_leaders_view');
                $statement->execute();
                while (($login = $statement->fetchOne()) != false) {
                    $logins[] = intval($login);
                }
            }
            $questionMarks = implode(', ', array_fill(0, count($logins), '?'));
        } catch (Throwable $any) {
            $this->logger->error(sprintf(
                "[%s::findInconsistentAccounts()] Exception: %s\n%s",
                self::class,
                $any->getMessage(),
                $any->getTraceAsString()
            ));
            throw new LeaderRepositoryException($any);
        }
        return $this->fetchAccounts(
            sprintf('`status` != ? AND `acc_no` IN (%s)', $questionMarks),
            array_merge([AccountStatus::ACTIVE], $logins)
        );
    }
}
