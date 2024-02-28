<?php

namespace Fxtm\CopyTrading\Interfaces\Statistics\Utils;

interface PlotDrawingService
{
    /**
     * Generates chart by given points
     * and returns image with given height and width
     *
     * @param array $points
     * @param int $width
     * @param int $height
     * @return string
     */
    public function generateChart(array $points, int $width, int $height);

    /**
     * Return PHPlot class instance
     * from library
     *
     * @param int $width
     * @param int $height
     * @return \PHPlot
     */
    public function createPlot(int $width, int $height) : \PHPlot;
}
