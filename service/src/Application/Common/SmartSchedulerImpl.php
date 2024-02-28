<?php

namespace Fxtm\CopyTrading\Application\Common;

use Fxtm\CopyTrading\Application\Common\Scheduler\Time;
use Fxtm\CopyTrading\Application\Common\Workflow\AbstractWorkflow;
use Fxtm\CopyTrading\Application\Common\Workflow\Scheduler;
use Fxtm\CopyTrading\Application\Follower\ChangeCopyCoefficientWorkflow;
use Fxtm\CopyTrading\Application\Follower\CloseAccountWorkflow;
use Fxtm\CopyTrading\Application\Follower\PauseCopyingWorkflow;
use Fxtm\CopyTrading\Application\Follower\ProcessDepositWorkflow as ProcessDepositWorkflow;
use Fxtm\CopyTrading\Application\Follower\ProcessPayoutWorkflow;
use Fxtm\CopyTrading\Application\Follower\ProcessWithdrawalWorkflow;
use Fxtm\CopyTrading\Application\Follower\ResumeCopyingWorkflow;
use Fxtm\CopyTrading\Application\Leader\CloseFollowerAccountsWorkflow;
use Fxtm\CopyTrading\Application\SettingsRegistry;
use Fxtm\CopyTrading\Application\TradeAccountGateway;
use Fxtm\CopyTrading\Application\TradeOrderGatewayFacade;
use Fxtm\CopyTrading\Domain\Common\DateTime;
use Fxtm\CopyTrading\Domain\Model\Follower\FollowerAccountRepository;
use Fxtm\CopyTrading\Domain\Model\Shared\AccountNumber;
use Fxtm\CopyTrading\Domain\Model\Shared\ServerAwareAccount;
use Fxtm\CopyTrading\Interfaces\Repository\BrokerRepositoryInterface;
use Psr\Log\LoggerInterface;
use RuntimeException;

/**
 * The scheduler delays execution of some of the follower
 * workflows (@see SmartSchedulerImpl::$blacklist), the others
 * are scheduled for an immediate execution.
 */
class SmartSchedulerImpl implements Scheduler
{
    private static $blacklist = [
        ChangeCopyCoefficientWorkflow::TYPE,
        CloseAccountWorkflow::TYPE,
        CloseFollowerAccountsWorkflow::TYPE,
        PauseCopyingWorkflow::TYPE,
        ProcessPayoutWorkflow::TYPE,
        ProcessDepositWorkflow::TYPE,
        ProcessWithdrawalWorkflow::TYPE,
        ResumeCopyingWorkflow::TYPE,
    ];

    /**
     * @var FollowerAccountRepository
     */
    private $follAccounts;

    private $brokerRepository;
    private $tradeAccounts = null;
    private $tradeOrders = null;
    private $settings = null;

    /**
     * @var LoggerInterface
     */
    private $logger;

    public function __construct(
        FollowerAccountRepository $follAccounts,
        BrokerRepositoryInterface $brokerRepository,
        TradeAccountGateway $tradeAccounts,
        TradeOrderGatewayFacade $tradeOrders,
        SettingsRegistry $settings,
        LoggerInterface $logger
    ) {
        $this->follAccounts = $follAccounts;
        $this->brokerRepository = $brokerRepository;
        $this->tradeAccounts = $tradeAccounts;
        $this->tradeOrders = $tradeOrders;
        $this->settings = $settings;
        $this->logger = $logger;
    }

