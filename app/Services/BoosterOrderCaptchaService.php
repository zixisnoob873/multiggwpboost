<?php

namespace App\Services;

use App\Models\Order;
use Illuminate\Contracts\Session\Session;

class BoosterOrderCaptchaService
{
    public function issueFreshCode(Session $session, string $scope, Order $order): string
    {
        $codes = (array) $session->get($this->sessionKey($scope), []);
        $codes[$order->getKey()] = $this->generateCode();
        $session->put($this->sessionKey($scope), $codes);

        return $codes[$order->getKey()];
    }

    public function issueFreshCodes(Session $session, string $scope, iterable $orders): array
    {
        $codes = [];

        foreach ($orders as $order) {
            if (! $order instanceof Order) {
                continue;
            }

            $codes[$order->getKey()] = $this->generateCode();
        }

        $session->put($this->sessionKey($scope), $codes);

        return $codes;
    }

    public function verify(Session $session, string $scope, Order $order, ?string $value): bool
    {
        $codes = (array) $session->get($this->sessionKey($scope), []);
        $expected = (string) ($codes[$order->getKey()] ?? '');

        if ($expected === '' || trim((string) $value) !== $expected) {
            return false;
        }

        unset($codes[$order->getKey()]);
        $session->put($this->sessionKey($scope), $codes);

        return true;
    }

    protected function sessionKey(string $scope): string
    {
        return 'booster_'.$scope.'_captcha_codes';
    }

    protected function generateCode(): string
    {
        return str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);
    }
}
