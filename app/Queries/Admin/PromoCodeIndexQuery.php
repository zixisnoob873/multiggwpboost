<?php

namespace App\Queries\Admin;

use App\Models\PromoCode;
use Illuminate\Http\Request;

class PromoCodeIndexQuery
{
    public function execute(Request $request): array
    {
        $search = trim((string) $request->input('search', ''));
        $perPage = max(10, min(100, (int) $request->input('per_page', 20)));

        $promoCodes = PromoCode::query()
            ->withCount('addonRules')
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($promoQuery) use ($search) {
                    $promoQuery
                        ->where('code', 'like', "%{$search}%")
                        ->orWhere('type', 'like', "%{$search}%");
                });
            })
            ->latest('created_at')
            ->paginate($perPage)
            ->withQueryString();

        return [
            'promoCodes' => $promoCodes,
            'promoCodeSearch' => $search,
        ];
    }
}
