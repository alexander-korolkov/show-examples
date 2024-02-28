<?php

namespace Fxtm\CopyTrading\Interfaces\Command;

use Fxtm\CopyTrading\Application\Leader\Statistics\LeaderEquityStatsCollector;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Command\LockableTrait;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class LeaderEquityStatsCommand extends Command
{
    public const FORCE_UPDATE_OPTION = 'force-update';

    use LockableTrait;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var LeaderEquityStatsCollector
     */
    private $collector;

    /**
     * @param LoggerInterface $logger
     */
    public function setLogger(LoggerInterface $logger): void
    {
        $this->logger = $logger;
    }

    /**
     * @param LeaderEquityStatsCollector $collector
     */
    public function setCollector(LeaderEquityStatsCollector $collector): void
    {
        $this->collector = $collector;
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this->setName('app:leader_equity_stats')
            ->setDescription('Collects leaders statistics')
            ->setHelp('Collects leaders statistics');

        $this->addOption(
            self::FORCE_UPDATE_OPTION,
            null,
            InputOption::VALUE_NONE
        );
    }

    /**
     * {@inheritdoc}
     * @throws \Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output) : int
    {
        if(!$this->lock($this->getName())) {
            $output->writeln("Another process is running");
            $this->logger->error("Failed to set lock, another process is running");
            return  -1;
        }

        $this->logger->info('LeaderEquityStats started.');

        try {
            $options = [];

            if ($input->getOption(self::FORCE_UPDATE_OPTION)) {
                $options[self::FORCE_UPDATE_OPTION] = true;
            }

            $this
                ->collector
                ->run($options);
        } catch (\Exception $e) {
            $this->logger->error(
                sprintf(
                    'LeaderEquityStats Exception %s with message %s, stack trace: %s',
                    get_class($e),
                    $e->getMessage(),
                    $e->getTraceAsString()
                )
            );
        }

        $this->logger->info('LeaderEquityStats finished.');

        $this->release();
        return 0;
    }

}
