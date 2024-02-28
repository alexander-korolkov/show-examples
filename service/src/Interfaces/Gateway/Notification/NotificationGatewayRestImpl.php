<?php

namespace Fxtm\CopyTrading\Interfaces\Gateway\Notification;

use Exception;
use Fxtm\CopyTrading\Application\GatewayException;
use Fxtm\CopyTrading\Application\NotificationGateway;
use Fxtm\CopyTrading\Domain\Model\Client\ClientId;
use Fxtm\CopyTrading\Domain\Model\Follower\FollowerAccountRepository;
use Fxtm\CopyTrading\Domain\Model\Shared\AccountNumber;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;

class NotificationGatewayRestImpl implements NotificationGateway
{
    /**
     * @var FollowerAccountRepository
     */
    private $followerAccountRepository;

    /**
     * @var array
     */
    private $restApiConfig;

    private static $paramsMap = [
        NotificationGateway::LEADER_FUNDS_WITHDRAWN_NO_ACTIVITY_FEE => [
            'amount' => 'amount',
            'currency' => 'accCurr',
            'account' => 'accNo',
        ],
    ];

    public function __construct(FollowerAccountRepository $followerAccountRepository, array $restApiConfig)
    {
        $this->followerAccountRepository = $followerAccountRepository;
        $this->restApiConfig = $restApiConfig;
    }

    /**
     * @param ClientId $clientId
     * @param string $broker
     * @param string $msgType
     * @param array $data
     * @throws Exception
     */
    public function notifyClient(ClientId $clientId, $broker, $msgType, array $data = [])
    {
        if (in_array(
                $msgType,
                [
                    NotificationGateway::FOLLOWER_FUNDS_DEPOSITED,
                    NotificationGateway::FOLLOWER_FUNDS_WITHDRAWN,
                    NotificationGateway::FOLLOWER_COPYING_STOPPED,
                    NotificationGateway::FOLLOWER_STOPLOSS_REACHED,
                    NotificationGateway::FOLLOWER_STOPLOSS_REACHED_STOPPED,
                ]
            )
        ) {
            $data['leadAccNo'] = $this->followerAccountRepository
                ->getLightAccount(new AccountNumber($data['accNo']))
                ->leaderAccountNumber()
                ->value();
        }

        try {
            $this->request(
                'notify_ct_client',
                'POST',
                $broker,
                [
                    'client_id' => $clientId->value(),
                    'msg_type' => $msgType,
                    'data' => static::mapData($msgType, $data),
                ]
            );
        } catch (\Throwable $e) {
            throw new GatewayException($e->getMessage());
        }
    }

    private static function mapData(string $msgType, array $data) : array
    {
        if (!isset(static::$paramsMap[$msgType])) {
            return $data;
        }

        $mappedData = [];

        foreach (static::$paramsMap[$msgType] as $key => $value) {
            $mappedData[$key] = $data[$value] ?? '';
        }

        return $mappedData;
    }


    /**
     * Make request to CMS of given $broker
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
    private function request($endpoint, $method, $broker, array $data)
    {
        if (!isset($this->restApiConfig[$broker])) {
            throw new Exception("Configuration for REST API of broker {$broker} is missing.");
        }

        $guzzle = new Client(['http_errors' => false]);

        if ($method == 'POST') {
            $dataType = 'json';
        } else {
            $dataType = 'query';
        }

        try {
            $response = $guzzle->request($method, $this->restApiConfig[$broker]['base_uri'] . '/' . $endpoint . '/', [
                $dataType => $data,
                'curl' => [
                    CURLOPT_SSLCERT => $this->restApiConfig[$broker]['cert'],
                    CURLOPT_SSLKEY => $this->restApiConfig[$broker]['key'],
                ],
            ]);
            $bodyStr = (string)$response->getBody();
            $result = json_decode($bodyStr, true);

            if ($response->getStatusCode() != 200) {
                throw new Exception("Request to {$endpoint} failed: status not 200 ({$response->getStatusCode()}): {$bodyStr}");
            }

            if ($result === null) {
                throw new Exception("REST API Exception: {$bodyStr}");
            }

            return $result;
        } catch (GuzzleException $e) {
            $responseBody = $responseCode = '';
            if ($e instanceof RequestException && $e->getResponse()) {
                $responseBody = (string) $e->getResponse()->getBody();
                $responseCode = $e->getResponse()->getStatusCode();
            }
            $exceptionMsg = sprintf(
                'Request to %s failed: `%s` %s (HTTP %s: `%s`)',
                $endpoint,
                get_class($e),
                $e->getMessage(),
                $responseCode,
                $responseBody
            );
            throw new Exception($exceptionMsg);
        }
    }
}
