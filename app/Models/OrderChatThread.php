<?php

namespace App\Models;

use App\Enums\OrderChatThreadType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OrderChatThread extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'thread_type',
    ];

    protected $casts = [
        'thread_type' => OrderChatThreadType::class,
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(OrderChatMessage::class);
    }
}
