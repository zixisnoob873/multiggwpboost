<?php

namespace App\Services\Mail;

use App\Models\TransactionalEmailDispatch;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class TransactionalMailDispatcher
{
    public function queue(
        ?string $recipientEmail,
        Mailable $mailable,
        ?string $recipientName = null,
        ?string $fingerprint = null,
        array $context = []
    ): bool {
        $email = strtolower(trim((string) $recipientEmail));

        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            Log::warning('Skipped transactional email because the recipient email was invalid.', [
                'recipient_email' => $recipientEmail,
                'mailable' => $mailable::class,
            ]);

            return false;
        }

        $recipientName = $recipientName ? trim($recipientName) : null;

        if ($fingerprint !== null) {
            $dispatch = TransactionalEmailDispatch::query()->firstOrCreate(
                ['fingerprint' => $fingerprint],
                [
                    'recipient_email' => $email,
                    'recipient_name' => $recipientName,
                    'mailable' => $mailable::class,
                    'payload' => property_exists($mailable, 'payload') ? (array) $mailable->payload : null,
                    'context' => $context,
                    'status' => TransactionalEmailDispatch::STATUS_QUEUED,
                ]
            );

            if (! $dispatch->wasRecentlyCreated) {
                return false;
            }
        }

        DB::afterCommit(function () use ($email, $mailable, $recipientName, $fingerprint): void {
            Mail::to($email, $recipientName)->queue($mailable);

            if ($fingerprint !== null) {
                TransactionalEmailDispatch::query()
                    ->where('fingerprint', $fingerprint)
                    ->update([
                        'queued_at' => now(),
                        'status' => TransactionalEmailDispatch::STATUS_QUEUED,
                    ]);
            }
        });

        return true;
    }
}
