<?php

namespace App\Http\Requests\Admin;

use App\Models\GameService;
use Illuminate\Validation\Rule;

class StoreFaqRequest extends AdminRequest
{
    public function authorize(): bool
    {
        return $this->authorizeAdminModule('content');
    }

    public function rules(): array
    {
        return [
            'question' => ['required', 'string', 'max:255'],
            'answer' => ['required', 'string', 'max:2000'],
            'order' => ['required', 'integer', 'min:0', 'max:9999'],
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

    protected function prepareForValidation(): void
    {
        $payload = $this->input('faq');

        if (is_array($payload)) {
            $this->merge($payload);
        }

        $this->merge([
            'question' => $this->trimNullableString('question'),
            'answer' => $this->trimNullableString('answer'),
            'game_id' => $this->input('game_id') ?: null,
            'service_id' => $this->input('service_id') ?: null,
        ]);
    }
}
