<?php

namespace Fxtm\CopyTrading\Domain\Model\Follower;

use Fxtm\CopyTrading\Domain\Common\DateTime;
use Fxtm\CopyTrading\Domain\Common\EventSourcedEntity;
use Fxtm\CopyTrading\Domain\Common\Identity;
use Fxtm\CopyTrading\Domain\Model\Client\ClientId;
use Fxtm\CopyTrading\Domain\Model\Leader\LeaderAccount;
use Fxtm\CopyTrading\Domain\Model\Shared\AccountNumber;
use Fxtm\CopyTrading\Domain\Model\Shared\AccountState;
use Fxtm\CopyTrading\Domain\Model\Shared\ClosedAccount;
use Fxtm\CopyTrading\Domain\Model\Shared\Currency;
use Fxtm\CopyTrading\Domain\Model\Shared\Money;
use Fxtm\CopyTrading\Domain\Model\Shared\ServerAwareAccount;

class FollowerAccount extends EventSourcedEntity implements ServerAwareAccount
{
    /** technical bottom limit in the account currency */
    public const MIN_REQUIRED_EQUITY = 0.10;

    public const SAFE_MODE_COPY_COEFFICIENT = 0.5;

    public const SETTLING_ACTION_TYPE_DEPOSIT = 0;
    public const SETTLING_ACTION_TYPE_WITHDRAWAL = 1;
    public const SETTLING_ACTION_TYPE_CLOSE_PERIOD = 2;
    public const SETTLING_ACTION_TYPE_CLOSE_ACCOUNT = 3;

    public const MINIMUM_FEE = 0.01;

    private $status         = AccountStatus::PASSIVE;
    private $state          = AccountState::NORMAL;
    private $accNo          = null;
    private $server         = null;
    private $leadAccNo      = null;
    private $leaderServer   = null;
    private $leaderAccountType = null;
    private $ownerId        = null;
    private $accCurr        = null;
    private $payFee         = 0;
    private $copyCoef       = 1.00;
    private $lockCopyCoef   = false;
    private $stopLossLevel  = 0;
    private $stopLossEquity = 0.00;
    private $stopLossAction = true;
    private $balance        = 0.00;
    private $openedAt       = null;
    private $closedAt       = null;
    private $activatedAt    = null;
    private $settledAt      = null;
    private $nextPayoutAt   = null;
    private $settlingEquity = 0.00;
    private $isCopying      = false;
    private $lockCopying    = false;
    private $requiredEquity = self::MIN_REQUIRED_EQUITY;
    private $requiredEquityInSafetyMode = self::MIN_REQUIRED_EQUITY * 2;
    private $broker;

    /**
     * Set by MT server.
     *
     * @var float
     */
    protected $equity = 0.00;

    public function __construct(
        AccountNumber $accNo,
        $broker,
        $server,
        ClientId $ownerId,
        LeaderAccount $leadAcc,
        Identity $sender,
        $copyCoef = 1.00,
        $stopLossLevel = 0
    ) {
        if (!$leadAcc->isOpen()) {
            throw new CantFollowClosedAccount();
        }
        $this->checkCopyCoefficient($copyCoef);
        $this->checkStopLossLevel($stopLossLevel);

        parent::__construct($accNo);
        $this->apply(
            new AccountOpened(
                $accNo,
                $broker,
                $server,
                $leadAcc->number(),
                $ownerId,
                $leadAcc->currency(),
                $leadAcc->remunerationFee(),
                $copyCoef,
                $stopLossLevel,
                $sender
            )
        );

        $this->leaderServer = $leadAcc->server();
        $this->leaderAccountType = $leadAcc->accountType();
    }

    public function identity()
    {
        return $this->number();
    }

    public function number()
    {
        return new AccountNumber($this->accNo);
    }

    public function server()
    {
        return $this->server;
    }

    public function ownerId()
    {
        return new ClientId($this->ownerId);
    }

    public function leaderAccountNumber()
    {
        return new AccountNumber($this->leadAccNo);
    }


    public function leaderAccountType()
    {
        return $this->leaderAccountType;
    }

    public function leaderServer()
    {
        return $this->leaderServer;
    }

    public function copyCoefficient()
    {
        return $this->copyCoef;
    }

    public function isInSafeMode()
    {
        return $this->copyCoef == self::SAFE_MODE_COPY_COEFFICIENT;
    }

    public function changeCopyCoefficient($copyCoef, Identity $sender)
    {
        $this->ensureIsOpen();
        $this->checkCopyCoefficient($copyCoef);
        $this->apply(new CopyCoefficientChanged($this->number(), $copyCoef, $sender));
    }

