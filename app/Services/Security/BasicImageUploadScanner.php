<?php

namespace App\Services\Security;

use App\Contracts\Security\FileUploadScanner;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class BasicImageUploadScanner implements FileUploadScanner
{
    protected const MAX_IMAGE_PIXELS = 20_000_000;

    public function scan(string $path, string $mimeType, array $context = []): void
    {
        $filename = (string) ($context['original_name'] ?? basename($path));
        $extension = $this->normalizedExtension($path, $context);
        $detectedMimeType = null;

        try {
            if (! is_file($path) || ! is_readable($path)) {
                throw new RuntimeException('Uploaded file is not readable.');
            }

            if (! array_key_exists($mimeType, $this->allowedMimeExtensions())) {
                throw new RuntimeException('Uploaded file MIME type is not permitted.');
            }

            $detectedMimeType = $this->detectedMimeType($path);

            if ($detectedMimeType !== $mimeType) {
                throw new RuntimeException('Detected MIME type does not match the declared MIME type.');
            }

            if ($extension !== '' && ! in_array($extension, $this->allowedMimeExtensions()[$mimeType], true)) {
                throw new RuntimeException('Uploaded file extension does not match the declared MIME type.');
            }

            $headBytes = $this->readBytes($path, 512);

            $this->assertMagicBytes($headBytes, $mimeType);
            $this->assertNoExecutableSignature($headBytes);
            $this->assertPermittedImageDimensions($path);

            Log::info('security.file_upload_scan', [
                'filename' => $filename,
                'temporary_file' => basename($path),
                'extension' => $extension !== '' ? $extension : null,
                'provided_mime' => $mimeType,
                'detected_mime' => $detectedMimeType,
                'passed' => true,
            ] + $context);
        } catch (\Throwable $exception) {
            Log::warning('security.file_upload_scan', [
                'filename' => $filename,
                'temporary_file' => basename($path),
                'extension' => $extension !== '' ? $extension : null,
                'provided_mime' => $mimeType,
                'detected_mime' => $detectedMimeType,
                'passed' => false,
                'reason' => $exception->getMessage(),
            ] + $context);

            throw $exception instanceof RuntimeException
                ? $exception
                : new RuntimeException('Uploaded file scan failed.', previous: $exception);
        }
    }

    protected function detectedMimeType(string $path): string
    {
        if (! function_exists('finfo_open')) {
            throw new RuntimeException('File information support is not installed on this server.');
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);

        if ($finfo === false) {
            throw new RuntimeException('Unable to initialize MIME detection for uploaded files.');
        }

        try {
            $detected = finfo_file($finfo, $path) ?: '';
        } finally {
            finfo_close($finfo);
        }

        if (! is_string($detected) || trim($detected) === '') {
            throw new RuntimeException('Unable to detect the uploaded file MIME type.');
        }

        return strtolower(trim($detected));
    }

    protected function readBytes(string $path, int $length): string
    {
        $handle = fopen($path, 'rb');

        if (! is_resource($handle)) {
            throw new RuntimeException('Unable to open the uploaded file for scanning.');
        }

        try {
            $bytes = fread($handle, $length);
        } finally {
            fclose($handle);
        }

        if (! is_string($bytes) || $bytes === '') {
            throw new RuntimeException('Unable to read the uploaded file signature.');
        }

        return $bytes;
    }

    protected function assertMagicBytes(string $bytes, string $mimeType): void
    {
        $valid = match ($mimeType) {
            'image/jpeg' => str_starts_with($bytes, "\xFF\xD8\xFF"),
            'image/png' => str_starts_with($bytes, "\x89PNG\x0D\x0A\x1A\x0A"),
            'image/webp' => str_starts_with($bytes, 'RIFF') && substr($bytes, 8, 4) === 'WEBP',
            default => false,
        };

        if (! $valid) {
            throw new RuntimeException('Uploaded file magic bytes do not match the declared MIME type.');
        }
    }

    protected function assertNoExecutableSignature(string $bytes): void
    {
        $trimmed = ltrim($bytes);
        $lower = strtolower($trimmed);

        if (str_starts_with($bytes, 'MZ')) {
            throw new RuntimeException('Executable signatures are not allowed in uploaded files.');
        }

        foreach (['<?php', '<?=', '#!', '#!/bin/sh', '#!/bin/bash', '#!/usr/bin/env sh', '#!/usr/bin/env bash', '#!/usr/bin/env php'] as $signature) {
            if (str_starts_with($lower, strtolower($signature))) {
                throw new RuntimeException('Executable script signatures are not allowed in uploaded files.');
            }
        }
    }

    protected function assertPermittedImageDimensions(string $path): void
    {
        $imageInfo = @getimagesize($path);

        if (! is_array($imageInfo)) {
            throw new RuntimeException('Uploaded file is not a valid image.');
        }

        $width = (int) ($imageInfo[0] ?? 0);
        $height = (int) ($imageInfo[1] ?? 0);

        if ($width <= 0 || $height <= 0 || ($width * $height) > self::MAX_IMAGE_PIXELS) {
            throw new RuntimeException('Uploaded image dimensions are not permitted.');
        }
    }

    protected function normalizedExtension(string $path, array $context): string
    {
        $extension = (string) ($context['original_extension'] ?? '');

        if ($extension === '' && isset($context['original_name'])) {
            $extension = pathinfo((string) $context['original_name'], PATHINFO_EXTENSION);
        }

        if ($extension === '') {
            $extension = pathinfo($path, PATHINFO_EXTENSION);
        }

        return strtolower(ltrim((string) $extension, '.'));
    }

    protected function allowedMimeExtensions(): array
    {
        return [
            'image/jpeg' => ['jpg', 'jpeg'],
            'image/png' => ['png'],
            'image/webp' => ['webp'],
        ];
    }
}
