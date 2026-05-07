<?php

namespace App\Http\Controllers;

use App\Models\Promotion;
use App\Models\User;
use App\Support\Security\StoredFilePath;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class PromotionImageController extends Controller
{
    public function __invoke(Request $request, Promotion $promotion): BinaryFileResponse
    {
        if (! $promotion->is_active && ! $this->requestUserCanViewInactivePromotion($request)) {
            abort(404);
        }

        $path = StoredFilePath::clean($promotion->image_path, [
            'uploads/promotion-images/',
            'promotion_pics/',
        ]);

        abort_if($path === null, 404);
        abort_unless(hash_equals(sha1($path), (string) $request->query('v', '')), 404);

        if (Str::startsWith($path, 'uploads/promotion-images/')) {
            abort_unless(Storage::disk('private')->exists($path), 404);

            return response()->file(Storage::disk('private')->path($path), [
                'Cache-Control' => 'private, max-age=900',
            ]);
        }

        if (Str::startsWith($path, 'promotion_pics/')) {
            abort_unless(Storage::disk('public')->exists($path), 404);

            return response()->file(Storage::disk('public')->path($path), [
                'Cache-Control' => 'public, max-age=604800',
            ]);
        }

        abort(404);
    }

    protected function requestUserCanViewInactivePromotion(Request $request): bool
    {
        $user = $request->user();

        return $user instanceof User && $user->isAdminUser();
    }
}
