<?php

namespace Fxtm\CopyTrading\Interfaces\Gateway\Market;

use Exception;
use Fxtm\CopyTrading\Application\Metrix\MetrixData;
use Fxtm\CopyTrading\Application\Metrix\MetrixService;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use Monolog\Logger;

/**
 * @deprecated
 * TODO delete it when account_candles in clickhouse will work good
 * Class MarketGateway
 * @package Fxtm\CopyTrading\Interfaces\Gateway\Market
 */
class MarketGateway
{
    /**
     * @var MetrixService
     */
    private $metricsService;

    /**
     * @var Logger
     */
    private $logger;

    /**
     * @var array
     */
    private $tradeAccountApiConfig;

    /**
     * MarketGatewayImpl constructor.
     * @param array $tradeAccountApiConfig
     * @param MetrixService $metricsService
     * @param Logger $logger
     */
    public function __construct(
        MetrixService $metricsService,
        Logger $logger,
        array $tradeAccountApiConfig
    ) {
        $this->metricsService = $metricsService;
        $this->logger = $logger;
        $this->tradeAccountApiConfig = $tradeAccountApiConfig;
    }

    /**
     * @param int $server
     * @param array $logins
     * @return array
     */
    public function getMarginsByLogins($server = null, array $logins = null)
    {
        if (!isset($this->tradeAccountApiConfig[$server])) {
            throw new Exception("Configuration for Trade Account API of server {$server} is missing.");
        }

        $guzzle = new Client(['http_errors' => false]);

        $start = microtime(true);
        try {
            $response = $guzzle->request('POST', $this->tradeAccountApiConfig[$server]['base_uri'] . '/get_bulk_equities_for_ct_account/', [
                'json' => [
                    'logins' => json_encode($logins),
                    'server' => $server,
                ],
                'curl' => [
                    CURLOPT_SSLCERT => $this->tradeAccountApiConfig[$server]['cert'],
                    CURLOPT_SSLKEY => $this->tradeAccountApiConfig[$server]['key'],
                ],
            ]);
            $bodyStr = (string)$response->getBody();
            $result = json_decode($bodyStr, true);

            if ($response->getStatusCode() != 200) {
                throw new Exception("Request to TradeAccountApi->get_bulk_equities_for_ct_account failed: status not 200 ({$response->getStatusCode()}): {$bodyStr}");
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
                'get_bulk_equities_for_ct_account',
                get_class($e),
                $e->getMessage(),
                $responseCode,
                $responseBody
            );
            throw new Exception($exceptionMsg);
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
}
