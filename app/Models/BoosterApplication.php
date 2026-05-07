<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BoosterApplication extends Model
{
    public const STATUS_NEW = 'new';

    public const STATUS_REVIEWING = 'reviewing';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_HIRED = 'hired';

    protected $fillable = [
        'name',
        'nickname',
        'email',
        'current_rank',
        'peak_rank',
        'average_time',
        'discord',
        'main_account_tracker',
        'marketplace_profile',
        'regions',
        'status',
        'admin_notes',
        'reviewed_by',
        'reviewed_at',
        'converted_booster_id',
        'converted_at',
    ];

    protected function casts(): array
    {
        return [
            'email' => 'encrypted',
            'phone' => 'encrypted',
            'discord' => 'encrypted',
            'regions' => 'array',
            'reviewed_at' => 'datetime',
            'converted_at' => 'datetime',
        ];
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by')->withTrashed();
    }

    public function convertedBooster(): BelongsTo
    {
        return $this->belongsTo(User::class, 'converted_booster_id')->withTrashed();
    }

    public static function statusOptions(): array
    {
        return [
            self::STATUS_NEW => 'New',
            self::STATUS_REVIEWING => 'Reviewing',
            self::STATUS_APPROVED => 'Approved',
            self::STATUS_REJECTED => 'Rejected',
            self::STATUS_HIRED => 'Hired / Converted',
        ];
    }

    public function isConverted(): bool
    {
        return $this->converted_booster_id !== null || $this->status === self::STATUS_HIRED;
    }

    public static function transitionTargets(?string $status, bool $isConverted = false): array
    {
        if ($isConverted) {
            return [self::STATUS_HIRED];
        }

        return match ($status) {
            self::STATUS_NEW => [self::STATUS_NEW, self::STATUS_REVIEWING, self::STATUS_APPROVED, self::STATUS_REJECTED],
            self::STATUS_REVIEWING => [self::STATUS_REVIEWING, self::STATUS_APPROVED, self::STATUS_REJECTED, self::STATUS_NEW],
            self::STATUS_APPROVED => [self::STATUS_APPROVED, self::STATUS_REVIEWING, self::STATUS_REJECTED],
            self::STATUS_REJECTED => [self::STATUS_REJECTED, self::STATUS_REVIEWING, self::STATUS_APPROVED],
            self::STATUS_HIRED => [self::STATUS_HIRED],
            default => array_keys(self::statusOptions()),
        };
    }

    public static function canTransition(?string $fromStatus, ?string $toStatus, bool $isConverted = false): bool
    {
        if (! is_string($toStatus) || ! array_key_exists($toStatus, self::statusOptions())) {
            return false;
        }

        return in_array($toStatus, self::transitionTargets($fromStatus, $isConverted), true);
    }

    public function canTransitionTo(string $status): bool
    {
        return self::canTransition($this->status, $status, $this->isConverted());
    }

    public function statusLabel(): string
    {
        return self::statusOptions()[$this->status] ?? ucfirst((string) $this->status);
    }
}
