<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderChatMessage extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_chat_thread_id',
        'sender_id',
        'sender_role',
        'sender_name',
        'body',
    ];

    public function thread(): BelongsTo
    {
        return $this->belongsTo(OrderChatThread::class, 'order_chat_thread_id');
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id')->withTrashed();
    }
}
