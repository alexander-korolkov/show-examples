<?php

namespace Fxtm\CopyTrading\Interfaces\Persistence\LeaderProfile;

use Doctrine\DBAL\Connection;
use Fxtm\CopyTrading\Domain\Common\Objects;
use Fxtm\CopyTrading\Domain\Model\Client\ClientId;
use Fxtm\CopyTrading\Domain\Model\LeaderProfile\LeaderProfile;
use Fxtm\CopyTrading\Domain\Model\LeaderProfile\LeaderProfileRepository;
use PDO;

class MySqlLeaderProfileRepository implements LeaderProfileRepository
{
    protected $dbConn = null;
    protected static $cache = [];

    public function __construct(Connection $dbConn)
    {
        $this->dbConn = $dbConn;
    }

    public function find(ClientId $clientId)
    {
        $leaderId = $clientId->value();
        if (empty(self::$cache[$leaderId])) {
            if (empty($profiles = $this->fetchProfiles("leader_id = ?", $leaderId)) || empty($profiles[$leaderId])) {
                return null;
            }
            self::$cache[$leaderId] = $profiles[$leaderId];
        }
        return self::$cache[$leaderId];
    }

    public function isUniqueNickname($nickname)
    {
        return empty($this->fetchProfiles("nickname = ?", $nickname));
    }

    public function store(LeaderProfile $profile)
    {
        $stmt = $this->dbConn->prepare("
            INSERT INTO leader_profiles (
                `leader_id`,
                `avatar`,
                `nickname`,
                `use_nickname`,
                `show_name`,
                `show_country`,
                `updated_at`
            ) VALUES (
                :leader_id,
                :avatar,
                :nickname,
                :use_nickname,
                :show_name,
                :show_country,
                :updated_at
            ) ON DUPLICATE KEY UPDATE
                `avatar`       = VALUES(`avatar`),
                `nickname`     = VALUES(`nickname`),
                `use_nickname` = VALUES(`use_nickname`),
                `show_name`    = VALUES(`show_name`),
                `show_country` = VALUES(`show_country`),
                `updated_at`   = VALUES(`updated_at`)
        ");
        $result = $stmt->execute($profile->toArray());
        self::$cache[$this->dbConn->lastInsertId()] = $profile;
        return $result;
    }

    private function fetchProfiles($cond, $args)
    {
        $args = func_get_args();
        $cond = array_shift($args);

        $stmt = $this->dbConn->prepare("{$this->sqlQuery()} WHERE {$cond}");
        $stmt->execute($args);

        $profiles = [];
        while (($row = $stmt->fetch(PDO::FETCH_ASSOC))) {
            $profile = Objects::newInstance(LeaderProfile::CLASS, $row);
            $profiles[$profile->leaderId()->value()] = $profile;
        }
        return $profiles;
    }

    private function sqlQuery()
    {
        return "SELECT lp.* FROM leader_profiles lp";
    }

    /**
     * @param ClientId $clientId
     * @return LeaderProfile
     */
    public function findOrNew(ClientId $clientId)
    {
        if (empty($profile = $this->find($clientId))) {
            $profile = new LeaderProfile($clientId);
        }

        return $profile;
    }
}
