<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderTip extends Model
{
    use HasFactory;

    public const RECIPIENT_BOOSTER = 'booster';

    public const RECIPIENT_ADMIN = 'admin';

    protected $fillable = [
        'order_id',
        'customer_id',
        'booster_id',
        'recipient_type',
        'checkout_reference',
        'amount_cents',
        'payment_provider',
        'payment_reference',
        'stripe_session_id',
        'paid_at',
        'metadata',
    ];

    protected $casts = [
        'amount_cents' => 'integer',
        'metadata' => 'array',
        'paid_at' => 'datetime',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id')->withTrashed();
    }

    public function booster(): BelongsTo
    {
        return $this->belongsTo(User::class, 'booster_id')->withTrashed();
    }
}
