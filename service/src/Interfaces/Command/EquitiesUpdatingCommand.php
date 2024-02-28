<?php

namespace Fxtm\CopyTrading\Interfaces\Command;

use Fxtm\CopyTrading\Application\Leader\Statistics\EquityUpdater;

use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Command\LockableTrait;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;


class EquitiesUpdatingCommand extends Command
{

    use LockableTrait;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var EquityUpdater
     */
    private $equityUpdater;

    /**
     * @param LoggerInterface $logger
     */
    public function setLogger(LoggerInterface $logger): void
    {
        $this->logger = $logger;
    }

    /**
     * @param EquityUpdater $equityUpdater
     */
    public function setEquityUpdater(EquityUpdater $equityUpdater): void
    {
        $this->equityUpdater = $equityUpdater;
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this->setName('app:equities_updating')
            ->setDescription('')
            ->setHelp('');
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

        try {
            $this->equityUpdater->run();
        }
        catch (\Exception $e) {
            $this->logger->error(
                sprintf(
                    'Exception %s with message %s, stack trace: %s',
                    get_class($e),
                    $e->getMessage(),
                    $e->getTraceAsString()
                )
            );
        }

        $this->release();
        return 0;
    }

}