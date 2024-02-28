<?php


namespace Fxtm\CopyTrading\Interfaces\Gateway\Transaction;


use Exception;
use Fxtm\CopyTrading\Application\TransactionGateway;
use Fxtm\CopyTrading\Application\TransactionGatewayExecuteResult;
use Fxtm\CopyTrading\Application\Transfer;
use Fxtm\CopyTrading\Domain\Model\Client\ClientId;
use Fxtm\CopyTrading\Domain\Model\Shared\AccountNumber;

use Fxtm\CopyTrading\Domain\Model\Shared\Currency;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

class TransactionGatewayRESTImpl implements TransactionGateway
{

    /**
     * @var array
     */
    private $cmsConfig;

    /**
     * TransactionGatewayImpl constructor.
     * @param array $cmsConfig
     */
    public function __construct(array $cmsConfig)
    {
        $this->cmsConfig = $cmsConfig;
    }

    /**
     * @inheritDoc
     * @throws TransactionGatewayException
     */
    public function executeTransaction($transId, $broker, $kind, AccountNumber $accNo)
    {
        $request = [
            'transfer_id' => $transId,
            'transfer_login' => $accNo->value(),
            'transfer_kind' => $kind
        ];

        try {
            $result = $this->request($request, $broker, $this->cmsConfig[$broker]['url']['execute']);
            if(isset($result['status'])) {
                return new TransactionGatewayExecuteResult(
                    intval($result['status']),
                    isset($result['order']) ? intval($result['order']) : -1
                );
            }
            throw new Exception("Invalid response");
        }
        catch (\Throwable $exception) {
            throw new TransactionGatewayException("executeTransaction({$transId}): {$exception->getMessage()}", 0, $exception);
        }
    }

    /**
     * @param AccountNumber $accNo
     * @param $broker
     * @param $amount
     * @return int
     * @throws TransactionGatewayException
     */
    public function createTransaction(AccountNumber $accNo, $broker, $amount)
    {
        $request = [
            'account' => $accNo->value(),
            'amount' => $amount
        ];

        try {
            $result = $this->request($request, $broker, $this->cmsConfig[$broker]['url']['create']);
            if (!isset($result['PK'])) {
                throw new Exception("Primary key was not found in the response");
            }
            return intval($result['PK']);
        }
        catch (\Throwable $previous) {
            throw new TransactionGatewayException(
                "createTransaction({$accNo->value()}, {$broker}, {$amount}): {$previous->getMessage()}",
                0,
                $previous
            );
        }
    }

    /**
     * @param AccountNumber $accNo
     * @param $broker
     * @param $amount
     * @return int
     * @throws TransactionGatewayException
     */
    public function createSynchronousTransaction(AccountNumber $accNo, $broker, $amount)
    {
        $request = [
            'account' => $accNo->value(),
            'amount' => $amount
        ];

        try {
            $result = $this->request($request, $broker, $this->cmsConfig[$broker]['url']['create_sync']);
            if (!isset($result['PK'])) {
                throw new Exception("Primary key was not found in the response");
            }
            return intval($result['PK']);
        }
        catch (\Throwable $previous) {
            throw new TransactionGatewayException(
                "createSynchronousTransaction({$accNo->value()}, {$broker}, {$amount}): {$previous->getMessage()}",
                0,
                $previous
            );
        }
    }

    /**
     * @param $transId
     * @param $broker
     * @throws TransactionGatewayException
     */
    public function cancelTransaction($transId, $broker)
    {
        $request = [
            'PK' => $transId,
        ];

        try {
            $result = $this->request($request, $broker, $this->cmsConfig[$broker]['url']['cancel']);
            if (!isset($result['OK'])) {
                throw new Exception("Operation status was not found in the response");
            }
        }
        catch (\Throwable $previous) {
            throw new TransactionGatewayException(
                "cancelTransaction({$transId}, {$broker}): {$previous->getMessage()}",
                0,
                $previous
            );
        }
    }

    /**
     * @param $transId
     * @param $broker
     * @return float
     * @throws TransactionGatewayException
     */
    public function getWithdrawalAmount($transId, $broker)
    {
        $request = [
            'PK' => $transId,
        ];

        try {
            $result = $this->request($request, $broker, $this->cmsConfig[$broker]['url']['withdrawal_amount']);
            if (!isset($result['amount'])) {
                throw new Exception("Amount was not found in the response");
            }
            return floatval($result['amount']);
        }
        catch (\Throwable $previous) {
            throw new TransactionGatewayException(
                "getWithdrawalAmount({$transId}, {$broker}): {$previous->getMessage()}",
                0,
                $previous
            );
        }
    }

