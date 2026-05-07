<?php

namespace App\Data\Security;

use RuntimeException;

class SanitizedUploadedImage
{
    public function __construct(
        public readonly string $temporaryPath,
        public readonly string $mimeType,
        public readonly string $extension,
    ) {}

    /**
     * @return resource
     */
    public function openReadStream()
    {
        $stream = fopen($this->temporaryPath, 'rb');

        if ($stream === false) {
            throw new RuntimeException('Unable to read the sanitized upload.');
        }

        return $stream;
    }

    public function cleanup(): void
    {
        if (is_file($this->temporaryPath)) {
            @unlink($this->temporaryPath);
        }
    }
}
