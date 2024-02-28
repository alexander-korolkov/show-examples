<?php

namespace Fxtm\CopyTrading\Application\Logger;

class LoggerProcessor
{
    /**
     * @var string
     */
    private $processId;

    /**
     * LoggerProcessor constructor.
     */
    public function __construct()
    {
        if (defined('CURRENT_WORKFLOW_ID')) {
            $this->processId = 'workflow.' . CURRENT_WORKFLOW_ID;
            return;
        }

        if (session_status() == PHP_SESSION_ACTIVE) {
            $this->processId = 'session.' . session_id();
            return;
        }

        $this->processId = sha1(random_bytes(10));
    }

    public function __invoke(array $record)
    {
        $record['extra']['process_id'] = $this->processId;

        return $record;
    }
}
