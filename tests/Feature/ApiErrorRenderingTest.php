<?php

namespace Tests\Feature;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use RuntimeException;
use Tests\TestCase;

class ApiErrorRenderingTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware('web')->get('/_testing/api-error', function () {
            throw new RuntimeException('Secret stack trace should never be exposed.');
        });

        Route::middleware(['web', 'throttle:testing-api-throttle'])
            ->get('/_testing/api-throttled', fn () => response()->json(['success' => true]));

        RateLimiter::for('testing-api-throttle', fn (Request $request) => [
            Limit::perMinute(1)->by($request->ip()),
        ]);
    }

    public function test_validation_errors_use_the_standard_json_contract(): void
    {
        $this->postJson(route('checkout.promo.preview'), [
            'promoCode' => '',
            'orderPayload' => '',
        ])
            ->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'The given data was invalid.',
                'error_code' => 'validation_failed',
            ])
            ->assertJsonStructure([
                'errors' => ['promoCode', 'orderPayload'],
            ]);
    }

    public function test_missing_routes_return_safe_json_errors(): void
    {
        $this->getJson('/_testing/route-that-does-not-exist')
            ->assertStatus(404)
            ->assertJson([
                'success' => false,
                'message' => 'The requested resource could not be found.',
                'error_code' => 'not_found',
            ]);
    }

    public function test_unhandled_api_exceptions_do_not_leak_internal_messages(): void
    {
        $this->getJson('/_testing/api-error')
            ->assertStatus(500)
            ->assertJson([
                'success' => false,
                'message' => 'Something went wrong.',
                'error_code' => 'server_error',
            ])
            ->assertDontSee('Secret stack trace should never be exposed.');
    }

    public function test_throttled_requests_return_safe_json_errors(): void
    {
        $this->getJson('/_testing/api-throttled')
            ->assertOk()
            ->assertJson([
                'success' => true,
            ]);

        $this->getJson('/_testing/api-throttled')
            ->assertStatus(429)
            ->assertJson([
                'success' => false,
                'message' => 'Too many requests. Please try again in a moment.',
                'error_code' => 'rate_limited',
            ]);
    }
}
