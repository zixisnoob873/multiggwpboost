<?php

namespace App\Services\Security;

use App\Contracts\Security\FileUploadScanner;
use App\Data\Security\SanitizedUploadedImage;
use Illuminate\Http\UploadedFile;
use RuntimeException;

class SanitizedImageUploadService
{
    protected const MAX_IMAGE_PIXELS = 20_000_000;

    public function __construct(protected FileUploadScanner $fileUploadScanner) {}

    public function sanitizeToTemporaryFile(
        UploadedFile $file,
        string $temporaryPrefix,
        string $uploadLabel,
        array $scanContext = [],
    ): SanitizedUploadedImage {
        [$resource, $mimeType, $extension] = $this->decodedImage($file);
        $tempPath = tempnam(sys_get_temp_dir(), $temporaryPrefix);

        if ($tempPath === false) {
            $this->destroyImage($resource);

            throw new RuntimeException("Unable to allocate a temporary file for the {$uploadLabel}.");
        }

        try {
            $resource = $this->applyExifOrientation($resource, $file, $mimeType);
            $this->encodeImage($resource, $mimeType, $tempPath, $uploadLabel);
            $this->fileUploadScanner->scan($tempPath, $mimeType, array_merge($scanContext, [
                'original_name' => $file->getClientOriginalName(),
                'original_extension' => strtolower((string) $file->getClientOriginalExtension()),
                'temporary_file' => basename($tempPath),
            ]));
        } catch (\Throwable $exception) {
            if (is_file($tempPath)) {
                @unlink($tempPath);
            }

            throw $exception;
        } finally {
            $this->destroyImage($resource);
        }

        return new SanitizedUploadedImage($tempPath, $mimeType, $extension);
    }

    protected function decodedImage(UploadedFile $file): array
    {
        if (! function_exists('imagecreatefromstring')) {
            throw new RuntimeException('Image processing support is not installed on this server.');
        }

        $realPath = $file->getRealPath();
        $imageInfo = $realPath ? @getimagesize($realPath) : false;
        $mimeType = is_array($imageInfo) ? (string) ($imageInfo['mime'] ?? '') : '';

        if (! in_array($mimeType, ['image/jpeg', 'image/png', 'image/webp'], true)) {
            throw new RuntimeException('Unsupported image type.');
        }

        $width = (int) ($imageInfo[0] ?? 0);
        $height = (int) ($imageInfo[1] ?? 0);

        if ($width <= 0 || $height <= 0 || ($width * $height) > self::MAX_IMAGE_PIXELS) {
            throw new RuntimeException('Uploaded image dimensions are not permitted.');
        }

        $binary = $realPath ? @file_get_contents($realPath) : false;
        $resource = is_string($binary) ? @imagecreatefromstring($binary) : false;

        if (! $resource) {
            throw new RuntimeException('Unable to decode the uploaded image.');
        }

        return [$resource, $mimeType, $this->extensionForMime($mimeType)];
    }

    protected function applyExifOrientation(\GdImage $resource, UploadedFile $file, string $mimeType): \GdImage
    {
        if ($mimeType !== 'image/jpeg' || ! function_exists('exif_read_data')) {
            return $resource;
        }

        $realPath = $file->getRealPath();

        if (! $realPath) {
            return $resource;
        }

        $exif = @exif_read_data($realPath);
        $orientation = (int) ($exif['Orientation'] ?? 1);
        $rotated = match ($orientation) {
            3 => imagerotate($resource, 180, 0),
            6 => imagerotate($resource, -90, 0),
            8 => imagerotate($resource, 90, 0),
            default => null,
        };

        if (! ($rotated instanceof \GdImage)) {
            return $resource;
        }

        imagedestroy($resource);

        return $rotated;
    }

    protected function encodeImage(\GdImage $resource, string $mimeType, string $targetPath, string $uploadLabel): void
    {
        $encoderAvailable = match ($mimeType) {
            'image/jpeg' => function_exists('imagejpeg'),
            'image/png' => function_exists('imagepng'),
            'image/webp' => function_exists('imagewebp'),
            default => false,
        };

        if (! $encoderAvailable) {
            throw new RuntimeException('Image processing support is incomplete on this server.');
        }

        $encoded = match ($mimeType) {
            'image/jpeg' => imagejpeg($resource, $targetPath, 88),
            'image/png' => imagepng($resource, $targetPath, 6),
            'image/webp' => imagewebp($resource, $targetPath, 85),
            default => false,
        };

        if (! $encoded) {
            throw new RuntimeException("Unable to sanitize the uploaded {$uploadLabel}.");
        }
    }

    protected function extensionForMime(string $mimeType): string
    {
        return match ($mimeType) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            default => 'jpg',
        };
    }

    protected function destroyImage(mixed $resource): void
    {
        if ($resource instanceof \GdImage) {
            imagedestroy($resource);
        }
    }
}
