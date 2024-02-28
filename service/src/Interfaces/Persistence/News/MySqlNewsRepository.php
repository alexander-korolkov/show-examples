<?php

namespace Fxtm\CopyTrading\Interfaces\Persistence\News;

use Doctrine\DBAL\Connection;
use Fxtm\CopyTrading\Domain\Common\Objects;
use Fxtm\CopyTrading\Domain\Model\Follower\AccountStatus;
use Fxtm\CopyTrading\Domain\Model\News\News;
use Fxtm\CopyTrading\Domain\Model\News\NewsRepository;
use Fxtm\CopyTrading\Domain\Model\News\NewsStatus;
use Fxtm\CopyTrading\Domain\Model\Shared\AccountNumber;
use PDO;
use RuntimeException;

class MySqlNewsRepository implements NewsRepository
{
    private $dbConn;

    private static $cache = [];

    public function __construct(Connection $dbConn)
    {
        $this->dbConn = $dbConn;
    }

    public function clearCache()
    {
        self::$cache = [];
    }

    public function get($id)
    {
        if (empty($news = $this->find($id))) {
            throw new RuntimeException("News #{$id} not found");
        }
        return $news;
    }

    public function find($id)
    {
        if (empty(self::$cache[$id])) {
            if (empty($news = $this->fetchNews("id = ?", $id))) {
                return null;
            }
            self::$cache[$news[0]->id()] = $news[0];
        }
        return self::$cache[$id];
    }

    public function findOneUnderReview(AccountNumber $accNo)
    {
        /* @var $news News */
        $found = array_filter(
            self::$cache,
            function (News $news) use ($accNo) {
                return $news->leaderAccountNumber()->isSameValueAs($accNo) && $news->isUnderReview();
            }
        );

        if (empty($news = array_values($found))) {
            if (empty($news = $this->fetchNews("acc_no = ? AND status = ?", $accNo->value(), NewsStatus::UNDER_REVIEW))) {
                return null;
            }
            self::$cache[$news[0]->id()] = $news[0];
        }
        return $news[0];
    }

    /**
     * @param string $cond
     * @param mixed $args
     * @return News[]
     * @throws \Doctrine\DBAL\DBALException
     */
    private function fetchNews($cond, $args)
    {
        $args   = func_get_args();
        $cond   = array_shift($args);
        $params = array_reduce(
            $args,
            function ($tmp, $item) {
                return array_merge($tmp, (array) $item);
            },
            []
        );

        $stmt = $this->dbConn->prepare("SELECT * FROM leader_accounts_news WHERE {$cond}");
        $stmt->execute($params);

        $news = [];
        while (($row = $stmt->fetch(PDO::FETCH_ASSOC))) {
            /* @var $new News */
            $new = Objects::newInstance(News::CLASS, $row);
            $news[] = $new;
        }
        return $news;
    }

    public function store(News $news)
    {
        $sql = "
            INSERT INTO leader_accounts_news (
                `id`,
                `acc_no`,
                `title`,
                `text`,
                `status`,
                `submitted_at`,
                `updated_at`,
                `reviewed_at`
            ) VALUES (
                :id,
                :acc_no,
                :title,
                :text,
                :status,
                :submitted_at,
                :updated_at,
                :reviewed_at
            ) ON DUPLICATE KEY UPDATE
                `title`       = VALUES(`title`),
                `text`        = VALUES(`text`),
                `status`      = VALUES(`status`),
                `updated_at`  = VALUES(`updated_at`),
                `reviewed_at` = VALUES(`reviewed_at`)
        ";

        $stmt = $this->dbConn->prepare($sql);
        if (!$stmt->execute($news->toArray())) {
            throw new RuntimeException("Coudn't store News #{$news->id()}");
        }

        if (empty($news->id())) {
            $news->fromArray(array_merge($news->toArray(), ["id" => $this->dbConn->lastInsertId()]));
        }
        self::$cache[$news->id()] = $news;
    }

