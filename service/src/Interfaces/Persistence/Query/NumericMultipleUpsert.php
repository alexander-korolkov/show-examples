<?php

namespace Fxtm\CopyTrading\Interfaces\Persistence\Query;

class NumericMultipleUpsert extends Upsert
{
    protected function createInsertValues(array $params): string
    {
        $fieldCount = \count($this->restFields) + \count($this->uniqueFields);
        $paramCount = \count($params);
        if ($paramCount % $fieldCount !== 0) {
            throw new UpsertException("Param count ({$paramCount}) does not match the number of fields ({$fieldCount})");
        }

        $numRows = $paramCount / $fieldCount;

        $rowPlaceholders = substr(str_repeat(',?', $fieldCount), 1);

        return substr(str_repeat(",({$rowPlaceholders})", $numRows), 1);
    }
}