    public function chooseTime(AbstractWorkflow $workflow)
    {
        $now = DateTime::NOW();
        if (!in_array($workflow->type(), self::$blacklist)) {
            $this->info($workflow, $now, 'reason: workflow\'s type is not in blacklist.');
            return $now;
        }

        if (in_array($workflow->type(), [CloseFollowerAccountsWorkflow::TYPE])) {
            //$workflow->getCorrelationId() - is always leader's trade account
            $tradeAcc = $this->tradeAccounts->fetchAccountByNumber(
                new AccountNumber($workflow->getCorrelationId()),
                $this->brokerRepository->getByTradeAccount($workflow->getCorrelationId())
            );

            if (!$this->tradeOrders->hasOpenPositions($tradeAcc)) {
                $this->info($workflow, $now, 'reason: workflow is of "close_follower" type and account has not any open orders.');
                return $now;
            }
        } else {
            //$workflow->getCorrelationId() - is always follower's account number
            $follAcc = $this->follAccounts->getLightAccountOrFail(new AccountNumber($workflow->getCorrelationId()));
            $openOrdersCount = $this->tradeOrders->getForServer($follAcc->leaderServer())->countOpenPositions($follAcc->leaderAccountNumber());
            $this->info($workflow, DateTime::NOW(), sprintf(
                    'account %s, server %s - %d open positions.',
                    $follAcc->leaderAccountNumber(),
                    $follAcc->leaderServer(),
                    $openOrdersCount
                )
            );
            if ($openOrdersCount == 0) {
                $this->info($workflow, $now, "reason: workflow account has not any open orders (server {$follAcc->leaderServer()}).");
                return $now;
            }

            if (!$this->tradeOrders->hasOpenPositions($follAcc) && $workflow->type() != ResumeCopyingWorkflow::TYPE) {
                $this->info($workflow, DateTime::NOW(), 'follower account is paused and wotkflow\'s type is not "Resume".');
                return $now;
            }

            $tradeAcc = $this->tradeAccounts->fetchAccountByNumber(
                $follAcc->leaderAccountNumber(),
                $this->brokerRepository->getByTradeAccount($follAcc->leaderAccountNumber())
            );

        }

        $holidays = $this->getHolidays($tradeAcc);
        $sessions = $this->getSessions($tradeAcc);

        $delay = intval($this->settings->get("workflows.schedule_delay_mins", 5));

        $isHoliday = function (DateTime $dt) use ($holidays, $delay) {
            $date = $dt->format("Y-m-d");
            if (empty($holidays[$date])) {
                return false;
            }

            $time = Time::fromString($dt->format("H:i:s"));
            foreach ($holidays[$date] as $interval) {
                if ($interval->start()->plusMinutes($delay) <= $time && $time <= $interval->end()) {
                    return false;
                }
            }
            return true;
        };

        $endOfHoliday = function (DateTime $dt) use ($holidays, $delay, &$endOfHoliday) {
            $date = $dt->format("Y-m-d");
            if (empty($holidays[$date])) {
                return $dt;
            }

            $time = Time::fromString($dt->format("H:i:s"));
            foreach ($holidays[$date] as $interval) {
                if ($time < $interval->start()->plusMinutes($delay)) {
                    return $dt->withTime($interval->start()->plusMinutes($delay));
                }
                if ($time <= $interval->end()) {
                    return $dt;
                }
            }
            return $endOfHoliday($dt->nextDay());
        };

        $isSession = function (DateTime $dt) use ($sessions, $delay) {
            $day = $dt->getWeekdayNumber();
            if (empty($sessions[$day])) {
                return false;
            }

            $time = Time::fromString($dt->format("H:i:s"));
            foreach ($sessions[$day] as $interval) {
                if ($interval->start()->plusMinutes($delay) <= $time && $time <= $interval->end()) {
                    return true;
                }
            }
            return false;
        };

        $nextSession = function (DateTime $dt) use ($sessions, $delay) {
            $c = $dt->getWeekdayNumber();
            $t = 7;
            for ($i = ($c - 1); $i < ($c + $t - 1); $i++) {
                $day = ($i % $t + 1);

                if (empty($sessions[$day])) {
                    $dt = $dt->nextDay();
                    continue;
                }

                $time = Time::fromString($dt->format("H:i:s"));
                foreach ($sessions[$day] as $interval) {
                    if ($time < $interval->start()->plusMinutes($delay)) {
                        return $dt->withTime($interval->start()->plusMinutes($delay));
                    }
                    if ($time <= $interval->end()) {
                        return $dt;
                    }
                }

                $dt = $dt->nextDay();
            }
            throw new RuntimeException("Couldn't find a session");
        };

        $dt = $now;
        $reason = 'now is not holiday and session is open';
        do {
            if ($isHoliday($dt)) {
                $dt = $endOfHoliday($dt);
                $reason = 'now is holiday';
                continue;
            }
            if (!$isSession($dt)) {
                $dt = $nextSession($dt);
                $reason = 'session is closed now';
                continue;
            }

            $this->info($workflow, $dt, "reason: {$reason}.");
            return $dt;
        } while (true);
    }

    private function getHolidays(ServerAwareAccount $acc)
    {
        $result = [];
        foreach ($this->tradeOrders->getApplicableHolidays($acc) as $date => $symbols) {
            $result[$date] = $this->normalizeIntervals(
                array_reduce(
                    $symbols,
                    function ($carry, $items) {
                        foreach ($items as $item) {
                            $carry[$item->__toString()] = $item;
                        }
                        return $carry;
                    },
                    []
                )
            );
        }
        return $result;
    }

    private function getSessions(ServerAwareAccount $acc)
    {
        $result = [];
        foreach ($this->tradeOrders->getApplicableSessions($acc) as $day => $symbols) {
            $symbols = array_values($symbols);
            if (empty($symbols)) {
                $result[$day] = [];
                continue;
            }

            $tmp1 = $this->normalizeIntervals($symbols[0]);
            $tmp2 = [];
            for ($i = 1; $i < count($symbols); $i++) {
                while (!empty($i1 = array_shift($tmp1))) {
                    foreach ($this->normalizeIntervals($symbols[$i]) as $i2) {
                        if (!empty($o = $i1->overlap($i2))) {
                            array_push($tmp2, $o);
                        }
                    }
                }
                $tmp1 = $tmp2;
                $tmp2 = [];
            }
            $result[$day] = $tmp1;
        }
        return $result;
    }

    private function normalizeIntervals(array $intervals)
    {
        sort($intervals);

        $result = [];
        foreach ($intervals as $interval) {
            if (empty($result)) {
                $result[] = $interval;
                continue;
            }

            $last = sizeof($result) - 1;
            if ($result[$last]->overlaps($interval) || $result[$last]->isAdjacentTo($interval)) {
                $result[$last] = $result[$last]->combine($interval);
                continue;
            }

            $result[] = $interval;
        }
        return $result;
    }

    private function info(AbstractWorkflow $workflow, DateTime $date, $message)
    {
        $this->logger->info(sprintf(
            '%s: wf #%d, time %s - %s',
            self::class,
            $workflow->id(),
            $date->format('Y-m-d H:i:s'),
            $message
        ));
    }
}
