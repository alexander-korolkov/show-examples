<?php

namespace Fxtm\CopyTrading\Application\Metrix;

use Psr\Log\LoggerInterface;

class GraylogMetrixService implements MetrixService
{
    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * GraylogMetrixService constructor.
     * @param LoggerInterface $logger
     */
    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    /**
     * @inheritdoc
     */
    public function write($reporter, $value, $tags = [], $additional = [], $timestamp = null)
    {
        try {
            $context = [
                'fx_request_time' => $value,
            ];
            $context = array_merge($context, $tags, $additional);

            if (MetrixData::getWorker()) {
                $reporter .= '::' . MetrixData::getWorker();
            }

            $this->logger->info($reporter, $context);
        }
        catch (\Exception $exception) {
            $this->logger->error(sprintf(
                "Operation failed with exception: %s: %s: %s",
                get_class($exception),
                $exception->getMessage(),
                $exception->getTraceAsString()
            ));
        }
    }
}
