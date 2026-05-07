<?php

namespace App\Http\Requests;

use App\Models\Order;
use App\Services\Orders\OrderProgressService;
use App\Support\OrderStatus;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateOrderProgressRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $order = $this->route('order');

        if (! $user || ! $order instanceof Order) {
            return false;
        }

        if ($user->isAdminUser()) {
            return true;
        }

        if ($user->role !== 'booster') {
            return false;
        }

        if ((int) $order->booster_id !== (int) $user->id) {
            return false;
        }

        return OrderStatus::canBoosterOpen($order->status);
    }

    public function rules(): array
    {
        return [
            'current_rank' => ['nullable', 'string', 'max:100'],
            'current_rr' => ['nullable', 'integer', 'min:0', 'max:100'],
            'completed_wins' => ['nullable', 'integer', 'min:0', 'max:999'],
            'completed_placements' => ['nullable', 'integer', 'min:0', 'max:50'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $order = $this->route('order');

                if (! $order) {
                    return;
                }

                $snapshot = app(OrderProgressService::class)->snapshot($order);

                if ($this->filled('current_rank') && ($snapshot['showCurrentRank'] ?? false)) {
                    $rankOptions = $snapshot['rankOptions'] ?? [];

                    if (! in_array($this->input('current_rank'), $rankOptions, true)) {
                        $validator->errors()->add('current_rank', 'Select a valid current rank for this order.');
                    }
                }

                if ($this->filled('completed_wins') && ($snapshot['showCompletedWins'] ?? false)) {
                    $maxWins = (int) ($snapshot['totalWins'] ?? 0);

                    if ((int) $this->input('completed_wins') > $maxWins) {
                        $validator->errors()->add('completed_wins', "Completed wins may not exceed {$maxWins}.");
                    }
                }

                if ($this->filled('completed_placements') && ($snapshot['showCompletedPlacements'] ?? false)) {
                    $maxPlacements = (int) ($snapshot['totalPlacements'] ?? 0);

                    if ((int) $this->input('completed_placements') > $maxPlacements) {
                        $validator->errors()->add('completed_placements', "Completed placement matches may not exceed {$maxPlacements}.");
                    }
                }
            },
        ];
    }

    protected function failedAuthorization(): void
    {
        $order = $this->route('order');
        $user = $this->user();

        if (
            $order instanceof Order
            && $user?->role === 'booster'
            && (int) $order->booster_id === (int) $user->id
            && ! OrderStatus::canBoosterOpen($order->status)
        ) {
            throw new AuthorizationException('Completed and cancelled orders are not available in the booster workspace.');
        }

        parent::failedAuthorization();
    }
}