    public function lockCopyCoefficient($lock = true)
    {
        $this->lockCopyCoef = $lock;
    }

    public function isCopyCoefficientLocked()
    {
        return $this->lockCopyCoef;
    }

    public function stopLossLevel()
    {
        return $this->stopLossLevel;
    }

    public function changeStopLossLevel($stopLossLevel, Money $stopLossEquity, Identity $sender)
    {
        $this->checkStopLossLevel($stopLossLevel);
        $this->apply(new StopLossLevelChanged($this->number(), $stopLossLevel, $stopLossEquity, $sender));
    }

    public function calculateStopLossEquity($level)
    {
        return $this->equity()->divide(100)->multiply($level);
    }

    public function updateStopLossEquity()
    {
        $this->setStopLossEquity($this->calculateStopLossEquity($this->stopLossLevel()));
    }

    public function setStopLossEquity(Money $equity)
    {
        $this->stopLossEquity = $equity->amount();
    }

    public function stopCopyingOnStopLoss($stop = true)
    {
        $this->ensureIsOpen();
        $this->stopLossAction = boolval($stop);
    }

    public function isCopyingStoppedOnStopLoss()
    {
        return $this->stopLossAction;
    }

    /**
     *
     * @return Money
     */
    public function stopLossEquity()
    {
        return new Money($this->stopLossEquity, $this->currency());
    }

    public function currency()
    {
        return Currency::forCode($this->accCurr);
    }

    public function payableFee()
    {
        return $this->payFee;
    }

    public function isCopying()
    {
        return $this->isCopying;
    }

    public function resumeCopying(Identity $sender)
    {
        $this->ensureIsOpen();
        if (!$this->isActivated()) {
            throw new AccountNotActivated();
        }
        $this->apply(new CopyingResumed($this->number(), $sender));
    }

    public function pauseCopying(Identity $sender)
    {
        $this->ensureIsOpen();
        $this->apply(new CopyingPaused($this->number(), $sender));
    }

    public function lockCopying($lock = true)
    {
        $this->lockCopying = $lock;
    }

    public function isCopyingLocked()
    {
        return $this->lockCopying;
    }

    public function balance()
    {
        return new Money($this->balance, $this->currency());
    }

    public function equity()
    {
        return new Money($this->equity, $this->currency());
    }

    public function settlingEquity()
    {
        return new Money($this->settlingEquity, $this->currency());
    }

    public function profit()
    {
        return $this->equity()->subtract($this->balance());
    }

    public function remuneration()
    {
        return $this->profit()->divide(100)->multiply($this->payFee);
    }

    public function requiredEquity()
    {

        if($this->isInSafeMode()) {
            return new Money($this->requiredEquityInSafetyMode, $this->currency());
        }

        return new Money($this->requiredEquity, $this->currency());
    }

    public function openedAt()
    {
        return DateTime::of($this->openedAt);
    }

    public function activatedAt()
    {
        return DateTime::of($this->activatedAt);
    }

    public function settledAt()
    {
        return DateTime::of($this->settledAt);
    }

    public function nextPayoutAt()
    {
        return DateTime::of($this->nextPayoutAt);
    }

    public function depositFunds(Money $funds, Identity $sender)
    {
        $this->ensureIsOpen();
        $this->apply(new FundsDeposited($this->number(), $funds, $sender));
    }

    public function withdrawFunds(Money $funds, Identity $sender, $updateStopLoss = true)
    {
        $this->ensureIsOpen();
        $this->apply(new FundsWithdrawn($this->number(), $funds, $sender, $updateStopLoss));
    }

    public function isOpen()
    {
        return $this->status !== AccountStatus::CLOSED;
    }

    public function isClosed()
    {
        return $this->status === AccountStatus::CLOSED;
    }

    public function isActivated()
    {
        return $this->status === AccountStatus::ACTIVE;
    }

    public function isPassive()
    {
        return $this->status === AccountStatus::PASSIVE;
    }

    public function getStatus()
    {
        switch ($this->status) {
            case AccountStatus::PASSIVE: return "passive";
            case AccountStatus::CLOSED: return "closed";
            default: return $this->isCopying() ? "active" : "paused";
        }
    }

    public function isBlocked()
    {
        return $this->state === AccountState::BLOCKED;
    }

    public function close(Identity $sender)
    {
        $this->ensureIsOpen();
        $this->apply(new AccountClosed($this->number(), $sender));
    }

