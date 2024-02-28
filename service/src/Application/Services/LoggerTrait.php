<?php

namespace Fxtm\CopyTrading\Application\Services;

use Exception;
use Psr\Log\LoggerInterface as Logger;

trait LoggerTrait
{
    /**
     * @var Logger
     */
    private $logger;

    /**
     * @param Logger $logger
     */
    public function setLogger(Logger $logger)
    {
        $this->logger = $logger;
    }

    /**
     * @return Logger
     */
    public function getLogger()
    {
        return $this->logger;
    }

    /**
     * @param Exception $e
     */
    public function logException(Exception $e)
    {
        $this->logger->error(
            sprintf(
                "Exception '%s' with message '%s' in %s on line %d. Trace: %s",
                get_class($e),
                $e->getMessage(),
                $e->getFile(),
                $e->getLine(),
                $e->getTraceAsString()
            )
        );
    }

    /**
     * @param $msg
     */
    public function logInfo($msg) {
        $this->logger->info($msg);
    }
}
