<?php

namespace Fxtm\CopyTrading\Interfaces\Gateway\Account;

use Exception;
use Fxtm\CopyTrading\Application\ClientGateway;
use Fxtm\CopyTrading\Application\Common\Semaphore;
use Fxtm\CopyTrading\Domain\Entity\MetaData\DataSourceFactory;
use Fxtm\CopyTrading\Domain\Model\Account\AccountType;
use Psr\Log\LoggerInterface as Logger;
use Fxtm\CopyTrading\Application\Metrix\MetrixData;
use Fxtm\CopyTrading\Application\Metrix\MetrixService;
use Fxtm\CopyTrading\Application\Services\LoggerTrait;
use Fxtm\CopyTrading\Application\GatewayException;
use Fxtm\CopyTrading\Application\TradeAccountGateway;
use Fxtm\CopyTrading\Domain\Model\Account\TradeAccount;
use Fxtm\CopyTrading\Domain\Model\Client\ClientId;
use Fxtm\CopyTrading\Domain\Model\Leader\LeaderAccount;
use Fxtm\CopyTrading\Domain\Model\Shared\AccountNumber;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use RuntimeException;

class TradeAccountGatewayImpl implements TradeAccountGateway
{
    use LoggerTrait;

    const SEMAPHORE_TIMEOUT = 10;

    /**
     * @var ClientGateway
     */
    private $clientGateway;

    /**
     * @var array
     */
    private $tradeAccountApiConfig;

    /**
     * @var DataSourceFactory
     */
    private $factory;

    /**
     * @var MetrixService
     */
    private $metricsService;

    /**
     * @var Semaphore
     */
    private $semaphore;

    /**
     * TradeAccountGatewayImpl constructor.
     * @param ClientGateway $clientGateway
     * @param DataSourceFactory $factory
     * @param array $tradeAccountApiConfig
     * @param Logger $logger
     * @param MetrixService $metricsService
     * @param Semaphore $semaphore
     */
    public function __construct(
        ClientGateway $clientGateway,
        DataSourceFactory $factory,
        array $tradeAccountApiConfig,
        Logger $logger,
        MetrixService $metricsService,
        Semaphore $semaphore
    ) {
        $this->clientGateway = $clientGateway;
        $this->factory = $factory;
        $this->tradeAccountApiConfig = $tradeAccountApiConfig;
        $this->metricsService = $metricsService;
        $this->semaphore = $semaphore;

        $this->setLogger($logger);
    }

    /**
     * @param LeaderAccount $leadAcc
     * @param string $broker
     * @return TradeAccount
     * @throws Exception
     */
    public function createAggregateAccount(LeaderAccount $leadAcc, $broker)
    {
        $aggregateAccountType = AccountType::GetAggregateTypeByLeaderType($leadAcc->accountType());
        $tradeAcc = $this->fetchAccountByNumber($leadAcc->number(), $broker);
        $leverage = $this->getLeverageForClient($leadAcc->ownerId(), $leadAcc->broker(), $aggregateAccountType);
        $groupId = $this->findSuitableGroupForAggregateAccount($leadAcc->number(), $broker);

        if (empty($groupId)) {
            throw new RuntimeException("Suitable group for aggregate account not found");
        }

        $params = [
            'account' => $aggregateAccountType,
            'currency' => $leadAcc->currency()->code(),
            'leverage' => $leverage,
            'group_id' => $groupId,
            'is_swap_free' => intval($tradeAcc->isSwapFree())
        ];

        return $this->createTradeAccount($leadAcc->ownerId(), $broker, $params);
    }

    /**
     * @param ClientId $clientId
     * @param string $followerBroker
     * @param string $leaderBroker
     * @param LeaderAccount $leadAcc
     * @return TradeAccount
     * @throws Exception
     */
    public function createFollowerAccount(ClientId $clientId, $followerBroker, $leaderBroker, LeaderAccount $leadAcc)
    {
        $follAccType = AccountType::GetFollowerTypeByLeaderType($leadAcc->accountType(), $followerBroker);
        $leverage = $this->getLeverageForClient($clientId, $followerBroker, $follAccType);
        $leadTradeAcc = $this->fetchAccountByNumber($leadAcc->number(), $leaderBroker);
        $params = [
            "account" => $follAccType,
            "currency" => $leadAcc->currency()->code(),
            "leverage" => $leverage,
            "is_swap_free" => intval($leadTradeAcc->isSwapFree())
        ];

        $follTradeAcc = $this->createTradeAccount($clientId, $followerBroker, $params);

        if ($leadTradeAcc->isSwapFree() && !$follTradeAcc->isSwapFree()) {
            $this->changeAccountSwapFree($follTradeAcc->number(), $followerBroker, 1);
        }

        return $follTradeAcc;
    }

