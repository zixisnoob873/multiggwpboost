<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BoosterWalletAdjustment extends Model
{
    use HasFactory;

    protected $fillable = [
        'booster_id',
        'admin_id',
        'withdrawal_request_id',
        'type',
        'amount_cents',
        'reason',
    ];

    protected $casts = [
        'amount_cents' => 'integer',
    ];

    public function signedAmountCents(): int
    {
        return $this->type === 'add'
            ? (int) $this->amount_cents
            : ((int) $this->amount_cents * -1);
    }

    public function booster(): BelongsTo
    {
        return $this->belongsTo(User::class, 'booster_id')->withTrashed();
    }

    public function withdrawalRequest(): BelongsTo
    {
        return $this->belongsTo(WithdrawalRequest::class);
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_id')->withTrashed();
    }
}
