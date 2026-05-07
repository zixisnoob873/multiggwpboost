<?php

namespace App\Services\Orders;

use App\Models\Order;
use App\Models\User;
use App\Services\Security\SanitizedImageUploadService;
use App\Support\Security\StoredFilePath;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class OrderCompletionProofStorageService
{
    public function __construct(protected SanitizedImageUploadService $sanitizedImageUploadService) {}

    public function store(UploadedFile $file, Order $order, User $booster): string
    {
        $sanitizedImage = $this->sanitizedImageUploadService->sanitizeToTemporaryFile(
            $file,
            'completion-proof-',
            'completion proof',
            [
                'order_id' => $order->getKey(),
                'user_id' => $booster->getKey(),
                'upload' => 'order_completion_proof',
            ],
        );

        try {
            $path = 'order-completion-proofs/'.$order->getKey().'/'.Str::uuid()->toString().'.'.$sanitizedImage->extension;
            $stream = $sanitizedImage->openReadStream();

            try {
                if (! Storage::disk('local')->put($path, $stream)) {
                    throw new RuntimeException('Unable to write the completion proof.');
                }
            } finally {
                fclose($stream);
            }

            return $path;
        } finally {
            $sanitizedImage->cleanup();
        }
    }

    public function delete(?string $path): void
    {
        $path = StoredFilePath::clean($path, 'order-completion-proofs/');

        if ($path === null) {
            return;
        }

        Storage::disk('local')->delete($path);
    }
}
