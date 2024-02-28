<?php

namespace Fxtm\CopyTrading\Interfaces\Statistics\Utils;

require_once __DIR__ . '/../libs/phplot-6.2.0/phplot.php';

class PlotDrawingServiceImpl implements PlotDrawingService
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
    public function generateChart(array $points, int $width, int $height)
    {
        $data[] = ['', 0, 0, 0];
        for ($i = 0; $i < count($points); $i++) {
            $data[] = ['', $i, 0, $points[$i] - 1];
        }

        $plot = $this->createPlot($width, $height);
        $plot->SetBackgroundColor("white");
        $plot->SetTransparentColor("white");
        $plot->SetPlotType("lines");
        $plot->SetDataColors(["grey", "red"]);
        $plot->SetDataType("data-data");
        $plot->TuneXAutoRange(0, 'R', 0);
        $plot->SetDataValues($data);

        $plot->SetXTickPos("none");          // Turn off X tick marks
        $plot->SetXTickLabelPos("none");     // Turn off X tick labels
        $plot->SetXDataLabelPos("none");     // Turn off X data labels
        $plot->SetYTickPos("none");          // Turn off Y tick marks
        $plot->SetYTickLabelPos("none");     // Turn off Y tick labels
        $plot->SetPlotBorderType("none");    // Turn off plot area border
        $plot->SetDrawXGrid(false);          // Turn off X grid lines
        $plot->SetDrawYGrid(false);          // Turn off Y grid lines
        $plot->SetDrawXAxis(false);          // Don't draw X axis line
        $plot->SetDrawYAxis(false);          // Don't draw Y axis line
        $plot->SetImageBorderWidth(0);
        $plot->SetMarginsPixels(0, 0, 0, 1);
        $plot->SetLineStyles(["dashed", "solid"]);

        $plot->SetPrintImage(false);
        $plot->SetOutputFile(false);
        $plot->SetFailureImage(false);
        $plot->DrawGraph();

        return $plot->EncodeImage("raw");
    }

    /**
     * Return PHPlot class instance
     * from library
     *
     * @param int $width
     * @param int $height
     * @return \PHPlot
     */
    public function createPlot(int $width, int $height): \PHPlot
    {
        return new \PHPlot($width, $height);
    }
}