    /**
     * Returns all approved news
     * Filtered by given manager's account number or follower's client id
     *
     * @param string|null $accountNumber
     * @param string|null $clientId
     * @param bool|null $onlyApproved
     * @param string|null $rankType
     * @param bool|null $isPublic
     * @param int|null $limit
     * @param int|null $offset
     * @return array
     */
    public function getAll(
        $accountNumber = null,
        $clientId = null,
        $onlyApproved = null,
        $rankType = null,
        $isPublic = null,
        ?int $limit = null,
        ?int $offset = null
    ) {
        $sql = '
            SELECT
             n.id,
             n.title,
             n.text,
             n.status,
             n.submitted_at as submittedAt,
             n.updated_at as updatedAt,
             n.reviewed_at as reviewedAt,
             les.acc_no as accountNumber,
             les.acc_name as accountName,
             les.manager_name as managerName,
             les.avatar as avatar,
             les.country as country,
             les.acc_curr as currency,
             les.is_veteran as isVeteran,
             les.volatility as volatility,
             les.risk_level as riskLevel,
             les.chart as chart
            FROM leader_accounts_news n
            INNER JOIN leader_equity_stats les on les.acc_no = n.acc_no
            WHERE 1 = 1 
        ';

        $sql = $this->addConditionsToNewsSql($sql, $accountNumber, $clientId, $onlyApproved, $rankType, $isPublic);

        $sql .= ' ORDER BY n.reviewed_at DESC';

        if (!is_null($limit)) {
            $sql .= ' LIMIT ' . $limit;
        }

        if (!is_null($offset)) {
            $sql .= ' OFFSET ' . $offset;
        }

        return $this->dbConn->fetchAll($sql);
    }

    /**
     * @throws \Doctrine\DBAL\Driver\Exception
     * @throws \Doctrine\DBAL\Exception
     */
    public function count(
        ?string $accountNumber = null,
        ?string $clientId = null,
        ?bool $onlyApproved = null,
        ?string $rankType = null,
        ?bool $isPublic = null
    ): int {
        $sql = '
            SELECT COUNT(*) FROM leader_accounts_news n
            INNER JOIN leader_equity_stats les on les.acc_no = n.acc_no
            WHERE 1 = 1 
        ';

        $sql = $this->addConditionsToNewsSql($sql, $accountNumber, $clientId, $onlyApproved, $rankType, $isPublic);

        $stmt = $this->dbConn->prepare($sql)->executeQuery();
        return intval($stmt->fetchOne());
    }

    private function addConditionsToNewsSql(
        string $sql,
        ?string $accountNumber = null,
        ?string $clientId = null,
        ?bool $onlyApproved = null,
        ?string $rankType = null,
        ?bool $isPublic = null
    ): string {
        if ($accountNumber !== null) {
            $sql .= " AND n.acc_no = $accountNumber";
        } elseif ($clientId !== null) {
            $sql .= " AND n.acc_no IN (
                SELECT lead_acc_no FROM follower_accounts WHERE owner_id = $clientId AND status != " .
                AccountStatus::CLOSED
                . ")";
        }
        if ($isPublic) {
            $sql .= " AND les.is_public = 1";
        }

        if ($onlyApproved) {
            $sql .= " AND n.status = " . NewsStatus::APPROVED;
        }

        if ($rankType == 'eu') {
            $sql .= " AND les.flags = 'eu'";
        } elseif ($rankType == 'aby') {
            $sql .= " AND les.flags = 'aby'";
        } elseif ($rankType == 'global') {
            $sql .= " AND les.flags != 'eu' AND les.flags != 'aby'";
        }

        return $sql;
    }

    /**
     * Returns array of news data by given id
     *
     * @param string $id
     * @return array
     * @throws \Doctrine\DBAL\DBALException
     */
    public function getAsArray($id)
    {
        $sql = '
            SELECT
             n.id,
             n.title,
             n.text,
             n.status,
             n.submitted_at as submittedAt,
             n.updated_at as updatedAt,
             n.reviewed_at as reviewedAt,
             les.acc_no as accountNumber,
             les.acc_name as accountName,
             les.manager_name as managerName,
             les.avatar as avatar,
             les.country as country,
             les.acc_curr as currency,
             les.is_veteran as isVeteran,
             les.volatility as volatility,
             les.risk_level as riskLevel,
             les.chart as chart
            FROM leader_accounts_news n
            INNER JOIN leader_equity_stats les on les.acc_no = n.acc_no
            WHERE n.id = :news_id
        ';

        return $this->dbConn->fetchAssoc($sql, ['news_id' => $id]);
    }
}
