<?php

namespace Tests\Feature;

use Tests\TestCase;

class RouteRateLimitingTest extends TestCase
{
    public function test_expected_routes_are_rate_limited(): void
    {
        $this->assertContains('throttle:login-route', $this->middlewareFor('login.submit'));
        $this->assertContains('throttle:register-route', $this->middlewareFor('signup.submit'));
        $this->assertContains('throttle:pricing-calculate', $this->middlewareFor('pricing.calculate'));
        $this->assertContains('throttle:promo-preview', $this->middlewareFor('checkout.promo.preview'));
        $this->assertContains('throttle:public-api-read', $this->middlewareFor('api.faqs'));
        $this->assertContains('throttle:chat-history', $this->middlewareFor('order-chat.messages.index'));
        $this->assertContains('throttle:chat-send', $this->middlewareFor('order-chat.messages.store'));
        $this->assertContains('throttle:order-progress-update', $this->middlewareFor('orders.progress.update'));
        $this->assertContains('throttle:checkout-submit', $this->middlewareFor('checkout.submit'));
        $this->assertContains('throttle:customer-order-actions', $this->middlewareFor('customer-orders.pause'));
        $this->assertContains('throttle:customer-order-actions', $this->middlewareFor('customer-orders.resume'));
        $this->assertContains('throttle:maintenance-mode-challenge', $this->middlewareFor('admin-maintenance-mode.challenge'));
        $this->assertContains('throttle:maintenance-mode-challenge', $this->middlewareFor('admin-maintenance-mode.confirm'));
        $this->assertContains('throttle:maintenance-mode-update', $this->middlewareFor('admin-maintenance-mode.captcha'));
        $this->assertContains('throttle:maintenance-mode-update', $this->middlewareFor('admin-maintenance-mode.password'));
        $this->assertContains('throttle:maintenance-mode-update', $this->middlewareFor('admin-maintenance-mode.update'));
        $this->assertContains('throttle:stripe-webhook', $this->middlewareFor('stripe.webhook'));
        $this->assertContains('throttle:cryptomus-webhook', $this->middlewareFor('cryptomus.webhook'));
    }

    /**
     * @return array<int, string>
     */
    protected function middlewareFor(string $routeName): array
    {
        $route = app('router')->getRoutes()->getByName($routeName);

        $this->assertNotNull($route, "Failed to find route [{$routeName}].");

        return $route->gatherMiddleware();
    }
}
