<?php

namespace Fxtm\CopyTrading\Interfaces\Persistence\Settings;

use Doctrine\DBAL\Connection;
use Fxtm\CopyTrading\Application\SettingsRegistry;
use PDO;

class MySqlSettingsRegistry implements SettingsRegistry
{
    private $dbConn = null;
    private static $cache = [];

    public function __construct(Connection $dbConn)
    {
        $this->dbConn = $dbConn;
    }

    public function get($key, $default = null)
    {
        if (empty(self::$cache[$key])) {
            $stmt = $this->dbConn->prepare("SELECT `value` FROM `service_settings` WHERE `setting` = :setting");
            $stmt->execute(["setting" => $key]);
            self::$cache[$key] = $stmt->fetchColumn() ?: $default;
        }
        return self::$cache[$key];
    }

    public function set($key, $value)
    {
        $stmt = $this->dbConn->prepare("
            INSERT INTO `service_settings` (
                `setting`,
                `value`
            ) VALUES (
                :setting,
                :value
            ) ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)
        ");
        $result = $stmt->execute(["setting" => $key, "value" => $value]);
        self::$cache[$key] = $value;
        return $result;
    }

    public function getAll()
    {
        $stmt = $this->dbConn->query("SELECT `setting`, `value` FROM `service_settings`");
        return $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    }
}
