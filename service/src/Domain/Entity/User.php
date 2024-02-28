<?php

namespace Fxtm\CopyTrading\Domain\Entity;

use Serializable;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Class User
 * @package App\Entity
 *
 * @ORM\Entity(repositoryClass="Fxtm\CopyTrading\Interfaces\Repository\UserRepository")
 * @ORM\Table(name="`user`")
 * @UniqueEntity("username")
 */
class User implements UserInterface, Serializable
{
    /**
     * @ORM\Id
     * @ORM\Column(type="integer")
     * @ORM\GeneratedValue(strategy="AUTO")
     *
     * @var int
     */
    private $id;

    /**
     * @ORM\Column(type="string", length=25, unique=true)
     *
     * @var string
     */
    private $username;

    /**
     * @ORM\Column(type="string", length=64)
     *
     * @var string
     */
    private $password;

    /**
     * @ORM\Column(type="string")
     *
     * @var string
     */
    private $role;

    /**
     * @ORM\Column(name="is_active", type="boolean", options={"default":true})
     *
     * @var bool
     */
    private $isActive = true;


    # region Getters

    public function getId() : int
    {
        return $this->id;
    }

    public function getUsername() : string
    {
        return $this->username;
    }

    public function getPassword() : string
    {
        return $this->password;
    }

    public function getRole() : string
    {
        return $this->role;
    }

    public function isActive() : bool
    {
        return $this->isActive;
    }

    # endregion Getters

    /**
     * Method fills fields of this user
     *
     * @param string $username
     * @param string $password
     * @param string $role
     */
    public function fill(string $username, string $password, string $role) : void
    {
        $this->username = $username;
        $this->password = $password;
        $this->role = $role;
    }

    /**
     * Method deactivates this user
     */
    public function deactivate() : void
    {
        $this->isActive = false;
    }

    /**
     * Method activates this user
     */
    public function activate() : void
    {
        $this->isActive = true;
    }

    public function getRoles() : array
    {
        return [$this->role];
    }

    public function getSalt() : ?string
    {
        return null;
    }

    public function eraseCredentials() : void
    {
    }

    /**
     * @see \Serializable::serialize()
     */
    public function serialize() : string
    {
        return serialize([
            $this->id,
            $this->username,
            $this->password,
            $this->role,
        ]);
    }

    /**
     * @see \Serializable::unserialize()
     * @param string $serialized
     */
    public function unserialize($serialized) : void
    {
        list (
            $this->id,
            $this->username,
            $this->password,
            $this->role,
            ) = unserialize($serialized, ['allowed_classes' => false]);
    }
}
