<?php


namespace Fxtm\CopyTrading\Interfaces\Adapter\PDO;


use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\Driver;
use PDOException;

class DoctrineDriver extends Driver\AbstractMySQLDriver implements Driver
{

    /**
     * @inheritDoc
     * @throws DBALException
     */
    public function connect(array $params, $username = null, $password = null, array $driverOptions = []) : Driver\Connection
    {
        try {
            $conn = new PDOReconnectable(
                $this->constructPdoDsn($params),
                $username,
                $password,
                $driverOptions
            );
        }
        catch (PDOException $e) {
            throw DBALException::driverException($this, $e);
        }

        return $conn;
    }

    /**
     * The code blow is copy-paste from original implementation
     *
     * Constructs the MySql PDO DSN.
     *
     * @param mixed[] $params
     *
     * @return string The DSN.
     */
    protected function constructPdoDsn(array $params)
    {
        $dsn = 'mysql:';
        if (isset($params['host']) && $params['host'] !== '') {
            $dsn .= 'host=' . $params['host'] . ';';
        }
        if (isset($params['port'])) {
            $dsn .= 'port=' . $params['port'] . ';';
        }
        if (isset($params['dbname'])) {
            $dsn .= 'dbname=' . $params['dbname'] . ';';
        }
        if (isset($params['unix_socket'])) {
            $dsn .= 'unix_socket=' . $params['unix_socket'] . ';';
        }
        if (isset($params['charset'])) {
            $dsn .= 'charset=' . $params['charset'] . ';';
        }

        return $dsn;
    }

    /**
     * @inheritDoc
     */
    public function getName()
    {
        return 'pdo_mysql_reconnectable';
    }

}