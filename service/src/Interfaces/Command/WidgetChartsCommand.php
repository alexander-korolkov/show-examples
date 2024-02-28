<?php

namespace Fxtm\CopyTrading\Interfaces\Command;

use Doctrine\DBAL\Connection;
use Fxtm\CopyTrading\Application\SettingsRegistry;
use Fxtm\CopyTrading\Domain\Common\DateTime;
use Fxtm\CopyTrading\Domain\Entity\Broker;
use Fxtm\CopyTrading\Domain\Entity\MetaData\DataSourceFactory;
use Fxtm\CopyTrading\Interfaces\Gateway\Filesystem\FileStorageFacade;
use PDO;
use PHPlot;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Command\LockableTrait;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class WidgetChartsCommand extends Command
{

    use LockableTrait;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var DataSourceFactory
     */
    private $connectionFactory;

    /**
     * @var FileStorageFacade
     */
    private $fileStorage;

    /**
     * @var SettingsRegistry
     */
    private $settings;

    /**
     * @param LoggerInterface $logger
     */
    public function setLogger(LoggerInterface $logger) : void
    {
        $this->logger = $logger;
    }

    /**
     * @param DataSourceFactory $factory
     */
    public function setDataSourceFactory(DataSourceFactory $factory) : void
    {
        $this->connectionFactory = $factory;
    }

    /**
     * @param FileStorageFacade $fileStorageFacade
     */
    public function setFileStorageFacade(FileStorageFacade $fileStorageFacade) : void
    {
        $this->fileStorage = $fileStorageFacade;
    }

    /**
     * @param SettingsRegistry $settingsRegistry
     */
    public function setSettingsRegistry(SettingsRegistry $settingsRegistry) : void
    {
        $this->settings = $settingsRegistry;
    }

    /**
     * {@inheritdoc}
     */
    protected function configure() : void
    {
        $this->setName('app:widget_charts')
            ->setDescription('Generates chart images')
            ->setHelp("Generates chart images");
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {

        if(!$this->lock($this->getName())) {
            $output->writeln("Another process is running");
            $this->logger->error("Failed to set lock, another process is running");
            return  -1;
        }

        // check the last update times
        try {
            $dt1 = $this->settings->get("stats.leader_equity_stats.last_update");
            $dt2 = $this->settings->get("widget_charts.last_update");
            if (empty($dt1)) {
                throw new \Exception("Can't run until after the equity stats are calculated");
            }

            if (!empty($dt2) && DateTime::of($dt2) > DateTime::of($dt1)) {
                $this->release();
                return 0;
            }
        }
        catch (\Exception $ignored) {
            $this->logger->warning("Couldn't datermine the datetimes of last updates. Exiting.");
            $this->release();
            return 1;
        }

        foreach (Broker::list() as $broker) {

            $sasConnection = $this
                ->connectionFactory
                ->getSasConnection($broker);

            $data = $this->getUnitPrices($sasConnection);
            if (empty($data)) {
                $this->logger->warning("Nothing found. Exiting");
                $this->release();
                return 0;
            }

           $this->logger->info(sprintf("Found %d accounts", sizeof($data)));

            $charts = [];
            foreach ($data as $accNo => $unitPrices) {
                try {
                    $this->logger->info(sprintf("Processing %d...\n", $accNo));
                    $charts[$accNo] = $this->generateChart($unitPrices, 316, 146, "#DEEA98", "#F37022");
                }
                catch (\Exception $e) {
                    $this->logger->error(
                        printf(
                            "Exception '%s' with message '%s' in %s on line %d.\n",
                            get_class($e),
                            $e->getMessage(),
                            $e->getFile(),
                            $e->getLine()
                        )
                    );
                }
            }
            if (empty($charts)) {
                $this->logger->warning("No charts were generated");
                $this->release();
                return 0;
            }

            $this->logger->info("Saving charts");
            $this->saveCharts($charts);
        }

        // update the last update time
        $this->settings->set("widget_charts.last_update", DateTime::NOW());
        $this->logger->info("Done");

        $this->release();

        return 0;
    }

    /**
     * @param Connection $sasConn
     * @return array
     * @throws \Doctrine\DBAL\Exception
     */
    private function getUnitPrices(Connection $sasConn): array
    {
        return $sasConn
            ->query("SELECT acc_no, unit_price FROM ct_unit_prices")
            ->fetchAll(PDO::FETCH_GROUP|PDO::FETCH_FUNC, function ($unitPrice) { return $unitPrice; });
    }

    /**
     * @param array $unitPrices
     * @param int $width
     * @param int $height
     * @param $areaColor
     * @param $lineColor
     * @return string
     */
    private function generateChart(array $unitPrices, int $width, int $height, $areaColor, $lineColor) : string
    {
        $data1 = [];
        $minVal = min($unitPrices);
        for ($i = 0; $i < sizeof($unitPrices); $i++) {
            $data1[] = ['', $i, $minVal, $unitPrices[$i]];
        }
        $plot1 = new PHPlot($width, $height);
        $plot1->SetPlotType("area");
        $plot1->SetDataType("data-data");
        $plot1->SetDataValues($data1);

        $plot1->SetBackgroundColor("white");
        $plot1->SetTransparentColor("white");
        $plot1->SetDataColors($areaColor);

        $plot1->TuneXAutoRange(0, 'R', 0);
        $plot1->TuneYAutoRange(0, 'R', 0);
        $plot1->SetImageBorderWidth(0);
        $plot1->SetMarginsPixels(0, 0, 0, 0);
        $plot1->SetXTickPos("none");          // Turn off X tick marks
        $plot1->SetXTickLabelPos("none");     // Turn off X tick labels
        $plot1->SetXDataLabelPos("none");     // Turn off X data labels
        $plot1->SetYTickPos("none");          // Turn off Y tick marks
        $plot1->SetYTickLabelPos("none");     // Turn off Y tick labels
        $plot1->SetPlotBorderType("none");    // Turn off plot area border
        $plot1->SetDrawXGrid(false);          // Turn off X grid lines
        $plot1->SetDrawYGrid(false);          // Turn off Y grid lines
        $plot1->SetDrawXAxis(false);          // Don't draw X axis line
        $plot1->SetDrawYAxis(false);          // Don't draw Y axis line

        $plot1->SetPrintImage(false);
        $plot1->SetOutputFile(false);
        $plot1->SetFailureImage(false);
        $plot1->DrawGraph();

        $chart1 = "/tmp/chart1.png";
        file_put_contents($chart1, $plot1->EncodeImage("raw"));


        $data2 = [];
        for ($i = 0; $i < sizeof($unitPrices); $i++) {
            $data2[] = ['', $i, 0, $unitPrices[$i]-1];
        }
        $plot2 = new PHPlot($width, $height, null, $chart1);
        $plot2->SetPlotType("lines");
        $plot2->SetLineWidths(2);
        $plot2->SetDataType("data-data");
        $plot2->SetDataValues($data2);

        $plot2->SetBackgroundColor("white");
        $plot2->SetTransparentColor("white");
        $plot2->SetDataColors($lineColor);

        $plot2->TuneXAutoRange(0, 'R', 0);
        $plot2->TuneYAutoRange(0, 'R', 0);
        $plot2->SetImageBorderWidth(0);
        $plot2->SetMarginsPixels(0, 0, 0, 0);
        $plot2->SetXTickPos("none");          // Turn off X tick marks
        $plot2->SetXTickLabelPos("none");     // Turn off X tick labels
        $plot2->SetXDataLabelPos("none");     // Turn off X data labels
        $plot2->SetYTickPos("none");          // Turn off Y tick marks
        $plot2->SetYTickLabelPos("none");     // Turn off Y tick labels
        $plot2->SetPlotBorderType("none");    // Turn off plot area border
        $plot2->SetDrawXGrid(false);          // Turn off X grid lines
        $plot2->SetDrawYGrid(false);          // Turn off Y grid lines
        $plot2->SetDrawXAxis(false);          // Don't draw X axis line
        $plot2->SetDrawYAxis(false);          // Don't draw Y axis line
        $plot2->SetLineStyles(["none", "solid"]);

        $plot2->SetPrintImage(false);
        $plot2->SetOutputFile(false);
        $plot2->SetFailureImage(false);
        $plot2->DrawGraph();

        return $plot2->EncodeImage("raw");
    }

    /**
     * @param array $charts
     */
    private function saveCharts(array $charts): void
    {
        $permDir = "widget_charts";
        $tempDir = "widget_charts/tmp";

        $this->fileStorage->mkdir($tempDir);
        foreach ($charts as $name => $chart) {
            $this->fileStorage->write("{$tempDir}/{$name}.png", $chart);
        }

        $this->fileStorage->rmfiles($permDir);
        $this->fileStorage->mvfiles($tempDir, $permDir);
        $this->fileStorage->rmdir($tempDir);
    }

}
