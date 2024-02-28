<?php
/**
 * Created by PhpStorm.
 * User: Eugene.Karpov
 * Date: 5/11/2018
 * Time: 11:53 AM
 */

namespace Fxtm\CopyTrading\Interfaces\Adapter\PDO;

class PDOStatementReconnectable extends \PDOStatement
{
    private $pdoReconnectable;
    private $pdoStatementInstance;
    private $driverOptions;

    private $methodsCallHistory = [];

    public function __construct(PDOReconnectable $pdoReconnectable, \PDOStatement $pdoStatementInstance, $driverOptions = [])
    {
        $this->pdoReconnectable = $pdoReconnectable;
        $this->pdoStatementInstance = $pdoStatementInstance;
        $this->driverOptions = $driverOptions;
    }

    public function execute($input_parameters = null)
    {
        return $this->proxyCall(__FUNCTION__, func_get_args());
    }

    public function fetch($fetch_style = null, $cursor_orientation = \PDO::FETCH_ORI_NEXT, $cursor_offset = 0)
    {
        return $this->proxyCall(__FUNCTION__, func_get_args());
    }

    public function bindParam($parameter, &$variable, $data_type = \PDO::PARAM_STR, $length = null, $driver_options = null)
    {
        return $this->proxyCall(__FUNCTION__, func_get_args(), true);
    }

    public function bindColumn($column, &$param, $type = null, $maxlen = null, $driverdata = null)
    {
        return $this->proxyCall(__FUNCTION__, func_get_args(), true);
    }

    public function bindValue($parameter, $value, $data_type = \PDO::PARAM_STR)
    {
        return $this->proxyCall(__FUNCTION__, func_get_args(), true);
    }

    public function rowCount()
    {
        return $this->proxyCall(__FUNCTION__, func_get_args());
    }

    public function fetchColumn($column_number = 0)
    {
        return $this->proxyCall(__FUNCTION__, func_get_args());
    }

    public function fetchAll($fetch_style = null, $fetch_argument = null, $ctor_args = null)
    {
        return $this->proxyCall(__FUNCTION__, func_get_args());
    }

    public function fetchObject($class_name = "stdClass", $ctor_args = null)
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

    public function setAttribute($attribute, $value)
    {
        return $this->proxyCall(__FUNCTION__, func_get_args(), true);
    }

    public function getAttribute($attribute)
    {
        return $this->proxyCall(__FUNCTION__, func_get_args());
    }

    public function columnCount()
    {
        return $this->proxyCall(__FUNCTION__, func_get_args());
    }

    public function getColumnMeta($column)
    {
        return $this->proxyCall(__FUNCTION__, func_get_args());
    }

    public function setFetchMode($mode, $params = null)
    {
        return $this->proxyCall(__FUNCTION__, func_get_args(), true);
    }

    public function nextRowset()
    {
        return $this->proxyCall(__FUNCTION__, func_get_args());
    }

    public function closeCursor()
    {
        return $this->proxyCall(__FUNCTION__, func_get_args());
    }

    public function debugDumpParams()
    {
        return $this->proxyCall(__FUNCTION__, func_get_args());
    }

    private function proxyCall($method, $args = [], $saveHistory = false)
    {
        try {
            $result = call_user_func_array([$this->pdoStatementInstance, $method], $args);

            $this->methodsCallHistory[] = [
                'method' => $method,
                'args' => $args,
            ];

            return $result;
        } catch(\PDOException $ex) {
            $this->pdoReconnectable->checkConnectionAndReconnectIfItNeeds();

            $this->pdoStatementInstance = $this->pdoReconnectable->prepare(
                $this->pdoStatementInstance->queryString,
                $this->driverOptions
            );
            foreach ($this->methodsCallHistory as $callData) {
                call_user_func_array([$this->pdoStatementInstance, $callData['method']], $callData['args']);
            }

            return call_user_func_array([$this->pdoStatementInstance, $method], $args);
        }
    }

}