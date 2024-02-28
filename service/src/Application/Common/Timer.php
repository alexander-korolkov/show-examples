<?php

namespace Fxtm\CopyTrading\Application\Common;

interface Timer
{
    /**
     * Just restarts last measure time
     */
    public function start();

    /**
     * Forget all measurements
     */
    public function clear();
    
    /**
     * Makes time measurement
     *
     * @param string $step
     * @return float
     */
    public function measure(string $step) : float;

    /**
     * Returns array of calculated average times
     * of all measured steps
     *
     * @return array
     */
    public function averageTimes();
}
