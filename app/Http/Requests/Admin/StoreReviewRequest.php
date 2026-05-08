<?php

namespace App\Http\Requests\Admin;

use App\Models\GameService;
use Illuminate\Validation\Rule;

class StoreReviewRequest extends AdminRequest
{
    public function authorize(): bool
    {
        return $this->authorizeAdminModule('marketing');
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'author_name' => trim((string) $this->input('author_name')),
            'service' => trim((string) $this->input('service')),
            'quote' => trim((string) $this->input('quote')),
            'game_id' => $this->input('game_id') ?: null,
            'service_id' => $this->input('service_id') ?: null,
        ]);
    }

    public function rules(): array
    {
        return [
            'author_name' => ['required', 'string', 'max:120'],
            'service' => ['required', 'string', 'max:120'],
            'quote' => ['required', 'string', 'min:20', 'max:1200'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:9999'],
            'game_id' => ['nullable', 'integer', Rule::exists('games', 'id')],
            'service_id' => ['nullable', 'integer', Rule::exists('game_services', 'id')],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $serviceId = $this->input('service_id');
            $gameId = $this->input('game_id');

            if (! $serviceId) {
                return;
            }

            if (! $gameId) {
                $validator->errors()->add('game_id', 'Select a game when assigning a service.');

                return;
            }

            $serviceBelongsToGame = GameService::query()
                ->whereKey($serviceId)
                ->where('game_id', $gameId)
                ->exists();

            if (! $serviceBelongsToGame) {
                $validator->errors()->add('service_id', 'Select a service that belongs to the selected game.');
            }
        });
    }
}
