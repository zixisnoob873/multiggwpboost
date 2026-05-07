<?php

namespace App\Notifications\Discord;

use App\Models\Order;
use App\Services\Orders\OrderPricingPayloadService;
use App\Support\OrderStatus;
use Illuminate\Support\Str;

class OrderCreatedMessage extends AbstractDiscordMessage
{
    public function __construct(protected Order $order) {}

    public function webhookConfigKey(): string
    {
        return 'services.discord.webhook_orders';
    }

    public function username(): string
    {
        return 'GGWP Orders';
    }

    protected function embed(): array
    {
        $customerName = $this->order->user?->name
            ?? trim(implode(' ', [
                $this->order->metadata['customer']['firstName'] ?? '',
                $this->order->metadata['customer']['lastName'] ?? '',
            ])) ?: $this->order->user?->email ?? 'Guest';
        $serviceType = $this->serviceType();

        return [
            'title' => "New order received - #{$this->order->order_number}",
            'description' => $serviceType,
            'color' => 0x57F287,
            'url' => route('admin-orders.edit', $this->order),
            'fields' => $this->fields($customerName, $serviceType),
            'footer' => ['text' => 'GGWP Boost'],
            'timestamp' => now()->toIso8601String(),
        ];
    }

    protected function fields(string $customerName, string $serviceType): array
    {
        return [
            ['name' => 'Customer', 'value' => $customerName, 'inline' => true],
            ['name' => 'Order ID', 'value' => $this->orderId(), 'inline' => true],
            ...$this->serviceSpecificFields($serviceType),
            ['name' => 'Payout', 'value' => $this->formatMoney($this->order->resolvedBoosterPayoutCents()), 'inline' => true],
            ['name' => 'Payment', 'value' => $this->order->payment_status ?? 'pending', 'inline' => true],
            ['name' => 'Status', 'value' => OrderStatus::label($this->order->status), 'inline' => true],
        ];
    }

    protected function serviceSpecificFields(string $serviceType): array
    {
        $payload = app(OrderPricingPayloadService::class)->basePayload($this->order);

        return match ($this->serviceKind($serviceType)) {
            'placement_matches' => [
                ['name' => 'Previous Rank', 'value' => $this->value($payload['currentDivision'] ?? $this->order->rankFromLabel()), 'inline' => true],
                ['name' => 'No. of Placement Matches', 'value' => $this->countValue($payload['numberOfPlacementGames'] ?? null), 'inline' => true],
            ],
            'ranked_wins' => [
                ['name' => 'Current Rank', 'value' => $this->value($payload['currentDivision'] ?? $this->order->rankFromLabel()), 'inline' => true],
                ['name' => 'No. of Ranked Wins', 'value' => $this->countValue($payload['numberOfWins'] ?? null), 'inline' => true],
            ],
            'rank_boost', 'radiant_boost' => [
                ['name' => 'Current Rank', 'value' => $this->value($payload['currentDivision'] ?? $this->order->rankFromLabel()), 'inline' => true],
                ['name' => 'Desired Rank', 'value' => $this->value($payload['targetDivision'] ?? $this->order->rankToLabel()), 'inline' => true],
                ['name' => 'List of Addons', 'value' => $this->addonsValue($payload['selectedAddons'] ?? $this->order->addonsList()), 'inline' => false],
            ],
            default => [
                ['name' => 'Amount', 'value' => $this->formatMoney((int) ($this->order->price_cents ?? 0)), 'inline' => true],
            ],
        };
    }

    protected function serviceType(): string
    {
        return trim((string) (app(OrderPricingPayloadService::class)->serviceType($this->order) ?: $this->order->serviceName()))
            ?: ($this->order->product ?: 'Rank Boosting');
    }

    protected function serviceKind(string $serviceType): ?string
    {
        $configured = config("pricing.services.{$serviceType}.kind");
        if (is_string($configured) && $configured !== '') {
            return $configured;
        }

        return match (Str::lower(trim($serviceType))) {
            'placement matches', 'placement games' => 'placement_matches',
            'ranked wins' => 'ranked_wins',
            'rank boosting', 'rank boost' => 'rank_boost',
            'radiant boost' => 'radiant_boost',
            default => null,
        };
    }

    protected function orderId(): string
    {
        return (string) ($this->order->order_number ?: $this->order->getKey());
    }

    protected function formatMoney(int $amountCents): string
    {
        $currency = strtoupper((string) ($this->order->currency ?: 'USD'));
        $formatted = number_format($amountCents / 100, 2);

        return $currency === 'USD'
            ? '$'.$formatted
            : sprintf('%s %s', $currency, $formatted);
    }

    protected function value(mixed $value, string $fallback = '-'): string
    {
        $text = trim((string) $value);

        return $text === '' ? $fallback : $text;
    }

    protected function countValue(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '-';
        }

        return (string) max(0, (int) $value);
    }

    protected function addonsValue(mixed $value): string
    {
        $addons = is_array($value) ? array_values(array_filter($value, fn ($addon) => trim((string) $addon) !== '')) : [];

        return $addons === [] ? 'None' : implode(', ', $addons);
    }
}
