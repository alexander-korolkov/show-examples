<?php

namespace Fxtm\CopyTrading\Interfaces\Persistence\Query;

use PDO;
use PDOStatement;

class MultipleUpdate
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
     * @var string
     */
    protected $uniqueField;

    /**
     * @var array
     */
    protected $restFields;

    /**
     * MassInsert constructor.
     * @param string $table
     * @param string $uniqueField
     * @param array $restFields
     */
    public function __construct(string $table, $uniqueField, array $restFields)
    {
        $this->table = $table;
        $this->uniqueField = $uniqueField;
        $this->restFields = $restFields;
    }

    /**
     * Execute the query
     *
     * @param PDO $connection
     * @param array $updates
     */
    public function execute(PDO $connection, array $updates)
    {
        $columns = $this->prepareColumns($updates);
        $keys = $this->prepareKeys($updates);
        $updates = array_merge($keys, $this->reduceNesting($columns));

        if (!$this->prepared || $this->preparedCount != count($updates)) {
            $this->preparedCount = count($updates);
            $sql = $this->getSql($keys, $columns);
            $this->prepared = $connection->prepare($sql);
        }

        $this->prepared->execute($updates);
    }

    /**
     * @param array $updates
     * @return array
     */
    private function prepareColumns(array $updates) : array
    {
        $result = [];
        foreach ($updates as $index1 => $data) {
            foreach ($this->restFields as $index2 => $restField) {
                $result[$restField][] = [
                    'key' . $index1 . $index2 => $data[$this->uniqueField],
                    'value' . $index1 . $index2 => $data[$restField]
                ];
            }
        }

        return $result;
    }

    /**
     * @param array $updates
     * @return array
     */
    private function prepareKeys(array $updates) : array
    {
        $result = [];
        foreach ($updates as $index => $data) {
            $result[$this->uniqueField . $index] = $data[$this->uniqueField];
        }

        return $result;
    }

    /**
     * @param array $columns
     * @return array
     */
    private function reduceNesting(array $columns) : array
    {
        $prepared = [];
        foreach ($columns as $field => $values) {
            foreach ($values as $data) {
                foreach ($data as $key => $value) {
                    $prepared[$key] = $value;
                }
            }
        }

        return $prepared;
    }

    /**
     * Method generates sql code for mass insert query
     *
     * @param array $keys
     * @param array $columns
     * @return string
     */
    private function getSql(array $keys, array $columns)
    {
        $uniqueValues = array_map(function ($key) {
            return ':' . $key;
        }, array_keys($keys));

        $updatingColumns = [];
        foreach ($columns as $field => $values) {
            $stmt = $field . ' = CASE ';

            foreach ($values as $value) {
                $keys = array_keys($value);
                $stmt .= 'WHEN ' . $this->uniqueField . ' = :' . $keys[0] . ' THEN :' . $keys[1] . ' ';
            }

            $stmt .= ' ELSE ' . $field . ' END';
            $updatingColumns[] = $stmt;
        }

        return '
            UPDATE ' . $this->table . '
            SET ' . implode(', ', $updatingColumns) . '
            WHERE ' . $this->uniqueField . ' IN (
            ' . implode(', ', $uniqueValues) . '
        )';
    }
}
