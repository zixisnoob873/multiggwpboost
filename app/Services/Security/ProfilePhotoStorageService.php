<?php

namespace App\Services\Security;

use App\Models\User;
use App\Support\Security\StoredFilePath;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class ProfilePhotoStorageService
{
    public function __construct(protected SanitizedImageUploadService $sanitizedImageUploadService) {}

    public function store(UploadedFile $file, User $user): string
    {
        $sanitizedImage = $this->sanitizedImageUploadService->sanitizeToTemporaryFile(
            $file,
            'profile-photo-',
            'profile photo',
            [
                'user_id' => $user->getKey(),
                'upload' => 'profile_photo',
            ],
        );

        try {
            $path = 'uploads/profile-photos/'.$user->getKey().'/'.Str::random(40).'.'.$sanitizedImage->extension;
            $stream = $sanitizedImage->openReadStream();

            try {
                if (! Storage::disk('private')->put($path, $stream)) {
                    throw new RuntimeException('Unable to write the sanitized profile photo.');
                }
            } finally {
                fclose($stream);
            }

            return $path;
        } finally {
            $sanitizedImage->cleanup();
        }
    }

    public function deleteIfManaged(?string $path): void
    {
        $path = StoredFilePath::clean($path, [
            'uploads/profile-photos/',
            'profile-photos/',
        ]);

        if ($path === null) {
            return;
        }

        if (Str::startsWith($path, 'uploads/profile-photos/')) {
            if (Storage::disk('private')->exists($path)) {
                Storage::disk('private')->delete($path);
            } else {
                $legacyPath = public_path($path);

                if (is_file($legacyPath)) {
                    @unlink($legacyPath);
                }
            }

            return;
        }

        if (Str::startsWith($path, 'profile-photos/')) {
            Storage::disk('public')->delete($path);
        }
    }
}
