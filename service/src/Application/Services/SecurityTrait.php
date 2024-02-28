<?php

namespace Fxtm\CopyTrading\Application\Services;

use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Core\Security;

trait SecurityTrait
{
    /**
     * @var Security
     */
    private $security;

    /**
     * @param Security $security
     */
    protected function setSecurityHandler(Security $security)
    {
        $this->security = $security;
    }

    /**
     * @return Security
     */
    protected function getSecurityHandler()
    {
        return $this->security;
    }

    /**
     * @param array $roles
     * @throw
     */
    protected function assertRequesterRoles(array $roles)
    {
        if (!$this->security->isGranted($roles)) {
            throw new AccessDeniedException();
        }
    }
}
