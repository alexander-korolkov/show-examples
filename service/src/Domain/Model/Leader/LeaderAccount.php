<?php

namespace Fxtm\CopyTrading\Domain\Model\Leader;

use Fxtm\CopyTrading\Domain\Common\DateTime;
use Fxtm\CopyTrading\Domain\Common\EventSourcedEntity;
use Fxtm\CopyTrading\Domain\Common\Identity;
use Fxtm\CopyTrading\Domain\Model\Client\ClientId;
use Fxtm\CopyTrading\Domain\Model\Shared\AccountNumber;
use Fxtm\CopyTrading\Domain\Model\Shared\AccountState;
use Fxtm\CopyTrading\Domain\Model\Shared\ClosedAccount;
use Fxtm\CopyTrading\Domain\Model\Shared\Currency;
use Fxtm\CopyTrading\Domain\Model\Shared\Money;
use Fxtm\CopyTrading\Domain\Model\Shared\ServerAwareAccount;

class LeaderAccount extends EventSourcedEntity implements ServerAwareAccount
{
    /** technical bottom limit in the account currency */
    public const MIN_REQUIRED_EQUITY = 10.00;

    private $status      = AccountStatus::PASSIVE;
    private $state       = AccountState::NORMAL;
    private $accNo       = null;
    private $accountType = null;
    private $server      = null;
    private $aggrAccNo   = null;
    private $accCurr     = null;
    private $ownerId     = null;
    private $accName     = "";
    private $prevAccName = null;
    private $accDescr    = "";
    private $remunFee    = 0;
    private $balance     = 0.00;
    private $openedAt    = null;
    private $activatedAt = null;
    private $closedAt    = null;
    private $broker;

    /**
     * Set by MT server.
     *
     * @var float
     */
    protected $equity = 0.00;

    private $isCopied = false;
    private $hasOpenPositions = false;
    private $requiredEquity = self::MIN_REQUIRED_EQUITY;
    private $prepStats = 0;
    private $isPublic = true;
    private $hiddenReason = null;
    private $isFollowable = true;
    private $isSwapFree;
    private $showEquity;

    /**
     * @var bool
     */
    private $isShowTradingDetails;

    public function __construct(
        AccountNumber $accNo,
        $broker,
        $accountType,
        $server,
        Currency $accCurr,
        ClientId $ownerId,
        $accName,
        Identity $sender,
        $remunFee = 0,
        $existingAcc = false
    ) {
        parent::__construct($accNo);
        $this->prepStats = intval($existingAcc);
        $this->apply(new AccountOpened($accNo, $broker, $accountType, $server, $accCurr, $ownerId, $accName, $remunFee, $sender));
    }

    public function identity()
    {
        return $this->number();
    }

    public function number()
    {
        return new AccountNumber($this->accNo);
    }

    public function accountType()
    {
        return $this->accountType;
    }

    public function server()
    {
        return $this->server;
    }

    public function aggregateAccountNumber()
    {
        return $this->aggrAccNo ? new AccountNumber($this->aggrAccNo) : null;
    }

    public function ownerId()
    {
        return new ClientId($this->ownerId);
    }

    public function name()
    {
        return $this->accName;
    }

    public function prevName()
    {
        return $this->prevAccName;
    }

    public function currency()
    {
        return Currency::forCode($this->accCurr);
    }

    public function description()
    {
        return $this->accDescr;
    }

    public function updateName($accName)
    {
        $this->prevAccName = $this->accName;
        $this->accName = $accName;
    }

    public function setDescription($accDescr)
    {
        $this->accDescr = $accDescr;
    }

    public function remunerationFee()
    {
        return $this->remunFee;
    }

    public function assignAggregateAccountNumber(AccountNumber $aggrAccNo)
    {
        $this->aggrAccNo = $aggrAccNo->value();
    }

    public function changeRemunerationFee($remunFee, Identity $sender)
    {
        $this->ensureIsOpen();
        $this->apply(new RemunerationFeeChanged($this->number(), $remunFee, $sender));
    }

    public function changeShowEquity($showEquity, Identity $sender)
    {
        $this->ensureIsOpen();
        $this->apply(new ShowEquityChanged($this->number(), $showEquity, $sender));
    }

    public function isCopied()
    {
        return $this->isCopied;
    }

    public function enableCopying()
    {
        $this->isCopied = true;
    }

    public function disableCopying()
    {
        $this->isCopied = false;
    }

