<?php

namespace Fxtm\CopyTrading\Interfaces\Persistence\Query;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver\Connection as DriverConnection;

abstract class Upsert
{
    /**
     * @var string
     */
    private $table;
    /**
     * @var array
     */
    protected $uniqueFields;
    /**
     * @var array
     */
    protected $restFields;

    /**
     * @var array
     */
    protected $allFields;

    /**
     * @var \PDOStatement
     */
    private $prepared;

    /**
     * @var string
     */
    private $preparedKey;

    public function __construct($table, array $uniqueFields, array $restFields)
    {
        $this->table = $table;
        $this->uniqueFields = $uniqueFields;
        $this->restFields = $restFields;
        $this->allFields = array_merge($uniqueFields, $restFields);
    }

    protected function formatWith(string $format): callable
    {
        return function ($item) use ($format) {
            return sprintf($format, $item);
        };
    }

    private function getQueryId(): string
    {
        $restCount = \count($this->restFields);

        $idFieldsStr = implode(', ', $this->uniqueFields);

        return "Query: {$this->table}: ids: {$idFieldsStr}, rest fields: {$restCount}";
    }

    private function getSql(string $allFieldsValues): string
    {
        $allFields = implode(',', $this->allFields);

        $restFields = implode(',', array_map($this->formatWith('%1$s = VALUES(%1$s)'), $this->restFields));

        return <<<SQL
-- {$this->getQueryId()}
INSERT IGNORE INTO {$this->table} (
  {$allFields}
) VALUES
  {$allFieldsValues}
ON DUPLICATE KEY UPDATE
  {$restFields}
SQL;
    }

    private function getSqlTmpTable(string $tmpTableName): string
    {
        $allFields = implode(',', $this->allFields);

        $restFields = implode(',', array_map($this->formatWith('%1$s = VALUES(%1$s)'), $this->restFields));

        return <<<SQL
-- {$this->getQueryId()}
INSERT IGNORE INTO {$this->table} ({$allFields}) 
SELECT {$allFields} FROM `{$tmpTableName}`
ON DUPLICATE KEY UPDATE
  {$restFields}
SQL;
    }

    abstract protected function createInsertValues(array $params);

    /**
     * Multiple upsert should make all
     * keys unique for correct sql query
     *
     * @example
     * incoming array: [ [id, age, name], [id, age, name], [id, age, name]]
     * outgoing array: [ [id1, age1, name1], [id2, age2, name2], [id3, age3, name3]]
     *
     * @param array $params
     * @return array
     */
    protected function makeKeysUnique(array $params)
    {
        return $params;
    }

    /**
     * Multiple upsert should reduce array nesting
     *
     * @example
     * incoming array: [ [id1, age1, name1], [id2, age2, name2], [id3, age3, name3]]
     * outgoing array: [id1, age1, name1, id2, age2, name2, id3, age3, name3]
     *
     * @param array $params
     * @return array
     */
    protected function reduceNesting(array $params)
    {
        return $params;
    }

    /**
     * @param \PDO|Connection $conn
     * @param array $params
     * @param string $broker
     * @throws Exception
     */
    public function execute($conn, array $params, $broker = '')
    {
        $preparedKey = sha1(json_encode($this->allFields, JSON_PARTIAL_OUTPUT_ON_ERROR) . $broker);

        try {
            $params = $this->makeKeysUnique($params);
            $normalizedParams = $this->reduceNesting($params);

            if (!$this->prepared || $preparedKey !== $this->preparedKey) {
                $this->preparedKey = $preparedKey;

                $sql = $this->getSql($this->createInsertValues($params));
                $this->prepared = $conn->prepare($sql);
            }

            $this->prepared->execute($normalizedParams);
        } catch (\Exception $exc) {
            if (!($exc instanceof \PDOException)) {
                throw $exc;
            }

            throw new UpsertException($exc->getMessage());
        }
    }

    /**
     * @param \PDO|DriverConnection $conn
     * @param string $tableName
     * @throws \Exception
     */
    public function fromTemporaryTable($conn, string $tableName)
    {
        try {
            $conn->exec($this->getSqlTmpTable($tableName));
        } catch (\Exception $exc) {
            if (!($exc instanceof \PDOException)) {
                throw $exc;
            }

            throw new UpsertException($exc->getMessage());
        }
    }
}
