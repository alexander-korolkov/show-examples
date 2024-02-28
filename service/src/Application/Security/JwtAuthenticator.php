<?php

namespace Fxtm\CopyTrading\Application\Security;

use Firebase\JWT\JWT;
use Fxtm\CopyTrading\Interfaces\Repository\UserRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Guard\AuthenticatorInterface;
use Symfony\Component\Security\Guard\Token\GuardTokenInterface;

class JwtAuthenticator implements AuthenticatorInterface
{
    /**
     * @var UserRepository
     */
    private $userRepository;

    /**
     * @var string
     */
    private $secretKey;

    /**
     * JwtAuthenticator constructor.
     * @param UserRepository $userRepository
     * @param string $secretKey
     */
    public function __construct(UserRepository $userRepository, string $secretKey)
    {
        $this->userRepository = $userRepository;
        $this->secretKey = $secretKey;
    }

    /**
     * Does the authenticator support the given Request?
     *
     * If this returns false, the authenticator will be skipped.
     *
     * @param Request $request
     *
     * @return bool
     */
    public function supports(Request $request) : bool
    {
        return $request->headers->has('Authorization');
    }

    /**
     * Create an authenticated token for the given user.
     *
     * If you don't care about which token class is used or don't really
     * understand what a "token" is, you can skip this method by extending
     * the AbstractGuardAuthenticator class from your authenticator.
     *
     * @see AbstractGuardAuthenticator
     *
     * @param UserInterface $user
     * @param string $providerKey The provider (i.e. firewall) key
     *
     * @return GuardTokenInterface
     */
    public function createAuthenticatedToken(UserInterface $user, $providerKey)
    {
        return new JwtToken($user, $this->secretKey);
    }

    /**
     * Returns a response that directs the user to authenticate.
     *
     * @param Request $request The request that resulted in an AuthenticationException
     * @param AuthenticationException $authException The exception that started the authentication process
     * @return Response
     */
    public function start(Request $request, AuthenticationException $authException = null) : Response
    {
        return new Response('Authentication Required.', Response::HTTP_UNAUTHORIZED);
    }

    /**
     * Get the authentication credentials from the request and return them
     *
     * @param Request $request
     * @return array
     * @throws \UnexpectedValueException If null is returned
     */
    public function getCredentials(Request $request) : array
    {
        $authData = explode(' ', $request->headers->get('Authorization'));
        if (count($authData) != 2 || $authData[0] != 'Bearer' || !$authData[1]) {
            throw new AuthenticationException('Authorization header is required.');
        }

        $token = $authData[1];

        return (array) JWT::decode($token, $this->secretKey, ['HS256']);
    }

    /**
     * Return a UserInterface object based on the credentials.
     *
     * @param array $credentials
     * @param UserProviderInterface $userProvider
     * @return UserInterface|null
     * @throws AuthenticationException
     */
    public function getUser($credentials, UserProviderInterface $userProvider) : ?UserInterface
    {
        return $this->userRepository->getByUsername($credentials['username']);
    }

    /**
     * Returns true if the credentials are valid.
     *
     * @param array $credentials
     * @param UserInterface $user
     * @return bool
     * @throws AuthenticationException
     */
    public function checkCredentials($credentials, UserInterface $user) : bool
    {
        return in_array($credentials['role'], $user->getRoles());
    }

    /**
     * Called when authentication executed, but failed (e.g. wrong username password).
     *
     * @param Request $request
     * @param AuthenticationException $exception
     * @return Response|null
     */
    public function onAuthenticationFailure(Request $request, AuthenticationException $exception) : Response
    {
        return new Response($exception->getMessage(), Response::HTTP_FORBIDDEN);
    }

    /**
     * Called when authentication executed and was successful!
     *
     * @param Request $request
     * @param TokenInterface $token
     * @param string $providerKey The provider (i.e. firewall) key
     * @return Response|null
     */
    public function onAuthenticationSuccess(Request $request, TokenInterface $token, $providerKey) : ?Response
    {
        return null;
    }

    /**
     * Does this method support remember me cookies?
     *
     * @return bool
     */
    public function supportsRememberMe() : bool
    {
        return false;
    }
}
