<?php

namespace Fxtm\CopyTrading\Application\EventListener;

use Firebase\JWT\ExpiredException;
use Fxtm\CopyTrading\Application\Common\Environment;
use Fxtm\CopyTrading\Application\Monitoring\MonitoringService;
use Fxtm\CopyTrading\Interfaces\Controller\ValidationException;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Debug\Exception\FatalThrowableError;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Security\Core\Exception\AuthenticationException;

class ExceptionSubscriber implements EventSubscriberInterface
{

    /**
     * @var array
     */
    private $processedExceptions = [
        ValidationException::class => 400,
        FatalThrowableError::class => 400,
        AuthenticationException::class => 403,
        AccessDeniedException::class => 403,
        AccessDeniedHttpException::class => 403,
        ExpiredException::class => 403,
        NotFoundHttpException::class => 404,
        MethodNotAllowedHttpException::class => 405,
    ];

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var MonitoringService
     */
    private $monitoringService;

    /**
     * @var bool
     */
    private $debug;

    /**
     * ExceptionSubscriber constructor.
     * @param LoggerInterface $logger
     * @param MonitoringService $monitoringService
     * @param bool $debug
     */
    public function __construct(LoggerInterface $logger, MonitoringService $monitoringService, bool $debug)
    {
        $this->logger = $logger;
        $this->monitoringService = $monitoringService;
        $this->debug = $debug;
    }

    /**
     * Handler for any exceptions of microservice
     *
     * @param ExceptionEvent $event
     */
    public function onKernelException(ExceptionEvent $event) : void
    {
        $e = $event->getException();

        list($message, $statusCode) = $this->getExceptionData($e);

        $response = new JsonResponse(['message' => $message]);
        $response->setStatusCode($statusCode);
        $event->setResponse($response);

        if ($statusCode == 500) {
            $this->logger->error($e->getMessage());
            if (Environment::isProd()) {
                $this->sendAlertToMonitoring($e->getMessage());
            }
        }
    }

    /**
     * Returns message and status code for response
     *
     * @param \Exception $e
     * @return array
     */
    private function getExceptionData(\Exception $e) : array
    {
        foreach ($this->processedExceptions as $processedException => $statusCode) {
            if ($e instanceof $processedException) {
                return [$e->getMessage(), $statusCode];
            }
        }

        return $this->debug
            ? [$e->getMessage() . PHP_EOL . ' Trace: ' . $e->getTraceAsString(), 500]
            : ['An error was occurred.', 500];
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
            KernelEvents::EXCEPTION => 'onKernelException',
        ];
    }

    /**
     * Method sends alert to monitoring service
     *
     * @param $message
     */
    private function sendAlertToMonitoring(string $message): void
    {
        $errorMessage = '';
        try {
            $status = $this->monitoringService->alert($message);
        } catch (\Exception $e) {
            $status = false;
            $errorMessage = $e->getMessage();
        }

        if (!$status) {
            $this->logger->error('Error. Failed sending alert to monitoring service. ' . $errorMessage);
        }
    }
}
