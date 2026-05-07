<?php

namespace App\Mail\Transactional;

class AccountSuspendedMail extends AbstractTransactionalMail
{
    protected function subjectLine(): string
    {
        return 'Your GGWP Boost account has been suspended';
    }

    protected function viewName(): string
    {
        return 'emails.transactional.account-suspended';
    }
}