    public function balance()
    {
        return new Money($this->balance, $this->currency());
    }

    public function equity()
    {
        return new Money($this->equity, $this->currency());
    }

    public function requiredEquity()
    {
        return new Money($this->requiredEquity, $this->currency());
    }

    public function openedAt()
    {
        return DateTime::of($this->openedAt);
    }

    public function depositFunds(Money $funds, Identity $sender)
    {
        $this->ensureIsOpen();
        $this->apply(new FundsDeposited($this->number(), $funds, $sender));
    }

    public function withdrawFunds(Money $funds, Identity $sender)
    {
        $this->ensureIsOpen();
        $this->apply(new FundsWithdrawn($this->number(), $funds, $sender));
    }

    public function hasOpenPositions()
    {
        return $this->hasOpenPositions;
    }

    public function isOpen()
    {
        return $this->status !== AccountStatus::CLOSED && $this->status !== AccountStatus::DELETED;
    }

    public function isClosed()
    {
        return $this->status === AccountStatus::CLOSED || $this->status == AccountStatus::DELETED;
    }

    public function isActivated()
    {
        return $this->status === AccountStatus::ACTIVE;
    }

    public function isBlocked()
    {
        return $this->state === AccountState::BLOCKED;
    }

    public function isPublic()
    {
        return $this->isPublic;
    }

    public function makePublic()
    {
        $this->isPublic = true;
    }

    public function makePrivate()
    {
        $this->isPublic = false;
    }

    public function setHiddenReason($reason)
    {
        $this->hiddenReason = $reason;
    }

    public function getHiddenReason()
    {
        return $this->hiddenReason;
    }

    public function isFollowable()
    {
        return $this->isFollowable;
    }

    public function acceptFollowers()
    {
        $this->isFollowable = true;
    }

    public function rejectFollowers()
    {
        $this->isFollowable = false;
    }

    public function getPrivacyMode()
    {
        if ($this->isPublic()) {
            return PrivacyMode::PUBLIK;
        }

        if ($this->isFollowable()) {
            return PrivacyMode::PRYVATE;
        }

        return PrivacyMode::OFF;
    }

    public function activate(Identity $sender)
    {
        $this->ensureIsOpen();
        if (!$this->hasEnoughEquity()) {
            //throw new InsufficientFunds($this->equity());
        }
        if ($this->isActivated()) {
            return;
        }
        $this->apply(new AccountActivated($this->number(), $sender));
    }

    public function deactivate()
    {
        $this->ensureIsOpen();
        $this->status = AccountStatus::PASSIVE;
    }

    public function setActivationDatetime(DateTime $activatedAt)
    {
        if (!$this->isActivated()) {
            return;
        }
        $this->activatedAt = $activatedAt->__toString();
    }

    public function close(Identity $sender)
    {
        $this->ensureIsOpen();
        $this->ensureHasNoOpenPositions();
        $this->apply(new AccountClosed($this->number(), $sender));
    }

    public function delete(Identity $sender)
    {
        $this->ensureIsOpen();
        $this->ensureHasNoOpenPositions();
        $this->apply(new AccountDeleted($this->number(), $sender));
    }


    protected function whenAccountOpened(AccountOpened $evt)
    {
        $this->accNo       = $evt->getAccountNumber()->value();
        $this->broker      = $evt->getBroker();
        $this->accountType = $evt->getAccountType();
        $this->server      = $evt->getServer();
        $this->accCurr     = $evt->getAccountCurrency()->code();
        $this->ownerId     = $evt->getAccountOwnerId()->value();
        $this->accName     = $evt->getAccountName();
        $this->remunFee    = $evt->getRemunerationFee();
        $this->openedAt    = $evt->getOccurredAt()->__toString();
    }

    protected function whenRemunerationFeeChanged(RemunerationFeeChanged $evt)
    {
        $evt->setOldRemunerationFee($this->remunFee);
        $this->remunFee = $evt->getNewRemunerationFee();
    }

    protected function whenShowEquityChanged(ShowEquityChanged $evt)
    {
        $evt->setOldShowEquity($this->showEquity);
        $this->showEquity = $evt->getNewShowEquity();
    }

    protected function whenFundsDeposited(FundsDeposited $evt)
    {
        $this->balance = $this->balance()->add($evt->getFunds())->amount();
        if ($this->hasEnoughEquity() && $this->status === AccountStatus::PASSIVE) {
            $this->activate($evt->getWorkflowId());
        }
    }

