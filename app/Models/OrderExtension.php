<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderExtension extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'customer_id',
        'service_type',
        'checkout_reference',
        'amount_cents',
        'previous_total_cents',
        'new_total_cents',
        'previous_booster_payout_cents',
        'new_booster_payout_cents',
        'selection_payload',
        'previous_order_payload',
        'updated_order_payload',
        'payment_provider',
        'payment_reference',
        'stripe_session_id',
        'paid_at',
        'metadata',
    ];

    protected $casts = [
        'amount_cents' => 'integer',
        'previous_total_cents' => 'integer',
        'new_total_cents' => 'integer',
        'previous_booster_payout_cents' => 'integer',
        'new_booster_payout_cents' => 'integer',
        'selection_payload' => 'array',
        'previous_order_payload' => 'array',
        'updated_order_payload' => 'array',
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
}
