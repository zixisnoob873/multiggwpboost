<?php

namespace App\Http\Resources;

use App\Support\OrderStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $customer = $this->whenLoaded('user');
        $booster = $this->whenLoaded('booster');
        $viewerRole = $this->viewerRole($request);
        $details = $this->visibleDetails($viewerRole);

        return [
            'id' => $this->id,
            'orderNumber' => $this->order_number,
            'status' => $this->status,
            'statusLabel' => OrderStatus::label($this->status),
            'statusTone' => OrderStatus::tone($this->status),
            'statusBadgeClass' => OrderStatus::badgeClass($this->status),
            'progressPercent' => $this->progressPercent(),
            'isClaimable' => OrderStatus::canBeClaimed($this->status, $this->booster_id),
            'boosterCanOpen' => OrderStatus::canBoosterOpen($this->status),
            'paymentStatus' => $this->payment_status,
            'priceCents' => $this->price_cents,
            'originalPriceCents' => $this->resolvedOriginalPriceCents(),
            'discountAmountCents' => $this->resolvedDiscountAmountCents(),
            'discountAmount' => round($this->resolvedDiscountAmountCents() / 100, 2),
            'boosterPayoutBasisCents' => $this->resolvedBoosterPayoutBasisCents(),
            'boosterPayoutCents' => $this->resolvedBoosterPayoutCents(),
            'currency' => $this->currency,
            'product' => $this->product,
            'serviceLabel' => $this->serviceName(),
            'rankFrom' => $this->rankFromLabel(),
            'rankTo' => $this->rankToLabel(),
            'details' => $details,
            'isCustom' => $this->is_custom,
            'paidAt' => optional($this->paid_at)->toDateTimeString(),
            'assignedAt' => optional($this->assigned_at)->toDateTimeString(),
            'createdAt' => optional($this->created_at)->toDateTimeString(),
            'updatedAt' => optional($this->updated_at)->toDateTimeString(),
            'customer' => $customer ? UserResource::make($customer) : null,
            'booster' => $booster ? UserResource::make($booster) : null,
        ];
    }

    protected function viewerRole(Request $request): string
    {
        $role = $request->user()?->role;

        return is_string($role) ? $role : 'guest';
    }

    protected function visibleDetails(string $viewerRole): array
    {
        $details = $this->detailsPayload();

        if ($viewerRole === 'admin' || $viewerRole === 'super_admin') {
            return $details;
        }

        unset($details['adminNotes']);

        if (isset($details['order']) && is_array($details['order'])) {
            unset($details['order']['adminNotes']);
        }

        return $details;
    }

    protected function detailsPayload(): array
    {
        return method_exists($this->resource, 'detailsPayload')
            ? $this->resource->detailsPayload()
            : (is_array($this->details) ? $this->details : []);
    }
}
