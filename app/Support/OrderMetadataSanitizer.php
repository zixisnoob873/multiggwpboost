<?php

namespace App\Support;

use Illuminate\Support\Arr;

class OrderMetadataSanitizer
{
    public static function forAdminTooling(array $metadata): array
    {
        $sanitized = $metadata;

        Arr::forget($sanitized, [
            'customer.email',
            'customer.whatsapp',
            'customer.discord',
            'stripeEventId',
            'checkoutReference',
            'completedOrderId',
            'paymentIntegrity',
        ]);

        return self::trimEmpty($sanitized);
    }

    protected static function trimEmpty(array $payload): array
    {
        foreach ($payload as $key => $value) {
            if (is_array($value)) {
                $payload[$key] = self::trimEmpty($value);
            }

            if ($payload[$key] === [] || $payload[$key] === null || $payload[$key] === '') {
                unset($payload[$key]);
            }
        }

        return $payload;
    }
}
