<?php

namespace App\Contracts\Security;

interface FileUploadScanner
{
    public function scan(string $path, string $mimeType, array $context = []): void;
}
