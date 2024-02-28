<?php

namespace Fxtm\CopyTrading\Interfaces\DbConnector;

class ClickHouseConnectorFactory
{
    public function __invoke()
    {
        return ClickHouseConnector::getInstance();
    }
}
