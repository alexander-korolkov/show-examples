<?php

namespace Fxtm\CopyTrading\Interfaces\API;

use Fxtm\CopyTrading\Application\Metrix\MetrixData;
use Fxtm\CopyTrading\Interfaces\Repository\UserRepository;
use Fxtm\CopyTrading\Application\Security\JwtAuthenticator;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;

class SecurityController
{
    /**
     * @var UserRepository
     */
    private $userRepository;

    /**
     * @var JwtAuthenticator
     */
    private $authenticator;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * SecurityController constructor.
     * @param UserRepository $userRepository
     * @param JwtAuthenticator $authenticator
     * @param LoggerInterface $logger
     */
    public function __construct(
        UserRepository $userRepository,
        JwtAuthenticator $authenticator,
        LoggerInterface $logger
    ) {
        $this->userRepository = $userRepository;
        $this->authenticator = $authenticator;
        $this->logger = $logger;
        if (!MetrixData::getWorker()) {
            MetrixData::setWorker('WEB[SECURITY]');
        }
    }

    /**
     * @Route(path="/auth/login", methods={"POST"})
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function login(Request $request) : JsonResponse
    {
        $this->logger->info(sprintf('%s:%s requested.', self::class, __METHOD__));
        $time = microtime(true);

        $data = json_decode($request->getContent(), true);
        $username = isset($data['login']) ? $data['login'] : null;
        $password = isset($data['password']) ? $data['password'] : null;

        $user = $this->userRepository->getByCredentials($username, $password);
        if (!$user) {
            throw new BadCredentialsException("Incorrect login or password.");
        }

        $tokenData = $this->authenticator->createAuthenticatedToken($user, '');

        $this->logger->info(sprintf('%s:%s finished, time: %f', self::class, __METHOD__, (microtime(true) - $time)));

        return new JsonResponse($tokenData->getAttributes(), 200);
    }
}
