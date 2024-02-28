<?php

namespace Fxtm\CopyTrading\Domain\Common;

abstract class AbstractEvent implements Event
{
    private $occurredAt = null;

    public function __construct()
    {
        $this->occurredAt = DateTime::NOW();
    }

    public function getOccurredAt()
    {
        return $this->occurredAt;
    }

    public static function type()
    {
        return str_replace("\\", ".", get_called_class());
    }
}
