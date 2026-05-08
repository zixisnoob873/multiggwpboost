<?php

namespace App\Http\Requests\Admin;

use App\Models\GameAddon;
use App\Models\GameService;
use Illuminate\Validation\Rule;
use Illuminate\Support\Str;

class MarketplaceServiceRequest extends AdminRequest
{
    public function authorize(): bool
    {
        return $this->authorizeAdminModule('marketplace');
    }

    public function rules(): array
    {
        $service = $this->route('service');
        $serviceId = $service instanceof GameService ? $service->id : null;

        return [
            'game_id' => ['required', 'integer', Rule::exists('games', 'id')],
            'name' => ['required', 'string', 'max:160'],
            'slug' => [
                'required',
                'string',
                'max:180',
                Rule::unique('game_services', 'slug')
                    ->where(fn ($query) => $query->where('game_id', $this->input('game_id')))
                    ->ignore($serviceId),
            ],
            'kind' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:2000'],
            'status' => ['required', Rule::in(GameService::STATUSES)],
            'sort_order' => ['required', 'integer', 'min:0', 'max:9999'],
            'homepage_featured' => ['boolean'],
            'base_price' => ['nullable', 'numeric', 'min:0', 'max:999999'],
            'meta_title' => ['nullable', 'string', 'max:255'],
            'meta_description' => ['nullable', 'string', 'max:130'],
            'addon_ids' => ['array'],
            'addon_ids.*' => ['integer', Rule::exists('game_addons', 'id')],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $service = $this->route('service');
            $gameId = (int) $this->input('game_id');

            if ($service instanceof GameService && (int) $service->game_id !== $gameId && $service->orders()->exists()) {
                $validator->errors()->add('game_id', 'Services with orders cannot be moved to another game.');
            }

            $addonIds = array_map('intval', (array) $this->input('addon_ids', []));

            if ($addonIds === []) {
                return;
            }

            $invalidAddonExists = GameAddon::query()
                ->whereIn('id', $addonIds)
                ->where('game_id', '!=', $gameId)
                ->exists();

            if ($invalidAddonExists) {
                $validator->errors()->add('addon_ids', 'Only addons from the selected game can be assigned.');
            }
        });
    }

    protected function prepareForValidation(): void
    {
        $name = trim((string) $this->input('name'));
        $slug = Str::slug((string) ($this->input('slug') ?: $name));
        $kind = Str::slug((string) ($this->input('kind') ?: $name), '_');
        $basePrice = $this->input('base_price');

        $this->merge([
            'name' => $name,
            'slug' => $slug,
            'kind' => $kind,
            'description' => $this->trimNullableString('description'),
            'status' => $this->input('status') ?: GameService::STATUS_DRAFT,
            'sort_order' => $this->input('sort_order', 0),
            'homepage_featured' => $this->boolean('homepage_featured'),
            'base_price' => $basePrice === '' ? null : $basePrice,
            'meta_title' => $this->normalizeNullableString('meta_title', 255),
            'meta_description' => $this->normalizeNullableString('meta_description', 130),
            'addon_ids' => array_values(array_filter((array) $this->input('addon_ids', []), 'is_numeric')),
        ]);
    }

    public function catalogData(): array
    {
        return $this->safe()->only([
            'game_id',
            'slug',
            'name',
            'kind',
            'description',
            'status',
            'sort_order',
        ]);
    }

    public function seoData(): array
    {
        return $this->safe()->only(['meta_title', 'meta_description']);
    }
}

