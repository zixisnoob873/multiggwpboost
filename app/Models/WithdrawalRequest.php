<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class WithdrawalRequest extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_PAID = 'paid';

    protected $fillable = [
        'booster_id',
        'amount_cents',
        'status',
        'reconciliation_status',
        'notes',
        'metadata',
        'processed_at',
        'reconciled_at',
    ];

    protected $casts = [
        'amount_cents' => 'integer',
        'metadata' => 'array',
        'processed_at' => 'datetime',
        'reconciled_at' => 'datetime',
    ];

    public function booster(): BelongsTo
    {
        return $this->belongsTo(User::class, 'booster_id')->withTrashed();
    }

    public function walletAdjustment(): HasOne
    {
        return $this->hasOne(BoosterWalletAdjustment::class);
    }
}
