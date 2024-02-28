<?php


namespace Fxtm\CopyTrading\Interfaces\Gateway\Transaction;

use \Fxtm\CopyTrading\Application\Transfer;
use Fxtm\CopyTrading\Domain\Model\Shared\AccountNumber;

class TransferProxy implements Transfer
{

    /**
     * @var array
     */
    private $row;

    public function __construct($row)
    {
        $this->row = $row;
    }

    /**
     * @inheritDoc
     */
    public function getId(): int
    {
        return intval($this->row['id']);
    }

    /**
     * @inheritDoc
     */
    public function getStatus(): int
    {
        return intval($this->row['status']);
    }

    /**
     * @inheritDoc
     */
    public function getFromAmount(): float
    {
        return floatval($this->row['from_amount']);
    }

    /**
     * @inheritDoc
     */
    public function getFromCurrency(): string
    {
        return strval($this->row['from_currency']);
    }

    /**
     * @inheritDoc
     */
    public function getToAmount(): float
    {
        return floatval($this->row['to_amount']);
    }

    /**
     * @inheritDoc
     */
    public function getToCurrency(): string
    {
        return strval($this->row['to_currency']);
    }

    /**
     * @inheritDoc
     */
    public function getTransferTypeId(): int
    {
        return intval($this->row['transfer_type']);
    }

    /**
     * @return AccountNumber
     */
    public function getFromAccountNumber(): AccountNumber
    {
        return new AccountNumber($this->row['from_account']);
    }

    /**
     * @return AccountNumber
     */
    public function getToAccountNumber(): AccountNumber
    {
        return new AccountNumber($this->row['to_account']);
    }
}