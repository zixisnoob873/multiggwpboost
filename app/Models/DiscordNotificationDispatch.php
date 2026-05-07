<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DiscordNotificationDispatch extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_SENT = 'sent';
    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'fingerprint',
        'webhook_config_key',
        'message_type',
        'payload',
        'status',
        'attempts',
        'sent_at',
        'last_error',
        'context',
    ];

    protected $casts = [
        'payload' => 'array',
        'context' => 'array',
        'attempts' => 'integer',
        'sent_at' => 'datetime',
    ];
}
