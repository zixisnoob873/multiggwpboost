<?php

namespace App\Mail\Transactional;

class PasswordResetMail extends AbstractTransactionalMail
{
    protected function subjectLine(): string
    {
        return 'Reset your GGWP Boost password';
    }

    protected function viewName(): string
    {
        return 'emails.transactional.password-reset';
    }
}
