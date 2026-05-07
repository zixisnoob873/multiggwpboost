<?php

namespace App\Models;

use App\Services\Chat\EnsureOrderChatThreads;
use App\Services\Orders\OrderProgressService;
use App\Support\BoostingCatalog;
use App\Support\OrderStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'booster_id',
        'game_id',
        'service_id',
        'promo_code_id',
        'order_number',
        'product',
        'status',
        'payment_status',
        'price_cents',
        'original_price_cents',
        'discount_amount',
        'booster_payout_rate',
        'booster_payout_cents',
        'booster_payout_basis_cents',
        'currency',
        'details',
        'metadata',
        'contact_method',
        'whatsapp',
        'discord',
        'is_custom',
        'stripe_session_id',
        'payment_reference',
        'paid_at',
        'assigned_at',
        'completion_proof_path',
        'completion_proof_uploaded_at',
        'completed_at',
        'completed_by_booster_id',
    ];

    protected $casts = [
        'details' => 'array',
        'metadata' => 'array',
        'customer_email' => 'encrypted',
        'customer_phone' => 'encrypted',
        'customer_discord' => 'encrypted',
        'price_cents' => 'integer',
        'original_price_cents' => 'integer',
        'discount_amount' => 'decimal:2',
        'booster_payout_rate' => 'float',
        'booster_payout_cents' => 'integer',
        'booster_payout_basis_cents' => 'integer',
        'is_custom' => 'boolean',
        'paid_at' => 'datetime',
        'assigned_at' => 'datetime',
        'completion_proof_uploaded_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $order): void {
            $order->details = app(OrderProgressService::class)->initializeDetails($order);
        });

        static::created(function (self $order): void {
            if (! Schema::hasTable('order_chat_threads')) {
                return;
            }

            app(EnsureOrderChatThreads::class)->execute($order);
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function booster(): BelongsTo
    {
        return $this->belongsTo(User::class, 'booster_id')->withTrashed();
    }

    public function completedByBooster(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by_booster_id')->withTrashed();
    }

    public function promoCode(): BelongsTo
    {
        return $this->belongsTo(PromoCode::class);
    }

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    public function gameService(): BelongsTo
    {
        return $this->belongsTo(GameService::class, 'service_id');
    }

    public function chatThreads(): HasMany
    {
        return $this->hasMany(OrderChatThread::class);
    }

    public function extensions(): HasMany
    {
        return $this->hasMany(OrderExtension::class);
    }

    public function tips(): HasMany
    {
        return $this->hasMany(OrderTip::class);
    }

    public static function configuredBoosterPayoutPercentage(): float
    {
        return (float) config('services.booster.payout_percentage', 60);
    }

    public static function configuredBoosterPayoutRate(): float
    {
        return max(0, self::configuredBoosterPayoutPercentage() / 100);
    }

    public static function originalPriceSql(string $table = ''): string
    {
        $priceColumn = self::qualifiedColumn('price_cents', $table);
        $originalPriceColumn = self::qualifiedColumn('original_price_cents', $table);
        $discountColumn = self::qualifiedColumn('discount_amount', $table);

        return "COALESCE({$originalPriceColumn}, {$priceColumn} + ROUND(COALESCE({$discountColumn}, 0) * 100), {$priceColumn})";
    }

    public static function boosterPayoutBasisSql(string $table = ''): string
    {
        $basisColumn = self::qualifiedColumn('booster_payout_basis_cents', $table);
        $originalPriceSql = self::originalPriceSql($table);

        return "COALESCE({$basisColumn}, {$originalPriceSql})";
    }

    public static function normalizedPayoutRateSql(string $table = ''): string
    {
        $rateColumn = self::qualifiedColumn('booster_payout_rate', $table);
        $defaultRate = self::configuredBoosterPayoutPercentage();

        return "CASE WHEN COALESCE({$rateColumn}, {$defaultRate}) > 1 THEN COALESCE({$rateColumn}, {$defaultRate}) / 100 ELSE COALESCE({$rateColumn}, {$defaultRate}) END";
    }

    public static function boosterPayoutSql(string $table = ''): string
    {
        $payoutColumn = self::qualifiedColumn('booster_payout_cents', $table);
        $basisSql = self::boosterPayoutBasisSql($table);
        $rateSql = self::normalizedPayoutRateSql($table);

        return "COALESCE({$payoutColumn}, ROUND(({$basisSql}) * ({$rateSql}), 0))";
    }

    public function customerPriceCents(): int
    {
        return max(0, (int) ($this->price_cents ?? 0));
    }

    public function resolvedDiscountAmountCents(): int
    {
        return max(0, (int) round(((float) ($this->discount_amount ?? 0)) * 100));
    }

    public function resolvedOriginalPriceCents(): int
    {
        if ($this->original_price_cents !== null) {
            return max(0, (int) $this->original_price_cents);
        }

        // Legacy orders did not store the original pre-promo amount, so reconstruct it from the
        // charged total plus the recorded discount to avoid underpaying boosters on older rows.
        return max(0, $this->customerPriceCents() + $this->resolvedDiscountAmountCents());
    }

    public function resolvedBoosterPayoutBasisCents(): int
    {
        if ($this->booster_payout_basis_cents !== null) {
            return max(0, (int) $this->booster_payout_basis_cents);
        }

        return $this->resolvedOriginalPriceCents();
    }

    public function hasDiscountApplied(): bool
    {
        return $this->resolvedDiscountAmountCents() > 0;
    }

    public function resolvedBoosterPayoutCents(): int
    {
        if ($this->booster_payout_cents !== null) {
            return (int) $this->booster_payout_cents;
        }

        return (int) round($this->resolvedBoosterPayoutBasisCents() * self::configuredBoosterPayoutRate());
    }

    public function statusLabel(): string
    {
        return OrderStatus::label($this->status);
    }

    public function statusBadgeClass(): string
    {
        return OrderStatus::badgeClass($this->status);
    }

    public function statusTone(): string
    {
        return OrderStatus::tone($this->status);
    }

    public function progressPercent(): int
    {
        $details = $this->detailsPayload();
        $progressPct = data_get($details, 'progress.pct');

        if (is_numeric($progressPct)) {
            return max(0, min(100, (int) round((float) $progressPct)));
        }

        return 0;
    }

    public function detailsPayload(): array
    {
        return is_array($this->details) ? $this->details : (json_decode((string) $this->details, true) ?: []);
    }

    public function orderPayload(): array
    {
        $payload = $this->detailsPayload()['order'] ?? [];

        return is_array($payload) ? $payload : [];
    }

    public function serviceName(): string
    {
        return trim((string) ($this->orderPayload()['orderType'] ?? $this->orderPayload()['serviceType'] ?? $this->product ?? 'Rank Boosting')) ?: 'Rank Boosting';
    }

    public function gameSlug(): string
    {
        $details = $this->detailsPayload();
        $payload = $this->orderPayload();

        return app(\App\Support\GameCatalog::class)->normalizeSlug(
            $this->game?->slug
            ?? $payload['gameSlug']
            ?? $payload['game_slug']
            ?? $details['gameSlug']
            ?? $details['game']
            ?? data_get($this->metadata, 'game.slug')
            ?? 'valorant'
        );
    }

    public function gameName(): string
    {
        return $this->game?->name
            ?? (string) (data_get($this->metadata, 'game.name') ?: data_get($this->detailsPayload(), 'game') ?: app(\App\Support\GameCatalog::class)->gameName($this->gameSlug()));
    }

    public function canonicalServiceName(): string
    {
        $service = $this->serviceName();

        return BoostingCatalog::normalizeServiceType($service) ?? $service;
    }

    public function serviceKind(): ?string
    {
        $service = $this->canonicalServiceName();
        $kind = BoostingCatalog::serviceKind($service);

        if ($kind !== null) {
            return $kind;
        }

        return match (Str::lower($service)) {
            'placement game', 'placement games' => 'placement_matches',
            'rank boost' => 'rank_boost',
            default => null,
        };
    }

    public function taskLabel(): string
    {
        return match ($this->serviceKind()) {
            'placement_matches' => ($placements = $this->placementGamesCount()) > 0
                ? sprintf('%d Placements', $placements)
                : '-',
            'ranked_wins' => ($wins = $this->winsCount()) > 0
                ? sprintf('%d Wins', $wins)
                : '-',
            default => $this->rankJourneyLabel(),
        };
    }

    public function rankFromLabel(): string
    {
        $details = $this->detailsPayload();
        $payload = $this->orderPayload();

        return trim((string) ($payload['currentDivision'] ?? $payload['from'] ?? $details['from'] ?? '-')) ?: '-';
    }

    public function rankToLabel(): string
    {
        $details = $this->detailsPayload();
        $payload = $this->orderPayload();

        return trim((string) ($payload['desiredDivision'] ?? $payload['to'] ?? $details['to'] ?? '-')) ?: '-';
    }

    public function regionLabel(): string
    {
        $details = $this->detailsPayload();
        $payload = $this->orderPayload();

        return trim((string) ($payload['region'] ?? $details['region'] ?? '-')) ?: '-';
    }

    public function addonsList(): array
    {
        $details = $this->detailsPayload();
        $payload = $this->orderPayload();

        return BoostingCatalog::normalizeAddons($payload['addons'] ?? $details['addons'] ?? []);
    }

    public function addonsLabel(): string
    {
        $addons = $this->addonsList();

        return $addons !== [] ? implode(', ', $addons) : 'None';
    }

    public function isActiveStatus(): bool
    {
        return OrderStatus::isActive($this->status);
    }

    public function needsAssignment(): bool
    {
        return $this->booster_id === null && $this->isActiveStatus();
    }

    public function isClosedStatus(): bool
    {
        return OrderStatus::isClosed($this->status);
    }

    public function canAdminPause(): bool
    {
        return in_array($this->status, [OrderStatus::PENDING, OrderStatus::IN_PROGRESS], true);
    }

    public function canAdminResume(): bool
    {
        return $this->status === OrderStatus::PAUSED;
    }

    public function canAdminComplete(): bool
    {
        return in_array($this->status, [OrderStatus::PENDING, OrderStatus::IN_PROGRESS, OrderStatus::PAUSED], true);
    }

    public function canAdminCancel(): bool
    {
        return in_array($this->status, [OrderStatus::PENDING, OrderStatus::IN_PROGRESS, OrderStatus::PAUSED], true);
    }

    public function canAdminRefund(): bool
    {
        return $this->payment_status === 'paid' && $this->status !== OrderStatus::REFUNDED;
    }

    public function hasPromoApplied(): bool
    {
        return $this->promo_code_id !== null || $this->hasDiscountApplied();
    }

    public function isExpedited(): bool
    {
        return collect($this->addonsList())
            ->contains(fn (string $addon): bool => str_contains(strtolower($addon), 'express'));
    }

    public function isHighValue(): bool
    {
        return $this->resolvedOriginalPriceCents() >= 20000;
    }

    public function canBeClaimed(): bool
    {
        return OrderStatus::canBeClaimed($this->status, $this->booster_id);
    }

    public function canBoosterOpenWorkspace(): bool
    {
        return OrderStatus::canBoosterOpen($this->status);
    }

    public function canBoosterUpdateStatusTo(string $status): bool
    {
        return OrderStatus::canBoosterUpdate($this->status, $status);
    }

    public function canBoosterDrop(): bool
    {
        return OrderStatus::canBoosterDrop($this->status);
    }

    protected function rankJourneyLabel(): string
    {
        $from = $this->rankFromLabel();
        $to = $this->rankToLabel();

        if ($from === '-' && $to === '-') {
            return '-';
        }

        return sprintf('%s -> %s', $from, $to);
    }

    protected function winsCount(): int
    {
        $details = $this->detailsPayload();
        $payload = $this->orderPayload();

        return $this->resolvedOrderCount([
            $payload['numberOfWins'] ?? null,
            $details['numberOfWins'] ?? null,
            $payload['desiredDivision'] ?? null,
            $details['to'] ?? null,
        ], 'win');
    }

    protected function placementGamesCount(): int
    {
        $details = $this->detailsPayload();
        $payload = $this->orderPayload();

        return $this->resolvedOrderCount([
            $payload['numberOfPlacementGames'] ?? null,
            $details['numberOfPlacementGames'] ?? null,
            $payload['desiredDivision'] ?? null,
            $details['to'] ?? null,
        ], 'placement');
    }

    protected function resolvedOrderCount(array $candidates, string $type): int
    {
        foreach ($candidates as $candidate) {
            if (is_numeric($candidate)) {
                return max(0, (int) $candidate);
            }

            $extracted = $this->extractCount($candidate, $type);

            if ($extracted > 0) {
                return $extracted;
            }
        }

        return 0;
    }

    protected function extractCount(mixed $value, string $type): int
    {
        $text = Str::lower(trim((string) $value));

        if ($text === '') {
            return 0;
        }

        $needle = $type === 'placement' ? 'placement' : 'win';

        if (! str_contains($text, $needle)) {
            return 0;
        }

        return preg_match('/(\d+)/', $text, $matches) === 1
            ? max(0, (int) ($matches[1] ?? 0))
            : 0;
    }

    public function resolveRouteBinding($value, $field = null): ?Model
    {
        if ($field !== 'order_number') {
            return parent::resolveRouteBinding($value, $field);
        }

        $order = $this->newQuery()
            ->where('order_number', $value)
            ->first();

        if ($order instanceof self) {
            return $order;
        }

        if (is_string($value) && ctype_digit($value)) {
            return $this->newQuery()->find((int) $value);
        }

        return null;
    }

    protected static function qualifiedColumn(string $column, string $table = ''): string
    {
        return $table !== '' ? "{$table}.{$column}" : $column;
    }
}
