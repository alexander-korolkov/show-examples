<?php

namespace Fxtm\CopyTrading\Interfaces\DbConnector;

class FrsConnectorFactory
{
    public function __invoke()
    {
        return FrsConnector::getInstance();
    }
}