    protected function whenAccountOpened(AccountOpened $evt)
    {
        $this->accNo = $evt->getAccountNumber()->value();
        $this->broker = $evt->getBroker();
        $this->server = $evt->getServer();
        $this->leadAccNo = $evt->getLeaderAccountNumber()->value();
        $this->accCurr = $evt->getAccountCurrency()->code();
        $this->ownerId = $evt->getAccountOwnerId()->value();
        $this->payFee = $evt->getPayableFee();
        $this->copyCoef = $evt->getCopyCoefficient();
        $this->stopLossLevel = $evt->getStopLossLevel();
        $this->openedAt = $evt->getOccurredAt()->__toString();
        $this->settledAt = $this->openedAt;
        $this->nextPayoutAt = $this->calculateNextPayoutDate($evt->getOccurredAt())->__toString();
    }

    /**
     * Calculates correct date of the next payout
     *
     * @param DateTime $settledAt
     * @return DateTime
     */
    private function calculateNextPayoutDate(DateTime $settledAt)
    {
        $date = clone $settledAt;
        $date->modify('+30 days');

        if ($date->getWeekdayNumber() == 6) { //Saturday
            $date->modify('+2 days');
        } else if ($date->getWeekdayNumber() == 7) { //Sunday
            $date->modify('+1 days');
        }

        $openedAt = $this->openedAt();
        $date->setTime(
            $openedAt->getHour(),
            $openedAt->getMinute(),
            $openedAt->getSecond()
        );

        return $date;
    }

    protected function whenCopyCoefficientChanged(CopyCoefficientChanged $evt)
    {
        $evt->setOldCopyCoefficient($this->copyCoef);
        $this->copyCoef = $evt->getNewCopyCoefficient();
    }

    protected function whenStopLossLevelChanged(StopLossLevelChanged $evt)
    {
        $evt->setOldStopLossLevel($this->stopLossLevel);
        $this->stopLossLevel = $evt->getNewStopLossLevel();
        $this->stopLossEquity = $evt->getNewStopLossEquity()->amount();
    }

    protected function whenCopyingResumed(CopyingResumed $evt)
    {
        $this->isCopying = true;
    }

    protected function whenCopyingPaused(CopyingPaused $evt)
    {
        $this->isCopying = false;
    }

    protected function whenFundsDeposited(FundsDeposited $evt)
    {
        $this->balance = $this->balance()->add($evt->getFunds())->amount();
        //$this->equity = $this->equity()->add($evt->getFunds())->amount();
        $this->updateStopLossEquity();
        $this->settle($evt->getFunds(), self::SETTLING_ACTION_TYPE_DEPOSIT);
    }

    public function activate()
    {
        $occurredAt = DateTime::NOW();
        $this->status = AccountStatus::ACTIVE;
        $this->activatedAt = $occurredAt;
        $this->settledAt = $occurredAt;
        $this->nextPayoutAt = $this->calculateNextPayoutDate($occurredAt)->__toString();
    }

    protected function whenFundsWithdrawn(FundsWithdrawn $evt)
    {
        $this->balance = $this->balance()->subtract($evt->getFunds())->amount();
        //$this->equity = $this->equity()->subtract($evt->getFunds())->amount();
        if($evt->getUpdateStopLossFlag()) {
            $this->updateStopLossEquity();
        }
    }

    protected function whenAccountClosed(AccountClosed $evt)
    {
        $this->status = AccountStatus::CLOSED;
        $this->closedAt = $evt->getOccurredAt()->__toString();
    }

    private function ensureIsOpen()
    {
        if (!$this->isOpen()) {
            throw new ClosedAccount();
        }
    }

    private function checkCopyCoefficient($copyCoef)
    {
        if ($copyCoef < 0.50 || $copyCoef > 1.50) {
            throw new InvalidCopyCoefficient();
        }
    }

    private function checkStopLossLevel($stopLossLevel)
    {
        if ($stopLossLevel < 0 || $stopLossLevel > 90) {
            throw new InvalidStopLossLevel($stopLossLevel);
        }
    }

    public function hasRequiredEquity()
    {
        return $this->equity()->isGreaterThanOrEqualTo($this->requiredEquity());
    }

    public function toArray()
    {
        return array(
            'acc_no'          => $this->accNo,
            'lead_acc_no'     => $this->leadAccNo,
            'owner_id'        => $this->ownerId,
            'broker'          => $this->broker,
            'server'          => $this->server,
            'acc_curr'        => $this->accCurr,
            'pay_fee'         => $this->payFee,
            'copy_coef'       => $this->copyCoef,
            'lock_copy_coef'  => $this->lockCopyCoef ? 1 : 0,
            'stoploss_level'  => $this->stopLossLevel,
            'stoploss_equity' => $this->stopLossEquity,
            'stoploss_action' => intval($this->stopLossAction),
            'balance'         => $this->balance,
            'is_copying'      => $this->isCopying ? 1 : 0,
            'lock_copying'    => $this->lockCopying ? 1 : 0,
            'status'          => $this->status,
            'state'           => $this->state,
            'opened_at'       => $this->openedAt,
            'closed_at'       => $this->closedAt,
            'activated_at'    => $this->activatedAt,
            'settled_at'      => $this->settledAt,
            'next_payout_at'  => $this->nextPayoutAt,
            'settling_equity' => $this->settlingEquity,
        );
    }