    /**
     * @param $transId
     * @param $broker
     * @return bool
     * @throws TransactionGatewayException
     */
    public function isNoActivityWithdrawal($transId, $broker)
    {
        $request = [
            'PK' => $transId,
        ];

        try {
            $result = $this->request($request, $broker, $this->cmsConfig[$broker]['url']['check_activity']);
            if (!isset($result['is_no_activity'])) {
                throw new Exception("Amount was not found in the response");
            }
            return boolval($result['is_no_activity']);
        }
        catch (\Throwable $previous) {
            throw new TransactionGatewayException(
                "isNoActivityWithdrawal({$transId}, {$broker}): {$previous->getMessage()}",
                0,
                $previous
            );
        }
    }

    /**
     * @inheritDoc
     * @throws TransactionGatewayException
     */
    public function getTransactionsForCopyTrading($broker)
    {
        $request = [
            'broker' => $broker,
        ];

        try {
            $result = $this->request($request, $broker, $this->cmsConfig[$broker]['url']['transactions']);
            if (!isset($result['rows'])) {
                throw new Exception("Date rows field was not found in the response");
            }
            if(is_array($result['rows'])) {
                return $this->hydrateTransfersArray($result['rows']);
            }
            return [];
        }
        catch (\Throwable $previous) {
            throw new TransactionGatewayException(
                "getTransactionsForCopyTrading({$broker}): {$previous->getMessage()}",
                0,
                $previous
            );
        }
    }

    /**
     * @inheritDoc
     * @throws TransactionGatewayException
     */
    public function changeExecutorToBackOffice($transferId, $broker, $comment = null)
    {
        $request = [
            'PK' => intval($transferId),
            'comment' => $comment,
        ];

        try {
            $result = $this->request($request, $broker, $this->cmsConfig[$broker]['url']['change_executor']);
            if (!isset($result['OK'])) {
                throw new Exception("Status was not found in the response");
            }
        }
        catch (\Throwable $previous) {
            throw new TransactionGatewayException(
                "changeExecutorToBackOffice({$transferId}, {$broker}): {$previous->getMessage()}",
                0,
                $previous
            );
        }
    }

    /**
     * @inheritDoc
     */
    public function createInnerBrokerTransaction($transId, $leaderAccNo, $broker, $amount)
    {
        $request = [
            'PK' => $transId,
            'account' => $leaderAccNo,
            'amount' => $amount
        ];

        try {
            $result = $this->request($request, $broker, $this->cmsConfig[$broker]['url']['create_inner_broker']);
            if (!isset($result['PK'])) {
                throw new Exception("Primary key was not found in the response");
            }
            return intval($result['PK']);
        }
        catch (\Throwable $previous) {
            throw new TransactionGatewayException(
                "createInnerBrokerTransaction({$transId}, {$leaderAccNo}, {$broker}, {$amount}): {$previous->getMessage()}",
                0,
                $previous
            );
        }
    }

    /**
     * @inheritDoc
     */
    public function createBetweenBrokersTransaction($followerBroker, $leaderBroker, $leaderClientId, $transId, $amount, Currency $currency)
    {
        try {
            $leaderClientRequest = [
                'PK' => $leaderClientId->value(),
                'currency' => $currency->code(),
            ];
            $leaderClientInfo = $this->request($leaderClientRequest, $leaderBroker, $this->cmsConfig[$leaderBroker]['url']['get_leader_details']);
            if (!isset($leaderClientInfo['client_id']) || !isset($leaderClientInfo['company_id']) || !isset($leaderClientInfo['purse'])) {
                throw new Exception("Required data was not found in the response");
            }

            $request = [
                'PK' => $transId,
                'amount' => $amount,
                'leader' => $leaderClientInfo,
                'src_broker' => $followerBroker,
                'dst_broker' => $leaderBroker
            ];
            $result = $this->request($request, $followerBroker, $this->cmsConfig[$followerBroker]['url']['create_between_first']);
            if (!isset($result['PK'])) {
                throw new Exception("Primary key was not found in the response");
            }
            return intval($result['PK']);
        }
        catch (\Throwable $previous) {
            throw new TransactionGatewayException(
                "createBetweenBrokersTransaction({$followerBroker}, {$leaderBroker}, {$leaderClientId}, {$transId}, {$amount}): {$previous->getMessage()}",
                0,
                $previous
            );
        }
    }

