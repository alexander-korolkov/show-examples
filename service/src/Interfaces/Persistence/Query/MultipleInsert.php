<?php

namespace Fxtm\CopyTrading\Interfaces\Persistence\Query;

use Doctrine\DBAL\Connection;
use PDOStatement;

class MultipleInsert
{
    /**
     * @var PDOStatement
     */
    private $prepared;

    /**
     * @var string
     */
    private $preparedCount;

    /**
     * @var string
     */
    private $table;

    /**
     * @var array
     */
    private $columns;

    /**
     * MassInsert constructor.
     * @param string $table
     * @param array $columns
     */
    public function __construct(string $table, array $columns)
    {
        $this->table = $table;
        $this->columns = $columns;
    }

    /**
     * Execute the query
     *
     * @param Connection $connection
     * @param array $inserts
     *
     * @throws \Doctrine\DBAL\Driver\Exception
     * @throws \Doctrine\DBAL\Exception
     */
    public function execute(Connection $connection, array $inserts)
    {
        $inserts = $this->makeKeysUnique($inserts);
        $normalizedInserts = $this->reduceNesting($inserts);

        if (!$this->prepared || $this->preparedCount != count($normalizedInserts)) {
            $this->preparedCount = count($normalizedInserts);
            $this->prepared = $connection->prepare($this->getSql($inserts));
        }

        $this->prepared->execute($normalizedInserts);
    }

    /**
     * Returns given array with unique index appended for each key
     *
     * @param array $inserts
     * @return array
     */
    private function makeKeysUnique(array $inserts) : array
    {
        $result = [];
        foreach ($inserts as $index => $data) {
            foreach ($data as $key => $value) {
                $result[$index][$key . $index] = $value;
            }
        }

        return $result;
    }

    /**
     * Combines array of arrays with unique keys to one-level array
     *
     * @param array $inserts
     * @return array
     */
    private function reduceNesting(array $inserts) : array
    {
        $preparedForInsert = [];
        foreach ($inserts as $data) {
            foreach ($data as $key => $value) {
                $preparedForInsert[$key] = $value;
            }
        }

        return $preparedForInsert;
    }

    /**
     * Method generates sql code for mass insert query
     *
     * @param array $inserts
     * @return string
     */
    private function getSql(array $inserts)
    {
        $valuesStatements = array_map(function ($data) {
            return '(' . implode(',', array_map(function ($key) { return ':' . $key; }, array_keys($data))) . ')';
        },$inserts);

        return 'INSERT INTO ' . $this->table . ' (' . implode(',', $this->columns) . ') VALUES ' . implode(',', $valuesStatements);
    }
}
