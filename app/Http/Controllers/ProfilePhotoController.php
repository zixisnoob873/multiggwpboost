<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\Security\StoredFilePath;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ProfilePhotoController extends Controller
{
    public function __invoke(Request $request, User $user): BinaryFileResponse
    {
        $path = StoredFilePath::clean($user->profile_photo_path, [
            'uploads/profile-photos/',
            'profile-photos/',
        ]);

        abort_if($path === null, 404);
        abort_unless(hash_equals(sha1($path), (string) $request->query('v', '')), 404);

        if (Str::startsWith($path, 'uploads/profile-photos/')) {
            if (Storage::disk('private')->exists($path)) {
                return response()->file(Storage::disk('private')->path($path), [
                    'Cache-Control' => 'private, max-age=900',
                ]);
            }

            $legacyPath = public_path($path);
            abort_unless(is_file($legacyPath), 404);

            return response()->file($legacyPath, [
                'Cache-Control' => 'public, max-age=604800',
            ]);
        }

        if (Str::startsWith($path, 'profile-photos/')) {
            abort_unless(Storage::disk('public')->exists($path), 404);

            return response()->file(Storage::disk('public')->path($path), [
                'Cache-Control' => 'public, max-age=604800',
            ]);
        }

        abort(404);
    }
}
