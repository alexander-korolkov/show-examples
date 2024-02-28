<?php


namespace Fxtm\CopyTrading\Application;

/**
 * Class TransactionGatewayExecuteResult
 *
 * Request to fx-cms for transfer execution returns multiple values,
 * this class is just container aka DTO
 *
 * @package Fxtm\CopyTrading\Application
 */
class TransactionGatewayExecuteResult
{


    const STATUS_NONE               = 0;
    const STATUS_OK                 = 1;
    const STATUS_DECLINED_BY_USER   = 2; // A workflow tobe canceled
    const STATUS_NOT_ENOUGH_BALANCE = 3;

    /**
     * @var int
     */
    private $status;

    /**
     * @var int
     */
    private $order;

    /**
     * TransactionGatewayExecuteResult constructor.
     *
     * @param int $status status of execution
     * @param int $order id of balance operation
     */
    public function __construct(int $status, int $order = -1)
    {
        $this->status   = $status;
        $this->order    = $order;
    }

    /**
     * @return int status of execution
     */
    public function getStatus() : int
    {
        return $this->status;
    }

    /**
     * @return int balance operation id or -1 if it wasn't provided
     */
    public function getOrder() : int
    {
        return $this->order;
    }

}