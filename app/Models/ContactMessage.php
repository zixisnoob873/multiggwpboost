<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContactMessage extends Model
{
    use HasFactory;

    public const STATUS_NEW = 'new';

    public const STATUS_READ = 'read';

    public const STATUS_REPLIED = 'replied';

    public const STATUS_IGNORED = 'ignored';

    protected $fillable = [
        'name',
        'email',
        'order_ref',
        'message',
        'status',
        'assigned_admin_id',
        'related_order_id',
        'related_customer_id',
        'internal_notes',
        'closed_at',
    ];

    protected function casts(): array
    {
        return [
            'email' => 'encrypted',
            'phone' => 'encrypted',
            'closed_at' => 'datetime',
        ];
    }

    public function assignedAdmin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_admin_id')->withTrashed();
    }

    public function relatedOrder(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'related_order_id');
    }

    public function relatedCustomer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'related_customer_id')->withTrashed();
    }

    public static function statusOptions(): array
    {
        return [
            self::STATUS_NEW => 'New',
            self::STATUS_READ => 'Read',
            self::STATUS_REPLIED => 'Replied',
            self::STATUS_IGNORED => 'Ignored',
        ];
    }

    public static function activeStatuses(): array
    {
        return [
            self::STATUS_NEW,
            self::STATUS_READ,
        ];
    }

    public static function resolvedStatuses(): array
    {
        return [
            self::STATUS_REPLIED,
            self::STATUS_IGNORED,
        ];
    }

    public static function transitionTargets(?string $status): array
    {
        return match ($status) {
            self::STATUS_NEW => [self::STATUS_NEW, self::STATUS_READ, self::STATUS_REPLIED, self::STATUS_IGNORED],
            self::STATUS_READ => [self::STATUS_READ, self::STATUS_REPLIED, self::STATUS_IGNORED],
            self::STATUS_REPLIED => [self::STATUS_REPLIED, self::STATUS_READ, self::STATUS_IGNORED],
            self::STATUS_IGNORED => [self::STATUS_IGNORED, self::STATUS_READ, self::STATUS_REPLIED],
            default => array_keys(self::statusOptions()),
        };
    }

    public static function canTransition(?string $fromStatus, ?string $toStatus): bool
    {
        if (! is_string($toStatus) || ! array_key_exists($toStatus, self::statusOptions())) {
            return false;
        }

        return in_array($toStatus, self::transitionTargets($fromStatus), true);
    }

    public function canTransitionTo(string $status): bool
    {
        return self::canTransition($this->status, $status);
    }

    public function statusLabel(): string
    {
        return self::statusOptions()[$this->status] ?? ucfirst((string) $this->status);
    }
}
