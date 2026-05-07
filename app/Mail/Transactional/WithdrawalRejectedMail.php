<?php

namespace App\Mail\Transactional;

class WithdrawalRejectedMail extends AbstractTransactionalMail
{
    protected function subjectLine(): string
    {
        return 'Your withdrawal request was rejected';
    }

    protected function viewName(): string
    {
        return 'emails.transactional.withdrawal-rejected';
    }
}