    protected function whenFundsWithdrawn(FundsWithdrawn $evt)
    {
        $this->balance = $this->balance()->subtract($evt->getFunds())->amount();
    }

    protected function whenAccountActivated(AccountActivated $evt)
    {
        $this->status = AccountStatus::ACTIVE;
        $this->activatedAt = $evt->getOccurredAt()->__toString();
    }

    protected function whenAccountClosed(AccountClosed $evt)
    {
        $this->status = AccountStatus::CLOSED;
        $this->closedAt = $evt->getOccurredAt()->__toString();
    }

    protected function whenAccountDeleted(AccountDeleted $evt)
    {
        $this->status = AccountStatus::DELETED;
        $this->closedAt = $evt->getOccurredAt()->__toString();
    }

    private function hasEnoughEquity()
    {
        return $this->equity()->isGreaterThanOrEqualTo($this->requiredEquity());
    }

    private function ensureHasNoOpenPositions()
    {
        if ($this->hasOpenPositions()) {
            throw new AccountHasOpenPositions();
        }
    }

    private function ensureIsOpen()
    {
        if (!$this->isOpen()) {
            throw new ClosedAccount();
        }
    }

    public function toArray()
    {
        return [
            'acc_no'         => $this->accNo,
            'broker'         => $this->broker,
            'account_type'   => $this->accountType,
            'server'         => $this->server,
            'aggr_acc_no'    => $this->aggrAccNo,
            'owner_id'       => $this->ownerId,
            'acc_name'       => $this->accName,
            'prev_acc_name'  => $this->prevAccName,
            'acc_descr'      => $this->accDescr,
            'acc_curr'       => $this->accCurr,
            'remun_fee'      => $this->remunFee,
            'is_copied'      => $this->isCopied ? 1 : 0,
            'balance'        => $this->balance,
            'status'         => $this->status,
            'state'          => $this->state,
            'is_public'      => $this->isPublic ? 1 : 0,
            'hidden_reason'  => $this->hiddenReason,
            'is_followable'  => $this->isFollowable ? 1 : 0,
            'opened_at'      => $this->openedAt,
            'activated_at'   => $this->activatedAt,
            'closed_at'      => $this->closedAt,
            'prepare_stats'  => $this->prepStats,
            'show_equity'    => $this->showEquity,
        ];
    }

    public function fromArray(array $array)
    {
        $this->accNo        = $array['acc_no'];
        $this->broker       = $array['broker'];
        $this->accountType  = $array['account_type'];
        $this->server       = $array['server'];
        $this->aggrAccNo    = $array['aggr_acc_no'];
        $this->ownerId      = $array['owner_id'];
        $this->accName      = $array['acc_name'];
        $this->prevAccName  = $array['prev_acc_name'];
        $this->accDescr     = $array['acc_descr'];
        $this->accCurr      = $array['acc_curr'];
        $this->remunFee     = $array['remun_fee'];
        $this->isCopied     = $array['is_copied'];
        $this->balance      = $array['balance'];
        $this->status       = intval($array['status']);
        $this->state        = intval($array['state']);
        $this->isPublic     = $array['is_public'];
        $this->hiddenReason = $array['hidden_reason'];
        $this->isFollowable = $array['is_followable'];
        $this->openedAt     = $array['opened_at'];
        $this->activatedAt  = $array['activated_at'];
        $this->closedAt     = $array['closed_at'];
        $this->prepStats    = $array['prepare_stats'];
        $this->showEquity   = $array['show_equity'];
        $this->isShowTradingDetails    = $array['show_trading_details'];
    }

    public function __toString()
    {
        return print_r($this->toArray(), true);
    }

    /**
     * @return string
     */
    public function broker()
    {
        return $this->broker;
    }

    /**
     * @return bool
     */
    public function isSwapFree()
    {
        return $this->isSwapFree;
    }

    public function getShowEquity()
    {
        return $this->showEquity;
    }

    public function setShowEquity($showEquity): LeaderAccount
    {
        $this->showEquity = $showEquity;
        return $this;
    }

    /**
     * @return string
     */
    public function urlName()
    {
        return str_replace(" ", "~", $this->name());
    }

    /**
     * @return bool
     */
    public function isShowTradingDetails(): bool
    {
        return $this->isShowTradingDetails;
    }
}
