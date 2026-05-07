<?php

namespace App\Mail\Transactional;

class AccountCreatedMail extends AbstractTransactionalMail
{
    protected function subjectLine(): string
    {
        return 'Your GGWP Boost account is ready';
    }

    protected function viewName(): string
    {
        return 'emails.transactional.account-created';
    }
}