    /**
     * @param ClientId $clientId
     * @param $broker
     * @param $follAccType
     * @return int|mixed
     * @throws Exception
     */
    private function getLeverageForClient(ClientId $clientId, $broker, $follAccType)
    {
        $client = $this->clientGateway->fetchClientByClientId($clientId, $broker);

        return min($client->getAppropriatenessLeverage(), $client->getMaxAllowedLeverageForAccountType($follAccType));
    }

    /**
     * @param ClientId $clientId
     * @param string $broker
     * @param array $params
     *
     * @return TradeAccount
     * @throws Exception
     */
    private function createTradeAccount(ClientId $clientId, $broker, array $params)
    {
        $resource = sprintf('create_trade_account:%s', $params['account']);
        if($this->semaphore->acquire($resource, self::SEMAPHORE_TIMEOUT)) {
            try {
                $this->logger->warning(sprintf("Requesting account creation: %s", print_r($params, true)));
                $response = $this->requestToTradeAccountApi(
                    'create_ct_account',
                    'POST',
                    $broker,
                    [
                        'client_id' => $clientId->value(),
                        'account_params' => $params,
                    ]
                );

                return TradeAccount::fromTradeAccountApiResponse($response);
            }
            catch (\Throwable $e) {
                throw new GatewayException($e->getMessage());
            }
            finally {
                $this->semaphore->release($resource);
            }
        }
        throw new \Exception(sprintf('Semaphore::acquire("%s") timed out', $resource));
    }

    /**
     * Returns data about the trade account with given login
     *
     * @param AccountNumber $accNo
     * @param string $broker
     * @return TradeAccount
     */
    public function fetchAccountByNumber(AccountNumber $accNo, $broker)
    {
        try {
            $response = $this->requestToTradeAccountApi(
                'get_ct_account',
                'GET',
                $broker,
                [
                    'login' => $accNo->value(),
                    'fresh_equity' => 0,
                ]
            );

            return TradeAccount::fromTradeAccountApiResponse($response);
        } catch (\Throwable $e) {
            throw new GatewayException($e->getMessage());
        }
    }

    /**
     * Returns data with a fresh equity from the WebGate
     * about trade account with given login
     *
     * @param AccountNumber $accNo
     * @param string $broker
     * @return TradeAccount
     */
    public function fetchAccountByNumberWithFreshEquity(AccountNumber $accNo, $broker)
    {
        $start = microtime(true);
        try {
            $response = $this->requestToTradeAccountApi(
                'get_ct_account',
                'GET',
                $broker,
                [
                    'login' => $accNo->value(),
                    'fresh_equity' => 1,
                ]
            );

            return TradeAccount::fromTradeAccountApiResponse($response);
        } catch (\Throwable $e) {
            throw new GatewayException($e->getMessage());
        } finally {
            $finishTime = microtime(true) - $start;
            $key = "wg_request";
            if (MetrixData::getWorker()) {
                $key .= '::' . MetrixData::getWorker();
            }

            try {
                $this->metricsService->write(
                    $key,
                    $finishTime * 1000
                );
            } catch (\Throwable $e) {
                $this->logger->error('Metrics Service Error, Details: ' . $e->getCode() . ' => ' . $e->getMessage());
            }
        }
    }

    public function changeAccountReadOnly(AccountNumber $accNo, $broker, $readOnly = true)
    {
        return $this->makeUpdateCtAccountRequest(
            $accNo->value(),
            $broker,
            'read_only',
            intval($readOnly)
        );
    }

    public function changeAccountLeverage(AccountNumber $accNo, $broker, $leverage)
    {
        return $this->makeUpdateCtAccountRequest(
            $accNo->value(),
            $broker,
            'leverage',
            $leverage
        );
    }

    public function changeAccountSwapFree(AccountNumber $accNo, $broker, $isSwapFree)
    {
        return $this->makeUpdateCtAccountRequest(
            $accNo->value(),
            $broker,
            'swap_free',
            (int)$isSwapFree
        );
    }

    public function changeAccountShowEquity(AccountNumber $accNo, $broker, $showEquity)
    {
        return $this->makeUpdateCtAccountRequest(
            $accNo->value(),
            $broker,
            'show_equity',
            (int)$showEquity
        );
    }

    public function refresh(AccountNumber $accNo, $broker)
    {
        return $this->makeUpdateCtAccountRequest(
            $accNo->value(),
            $broker,
            'refresh',
            1
        );
    }

