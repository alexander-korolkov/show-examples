<?php

namespace Fxtm\CopyTrading\Application\Security;

use DateTime;
use Firebase\JWT\JWT;
use Fxtm\CopyTrading\Domain\Entity\User;
use Symfony\Component\Security\Core\Role\Role;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Guard\Token\GuardTokenInterface;

class JwtToken implements GuardTokenInterface
{

    const EXPIRATION_TIME = '1 week';

    /**
     * @var string
     */
    private $secretKey;

    /**
     * @var User
     */
    private $user;

    /**
     * @var string
     */
    private $accessToken;

    /**
     * @var string
     */
    private $expiresIn;

    /**
     * JwtToken constructor.
     * @param UserInterface $user
     * @param string $secretKey
     */
    public function __construct(UserInterface $user, $secretKey)
    {
        $this->user = $user;
        $this->secretKey = $secretKey;

        $this->generateToken();
    }

    /**
     * Method generates access jwt token
     */
    private function generateToken()
    {
        $notBefore = (new DateTime('now'))->modify('- 1 second');
        $expiresIn = (new DateTime('now'))->modify('+ ' . self::EXPIRATION_TIME);
        $payloads = [
            'username' => $this->user->getUsername(),
            'role' => $this->user->getRole(),
            'nbf' => $notBefore->getTimestamp(),
            'exp' => $expiresIn->getTimestamp(),
        ];

        $this->accessToken = JWT::encode($payloads, $this->secretKey);
        $this->expiresIn = $expiresIn->getTimestamp();
    }

    /**
     * String representation of object
     * @link http://php.net/manual/en/serializable.serialize.php
     * @return string the string representation of the object or null
     * @since 5.1.0
     */
    public function serialize()
    {
        return serialize([
            clone $this->user,
            $this->secretKey,
            $this->accessToken,
            $this->expiresIn,
        ]);
    }

    /**
     * Constructs the object
     * @link http://php.net/manual/en/serializable.unserialize.php
     * @param string $serialized <p>
     * The string representation of the object.
     * </p>
     * @return void
     * @since 5.1.0
     */
    public function unserialize($serialized)
    {
        list($this->user, $this->secretKey, $this->accessToken, $this->expiresIn) = unserialize($serialized);
    }

    /**
     * Returns a string representation of the Token.
     *
     * This is only to be used for debugging purposes.
     *
     * @return string
     */
    public function __toString()
    {
        $class = \get_class($this);
        $class = substr($class, strrpos($class, '\\') + 1);

        return sprintf(
            '%s(user="%s", access_token=%s, roles="%s", expired_in="%s")',
            $class,
            $this->getUsername(),
            $this->accessToken,
            implode(', ', $this->user->getRoles()),
            $this->expiresIn
        );
    }

    /**
     * Returns the user roles.
     *
     * @return Role[] An array of Role instances
     */
    public function getRoles()
    {
        $stringRoles = $this->user->getRoles();

        return array_map(function ($role) {
            return new Role($role);
        }, $stringRoles);
    }

    /**
     * Returns the user credentials.
     *
     * @return mixed The user credentials
     */
    public function getCredentials()
    {
        return [];
    }

    /**
     * Returns a user representation.
     *
     * @return string|object Can be a UserInterface instance, an object implementing a __toString method,
     *                       or the username as a regular string
     *
     * @see AbstractToken::setUser()
     */
    public function getUser()
    {
        return $this->user;
    }

    /**
     * Sets the user in the token.
     *
     * The user can be a UserInterface instance, or an object implementing
     * a __toString method or the username as a regular string.
     *
     * @param string|object $user The user
     *
     * @throws \InvalidArgumentException
     */
    public function setUser($user)
    {
        $this->user = $user;

        $this->generateToken();
    }

    /**
     * Returns the username.
     *
     * @return string
     */
    public function getUsername()
    {
        return $this->user->getUsername();
    }

    /**
     * Returns whether the user is authenticated or not.
     *
     * @return bool true if the token has been authenticated, false otherwise
     */
    public function isAuthenticated()
    {
        return true;
    }

    /**
     * Sets the authenticated flag.
     *
     * @param bool $isAuthenticated The authenticated flag
     */
    public function setAuthenticated($isAuthenticated)
    {
        //
    }

    /**
     * Removes sensitive information from the token.
     */
    public function eraseCredentials()
    {
        $this->user->eraseCredentials();
    }

    /**
     * Returns the token attributes.
     *
     * @return array The token attributes
     */
    public function getAttributes()
    {
        return [
            'access_token' => $this->accessToken,
            'expired_in' => $this->expiresIn,
        ];
    }

    /**
     * Sets the token attributes.
     *
     * @param array $attributes The token attributes
     */
    public function setAttributes(array $attributes)
    {
        //
    }

    /**
     * Returns true if the attribute exists.
     *
     * @param string $name The attribute name
     *
     * @return bool true if the attribute exists, false otherwise
     */
    public function hasAttribute($name)
    {
        return array_key_exists($name, $this->getAttributes());
    }

    /**
     * Returns an attribute value.
     *
     * @param string $name The attribute name
     *
     * @return mixed The attribute value
     *
     * @throws \InvalidArgumentException When attribute doesn't exist for this token
     */
    public function getAttribute($name)
    {
        return $this->getAttributes()[$name];
    }

    /**
     * Sets an attribute.
     *
     * @param string $name The attribute name
     * @param mixed $value The attribute value
     */
    public function setAttribute($name, $value)
    {
        //
    }
}
