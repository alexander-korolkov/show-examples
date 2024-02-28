<?php

namespace Fxtm\CopyTrading\Interfaces\Common;

use Exception;
use Psr\Log\LoggerInterface as Logger;

class Processor
{
    private $pid = 0;
    private $procCnt = 0;
    private $processes = [];
    private $logger = null;
    private $debug = false;

    private $isShuttingDown = false;

    private static $lastReportTime = 0;

    public function __construct($procCnt, Logger $logger, $debug = false)
    {
        $this->pid = posix_getpid();
        $this->procCnt = $procCnt;
        $this->logger = $logger;
        $this->debug = $debug;

        pcntl_signal(SIGINT, function ($signal) {
            $this->debug("SIGINT: shutting down...");
            $this->shutDown();
        });
        pcntl_signal(SIGTERM, function ($signal) {
            $this->debug("SIGTERM: shutting down...");
            $this->shutDown();
        });

        pcntl_signal(SIGCHLD, function ($signal) {
            foreach (array_keys($this->processes) as $pid) {
                $res = pcntl_waitpid($pid, $status, WNOHANG | WUNTRACED);
                if ($res > 0) {
                    $this->debug(sprintf("CHILD FINISH: %d => %d", $pid, pcntl_wexitstatus($status)));
                    unset($this->processes[$pid]);
                }
            }
        });
    }

    public function getPid()
    {
        return $this->pid;
    }

    public function isAccepting()
    {
        return count($this->processes) < $this->procCnt;
    }

    public function isShuttingDown()
    {
        return $this->isShuttingDown;
    }

    public function submit(callable $callable)
    {
        if($this->itsTimeToReport()) {
            $this->report();
        }

        while (!$this->isShuttingDown() && !$this->isAccepting()) {
            $this->debug("Process limit exceeded, waiting...");
            sleep(1);
        }

        if ($this->isShuttingDown()) {
            $this->debug("Shutting down...");
            return 0;
        }

        $pid = pcntl_fork();
        if ($pid === -1) {
            throw new Exception("Unable to fork");
        }

        if ($pid > 0) { // parent
            $this->processes[$pid] = true;
            return $pid;
        }

        declare(ticks = 1);

        try { // child
            $this->processes = [];

            pcntl_signal(SIGINT, SIG_IGN);
            pcntl_signal(SIGTERM, SIG_IGN);

            call_user_func($callable, $this);
            exit(0);
        } catch (Exception $e) {
            $this->logger->error(
                sprintf('%s(%s) in %s on line %d', \get_class($e), $e->getMessage(), $e->getFile(), $e->getLine()),
                [
                    'pid' => $this->pid,
                    'exception' => $e,
                ]
            );
            exit(1);
        }
    }

    public function join()
    {
        while (!empty($this->processes)) {
            sleep(1);
        }
    }

    public function wait($hang = true)
    {
        foreach (array_keys($this->processes) as $pid) {
            pcntl_waitpid($pid, $status, ($hang ? 0 : WNOHANG) | WUNTRACED);
        }
    }

    public function shutDown()
    {
        $this->isShuttingDown = true;
    }

    public function getProcessInProgress() {
        return $this->processes;
    }

    private function debug($msg)
    {
        printf("\n[%d] %s\n", $this->pid, $msg);
    }

    private function itsTimeToReport()
    {
        if (self::$lastReportTime == 0) {
            self::$lastReportTime = microtime(true);
            return true;
        }

        $currTime = microtime(true);

        return $currTime - self::$lastReportTime >= 15;
    }

    private function report()
    {
        $memoryFormat = function ($bytes) {
            $units = ['B', 'KB', 'MB', 'GB', 'TB', 'PB'];
            return sprintf("%.2f%s", round($bytes / pow(1024, ($i = floor(log($bytes, 1024)))), 2), $units[$i]);
        };

        $this->logger->info(sprintf("Memory: %s (Peak: %s)\n", $memoryFormat(memory_get_usage()), $memoryFormat(memory_get_peak_usage())));
        $this->logger->info(sprintf("Processes in progress: %s\n", json_encode($this->getProcessInProgress())));

        self::$lastReportTime = microtime(true);
    }
}
