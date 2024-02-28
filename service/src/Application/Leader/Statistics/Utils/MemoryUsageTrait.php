<?php

namespace Fxtm\CopyTrading\Application\Leader\Statistics\Utils;

trait MemoryUsageTrait
{
    abstract protected function log(string $message, string $level = 'info');

    /**
     * Function for checking memory limits
     *
     * @param string $step
     */
    protected function memoryUsage(string $step)
    {
        $peak = round(memory_get_peak_usage() / 1024);
        $limit = ini_get('memory_limit');

        if (preg_match('/^(\d+)(.)$/', $limit, $matches)) {
            if ($matches[2] == 'M') {
                $limit = $matches[1] * 1024; //MB
            } else if ($matches[2] == 'K') {
                $limit = $matches[1]; //KB
            }
        }

        $percent = round(($peak / $limit) * 100, 2);

        $this->log(sprintf('Step %s - peak memory usage: %d Kb (%.2f%%)', $step, $peak, $percent));
    }
}
