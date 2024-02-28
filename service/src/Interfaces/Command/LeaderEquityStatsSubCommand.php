<?php

namespace Fxtm\CopyTrading\Interfaces\Command;

use Fxtm\CopyTrading\Application\Common\Timer;
use Fxtm\CopyTrading\Application\Leader\Statistics\LeaderEquityStatsCalculator;
use Fxtm\CopyTrading\Application\Leader\Statistics\LeaderEquityStatsSaver;
use Fxtm\CopyTrading\Domain\Model\Leader\LeaderAccountRepository;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\InvalidArgumentException;
use Psr\Log\LoggerInterface;
use Symfony\Component\Cache\Adapter\RedisAdapter;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class LeaderEquityStatsSubCommand extends LeaderEquityStatsBaseCommand
{
    /**
     * @var LeaderEquityStatsCalculator
     */
    private $leaderEquityStatsCalculator;

    /**
     * @var LeaderEquityStatsSaver
     */
    private $leaderEquityStatsSaver;

    /**
     * @var LeaderAccountRepository
     */
    private $leaderAccountRepository;

    /**
     * @var Timer
     */
    private $timer;

    public function __construct(
        LeaderEquityStatsCalculator $leaderEquityStatsCalculator,
        LeaderEquityStatsSaver $leaderEquityStatsSaver,
        LeaderAccountRepository $leaderAccountRepository,
        Timer $timer,
        RedisAdapter $cacheRedis,
        LoggerInterface $logger,
        string $name = null
    ) {
        $this->leaderEquityStatsCalculator = $leaderEquityStatsCalculator;
        $this->leaderEquityStatsSaver = $leaderEquityStatsSaver;
        $this->leaderAccountRepository = $leaderAccountRepository;
        $this->timer = $timer;
        parent::__construct($cacheRedis, $logger, $name);
    }

    protected function configure()
    {
        $this
            ->setName(self::SUB_PROCESS_COMMAND)
            ->addArgument(self::COMMAND_ID_ARGUMENT, InputArgument::REQUIRED);
        parent::configure();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $commandId = $input->getArgument(self::COMMAND_ID_ARGUMENT);

        /** @var CacheItemInterface $cacheItem */
        try {
            $cacheItemId = self::REDIS_PREFIX . self::REDIS_MAIN_SUFFIX . $commandId;
            $cacheItem = $this->cacheRedis->getItem($cacheItemId);
            if (!$cacheItem->isHit()) {
                $this->logFmt('Cache had been outdated and removed: key = ' . $cacheItemId, [], 'error');
                return 0;
            }
            else{
                $this->cacheRedis->deleteItem($cacheItemId);
            }

            $accountIds  = $cacheItem->get();
            if (empty($accountIds)) {
                $this->logFmt('Cache object has empty payload', [], 'error');
                return 0;
            }

            $chunk = $this->leaderAccountRepository->getForCalculatingStats($accountIds);

            $handled = $this->leaderEquityStatsCalculator->calculate($chunk);
            $handled = $this->leaderEquityStatsSaver->save($handled, $this->fillOptions($input));
            $this->logFmt('Handled accounts chunk');

            $this->timer->measure('chunk_cycle_iteration');
            $this->memoryUsage('updateEquityStats[chunk_cycle_iteration]');
            $this->logFmt('Time measurements: %s', [json_encode($this->timer->averageTimes())]);

            /** @var CacheItemInterface $cacheItem */
            $cacheItem = $this->cacheRedis->getItem(self::REDIS_PREFIX . self::REDIS_SUB_SUFFIX . $commandId);
            $cacheItem->set($handled);
            $cacheItem->expiresAfter(3600);
            $this->cacheRedis->save($cacheItem);
        } catch (InvalidArgumentException $e) {
            $this->logFmt(self::CACHE_ERROR, [], 'error');
            return -1;
        }

        return 0;
    }
}