    public function destroyAccount(AccountNumber $accNo, $broker)
    {
        try {
            $response = $this->requestToTradeAccountApi(
                'destroy_ct_account',
                'DELETE',
                $broker,
                [
                    'login' => $accNo->value(),
                ]
            );

            return $response['result'];
        } catch (\Throwable $e) {
            throw new GatewayException($e->getMessage());
        }
    }

    /**
     * @param string $broker
     * @param string $accountNumber
     * @param float $amountDifference
     * @return array
     */
    public function adjustAggregatorBalance(string $broker, string $accountNumber, float $amountDifference): array
    {
        try {
            $this->logger->info(
                "Sent request to adjusting balance of the aggregator account: {$accountNumber}",
                [
                    'account_number' => $accountNumber,
                    'amount' => $amountDifference,
                ]
            );

            $response = $this->requestToTradeAccountApi(
                'adjust_aggregator_balance',
                'POST',
                $broker,
                [
                    'account_number' => $accountNumber,
                    'amount_difference' => $amountDifference,
                ]
            );

            $this->logger->info("The request to adjust balance of account {$accountNumber} has been executed successfully");

            return $response;
        } catch (\Throwable $exception) {
            throw new GatewayException($exception->getMessage());
        }
    }

    /**
     * Make request to TradeAccountApi of given $broker
     * to given $endpoint with given $method and $data
     *
     * @param string $endpoint
     * @param string $method [GET, POST, PATCH, DELETE]
     * @param string $broker
     * @param array $data
     *
     * @return array TradeAccountApi Response
     * @throws Exception
     */
    private function requestToTradeAccountApi($endpoint, $method, $broker, array $data)
    {
        if (!isset($this->tradeAccountApiConfig[$broker])) {
            throw new Exception("Configuration for Trade Account API of broker {$broker} is missing.");
        }

        $guzzle = new Client(['http_errors' => false]);

        if ($method == 'POST') {
            $dataType = 'json';
        } else {
            $dataType = 'query';
        }

        try {
            $response = $guzzle->request($method, $this->tradeAccountApiConfig[$broker]['base_uri'] . '/' . $endpoint . '/', [
                $dataType => $data,
                'curl' => [
                    CURLOPT_SSLCERT => $this->tradeAccountApiConfig[$broker]['cert'],
                    CURLOPT_SSLKEY => $this->tradeAccountApiConfig[$broker]['key'],
                ],
            ]);
            $bodyStr = (string)$response->getBody();
            $result = json_decode($bodyStr, true);

            if ($response->getStatusCode() != 200) {
                throw new Exception("Request to TradeAccountApi->{$endpoint} failed: status not 200 ({$response->getStatusCode()}): {$bodyStr}");
            }

            if ($result === null) {
                throw new Exception("TradeAccountApi Exception: {$bodyStr}");
            }

            return $result;
        } catch (GuzzleException $e) {
            $responseBody = $responseCode = '';
            if ($e instanceof RequestException && $e->getResponse()) {
                $responseBody = (string) $e->getResponse()->getBody();
                $responseCode = $e->getResponse()->getStatusCode();
            }
            $exceptionMsg = sprintf(
                'Request to TradeAccountApi->%s failed: `%s` %s (HTTP %s: `%s`)',
                $endpoint,
                get_class($e),
                $e->getMessage(),
                $responseCode,
                $responseBody
            );
            throw new Exception($exceptionMsg);
        }
    }

    /**
     * @param AccountNumber $leadAccNo
     * @param string $broker
     * @return mixed
     */
    private function findSuitableGroupForAggregateAccount(AccountNumber $leadAccNo, $broker)
    {
        $leaderAccount = $this->fetchAccountByNumber($leadAccNo, $broker);

        $groupName = $this
            ->factory
            ->getCTConnection()
            ->executeQuery("SELECT aggregate_group_name FROM account_groups_mapping WHERE leader_group_name = ?", [$leaderAccount->groupName()])
            ->fetchOne();

        $groupName = addcslashes($groupName, '\\');

        $sql = "
                SELECT n.id
                FROM account_group n
                WHERE n.name = '{$groupName}'
            ";

        return $this
            ->factory
            ->getMyConnection($broker)
            ->executeQuery($sql)
            ->fetchColumn();
    }

    private function makeUpdateCtAccountRequest($accNo, $broker, $property, $value)
    {
        try {
            $response = $this->requestToTradeAccountApi(
                'update_ct_account',
                'PATCH',
                $broker,
                [
                    'login' => $accNo,
                    'property' => $property,
                    'value' => $value,
                ]
            );

            return $response['result'];
        } catch (\Throwable $e) {
            throw new GatewayException($e->getMessage());
        }
    }
}
