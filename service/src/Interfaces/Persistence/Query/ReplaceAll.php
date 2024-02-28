<?php

namespace Fxtm\CopyTrading\Interfaces\Persistence\Query;


class ReplaceAll
{
    /**
     * @var string
     */
    private $table;

    /**
     * @var array
     */
    protected $allFields;

    /**
     * @var \PDOStatement
     */
    private $preparedDeleteQuery;

    /**
     * @var \PDOStatement
     */
    private $preparedInsertQuery;

    /**
     * @var string
     */
    private $preparedCount;

    /**
     * @var string
     */
    private $preparedKey;

    /**
     * @var string
     */
    private $deleteKey;

    public function __construct($table, array $allFields, string $deleteKey)
    {
        $this->table = $table;
        $this->allFields = $allFields;
        $this->deleteKey = $deleteKey;
    }

    protected function formatWith(string $format): callable
    {
        return function ($item) use ($format) {
            return sprintf($format, $item);
        };
    }

    private function getQueryId(): string
    {
        $idFieldsStr = implode(', ', $this->allFields);

        return "Query: {$this->table}: ids: {$idFieldsStr}";
    }

    private function getInsertSqlQuery(string $allFieldsValues): string
    {
        $allFields = implode(',', $this->allFields);

        $insert_query =  <<<SQL
-- {$this->getQueryId()}
REPLACE INTO {$this->table} (
  {$allFields}
) VALUES
  {$allFieldsValues}
SQL;

     return $insert_query;
    }

    private function getDeleteSqlQuery(string $allDeleteKeys): string
    {

        $delete_query =  <<<SQL
-- {$this->getQueryId()}
DELETE FROM {$this->table}
WHERE {$this->deleteKey} IN 
{$allDeleteKeys}
SQL;

        return $delete_query;
    }

    /**
     * @param array $params
     * @return string
     */
    protected function createInsertValues(array $params): string
    {
        $valuesStatements = [];
        foreach ($params as $index => $row) {
            $fields = [];

            foreach ($this->allFields as $field) {
                if(array_key_exists($field . $index, $row)) {
                    $fields[] = ':' . $field . $index;
                }
            }

            $valuesStatements[] = '(' . implode(',', $fields) . ')';
        }

        return implode(',', $valuesStatements);
    }

    /**
     * @param array $params
     * @return string
     */
    protected function createDeleteKeys(array $params): string
    {
        $valuesStatements = array_keys($params);

        return '(' .  implode(',', $valuesStatements) . ')';
    }

    /**
     * Prepare array of params for next using in db queries.
     *
     * @param array $params
     * @return array of prepared arrays
     */
    protected function prepareParams(array $params)
    {
        $result = [
            'reducedNestingArray' => [],
            'keysUniqueArray' => [],
            'deleteKeysArray' => [],
        ];

        $deleteKeysArray = [];
        foreach ($params as $index => $data) {
            foreach ($data as $key => $value) {
                if (in_array($key, $this->allFields)) {
                    $result['keysUniqueArray'][$index][$key . $index] = $value;
                }
                $result['reducedNestingArray'][$key . $index] = $value;
            }
            $deleteKeysArray[$data[$this->deleteKey]] = $data[$this->deleteKey];
        }

        $k = 0;
        foreach ($deleteKeysArray as $v) {
            $result['deleteKeysArray'][':'.$this->deleteKey.$k] = $v;
            $k++;
        }

        return $result;
    }

    /**
     * @param \PDO $conn
     * @param array $params
     * @param string $broker
     * @throws \Exception
     */
    public function execute(\PDO $conn, array $params, $broker = '')
    {
        $preparedKey = sha1(json_encode($this->allFields, JSON_PARTIAL_OUTPUT_ON_ERROR) . $broker);

        try {
            $preparedParams = $this->prepareParams($params);
            $normalizedParams = $preparedParams['reducedNestingArray'];

            if (!$this->preparedDeleteQuery || !$this->preparedInsertQuery || $preparedKey !== $this->preparedKey
                || $this->preparedCount != count($preparedParams['deleteKeysArray'])) {

                $this->preparedCount = count($preparedParams['deleteKeysArray']);
                $this->preparedKey = $preparedKey;

                $insertSqlQuery = $this->getInsertSqlQuery($this->createInsertValues($preparedParams['keysUniqueArray']));

                $deleteSqlQuery = $this->getDeleteSqlQuery($this->createDeleteKeys($preparedParams['deleteKeysArray']));

                $this->preparedDeleteQuery = $conn->prepare($deleteSqlQuery);
                $this->preparedInsertQuery = $conn->prepare($insertSqlQuery);

            }

            try {
                // First of all, let's begin a transaction
                $conn->beginTransaction();

                // A set of queries; if one fails, an exception should be thrown
                $this->preparedDeleteQuery->execute($preparedParams['deleteKeysArray']);
                $this->preparedInsertQuery->execute($normalizedParams);

                // If we arrive here, it means that no exception was thrown
                // i.e. no query has failed, and we can commit the transaction
                $conn->commit();
            } catch (\Exception $exc) {
                // An exception has been thrown
                // We must rollback the transaction
                $conn->rollback();
                throw new \Exception($exc->getMessage());
            }
        } catch (\Exception $exc) {

            if (!($exc instanceof \PDOException)) {
                throw $exc;
            }

            throw new \Exception($exc->getMessage());
        }
    }
}
