<?php

namespace Fxtm\CopyTrading\Application\Common;

class TimerImpl implements Timer
{
    /**
     * @var float
     */
    private $lastTime;

    /**
     * @var array
     */
    private static $measurements = [];
    
    /**
     * Just restarts last measure time
     */
    public function start()
    {
        $this->lastTime = microtime(true);
    }

    /**
     * Forget all measurements
     */
    public function clear()
    {
        self::$measurements = [];
    }

    /**
     * Makes time measurement
     *
     * @param string $step
     * @return float
     */
    public function measure(string $step) : float
    {
        $time = microtime(true);
        $spentTime = $time - $this->lastTime;
        $this->saveMeasurement($step, $spentTime);
        $this->lastTime = $time;

        return $spentTime;
    }

    /**
     * Saves measurement for calculating average time in future
     *
     * @param string $step
     * @param float $time
     */
    private function saveMeasurement(string $step, float $time)
    {
        if (!isset(self::$measurements[$step])) {
            self::$measurements[$step] = [
                'count' => 0,
                'time' => 0,
            ];
        }

        self::$measurements[$step]['count']++;
        self::$measurements[$step]['time'] += $time;
    }

    /**
     * Returns array of calculated average times
     * of all measured steps
     *
     * @return array
     */
    public function averageTimes()
    {
        $avgTimes = [];
        foreach (self::$measurements as $step => $data) {
            $avgTimes[$step] = [
                'count' => $data['count'],
                'avg_time' => $data['count'] > 0 ? $data['time'] / $data['count'] : 0,
            ];
        }

        return $avgTimes;
    }
}
