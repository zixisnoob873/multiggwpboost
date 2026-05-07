<?php

namespace App\Queries\Admin;

use App\Models\Promotion;
use Illuminate\Http\Request;

class PromotionIndexQuery
{
    public function execute(Request $request): array
    {
        $search = trim((string) $request->query('search', ''));

        $promotions = Promotion::query()
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($promotionQuery) use ($search) {
                    $promotionQuery
                        ->where('title', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%")
                        ->orWhere('button_text', 'like', "%{$search}%")
                        ->orWhere('button_link', 'like', "%{$search}%");
                });
            })
            ->ordered()
            ->paginate(12);

        return [
            'promotions' => $promotions,
            'promotionSearch' => $search,
            'promotionCount' => Promotion::query()->count(),
            'homepagePromotionCount' => Promotion::query()->where('show_on_homepage', true)->count(),
        ];
    }
}
