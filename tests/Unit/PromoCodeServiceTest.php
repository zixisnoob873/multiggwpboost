<?php

namespace Tests\Unit;

use App\Models\PromoCode;
use App\Services\PromoCodeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PromoCodeServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_validate_code_applies_percentage_discount(): void
    {
        PromoCode::factory()->create([
            'code' => 'BOOST10',
            'type' => PromoCode::TYPE_PERCENTAGE,
            'value' => 10,
        ]);

        $result = app(PromoCodeService::class)->validateCode('boost10', 50);

        $this->assertTrue($result->valid);
        $this->assertSame('BOOST10', $result->promoCode?->code);
        $this->assertSame(5.0, $result->discountAmount);
        $this->assertSame(45.0, $result->discountedTotal);
    }

    public function test_validate_code_rejects_invalid_states(): void
    {
        $disabled = PromoCode::factory()->inactive()->create([
            'code' => 'OFF',
        ]);
        $expired = PromoCode::factory()->expired()->create([
            'code' => 'OLD',
        ]);
        $maxed = PromoCode::factory()->maxedOut()->create([
            'code' => 'FULL',
        ]);

        $disabledResult = app(PromoCodeService::class)->validateCode($disabled->code, 40);
        $expiredResult = app(PromoCodeService::class)->validateCode($expired->code, 40);
        $maxedResult = app(PromoCodeService::class)->validateCode($maxed->code, 40);

        $this->assertFalse($disabledResult->valid);
        $this->assertSame('This promo code is disabled.', $disabledResult->firstError());
        $this->assertFalse($expiredResult->valid);
        $this->assertSame('This promo code has expired.', $expiredResult->firstError());
        $this->assertFalse($maxedResult->valid);
        $this->assertSame('This promo code has reached its usage limit.', $maxedResult->firstError());
    }

    public function test_last_available_slot_cannot_be_consumed_twice(): void
    {
        $promoCode = PromoCode::factory()->create([
            'code' => 'LASTCALL',
            'max_uses' => 1,
            'used_count' => 0,
        ]);

        $service = app(PromoCodeService::class);

        $service->consumeCode($promoCode->id);

        $this->assertSame(1, $promoCode->fresh()->used_count);

        try {
            $service->consumeCode($promoCode->id);
            $this->fail('Expected the second promo consumption attempt to fail.');
        } catch (ValidationException $exception) {
            $this->assertSame(
                'This promo code has reached its usage limit.',
                $exception->errors()['promoCode'][0] ?? null,
            );
        }

        $this->assertSame(1, $promoCode->fresh()->used_count);
    }

    public function test_resolve_code_for_payload_can_add_and_discount_addons(): void
    {
        $promoCode = PromoCode::factory()->addonPromo()->create([
            'code' => 'ADDON25',
        ]);
        $promoCode->addonRules()->createMany([
            [
                'addon_slug' => 'streaming',
                'discount_type' => PromoCode::ADDON_DISCOUNT_TYPE_FREE,
                'discount_value' => 0,
            ],
            [
                'addon_slug' => 'solo-queue-only',
                'discount_type' => PromoCode::ADDON_DISCOUNT_TYPE_PERCENTAGE,
                'discount_value' => 25,
            ],
        ]);

        $payload = [
            'serviceType' => 'Rank Boosting',
            'currentDivision' => 'Gold II',
            'desiredDivision' => 'Platinum II',
            'currentRR' => 55,
            'avgRRPerWin' => '16 OR LOWER',
            'region' => 'NA',
            'platform' => 'PC',
            'boostMode' => 'Account Shared',
            'selectedAddons' => [],
        ];

        $result = app(PromoCodeService::class)->resolveCodeForPayload('ADDON25', $payload);

        $this->assertTrue($result->valid);
        $this->assertSame('ADDON25', $result->promoCode?->code);
        $this->assertEqualsCanonicalizing(['Streaming', 'Solo-Queue Only'], $result->promoManagedAddons);
        $this->assertEqualsCanonicalizing(['Streaming', 'Solo-Queue Only'], $result->promoAddedAddons);
        $this->assertEqualsCanonicalizing(['Streaming', 'Solo-Queue Only'], $result->originalOrderPayload['addons'] ?? []);
        $this->assertGreaterThan(0, $result->discountAmount);
        $this->assertGreaterThan($result->discountedTotal, $result->orderAmount);
        $this->assertCount(2, $result->promoAddonAdjustments);

        $streamingAdjustment = collect($result->promoAddonAdjustments)->firstWhere('label', 'Streaming');
        $soloAdjustment = collect($result->promoAddonAdjustments)->firstWhere('label', 'Solo-Queue Only');

        $this->assertSame(0.0, (float) ($streamingAdjustment['discountedAmount'] ?? -1));
        $this->assertTrue(($streamingAdjustment['addedByPromo'] ?? false));
        $this->assertSame(25.0, (float) ($soloAdjustment['discountValue'] ?? -1));
        $this->assertGreaterThan((float) ($soloAdjustment['discountedAmount'] ?? 0), (float) ($soloAdjustment['originalAmount'] ?? 0));
    }
}
