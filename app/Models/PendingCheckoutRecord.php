<?php

namespace App\Models;

use App\Casts\EncryptedArray;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PendingCheckoutRecord extends Model
{
    use HasFactory;

    protected $table = 'pending_checkouts';

    protected $fillable = [
        'token',
        'reference',
        'user_id',
        'game_id',
        'service_id',
        'payment_method',
        'price_cents',
        'total',
        'subtotal',
        'promo_code_id',
        'promo_code',
        'discount_amount',
        'request_data',
        'order_payload',
        'base_order_payload',
        'metadata',
        'completed_order_id',
        'finalized_at',
        'expires_at',
    ];

    protected $casts = [
        'price_cents' => 'integer',
        'total' => 'decimal:2',
        'subtotal' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'request_data' => EncryptedArray::class,
        'order_payload' => EncryptedArray::class,
        'base_order_payload' => EncryptedArray::class,
        'metadata' => EncryptedArray::class,
        'finalized_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    public function gameService(): BelongsTo
    {
        return $this->belongsTo(GameService::class, 'service_id');
    }

    public function completedOrder(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'completed_order_id');
    }
}
