<?php

namespace Fxtm\CopyTrading\Interfaces\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use DateTimeImmutable;
use Psr\Log\LoggerInterface;
use Fxtm\CopyTrading\Interfaces\Statistics\Utils\FixEquityService;
use Fxtm\CopyTrading\Interfaces\Repository\LeaderAccountRepository;
use Fxtm\CopyTrading\Interfaces\Repository\FollowerAccountRepository;
use Fxtm\CopyTrading\Domain\Model\Leader\AccountStatus as LeaderAccountStatus;
use Fxtm\CopyTrading\Domain\Model\Follower\AccountStatus as FollowerAccountStatus;

class FixEquityCommand extends Command
{
    private const ARGUMENT_LOGIN_ALL_LEADERS = 'all_leaders';
    private const ARGUMENT_LOGIN_ALL_FOLLOWERS = 'all_followers';

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var LeaderAccountRepository
     */
    private $leaderAccountRepository;

    /**
     * @var FollowerAccountRepository
     */
    private $followerAccountRepository;

    /**
     * @var FixEquityService
     */
    private $fixEquityService;

    /**
     * @param LoggerInterface $logger
     * @param LeaderAccountRepository $leaderAccountRepository
     * @param FollowerAccountRepository $followerAccountRepository
     * @param FixEquityService $fixEquityService
     */
    public function __construct(
        LoggerInterface $logger,
        LeaderAccountRepository $leaderAccountRepository,
        FollowerAccountRepository $followerAccountRepository,
        FixEquityService $fixEquityService
    ) {
        $this->logger = $logger;
        $this->leaderAccountRepository = $leaderAccountRepository;
        $this->followerAccountRepository = $followerAccountRepository;
        $this->fixEquityService = $fixEquityService;
        parent::__construct();
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this->setName('app:fix-equity')
            ->setDescription('Fixes missing equities')
            ->setHelp('Fixes missing equities')
            ->addArgument('login',InputArgument::REQUIRED,
                'account login or "'
                . static::ARGUMENT_LOGIN_ALL_LEADERS . '" for all leaders or "'
                . static::ARGUMENT_LOGIN_ALL_FOLLOWERS . '" for all followers'
            )
            ->addOption('fix',null, InputArgument::OPTIONAL, 'inserts equities to the database')
            ->addOption('from',null, InputArgument::OPTIONAL, 'date from')
            ->addOption('to',null,InputArgument::OPTIONAL, 'date to');
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        switch ($input->getArgument('login')) {
            case static::ARGUMENT_LOGIN_ALL_LEADERS:
                $logins = $this->leaderAccountRepository->getLoginsAsArray([
                    LeaderAccountStatus::ACTIVE,
                    LeaderAccountStatus::CLOSED,
                    LeaderAccountStatus::DELETED
                ]);
                break;
            case static::ARGUMENT_LOGIN_ALL_FOLLOWERS:
                $logins = $this->followerAccountRepository->getLoginsAsArray([FollowerAccountStatus::ACTIVE, FollowerAccountStatus::CLOSED]);
                break;
            default:
                $logins = [(int) $input->getArgument('login')];
        }
        $toFix = (bool) $input->getOption('fix');
        $from = $input->getOption('from');
        $to = $input->getOption('to');
        $dateFrom = (null === $from) ? null : new DateTimeImmutable($from);
        $dateTo = (null === $to) ? null : new DateTimeImmutable($to);

        $output->writeln(
            ($toFix ? 'Adding' : 'Getting') .' missed equities for account(s)'
            . (null === $dateFrom ? '' : ', from: ' . $dateFrom->format('Y-m-d H:i:s'))
            . (null === $dateTo ? '' : ', to: ' . $dateTo->format('Y-m-d H:i:s'))
        );

        foreach ($logins as $login) {
            $result = $this->fixEquityService->addMissingHourlyEquities($login, $dateFrom, $dateTo, !$toFix);
            foreach ($result as $timeText => $value) {
                $message = 'Login: ' . $login . ', Time: ' . $timeText . ', Equity: ' . $value[1] . ', id: ' . $value[0];
                $output->writeln($message);
                if ($toFix) {
                    $this->logger->info('Equity record added: ' . $message);
                }
            }
        }
    }
}
