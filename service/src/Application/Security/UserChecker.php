<?php

namespace Fxtm\CopyTrading\Application\Security;

use Fxtm\CopyTrading\Domain\Entity\User;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\Exception\AccountStatusException;

class UserChecker implements UserCheckerInterface
{

    /**
     * Checks the user account before authentication.
     *
     * @param UserInterface $user
     * @throws AccountStatusException
     */
    public function checkPreAuth(UserInterface $user) : void
    {
        if (!$user instanceof User) {
            return;
        }

        if (!$user->isActive()) {
            throw new AccountDisabledException();
        }
    }

    /**
     * Checks the user account after authentication.
     *
     * @param UserInterface $user
     * @throws AccountStatusException
     */
    public function checkPostAuth(UserInterface $user) : void
    {
        return;
    }
}
