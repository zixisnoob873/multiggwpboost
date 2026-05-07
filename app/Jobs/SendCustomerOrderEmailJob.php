<?php

namespace App\Jobs;

use App\Models\CustomerOrderEmailDispatch;
use App\Services\Mail\CustomerOrderEmailNotifier;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendCustomerOrderEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    public int $timeout = 60;

    public function __construct(public readonly int $dispatchId)
    {
        $this->onQueue((string) config('payments.customer_order_emails.queue', 'notifications'));
    }

    public function backoff(): array
    {
        return [10, 60, 300, 600];
    }

    public function handle(CustomerOrderEmailNotifier $customerOrderEmailNotifier): void
    {
        $dispatch = $this->lockDispatchForSend();

        if (! $dispatch) {
            return;
        }

        try {
            Mail::to($dispatch->recipient_email)->send(
                $customerOrderEmailNotifier->makeMailable($dispatch)
            );

            CustomerOrderEmailDispatch::query()
                ->whereKey($dispatch->id)
                ->update([
                    'status' => CustomerOrderEmailDispatch::STATUS_SENT,
                    'sent_at' => now(),
                    'last_error' => null,
                ]);

            Log::channel('customer_mail')->info('Customer order email sent.', [
                'dispatch_id' => $dispatch->id,
                'email_type' => $dispatch->email_type,
                'order_id' => $dispatch->order_id,
                'user_id' => $dispatch->user_id,
            ]);
        } catch (\Throwable $exception) {
            CustomerOrderEmailDispatch::query()
                ->whereKey($dispatch->id)
                ->update([
                    'status' => CustomerOrderEmailDispatch::STATUS_FAILED,
                    'last_error' => $exception->getMessage(),
                ]);

            Log::channel('customer_mail')->warning('Customer order email attempt failed.', [
                'dispatch_id' => $dispatch->id,
                'email_type' => $dispatch->email_type,
                'order_id' => $dispatch->order_id,
                'user_id' => $dispatch->user_id,
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }

    public function failed(\Throwable $exception): void
    {
        CustomerOrderEmailDispatch::query()
            ->whereKey($this->dispatchId)
            ->where('status', '!=', CustomerOrderEmailDispatch::STATUS_SENT)
            ->update([
                'status' => CustomerOrderEmailDispatch::STATUS_FAILED,
                'last_error' => $exception->getMessage(),
            ]);

        Log::channel('customer_mail')->error('Customer order email job permanently failed.', [
            'dispatch_id' => $this->dispatchId,
            'exception' => $exception::class,
            'message' => $exception->getMessage(),
        ]);
    }

    protected function lockDispatchForSend(): ?CustomerOrderEmailDispatch
    {
        return DB::transaction(function () {
            $dispatch = CustomerOrderEmailDispatch::query()
                ->lockForUpdate()
                ->find($this->dispatchId);

            if (! $dispatch) {
                return null;
            }

            if ($dispatch->status === CustomerOrderEmailDispatch::STATUS_SENT) {
                return null;
            }

            if (
                $dispatch->status === CustomerOrderEmailDispatch::STATUS_PROCESSING
                && $dispatch->updated_at
                && $dispatch->updated_at->gt(now()->subMinutes(max(1, (int) config('payments.customer_order_emails.retry_failed_after_minutes', 10))))
            ) {
                return null;
            }

            $dispatch->forceFill([
                'status' => CustomerOrderEmailDispatch::STATUS_PROCESSING,
                'attempts' => ((int) $dispatch->attempts) + 1,
            ])->save();

            return $dispatch->refresh();
        }, 3);
    }
}
