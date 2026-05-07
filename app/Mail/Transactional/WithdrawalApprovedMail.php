<?php

namespace App\Mail\Transactional;

class WithdrawalApprovedMail extends AbstractTransactionalMail
{
    protected function subjectLine(): string
    {
        return 'Your withdrawal request has been approved';
    }

    protected function viewName(): string
    {
        return 'emails.transactional.withdrawal-approved';
    }
}
