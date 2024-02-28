<?php

namespace Fxtm\CopyTrading\Interfaces\Persistence\Query;

class SingleUpsert extends Upsert
{
    protected function createInsertValues(array $params): string
    {
        $fieldCount = \count($this->restFields) + \count($this->uniqueFields);
        $paramCount = \count($params);
        if ($paramCount !== $fieldCount) {
            throw new UpsertException("Param count ({$paramCount}) does not match the number of fields ({$fieldCount})");
        }

        $allFieldsArr = array_merge($this->uniqueFields, $this->restFields);
        $allFieldsPlaceholders = implode(',', array_map($this->formatWith(':%s'), $allFieldsArr));

        return "({$allFieldsPlaceholders})";
    }
}