    /**
     * @inheritDoc
     */
    public function createBetweenBrokersTransactionSecond(
        $followerBroker,
        $leaderBroker,
        ClientId $leaderClientId,
        $transId,
        $amount
    ) {
        try {
            $firstTransferInfoRequest = [
                'PK' => $transId,
            ];
            $firstTransferInfo = $this->request($firstTransferInfoRequest, $followerBroker, $this->cmsConfig[$followerBroker]['url']['get_first_transfer_details']);
            if (!isset($firstTransferInfo['follower']) || !isset($firstTransferInfo['first_transfer']) || !isset($firstTransferInfo['dst_account'])) {
                throw new Exception("Required data was not found in the response");
            }

            $request = [
                'amount' => $amount,
                'leader' => $firstTransferInfo['dst_account'],
                'first_transfer' => $firstTransferInfo['first_transfer'],
                'follower' => $firstTransferInfo['follower'],
                'src_broker' => $followerBroker,
                'dst_broker' => $leaderBroker
            ];
            $result = $this->request($request, $leaderBroker, $this->cmsConfig[$leaderBroker]['url']['create_between_second']);
            if (!isset($result['PK'])) {
                throw new Exception("Primary key was not found in the response");
            }
            return intval($result['PK']);
        }
        catch (\Throwable $previous) {
            throw new TransactionGatewayException(
                "createBetweenBrokersTransactionSecond({$followerBroker}, {$leaderBroker}, {$leaderClientId}, {$transId}, {$amount}): {$previous->getMessage()}",
                0,
                $previous
            );
        }
    }

    /**
     * @param $transId
     * @param $broker
     * @return bool
     * @throws TransactionGatewayException
     */
    public function transferWasExecuted($transId, $broker)
    {
        $request = [
            'PK' => $transId,
        ];

        try {
            $result = $this->request($request, $broker, $this->cmsConfig[$broker]['url']['check_executed']);
            if (!isset($result['is_executed'])) {
                throw new Exception("Amount was not found in the response");
            }
            return boolval($result['is_executed']);
        }
        catch (\Throwable $previous) {
            throw new TransactionGatewayException(
                "transferWasExecuted({$transId}, {$broker}): {$previous->getMessage()}",
                0,
                $previous
            );
        }
    }

    /**
     * @inheritDoc
     */
    public function changeDestinationToWallet($transId, $broker)
    {
        $request = [
            'PK' => $transId,
        ];

        try {
            $result = $this->request($request, $broker, $this->cmsConfig[$broker]['url']['change_destination']);
            if (!isset($result['OK'])) {
                throw new Exception("Status was not found in the response");
            }
        }
        catch (\Throwable $previous) {
            throw new TransactionGatewayException(
                "changeExecutorToBackOffice({$transId}, {$broker}): {$previous->getMessage()}",
                0,
                $previous
            );
        }
    }

    /**
     * @param array $requestData
     * @param string $broker
     * @param string $endpoint
     * @param string $method
     * @return array
     * @throws Exception|GuzzleException
     */
    private function request(array $requestData, string $broker, string $endpoint, string $method = 'POST'): array
    {

        $guzzle = new Client(['http_errors' => false]);

        $response = $guzzle->request($method, $endpoint, [
            'json' => $requestData,
            'curl' => [
                CURLOPT_SSLCERT => $this->cmsConfig[$broker]['cert'],
                CURLOPT_SSLKEY => $this->cmsConfig[$broker]['key'],
            ],
        ]);

        $bodyStr = $response->getBody()->getContents();

        $result = json_decode($bodyStr, true);

        // Do not throw exception if the response status is 500 but valid JSON received;
        // This is related to internal CMS issues
        if($response->getStatusCode() == 500 && $result != null) {
            return $result;
        }

        if ($response->getStatusCode() != 200) {
            throw new Exception("request(): status not 200 ({$response->getStatusCode()}): {$bodyStr}");
        }

        if($result == null) {
            throw new Exception("request(): Invalid response ({$response->getStatusCode()})");
        }

        return $result;
    }

    /**
     * @param array $transfers
     * @return array
     */
    private function hydrateTransfersArray(array $transfers): array
    {
        $resultSet = [];
        foreach ($transfers as $row) {
            $resultSet[] = new TransferProxy($row);
        }
        return $resultSet;
    }

}