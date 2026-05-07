<?php

namespace App\Models;

use App\Casts\EncryptedArray;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentWebhookEvent extends Model
{
    use HasFactory;

    public const STATUS_PROCESSING = 'processing';
    public const STATUS_PROCESSED = 'processed';
    public const STATUS_IGNORED = 'ignored';
    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'provider',
        'event_id',
        'event_type',
        'status',
        'attempts',
        'payload',
        'pending_checkout_id',
        'order_id',
        'processed_at',
        'last_error',
    ];

    protected $casts = [
        'attempts' => 'integer',
        'payload' => EncryptedArray::class,
        'processed_at' => 'datetime',
    ];

    public function pendingCheckout(): BelongsTo
    {
        return $this->belongsTo(PendingCheckoutRecord::class, 'pending_checkout_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function isTerminal(): bool
    {
        return in_array($this->status, [self::STATUS_PROCESSED, self::STATUS_IGNORED], true);
    }
}
