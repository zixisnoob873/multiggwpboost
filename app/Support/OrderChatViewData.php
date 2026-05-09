<?php

namespace App\Support;

use App\Enums\OrderChatThreadType;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class OrderChatViewData
{
    public static function make(Order $order, ?User $viewer = null): array
    {
        $details = self::normalize($order->details);
        $baseOrder = self::normalize($details['order'] ?? []);
        $metadata = self::normalize($order->metadata);
        $progress = self::normalize($details['progress'] ?? []);
        $viewerRole = self::viewerRole($viewer);
        $viewerIsAdmin = self::isAdminRole($viewerRole);
        $visibleMetadata = $viewerIsAdmin ? OrderMetadataSanitizer::forAdminTooling($metadata) : [];
        $service = self::value($details['service'] ?? $baseOrder['orderType'] ?? $order->product, $order->product ?: 'Boost Order');
        $serviceKind = self::serviceKind($service);

        $addons = BoostingCatalog::normalizeAddons($details['addons'] ?? $baseOrder['addons'] ?? []);
        $specificAgents = ValorantAgentCatalog::resolveMany($baseOrder['specificAgents'] ?? $details['specificAgents'] ?? []);
        $specificAgentUuids = array_column($specificAgents, 'uuid');
        $oneTrickAgent = ValorantAgentCatalog::resolveMany($baseOrder['oneTrickAgent'] ?? $details['oneTrickAgent'] ?? []);
        $oneTrickAgentUuids = array_column($oneTrickAgent, 'uuid');
        $contactMethod = self::value($metadata['contactMethod'] ?? $order->contact_method ?? 'email', 'Email');
        $customer = $order->user;
        $booster = $order->booster;

        $fromRank = self::value(
            $details['from']
                ?? $baseOrder['currentDivision']
                ?? $baseOrder['from']
                ?? 'Unranked',
            'Unranked'
        );

        $desiredRank = self::value(
            $details['to']
                ?? $baseOrder['desiredDivision']
                ?? $baseOrder['to']
                ?? 'Unranked',
            'Unranked'
        );

        $currentRank = self::value(
            $details['currentRank']
                ?? $progress['currentRank']
                ?? $baseOrder['currentRank']
                ?? $baseOrder['currentDivision']
                ?? $details['from']
                ?? 'Unranked',
            'Unranked'
        );

        $currentRr = self::scalarValue(
            $details['currentRR']
                ?? $progress['currentRR']
                ?? $baseOrder['currentRR']
                ?? $details['rr']
                ?? null
        );

        $completedWins = max(0, (int) ($progress['completedWins'] ?? $progress['winsCompleted'] ?? $details['completedWins'] ?? 0));
        $completedPlacements = max(0, (int) ($progress['completedPlacements'] ?? $progress['placementsCompleted'] ?? $details['completedPlacements'] ?? 0));
        $totalWins = max(0, (int) ($baseOrder['numberOfWins'] ?? self::extractCount($desiredRank, 'win')));
        $totalPlacements = max(0, (int) ($baseOrder['numberOfPlacementGames'] ?? self::extractCount($desiredRank, 'placement')));
        $currentProgressLabel = match ($serviceKind) {
            'ranked_wins' => sprintf('%d / %d Wins', min($completedWins, $totalWins), $totalWins),
            'placement_matches' => sprintf('%d / %d Matches', min($completedPlacements, $totalPlacements), $totalPlacements),
            default => $currentRr !== null && $currentRr !== '' ? sprintf('%s RR', $currentRr) : '-',
        };
        $winsDoneLabel = sprintf('%d / %d', min($completedWins, $totalWins), $totalWins);
        $placementsPlayedLabel = sprintf('%d / %d', min($completedPlacements, $totalPlacements), $totalPlacements);

        $progressPct = self::progressPercent($order, $details, $progress);
        $customerTotal = self::money($order->customerPriceCents() / 100, $order->currency ?? 'USD');
        $originalTotal = self::money($order->resolvedOriginalPriceCents() / 100, $order->currency ?? 'USD');
        $promoDiscount = self::money($order->resolvedDiscountAmountCents() / 100, $order->currency ?? 'USD');
        $boosterPayoutBasis = self::money($order->resolvedBoosterPayoutBasisCents() / 100, $order->currency ?? 'USD');
        $boosterPayout = self::money(($order->resolvedBoosterPayoutCents() ?? 0) / 100, $order->currency ?? 'USD');
        $preferredContactValue = self::preferredContactValue($contactMethod, $order, $customer, $metadata, $viewerRole);
        $statusLabel = self::statusLabel($order->status);
        $statusTone = self::statusTone($order->status);

        $overview = [
            'game' => self::value($details['game'] ?? $baseOrder['game'] ?? 'VALORANT', 'VALORANT'),
            'service' => $service,
            'serviceKind' => $serviceKind,
            'region' => self::value($details['region'] ?? $baseOrder['region'] ?? null, 'Not specified'),
            'platform' => self::value($details['platform'] ?? $baseOrder['platform'] ?? null, 'PC'),
            'boostType' => self::value($details['accountType'] ?? $baseOrder['accountType'] ?? null, 'Not specified'),
            'averageRR' => self::value($details['averageRR'] ?? $baseOrder['averageRR'] ?? null, 'Standard'),
            'startRank' => $fromRank,
            'desiredRank' => $desiredRank,
            'currentRank' => $currentRank,
            'currentRR' => $currentProgressLabel,
            'winsDone' => $winsDoneLabel,
            'placementsPlayed' => $placementsPlayedLabel,
            'contactMethod' => Str::title(str_replace('-', ' ', (string) $contactMethod)),
            'contactValue' => $preferredContactValue,
            'addons' => $addons,
            'specificAgents' => $specificAgents,
            'specificAgentUuids' => $specificAgentUuids,
            'oneTrickAgent' => $oneTrickAgent,
            'oneTrickAgentUuids' => $oneTrickAgentUuids,
            'notes' => self::value($details['notes'] ?? null, '-'),
            'adminNotes' => $viewerIsAdmin ? self::value($details['adminNotes'] ?? null, '-') : '-',
            'total' => $customerTotal,
            'customerTotal' => $customerTotal,
            'originalTotal' => $originalTotal,
            'promoDiscount' => $promoDiscount,
            'payoutBasis' => $boosterPayoutBasis,
            'hasDiscount' => $order->hasDiscountApplied(),
            'payout' => $boosterPayout,
            'paymentStatus' => Str::title((string) ($order->payment_status ?? 'pending')),
            'orderNumber' => $order->order_number ?: (string) $order->id,
            'status' => $statusLabel,
            'statusTone' => $statusTone,
            'progressPct' => $progressPct,
        ];

        $timeline = [
            ['label' => 'Created', 'value' => optional($order->created_at)->format('M j, Y g:i A') ?: '-'],
            ['label' => 'Paid', 'value' => optional($order->paid_at)->format('M j, Y g:i A') ?: 'Not marked paid'],
            ['label' => 'Assigned', 'value' => optional($order->assigned_at)->format('M j, Y g:i A') ?: 'Not assigned'],
            ['label' => 'Completed', 'value' => optional($order->completed_at)->format('M j, Y g:i A') ?: 'Not completed'],
            ['label' => 'Last progress update', 'value' => self::value(
                self::dateString($progress['updatedAt'] ?? $details['progressUpdatedAt'] ?? null),
                optional($order->updated_at)->format('M j, Y g:i A') ?: '-'
            )],
        ];

        $detailSections = [
            [
                'title' => 'Order essentials',
                'rows' => array_values(array_filter([
                    ['label' => 'Order ID', 'value' => $overview['orderNumber']],
                    ['label' => 'Game', 'value' => $overview['game']],
                    ['label' => 'Service', 'value' => $overview['service']],
                    ['label' => 'Region', 'value' => $overview['region']],
                    ['label' => 'Platform', 'value' => $overview['platform']],
                    ['label' => 'Boost Type', 'value' => $overview['boostType']],
                    ['label' => 'Payment Status', 'value' => $overview['paymentStatus']],
                    ['label' => 'Charged Total', 'value' => $overview['customerTotal']],
                    $overview['hasDiscount'] ? ['label' => 'Original Price', 'value' => $overview['originalTotal']] : null,
                    $overview['hasDiscount'] ? ['label' => 'Promo Discount', 'value' => $overview['promoDiscount']] : null,
                ])),
            ],
            [
                'title' => 'Rank journey',
                'rows' => [
                    ['label' => 'Start Rank', 'value' => $overview['startRank']],
                    ['label' => 'Desired Rank', 'value' => $overview['desiredRank']],
                    ['label' => 'Current Rank', 'value' => $overview['currentRank']],
                    ['label' => 'Current RR', 'value' => $overview['currentRR']],
                    ['label' => 'Average RR Gain', 'value' => $overview['averageRR']],
                    ['label' => 'Progress', 'value' => sprintf('%s%%', $overview['progressPct'])],
                ],
            ],
            [
                'title' => 'Communication & preferences',
                'rows' => array_values(array_filter([
                    ['label' => 'Preferred Contact', 'value' => $overview['contactMethod']],
                    ['label' => 'Contact Handle', 'value' => $overview['contactValue']],
                    ['label' => 'Addons', 'value' => count($addons) ? implode(', ', $addons) : 'None'],
                    count($specificAgents) ? ['label' => 'Specific Agents', 'value' => implode(', ', array_column($specificAgents, 'displayName'))] : null,
                    count($oneTrickAgent) ? ['label' => 'One-Trick Agent', 'value' => implode(', ', array_column($oneTrickAgent, 'displayName'))] : null,
                    ['label' => 'Customer Notes', 'value' => $overview['notes']],
                    $viewerIsAdmin ? ['label' => 'Admin Notes', 'value' => $overview['adminNotes']] : null,
                ])),
            ],
        ];

        $customerProfile = [
            'name' => self::visibleIdentity($customer, $viewerRole, 'Customer'),
            'email' => $viewerIsAdmin ? self::value($customer?->email, '-') : 'Protected',
            'status' => Str::title((string) ($customer?->account_status ?? 'active')),
            'joinedAt' => optional($customer?->created_at)->format('M j, Y') ?: '-',
        ];

        $boosterProfile = [
            'name' => self::visibleIdentity($booster, $viewerRole, 'Unassigned'),
            'email' => $viewerIsAdmin ? self::value($booster?->email, '-') : 'Protected',
            'status' => $booster ? Str::title((string) ($booster->account_status ?? 'active')) : 'Unassigned',
            'assignedAt' => optional($order->assigned_at)->format('M j, Y g:i A') ?: 'Not assigned',
            'payout' => $boosterPayout,
        ];

        $workspaceNotices = [];
        $latestExtension = self::normalize($metadata['latestExtension'] ?? []);
        $channels = collect(OrderChatThreadType::visibleForRole($viewerRole))
            ->map(fn (OrderChatThreadType $threadType): array => self::chatChannel($order, $threadType, $viewerRole))
            ->values()
            ->all();

        if ($viewerRole === 'booster' && $order->status === OrderStatus::PAUSED) {
            $workspaceNotices[] = [
                'key' => 'paused',
                'tone' => 'warning',
                'message' => 'Boost Paused, Contact Admin',
                'meta' => 'This order is paused in the database. Wait for admin or the customer to resume it.',
                'pulse' => true,
            ];
        }

        if ($viewerRole === 'booster' && $latestExtension !== []) {
            $extensionPayoutCents = (int) ($latestExtension['newBoosterPayoutCents'] ?? $order->resolvedBoosterPayoutCents());
            $extensionChangedAt = self::dateString($latestExtension['changedAt'] ?? null);

            $workspaceNotices[] = [
                'key' => 'extension',
                'tone' => 'danger',
                'message' => self::value($latestExtension['message'] ?? null, 'Boost order has been extended, please re-read the order details before continuing'),
                'meta' => trim(implode(' | ', array_filter([
                    'Updated payout '.self::money($extensionPayoutCents / 100, $order->currency ?? 'USD'),
                    $extensionChangedAt,
                ]))),
                'pulse' => true,
            ];
        }

        return [
            'details' => $details,
            'baseOrder' => $baseOrder,
            'metadata' => $visibleMetadata,
            'progress' => [
                'pct' => $progressPct,
                'statusLabel' => $statusLabel,
                'statusTone' => $statusTone,
                'currentRank' => $currentRank,
                'currentRR' => $currentRr,
                'completedWins' => $completedWins,
                'completedPlacements' => $completedPlacements,
                'winsDone' => $winsDoneLabel,
                'placementsPlayed' => $placementsPlayedLabel,
                'updatedAt' => self::dateString($progress['updatedAt'] ?? $details['progressUpdatedAt'] ?? null),
                'updatedBy' => self::value($progress['updatedBy'] ?? null, 'System'),
                'updatedByRole' => self::value($progress['updatedByRole'] ?? null, 'system'),
            ],
            'overview' => $overview,
            'detailSections' => $detailSections,
            'rawDetailRows' => $viewerIsAdmin ? self::flatten($details) : [],
            'rawMetadataRows' => $viewerIsAdmin ? self::flatten($visibleMetadata) : [],
            'timeline' => $timeline,
            'customerProfile' => $customerProfile,
            'boosterProfile' => $boosterProfile,
            'workspaceNotices' => $workspaceNotices,
            'channels' => $channels,
        ];
    }

    protected static function normalize(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if (is_string($value) && trim($value) !== '') {
            $decoded = json_decode($value, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return [];
    }

    protected static function viewerRole(?User $viewer): string
    {
        $role = $viewer?->role;

        return is_string($role) ? User::normalizeRole($role) : 'guest';
    }

    protected static function isAdminRole(string $viewerRole): bool
    {
        return $viewerRole === User::ROLE_SUPER_ADMIN;
    }

    protected static function progressPercent(Order $order, array $details, array $progress): int
    {
        $raw = $progress['pct'] ?? $details['progress_pct'] ?? null;
        if (is_numeric($raw)) {
            return max(0, min(100, (int) round((float) $raw)));
        }

        return 0;
    }

    protected static function visibleIdentity(?User $user, string $viewerRole, string $fallback): string
    {
        if (! $user) {
            return $fallback;
        }

        if (in_array($viewerRole, ['customer', 'booster'], true)) {
            return self::value($user->publicIdentity($fallback), $fallback);
        }

        return self::value($user->fullIdentity($fallback), $fallback);
    }

    protected static function preferredContactValue(string $contactMethod, Order $order, mixed $customer, array $metadata, string $viewerRole): string
    {
        if (! self::isAdminRole($viewerRole)) {
            return 'Protected';
        }

        return match (Str::lower($contactMethod)) {
            'whatsapp' => self::value($order->whatsapp ?? Arr::get($metadata, 'customer.whatsapp'), 'Not supplied'),
            'discord' => self::value($order->discord ?? Arr::get($metadata, 'customer.discord'), 'Not supplied'),
            default => self::value($customer?->email ?? Arr::get($metadata, 'customer.email'), 'Not supplied'),
        };
    }

    protected static function flatten(array $items, ?string $parentKey = null): array
    {
        $rows = [];

        foreach ($items as $key => $value) {
            $field = $parentKey ? $parentKey.'.'.$key : (string) $key;
            $label = Str::headline(str_replace('.', ' ', $field));

            if (is_array($value)) {
                if ($value === []) {
                    $rows[] = [
                        'key' => $field,
                        'label' => $label,
                        'value' => '-',
                    ];

                    continue;
                }

                $isList = array_values($value) === $value;
                $allScalars = collect($value)->every(fn ($item) => ! is_array($item));

                if ($isList && $allScalars) {
                    $rows[] = [
                        'key' => $field,
                        'label' => $label,
                        'value' => implode(', ', array_map(fn ($item) => self::value($item, '-'), $value)),
                    ];

                    continue;
                }

                $rows = array_merge($rows, self::flatten($value, $field));

                continue;
            }

            $rows[] = [
                'key' => $field,
                'label' => $label,
                'value' => self::value($value, '-'),
            ];
        }

        return $rows;
    }

    protected static function statusLabel(?string $status): string
    {
        return OrderStatus::label($status);
    }

    protected static function statusTone(?string $status): string
    {
        return OrderStatus::tone($status);
    }

    protected static function money(float|int $amount, string $currency = 'USD'): string
    {
        return sprintf('%s %s', strtoupper($currency), number_format((float) $amount, 2));
    }

    protected static function scalarValue(mixed $value): string|int|float|null
    {
        if (is_scalar($value) && ! is_bool($value)) {
            return $value;
        }

        return null;
    }

    protected static function value(mixed $value, string $fallback = '-'): string
    {
        if ($value === null) {
            return $fallback;
        }

        if (is_bool($value)) {
            return $value ? 'Yes' : 'No';
        }

        if (is_array($value)) {
            if ($value === []) {
                return $fallback;
            }

            $isList = array_values($value) === $value;
            if ($isList) {
                return implode(', ', array_map(fn ($item) => self::value($item, ''), $value));
            }

            return (string) json_encode($value, JSON_UNESCAPED_SLASHES);
        }

        $string = trim((string) $value);

        return $string === '' ? $fallback : $string;
    }

    protected static function dateString(mixed $value): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return \Illuminate\Support\Carbon::parse($value)->format('M j, Y g:i A');
        } catch (\Throwable) {
            return $value;
        }
    }

    protected static function serviceKind(string $serviceType): string
    {
        return (string) (config("pricing.services.{$serviceType}.kind") ?? match ($serviceType) {
            'Ranked Wins' => 'ranked_wins',
            'Placement Matches' => 'placement_matches',
            'Radiant Boost' => 'radiant_boost',
            default => 'rank_boost',
        });
    }

    protected static function extractCount(mixed $value, string $type): int
    {
        $text = Str::lower(trim((string) $value));
        if ($text === '') {
            return 0;
        }

        $needle = $type === 'placement' ? 'placement' : 'win';
        if (! str_contains($text, $needle)) {
            return 0;
        }

        if (preg_match('/(\d+)/', $text, $matches) !== 1) {
            return 0;
        }

        return (int) ($matches[1] ?? 0);
    }

    protected static function chatChannel(Order $order, OrderChatThreadType $threadType, string $viewerRole): array
    {
        return [
            'key' => $threadType->value,
            'label' => $threadType->buttonLabelForRole($viewerRole),
            'titleLabel' => $threadType->titleLabelForRole($viewerRole),
            'hint' => $threadType->hintForRole($viewerRole),
            'state' => $threadType->stateLabelForRole($viewerRole),
            'canSend' => $threadType->canSendForRole($viewerRole),
            'channelName' => "order-chat.{$order->id}.{$threadType->value}",
            'historyUrl' => route('order-chat.messages.index', ['order' => $order, 'threadType' => $threadType->value]),
            'sendUrl' => route('order-chat.messages.store', ['order' => $order, 'threadType' => $threadType->value]),
            'emptyTitle' => $threadType->emptyTitleForRole($viewerRole),
            'emptyCopy' => $threadType->emptyCopyForRole($viewerRole),
        ];
    }
}
