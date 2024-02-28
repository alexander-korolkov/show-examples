<?php

namespace Fxtm\CopyTrading\Application\Follower;

use Fxtm\CopyTrading\Application\EquityService;
use Fxtm\CopyTrading\Application\StatisticsService;
use Fxtm\CopyTrading\Domain\Common\DateTime;
use Fxtm\CopyTrading\Domain\Model\Follower\Commission;
use Fxtm\CopyTrading\Domain\Model\Follower\CommissionRepository;
use Fxtm\CopyTrading\Domain\Model\Follower\FollowerAccountRepository;
use Fxtm\CopyTrading\Domain\Model\Leader\LeaderAccountRepository;
use Fxtm\CopyTrading\Domain\Model\Shared\AccountNumber;

class StatementService
{
    /**
     * @var FollowerAccountRepository
     */
    private $follAccRepo;

    /**
     * @var LeaderAccountRepository
     */
    private $leadAccRepo;

    private $equitySvc;
    private $commRepo;
    private $statsSvc;

    public function __construct(
        FollowerAccountRepository $follAccRepo,
        LeaderAccountRepository $leadAccRepo,
        EquityService $equitySvc,
        CommissionRepository $commRepo,
        StatisticsService $statsSvc
    ) {
        $this->follAccRepo  = $follAccRepo;
        $this->leadAccRepo  = $leadAccRepo;
        $this->equitySvc    = $equitySvc;
        $this->commRepo     = $commRepo;
        $this->statsSvc     = $statsSvc;
    }

    public function prepareStatementData(AccountNumber $accNo, DateTime $start, DateTime $end)
    {
        $endFee = $this->commRepo->getLatestForStatement($accNo);

        if ($endFee['type'] == Commission::TYPE_CLOSE_ACCOUNT) {
            $startFee = $this->commRepo->getLastPayout($accNo);
        } else {
            $startFee = $this->commRepo->getPreviousPayout($accNo);
        }

        if (!$startFee) {
            $startEquityRow = $this->equitySvc->getFirstActiveEquity($accNo);
            $startFee = [
                'prev_equity' => $startEquityRow['equity'],
                'amount' => 0,
                'created_at' => $startEquityRow['date_time'],
            ];
        }

        if (!$startFee || !$endFee) {
            throw new \Exception(sprintf(
                'Cannot to create investment statement! AccountNumber: %s, EndFee: %s, StartFee: %s',
                $accNo->value(),
                print_r($endFee, true),
                print_r($startFee, true)
            ));
        }

        $startEquityRow = $this->equitySvc->getEquityRowByFee($accNo, $startFee['created_at'], $startFee['amount'] * -1);
        $endEquityRow = $this->equitySvc->getEquityRowByFee($accNo, $endFee['created_at'], $endFee['amount'] * -1);

        $feesPaid = array_reduce(
            $this->commRepo->findByAccountNumberForPeriod($accNo, $startFee['created_at'], $endFee['created_at']),
            function ($carry, $fee) {
                return $carry += $fee->toArray()["amount"];
            }
        );

        $startEquity = $startFee['prev_equity'] - $startFee['amount'];
        $endEquity = $endFee['type'] == Commission::TYPE_CLOSE_ACCOUNT ? 0 : $endFee['prev_equity'] - $endFee['amount'];
        $deposits = $this->equitySvc->calculateDeposits($accNo, $startEquityRow['date_time'], $endEquityRow['date_time']);
        $withdrawals = $this->equitySvc->calculateWithdrawals($accNo, $startEquityRow['date_time'], $endEquityRow['date_time']);
        $profit = $endEquity - $startEquity - ($deposits + $withdrawals);

        $follAcc = $this->follAccRepo->getLightAccountOrFail($accNo);
        $leadAcc = $this->leadAccRepo->getLightAccountOrFail($follAcc->leaderAccountNumber());
        $leadStats = $this->statsSvc->getLeaderEquityStatistics($leadAcc->number());

        return [
            "accNo"       => $follAcc->number()->value(),
            "accCurr"     => $follAcc->currency()->code(),
            "leadAccName" => $leadAcc->name(),
            "startDate"   => $start->format("Y.m.d"),
            "endDate"     => $end->format("Y.m.d"),
            "startEquity" => sprintf("%.2f", $startEquity),
            "endEquity"   => sprintf("%.2f", $endEquity),
            "profit"      => sprintf("%.2f", $profit),
            "feesPaid"    => sprintf("%.2f", $feesPaid),
            "deposits"    => sprintf("%.2f", $deposits),
            "withdrawals" => sprintf("%.2f", $withdrawals),
            "volatility"  => $leadStats["volatility"],
            "safeMode"    => $follAcc->isInSafeMode(),
            "includeNote" => $start->diff($end)->days >= 1
        ];
    }
}
