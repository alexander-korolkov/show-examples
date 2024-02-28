<?php

namespace Fxtm\CopyTrading\Interfaces\Repository;

use Doctrine\Bundle\DoctrineBundle\Registry;
use Fxtm\CopyTrading\Domain\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;

class UserRepository extends ServiceEntityRepository
{
    /**
     * @var UserPasswordEncoderInterface
     */
    private $encoder;

    /**
     * SeminarRepository constructor.
     * @param Registry $registry
     * @param UserPasswordEncoderInterface $encoder
     */
    public function __construct(Registry $registry, UserPasswordEncoderInterface $encoder)
    {
        parent::__construct($registry, User::class);
        $this->encoder = $encoder;
    }

    /**
     * Method seeks user in the database and compares given password
     * with stored in the database
     *
     * @param string $username
     * @param string $password
     * @return User|null
     */
    public function getByCredentials(string $username, string $password) : ?User
    {
        /** @var User $user */
        $user = $this->getByUsername($username);
        if (!$user) {
            return null;
        }

        return $user->isActive() && $this->encoder->isPasswordValid($user, $password)
            ? $user
            : null;
    }

    /**
     * Returns user with given username
     *
     * @param $username
     * @return User|object
     */
    public function getByUsername(string $username) : ?User
    {
        return $this->findOneBy(['username' => $username]);
    }
}
