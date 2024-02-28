<?php

namespace Fxtm\CopyTrading\Interfaces\DbConnector;

class DatabaseConnectorFactory
{
    public function __invoke()
    {
        return DatabaseConnector::getInstance();
    }
}
