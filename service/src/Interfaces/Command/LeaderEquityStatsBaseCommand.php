<?php

namespace Fxtm\CopyTrading\Interfaces\Command;

use Fxtm\CopyTrading\Application\Leader\Statistics\Utils\LoggingTrait;
use Fxtm\CopyTrading\Application\Leader\Statistics\Utils\MemoryUsageTrait;
use Fxtm\CopyTrading\Application\Leader\Statistics\Utils\OptionsTrait;
use Psr\Log\LoggerInterface;
use Symfony\Component\Cache\Adapter\RedisAdapter;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;

abstract class LeaderEquityStatsBaseCommand extends Command
{
    use OptionsTrait;
    use LoggingTrait;
    use MemoryUsageTrait;

    protected const CHUNK_SIZE_ARGUMENT = 'chunk_size';
    protected const FORCE_UPDATE_OPTION = 'force-update';
    protected const INSTANCE_ARGUMENT = 'instance';
    protected const ACCOUNT_ARGUMENT = 'account';
    protected const STEP_ARGUMENT = 'step';
    protected const SKIP_ARGUMENT = 'skip';
    protected const DEBUG_OPTION = 'debug';
    protected const COMMAND_ID_ARGUMENT = 'cid';

    protected const DEFAULT_CHUNK_SIZE = 100;
    protected const MAIN_PROCESS_COMMAND = 'app:leader_equity_stats_main';
    protected const SUB_PROCESS_COMMAND = 'app:leader_equity_stats_sub';
    protected const REDIS_PREFIX = 'equity_command_';
    protected const REDIS_MAIN_SUFFIX = 'main_';
    protected const REDIS_SUB_SUFFIX = 'sub_';

    protected const ARGS_NOT_VALID_ERROR = 'Arguments are not presented in the valid format';
    protected const CACHE_ERROR = 'Redis error';

    /**
     * @var RedisAdapter
     */
    protected $cacheRedis;

    public function __construct(
        RedisAdapter $cacheRedis,
        LoggerInterface $logger,
        string $name = null
    ) {
        $this->setLogger($logger);
        $this->cacheRedis = $cacheRedis;
        $this->cacheRedis->setLogger($this->logger);
        parent::__construct($name);
    }

    protected function configure()
    {
        $this->addOption(self::FORCE_UPDATE_OPTION)
            ->addOption(self::DEBUG_OPTION)
            ->addArgument(self::CHUNK_SIZE_ARGUMENT, InputArgument::OPTIONAL, '', self::DEFAULT_CHUNK_SIZE)
            ->addArgument(self::INSTANCE_ARGUMENT, InputArgument::OPTIONAL)
            ->addArgument(self::ACCOUNT_ARGUMENT, InputArgument::OPTIONAL)
            ->addArgument(self::STEP_ARGUMENT, InputArgument::OPTIONAL)
            ->addArgument(self::SKIP_ARGUMENT, InputArgument::OPTIONAL);
    }

    protected function fillOptions(InputInterface $input): array
    {
        $result = [];
        foreach (
            [
                self::FORCE_UPDATE_OPTION,
                self::DEBUG_OPTION,
            ] as $option
        ) {
            if ($input->hasOption($option) && $input->getOption($option) !== false) {
                $result[$option] = $input->getOption($option);
            }
        }
        foreach (
            [
                self::INSTANCE_ARGUMENT,
                self::ACCOUNT_ARGUMENT,
                self::STEP_ARGUMENT,
                self::SKIP_ARGUMENT,
            ] as $argument
        ) {
            if ($input->hasArgument($argument) && !is_null($input->getArgument($argument))) {
                $result[$argument] = $input->getArgument($argument);
            }
        }
        return $result;
    }

    protected function logFmt(string $msg, array $params = [], string $level = 'info'): void
    {
        $this->log(
            sprintf("[COMMAND %s]: %s", static::class, vsprintf($msg, $params)),
            $level
        );
    }
}
