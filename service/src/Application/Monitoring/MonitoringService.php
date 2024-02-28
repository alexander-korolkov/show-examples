<?php

namespace Fxtm\CopyTrading\Application\Monitoring;

interface MonitoringService
{
    /**
     * Method sends notifications
     * to external monitoring service
     * Returns true if everything is fine
     *
     * @param string $message
     * @return bool
     */
    public function alert(string $message) : bool;
}
