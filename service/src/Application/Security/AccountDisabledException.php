<?php

namespace Fxtm\CopyTrading\Application\Security;

use Symfony\Component\Security\Core\Exception\AccountStatusException;

class AccountDisabledException extends AccountStatusException
{
    /**
     * {@inheritdoc}
     */
    public function getMessageKey() : string
    {
        return 'Account has disabled.';
    }
}
