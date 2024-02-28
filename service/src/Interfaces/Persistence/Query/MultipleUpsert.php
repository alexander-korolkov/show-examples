<?php

namespace Fxtm\CopyTrading\Interfaces\Persistence\Query;

class MultipleUpsert extends Upsert
{
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
        $result = [];

        foreach ($params as $index => $data) {
            foreach ($data as $key => $value) {
                if (in_array($key, $this->allFields)) {
                    $result[$index][$key . $index] = $value;
                }
            }
        }

        return $result;
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
        $result = [];
        foreach ($params as $data) {
            foreach ($data as $key => $value) {
                $result[$key] = $value;
            }
        }

        return $result;
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
}
