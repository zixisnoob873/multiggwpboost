<?php

namespace App\Http\Requests\Admin;

use App\Models\Game;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class MarketplaceGameRequest extends AdminRequest
{
    public function authorize(): bool
    {
        return $this->authorizeAdminModule('marketplace');
    }

    public function rules(): array
    {
        $game = $this->route('game');
        $gameId = $game instanceof Game ? $game->id : null;

        return [
            'game_category_id' => ['nullable', 'integer', Rule::exists('game_categories', 'id')],
            'name' => ['required', 'string', 'max:160'],
            'short_name' => ['nullable', 'string', 'max:80'],
            'slug' => ['required', 'string', 'max:180', Rule::unique('games', 'slug')->ignore($gameId)],
            'description' => ['nullable', 'string', 'max:2000'],
            'status' => ['required', Rule::in(Game::STATUSES)],
            'sort_order' => ['required', 'integer', 'min:0', 'max:9999'],
            'featured' => ['boolean'],
            'meta_title' => ['nullable', 'string', 'max:255'],
            'meta_description' => ['nullable', 'string', 'max:130'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $name = trim((string) $this->input('name'));
        $slug = Str::slug((string) ($this->input('slug') ?: $name));

        $this->merge([
            'name' => $name,
            'short_name' => $this->normalizeNullableString('short_name', 80),
            'slug' => $slug,
            'description' => $this->trimNullableString('description'),
            'status' => $this->input('status') ?: Game::STATUS_DRAFT,
            'sort_order' => $this->input('sort_order', 0),
            'featured' => $this->boolean('featured'),
            'meta_title' => $this->normalizeNullableString('meta_title', 255),
            'meta_description' => $this->normalizeNullableString('meta_description', 130),
        ]);
    }

    public function catalogData(): array
    {
        return $this->safe()->only([
            'game_category_id',
            'slug',
            'name',
            'short_name',
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
