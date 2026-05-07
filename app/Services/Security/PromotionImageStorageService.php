<?php

namespace App\Services\Security;

use App\Models\Promotion;
use App\Support\Security\StoredFilePath;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class PromotionImageStorageService
{
    public function __construct(protected SanitizedImageUploadService $sanitizedImageUploadService) {}

    public function store(UploadedFile $file): string
    {
        $sanitizedImage = $this->sanitizedImageUploadService->sanitizeToTemporaryFile(
            $file,
            'promotion-image-',
            'promotion image',
            [
                'upload' => 'promotion_image',
            ],
        );

        try {
            $directory = 'uploads/promotion-images';

            Storage::disk('private')->makeDirectory($directory);

            $path = $directory.'/'.Str::random(40).'.'.$sanitizedImage->extension;
            $stream = $sanitizedImage->openReadStream();

            try {
                if (! Storage::disk('private')->put($path, $stream)) {
                    throw new RuntimeException('Unable to write the sanitized promotion image.');
                }
            } finally {
                fclose($stream);
            }

            return $path;
        } finally {
            $sanitizedImage->cleanup();
        }
    }

    public function deleteIfUnused(?string $path, ?int $ignorePromotionId = null): void
    {
        $path = StoredFilePath::clean($path, [
            'uploads/promotion-images/',
            'promotion_pics/',
        ]);

        if ($path === null) {
            return;
        }

        $existingUsage = Promotion::query()
            ->where('image_path', $path)
            ->when($ignorePromotionId !== null, fn ($query) => $query->whereKeyNot($ignorePromotionId))
            ->exists();

        if ($existingUsage) {
            return;
        }

        if (Str::startsWith($path, 'uploads/promotion-images/')) {
            Storage::disk('private')->delete($path);

            return;
        }

        Storage::disk('public')->delete($path);
    }
}
