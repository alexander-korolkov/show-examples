<?php
/**
 * Created by PhpStorm.
 * User: Eugene.Karpov
 * Date: 5/11/2018
 * Time: 11:53 AM
 */

namespace Fxtm\CopyTrading\Interfaces\Adapter\PDO;

use Doctrine\DBAL\Driver\Connection;
use PDO;

class PDOReconnectable implements Connection
{

    const CONNECT_RETRIES = 5;

    /**
     * @var PDO
     */
    private $pdoInstance;
    private $opts = [];
    private $loggerCallback;

    public function __construct($dsn, $username = null, $passwd = null, $options = null)
    {
        $this->opts['dsn'] = $dsn;
        $this->opts['username'] = $username;
        $this->opts['password'] = $passwd;
        $this->opts['options'] = $options;
    }

    public function setLoggerCallback(callable $callback)
    {
        $this->loggerCallback = $callback;
    }

    public function prepare($statement, $driver_options = null)
    {
        $pdoStatement = $this->proxyCall(__FUNCTION__, func_get_args());
        return new PDOStatementReconnectable($this, $pdoStatement);
    }

    public function beginTransaction()
    {
        return $this->proxyCall(__FUNCTION__, func_get_args());
    }

    public function commit()
    {
        return $this->proxyCall(__FUNCTION__, func_get_args());
    }

    public function rollBack()
    {
        return $this->proxyCall(__FUNCTION__, func_get_args());
    }

    public function inTransaction()
    {
        return $this->proxyCall(__FUNCTION__, func_get_args());
    }

    public function setAttribute($attribute, $value)
    {
        return $this->proxyCall(__FUNCTION__, func_get_args());
    }

    public function exec($statement)
    {
        return $this->proxyCall(__FUNCTION__, func_get_args());
    }

    public function query($statement = null, $mode = \PDO::ATTR_DEFAULT_FETCH_MODE, $arg3 = null, array $ctorargs = array())
    {
        return $this->proxyCall(__FUNCTION__, func_get_args());
    }

    public function lastInsertId($name = null)
    {
        return $this->proxyCall(__FUNCTION__, func_get_args());
    }

    public function errorCode()
    {
        return $this->proxyCall(__FUNCTION__, func_get_args());
    }

    public function errorInfo()
    {
        return $this->proxyCall(__FUNCTION__, func_get_args());
    }

    public function getAttribute($attribute)
    {
        return $this->proxyCall(__FUNCTION__, func_get_args());
    }

    public function quote($string, $parameter_type = \PDO::PARAM_STR)
    {
        return $this->proxyCall(__FUNCTION__, func_get_args());
    }

    public function checkConnectionAndReconnectIfItNeeds()
    {

        $connectRetries = self::CONNECT_RETRIES;

        try {
            retry:
            $this->pdoInstance->query("SELECT 1;");
        } catch (\PDOException $ex) {

            if (!$connectRetries) {
                $this->log("PDOReconnectable reconnection failed due to Exception: \n" . $ex . "\n");
                throw $ex;
            }

            --$connectRetries;

            usleep(50);
            // refresh DB connection
            $this->connect();
            $this->log("PDOReconnectable reconnected successfully after Exception: \n" . $ex . "\n");
            goto retry;
        }
    }

    private function connect()
    {
        $this->pdoInstance = new PDO(
            $this->opts['dsn'],
            $this->opts['username'],
            $this->opts['password'],
            $this->opts['options']
        );
    }

    private function proxyCall($methodName, $args = [])
    {
        if (!$this->pdoInstance) {
            $this->connect();
            $this->log("PDOReconnectable first connection is initialized");
        }

        $this->checkConnectionAndReconnectIfItNeeds();

        return call_user_func_array([$this->pdoInstance, $methodName], $args);
    }

    private function log($msg, $params = null)
    {
        if ($this->loggerCallback) {
            call_user_func_array($this->loggerCallback, func_get_args());
        }
    }

}