    public function fromArray(array $array)
    {
        $this->accNo          = $array['acc_no'];
        $this->leadAccNo      = $array['lead_acc_no'];
        $this->leaderAccountType = $array['leader_account_type'];
        $this->leaderServer   = $array['leader_server'];
        $this->ownerId        = $array['owner_id'];
        $this->broker         = $array['broker'];
        $this->server         = $array['server'];
        $this->accCurr        = $array['acc_curr'];
        $this->payFee         = $array['pay_fee'];
        $this->copyCoef       = $array['copy_coef'];
        $this->lockCopyCoef   = $array['lock_copy_coef'];
        $this->stopLossLevel  = $array['stoploss_level'];
        $this->stopLossEquity = $array['stoploss_equity'];
        $this->stopLossAction = boolval($array['stoploss_action']);
        $this->balance        = $array['balance'];
        $this->equity         = $array['equity'];
        $this->isCopying      = $array['is_copying'];
        $this->lockCopying    = $array['lock_copying'];
        $this->status         = intval($array['status']);
        $this->state          = intval($array['state']);
        $this->openedAt       = $array['opened_at'];
        $this->closedAt       = $array['closed_at'];
        $this->activatedAt    = $array['activated_at'];
        $this->settledAt      = $array['settled_at'];
        $this->nextPayoutAt   = $array['next_payout_at'];
        $this->settlingEquity = $array['settling_equity'];
    }

    public function __toString()
    {
        return print_r($this->toArray(), true);
    }

    public function settle(Money $funds = null, $settlingActionType, $equity = null)
    {
        $fee = 0.00;

        $equity = $equity ? new Money($equity, $this->currency()) : $this->equity();

        switch ($settlingActionType) {
            case self::SETTLING_ACTION_TYPE_DEPOSIT:
                $this->settlingEquity = $this->settlingEquity()->add($funds)->amount();
                break;
            case self::SETTLING_ACTION_TYPE_WITHDRAWAL:
                if ($equity->amount() > $this->settlingEquity()->amount()) {
                    //Fee = Withdrawal / Equity * (Equity - Settling_equity_level) * (Fee_percentage / 100)
                    $fee = $funds->divide($equity->amount())->multiply($equity->subtract($this->settlingEquity())->amount())->multiply($this->payableFee())->divide(100)->amount();

                    //Settling equity level *= 1 - Withdrawal / Equity
                    $this->settlingEquity = $this->settlingEquity()->multiply(1 - $funds->divide($equity->amount())->amount())->amount();
                } else {
                    $this->settlingEquity = $this->settlingEquity()->subtract($funds)->amount();
                }
                break;
            case self::SETTLING_ACTION_TYPE_CLOSE_PERIOD:
                if ($equity->amount() > $this->settlingEquity()->amount()) {
                    //Fee = (Equity - Settling_equity_level) * (Fee_percentage / 100)
                    $fee = $equity->subtract($this->settlingEquity())->multiply($this->payableFee())->divide(100)->amount();

                    //Settling equity level = Equity - (Equity – Settling equity level) * (Fee_percentage / 100)
                    $this->settlingEquity = $equity->amount() - $fee;
                }
                $settledAt = DateTime::NOW();
                $this->settledAt = $settledAt->__toString();
                $this->nextPayoutAt = $this->calculateNextPayoutDate($settledAt)->__toString();
                break;
            case self::SETTLING_ACTION_TYPE_CLOSE_ACCOUNT:
                if ($equity->amount() > $this->settlingEquity()->amount()) {
                    //Fee = (Equity - Settling_equity_level) * (Fee_percentage / 100)
                    $fee = $equity->subtract($this->settlingEquity())->multiply($this->payableFee())->divide(100)->amount();
                }
                break;
            default:
                return $fee;
                break;
        }

        if ($fee > 0 && $fee < self::MINIMUM_FEE) {
            $fee = self::MINIMUM_FEE;
        }

        return $fee;
    }

    /**
     * @return string
     */
    public function broker()
    {
        return $this->broker;
    }

    public function updateNet(Money $money)
    {
        $this->balance = $this->balance()->add($money)->amount();
    }
}
