<?php

namespace Fxtm\CopyTrading\Application\Leader\Statistics\Utils;

use Psr\Log\LoggerInterface;

trait LoggingTrait
{
    abstract protected function debugMode(): bool;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var string
     */
    private $sourceName = '';

    /**
     * @param LoggerInterface $logger
     */
    public function setLogger(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    /**
     * @param string $name
     */
    public function setSourceName(string $name)
    {
        $this->sourceName = $name;
    }

    /**
     * Sends message to logs
     *
     * @param string $message
     * @param string $level
     */
    protected function log(string $message, string $level = 'info')
    {
        if ($level == 'debug' && !$this->debugMode()) {
            return;
        }

        try {
            $this->logger->$level($this->sourceName . ' : ' . $message);
        } catch (\Exception $e) {
            echo 'Logger failed on sending next message: ' . $message . '. Error: ' . $e->getMessage() . PHP_EOL;
        }
    }
}
