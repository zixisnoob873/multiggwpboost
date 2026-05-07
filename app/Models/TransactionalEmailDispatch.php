<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TransactionalEmailDispatch extends Model
{
    public const STATUS_QUEUED = 'queued';

    protected $fillable = [
        'fingerprint',
        'recipient_email',
        'recipient_name',
        'mailable',
        'payload',
        'context',
        'status',
        'queued_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'context' => 'array',
        'queued_at' => 'datetime',
    ];
}
