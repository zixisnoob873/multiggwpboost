<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerOrderEmailDispatch extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_SENT = 'sent';
    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'order_id',
        'user_id',
        'fingerprint',
        'recipient_email',
        'recipient_name',
        'email_type',
        'mailable',
        'payload',
        'context',
        'status',
        'attempts',
        'sent_at',
        'last_error',
    ];

    protected $casts = [
        'payload' => 'array',
        'context' => 'array',
        'attempts' => 'integer',
        'sent_at' => 'datetime',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }
}
