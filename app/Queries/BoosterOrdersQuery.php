<?php

namespace App\Queries;

use App\Models\Order;
use App\Models\User;
use App\Support\BoostingCatalog;
use App\Support\OrderStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class BoosterOrdersQuery
{
    public function browse(User $user, array $filters): array
    {
        $view = (string) ($filters['view'] ?? 'all');
        $baseQuery = $this->baseAssignedQuery($user);
        $ordersQuery = (clone $baseQuery)
            ->when($view === 'assigned', fn (Builder $builder) => $builder->whereIn('status', OrderStatus::boosterWorkspaceValues()));

        $this->applyFilters($ordersQuery, $filters);

        return [
            'orders' => $ordersQuery
                ->orderByDesc('assigned_at')
                ->orderByDesc('updated_at')
                ->orderByDesc('created_at')
                ->paginate(18)
                ->withQueryString(),
            'orderFilters' => [
                'search' => $filters['search'] ?? null,
                'status' => $filters['status'] ?? null,
                'region' => $filters['region'] ?? null,
                'service' => $filters['service'] ?? null,
                'view' => $view,
            ],
            'orderFilterOptions' => [
                'statuses' => OrderStatus::options(),
                'regions' => $this->regionOptions(),
                'services' => $this->serviceOptions(),
            ],
            'orderViewOptions' => [
                [
                    'value' => 'all',
                    'label' => 'All',
                    'count' => (clone $baseQuery)->count(),
                ],
                [
                    'value' => 'assigned',
                    'label' => 'Assigned Orders',
                    'count' => (clone $baseQuery)->whereIn('status', OrderStatus::boosterWorkspaceValues())->count(),
                ],
            ],
        ];
    }

    public function chatIndex(User $user, array $filters): array
    {
        $ordersQuery = $this->baseAssignedQuery($user)
            ->whereIn('status', OrderStatus::boosterWorkspaceValues());

        $this->applySearch($ordersQuery, (string) ($filters['search'] ?? ''));

        return [
            'orders' => $ordersQuery
                ->orderByDesc('updated_at')
                ->orderByDesc('assigned_at')
                ->orderByDesc('created_at')
                ->paginate(18)
                ->withQueryString(),
            'chatFilters' => [
                'search' => $filters['search'] ?? null,
            ],
        ];
    }

    public function claimable(): Collection
    {
        return Order::query()
            ->select([
                'id',
                'user_id',
                'order_number',
                'product',
                'status',
                'details',
                'price_cents',
                'original_price_cents',
                'discount_amount',
                'currency',
                'booster_payout_rate',
                'booster_payout_cents',
                'booster_payout_basis_cents',
                'created_at',
            ])
            ->with('user:id,name,nickname,email')
            ->whereNull('booster_id')
            ->where('status', OrderStatus::PENDING)
            ->latest('created_at')
            ->get();
    }

    protected function baseAssignedQuery(User $user): Builder
    {
        return Order::query()
            ->select([
                'id',
                'user_id',
                'booster_id',
                'order_number',
                'product',
                'status',
                'details',
                'price_cents',
                'original_price_cents',
                'discount_amount',
                'currency',
                'booster_payout_rate',
                'booster_payout_cents',
                'booster_payout_basis_cents',
                'assigned_at',
                'created_at',
                'updated_at',
                'completed_at',
            ])
            ->with('user:id,name,nickname,email')
            ->where('booster_id', $user->id);
    }

    protected function applyFilters(Builder $query, array $filters): void
    {
        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['region'])) {
            $this->applyRegionFilter($query, (string) $filters['region']);
        }

        if (! empty($filters['service'])) {
            $this->applyServiceFilter($query, (string) $filters['service']);
        }

        $this->applySearch($query, (string) ($filters['search'] ?? ''));
    }

    protected function applyRegionFilter(Builder $query, string $region): void
    {
        $query->where(function (Builder $builder) use ($region): void {
            $builder
                ->where('details->region', $region)
                ->orWhere('details->order->region', $region);
        });
    }

    protected function applyServiceFilter(Builder $query, string $service): void
    {
        $matches = $this->matchingServiceValues($service);

        $query->where(function (Builder $builder) use ($matches): void {
            $builder
                ->whereIn('product', $matches)
                ->orWhereIn('details->service', $matches)
                ->orWhereIn('details->order->orderType', $matches);
        });
    }

    protected function applySearch(Builder $query, string $search): void
    {
        $search = trim($search);

        if ($search === '') {
            return;
        }

        $like = '%'.$search.'%';

        $query->where(function (Builder $builder) use ($like): void {
            $builder
                ->where('order_number', 'like', $like)
                ->orWhere('product', 'like', $like)
                ->orWhereHas('user', function (Builder $userQuery) use ($like): void {
                    $userQuery
                        ->where('name', 'like', $like)
                        ->orWhere('email', 'like', $like)
                        ->orWhere('nickname', 'like', $like);
                });
        });
    }

    protected function matchingServiceValues(string $service): array
    {
        return match ($service) {
            'Placement Matches' => ['Placement Matches', 'Placement Games'],
            'Rank Boosting' => ['Rank Boosting', 'Rank Boost'],
            default => [$service],
        };
    }

    protected function regionOptions(): array
    {
        return collect(BoostingCatalog::regions())
            ->mapWithKeys(fn (string $region): array => [$region => $region])
            ->all();
    }

    protected function serviceOptions(): array
    {
        return collect(BoostingCatalog::serviceOptions())
            ->mapWithKeys(fn (string $service): array => [$service => $service])
            ->all();
    }
}
