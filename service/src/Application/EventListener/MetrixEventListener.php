<?php

namespace Fxtm\CopyTrading\Application\EventListener;

use Fxtm\CopyTrading\Application\Common\Environment;
use Fxtm\CopyTrading\Application\Metrix\MetrixService;
use Fxtm\CopyTrading\Application\Metrix\Utils\TimeUtils;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;
use Symfony\Component\HttpKernel\Event\FilterResponseEvent;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class MetrixEventListener implements EventSubscriberInterface
{
    /**
     * @var MetrixService
     */
    private $metrixService;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * MetrixEventListener constructor.
     * @param MetrixService $metrixService
     * @param LoggerInterface $logger
     */
    public function __construct(MetrixService $metrixService, LoggerInterface $logger)
    {
        $this->metrixService = $metrixService;
        $this->logger = $logger;
    }

    /**
     * Define requestId and save time for metrix
     *
     * @param GetResponseEvent $event
     * @throws \Exception
     */
    public function onKernelRequest(GetResponseEvent $event) : void
    {
        if (!Environment::isProd()) {
            return;
        }

        try {
            $request = $event->getRequest();

            $requestId = $request->headers->get('X-FX-REQUEST-ID');
            if (empty($requestId)) {
                $requestId = Uuid::uuid4()->toString();
            }
            define('FX_REQUEST_ID', $requestId);

            $currentTime = TimeUtils::getCurrentNanotime();
            $requestTime = $request->headers->get('X-FX-REQUEST-TIME');
            if (empty($requestTime)) {
                $requestTime = $currentTime;
            }
            define('FX_REQUEST_TIME', $requestTime);

            $endpoint = $request->headers->get('X-FX-REQUEST-ENDPOINT');
            if (empty($endpoint)) {
                $endpoint = $request->getRequestUri();
                $endpoint = explode('?', $endpoint)[0];
                $endpoint = str_replace(['http://', 'https://'], '', $endpoint);
                $endpoint = preg_replace('/([^{%,\/}]\d+)/', '{NUMBER}', $endpoint);
            }
            define('FX_REQUEST_ENDPOINT', $endpoint);

            $broker = $request->headers->get('X-FX-REQUEST-BROKER');
            if (empty($broker)) {
                $broker = 'unknown';
            }
            define('FX_REQUEST_BROKER', $broker);

            $value = TimeUtils::nanoTimeToMicro($currentTime - $requestTime);
            if ($value > 0) {
                $this->metrixService->write(
                    'ct_api_start',
                    $value,
                    [
                        'fx_endpoint' => $endpoint,
                        'fx_broker' => $broker,
                    ],
                    [
                        'fx_request_id' => $requestId,
                    ],
                    $requestTime
                );
            }
        } catch (\Throwable $e) {
            $this->logger->error('Sending event "ct_api_start" to Influx failed. Exception: ' . $e->getMessage());
        }
    }

    /**
     * Define requestId and save time for metrix
     *
     * @param FilterResponseEvent $event
     */
    public function onKernelResponse(FilterResponseEvent $event) : void
    {
        if (!Environment::isProd()) {
            return;
        }

        if (!defined('FX_REQUEST_ID') ||
            !defined('FX_REQUEST_TIME') ||
            !defined('FX_REQUEST_ENDPOINT') ||
            !defined('FX_REQUEST_BROKER')
        ) {
            return;
        }

        try {
            $requestId = FX_REQUEST_ID;
            $requestTime = FX_REQUEST_TIME;
            $endpoint = FX_REQUEST_ENDPOINT;
            $broker = FX_REQUEST_BROKER;
            $currentTime = TimeUtils::getCurrentNanotime();

            $this->metrixService->write(
                'ct_api_finish',
                TimeUtils::nanoTimeToMicro($currentTime - $requestTime),
                [
                    'fx_endpoint' => $endpoint,
                    'fx_broker' => $broker,
                ],
                [
                    'fx_request_id' => $requestId,
                ],
                $requestTime
            );

        } catch (\Throwable $e) {
            $this->logger->error('Sending event "ct_api_finish" to Influx failed. Exception: ' . $e->getMessage());
        }
    }

    /**
     * Returns an array of event names this subscriber wants to listen to.
     *
     * The array keys are event names and the value can be:
     *
     *  * The method name to call (priority defaults to 0)
     *  * An array composed of the method name to call and the priority
     *  * An array of arrays composed of the method names to call and respective
     *    priorities, or 0 if unset
     *
     * For instance:
     *
     *  * array('eventName' => 'methodName')
     *  * array('eventName' => array('methodName', $priority))
     *  * array('eventName' => array(array('methodName1', $priority), array('methodName2')))
     *
     * @return array The event names to listen to
     */
    public static function getSubscribedEvents() : array
    {
        return [
            KernelEvents::REQUEST => 'onKernelRequest',
            KernelEvents::RESPONSE => 'onKernelResponse',
        ];
    }
}
