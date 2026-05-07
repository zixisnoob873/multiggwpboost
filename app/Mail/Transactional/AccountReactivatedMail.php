<?php

namespace App\Mail\Transactional;

class AccountReactivatedMail extends AbstractTransactionalMail
{
    protected function subjectLine(): string
    {
        return 'Your GGWP Boost account has been reactivated';
    }

    protected function viewName(): string
    {
        return 'emails.transactional.account-reactivated';
    }
}
