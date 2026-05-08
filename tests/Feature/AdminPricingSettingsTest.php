<?php

namespace Tests\Feature;

use App\Contracts\Payments\PaymentProvider;
use App\Data\Payments\PaymentCheckoutData;
use App\Data\Payments\PaymentInitializationResult;
use App\Data\Payments\PaymentProviderDescriptor;
use App\Data\Payments\PaymentVerificationResult;
use App\Data\Payments\PendingCheckout;
use App\Models\PricingSetting;
use App\Models\PricingSettingRevision;
use App\Models\User;
use App\Services\Payments\PaymentManager;
use App\Support\Pricing\ValorantPricingConfigRepository;
use Database\Seeders\GameCatalogSeeder;
use Database\Seeders\PricingSettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AdminPricingSettingsTest extends TestCase
{
    use RefreshDatabase;

    public ?PendingCheckout $capturedPendingCheckout = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PricingSettingSeeder::class);
    }

    public function test_super_admin_can_view_pricing_editor(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->get(route('admin-pricing.index'))
            ->assertOk()
            ->assertSee('Valorant Pricing')
            ->assertSee('Base Prices')
            ->assertSee('Special Rank Steps')
            ->assertSee('RR Rules');
    }

    public function test_guest_pricing_editor_request_redirects_to_login_instead_of_404(): void
    {
        $this->get(route('admin-pricing.index'))
            ->assertRedirect(route('login'));
    }

    public function test_super_admin_can_see_pricing_navigation_link(): void
    {
        $admin = $this->admin();

        $response = $this->actingAs($admin)
            ->get(route('admin-dashboard'));

        $response
            ->assertOk()
            ->assertSee('href="'.route('admin-pricing.index').'"', false)
            ->assertSee('Pricing');

        $this->assertMatchesRegularExpression(
            '/<a class="admin-nav-sublink[^"]*" href="'.preg_quote(route('admin-pricing.index'), '/').'">\s*<span>Pricing<\/span>\s*<\/a>/',
            $response->getContent(),
        );
    }

    public function test_non_admin_users_cannot_see_pricing_navigation_link(): void
    {
        $this->actingAs(User::factory()->create([
            'role' => User::ROLE_CUSTOMER,
            'account_status' => 'active',
        ]))
            ->get(route('admin-dashboard'))
            ->assertForbidden();
    }

    public function test_non_admin_users_cannot_access_pricing_editor(): void
    {
        foreach ([User::ROLE_CUSTOMER, User::ROLE_BOOSTER] as $role) {
            $this->actingAs(User::factory()->create([
                'role' => $role,
                'account_status' => 'active',
            ]))
                ->get(route('admin-pricing.index'))
                ->assertForbidden();
        }
    }

    public function test_admin_sub_role_strings_cannot_access_pricing_editor(): void
    {
        foreach (['finance_admin', 'content_admin', 'ops_admin'] as $role) {
            $this->actingAs(User::factory()->create([
                'role' => $role,
                'account_status' => 'active',
            ]))
                ->get(route('admin-pricing.index'))
                ->assertForbidden();
        }
    }

    public function test_only_super_admins_can_update_pricing(): void
    {
        $payload = $this->editorPayload(app(ValorantPricingConfigRepository::class)->defaults());

        $customer = User::factory()->create([
            'role' => User::ROLE_CUSTOMER,
            'account_status' => 'active',
        ]);

        $this->actingAs($customer)
            ->put(route('admin-pricing.update'), $payload)
            ->assertForbidden();

        $booster = User::factory()->create([
            'role' => User::ROLE_BOOSTER,
            'account_status' => 'active',
        ]);

        $this->actingAs($booster)
            ->put(route('admin-pricing.update'), $payload)
            ->assertForbidden();
    }

    public function test_super_admin_can_update_base_prices_and_modifiers(): void
    {
        $admin = $this->admin();
        $repository = app(ValorantPricingConfigRepository::class);
        $config = $repository->defaults();
        $config['base_prices']['Placement Matches']['Iron I'] = 7.25;
        $config['modifiers']['region']['NA'] = 1.25;

        $this->actingAs($admin)
            ->put(route('admin-pricing.update'), $this->editorPayload($config))
            ->assertRedirect(route('admin-pricing.index'))
            ->assertSessionHas('status', 'Valorant pricing updated.');

        $this->assertDatabaseHas('pricing_settings', [
            'key' => 'valorant',
            'version' => 2,
        ]);
        $this->assertDatabaseHas('admin_audit_logs', [
            'module' => 'system',
            'action' => 'pricing_updated',
        ]);

        $current = $repository->current();

        $this->assertSame(7.25, (float) data_get($current, 'config.base_prices.Placement Matches.Iron I'));
        $this->assertSame(1.25, (float) data_get($current, 'config.modifiers.region.NA'));
    }

    public function test_super_admin_can_update_labels_used_by_pricing_forms_and_calculations(): void
    {
        $admin = $this->admin();
        $repository = app(ValorantPricingConfigRepository::class);
        $config = $repository->defaults();
        $config['labels']['boost_modes']['normal'] = 'Shared Account';
        $config['labels']['boost_modes']['self_play'] = 'Play Alongside';
        $config['labels']['avg_rr']['18'] = 'Standard RR';

        $this->actingAs($admin)
            ->put(route('admin-pricing.update'), $this->editorPayload($config))
            ->assertRedirect(route('admin-pricing.index'))
            ->assertSessionHas('status', 'Valorant pricing updated.');

        $current = $repository->current();

        $this->assertSame('Shared Account', data_get($current, 'config.labels.boost_modes.normal'));
        $this->assertSame('Play Alongside', data_get($current, 'config.labels.boost_modes.self_play'));
        $this->assertSame('Standard RR', data_get($current, 'config.labels.avg_rr.18'));

        $this->actingAs($admin)
            ->get(route('admin-pricing.index'))
            ->assertOk()
            ->assertSee('value="Shared Account"', false)
            ->assertSee('value="Play Alongside"', false)
            ->assertSee('value="Standard RR"', false);

        $this->getJson(route('pricing.config'))
            ->assertOk()
            ->assertJsonPath('pricingPreview.labels.boost_modes.normal', 'Shared Account')
            ->assertJsonPath('pricingPreview.labels.boost_modes.self_play', 'Play Alongside')
            ->assertJsonPath('pricingPreview.labels.avg_rr.18', 'Standard RR');

        $payload = $this->rankBoostPayload();
        $payload['boostMode'] = 'self_play';
        $payload['avgRRPerWin'] = '18';

        $this->postJson(route('pricing.calculate'), $payload)
            ->assertOk()
            ->assertJsonPath('accountType', 'Play Alongside')
            ->assertJsonPath('averageRR', 'Standard RR')
            ->assertJsonPath('modifiers.boostMode.label', 'Play Alongside');

        $this->seed(GameCatalogSeeder::class);

        $this->get(route('games.show', ['game' => 'valorant']))
            ->assertOk()
            ->assertSee('<option value="normal">Shared Account</option>', false)
            ->assertSee('<option value="self_play">Play Alongside</option>', false)
            ->assertSee('<option value="18">Standard RR</option>', false);
    }

    public function test_admin_can_update_special_rank_boost_step_price(): void
    {
        $admin = $this->admin();
        $repository = app(ValorantPricingConfigRepository::class);
        $config = $repository->defaults();
        $config['special_rank_boost_steps']['Ascendant III->Immortal I'] = 44.44;

        $this->actingAs($admin)
            ->put(route('admin-pricing.update'), $this->editorPayload($config))
            ->assertRedirect(route('admin-pricing.index'))
            ->assertSessionHas('status', 'Valorant pricing updated.');

        $setting = PricingSetting::query()->where('key', 'valorant')->firstOrFail();

        $this->assertSame(
            44.44,
            (float) data_get($setting->config, 'special_rank_boost_steps.Ascendant III->Immortal I'),
        );
        $this->assertSame(
            44.44,
            (float) data_get($repository->current(), 'config.special_rank_boost_steps.Ascendant III->Immortal I'),
        );
    }

    public function test_invalid_special_rank_step_price_is_rejected(): void
    {
        $admin = $this->admin();
        $repository = app(ValorantPricingConfigRepository::class);
        $payload = $this->editorPayload($repository->defaults());
        $payload['special_rank_boost_rows'][0]['price'] = -1;

        $this->actingAs($admin)
            ->from(route('admin-pricing.index'))
            ->put(route('admin-pricing.update'), $payload)
            ->assertRedirect(route('admin-pricing.index'))
            ->assertSessionHasErrors([
                'special_rank_boost_rows.0.price',
            ]);

        $this->assertSame(
            39.99,
            (float) data_get($repository->current(), 'config.special_rank_boost_steps.Ascendant III->Immortal I'),
        );
    }

    public function test_updated_special_rank_price_persists_after_refresh(): void
    {
        $admin = $this->admin();
        $config = app(ValorantPricingConfigRepository::class)->defaults();
        $config['special_rank_boost_steps']['Immortal I->Immortal II'] = 88.88;

        $this->actingAs($admin)
            ->put(route('admin-pricing.update'), $this->editorPayload($config))
            ->assertRedirect(route('admin-pricing.index'));

        $this->actingAs($admin)
            ->get(route('admin-pricing.index'))
            ->assertOk()
            ->assertSee('name="special_rank_boost_rows[1][price]"', false)
            ->assertSee('value="88.88"', false);
    }

    public function test_special_rank_price_inputs_are_rendered_without_a_false_zero_maximum(): void
    {
        $admin = $this->admin();

        $response = $this->actingAs($admin)
            ->get(route('admin-pricing.index'))
            ->assertOk();

        $this->assertMatchesRegularExpression(
            '/name="special_rank_boost_rows\[0\]\[price\]"(?![^>]*\smax=)[^>]*>/',
            $response->getContent(),
        );
    }

    public function test_invalid_pricing_payloads_are_rejected(): void
    {
        $admin = $this->admin();
        $config = app(ValorantPricingConfigRepository::class)->defaults();
        $payload = $this->editorPayload($config);
        $payload['base_prices']['Placement Matches']['Iron I'] = -1;
        $payload['modifiers']['region']['NA'] = 7;
        $payload['special_rank_boost_rows'][] = [
            'from' => 'Gold I',
            'to' => 'Platinum I',
            'price' => 10,
        ];
        $payload['special_rank_boost_rows'][] = [
            'from' => 'Gold I',
            'to' => 'Platinum I',
            'price' => 12,
        ];
        unset($payload['base_prices']['Ranked Wins']['Iron I']);

        $this->actingAs($admin)
            ->from(route('admin-pricing.index'))
            ->put(route('admin-pricing.update'), $payload)
            ->assertRedirect(route('admin-pricing.index'))
            ->assertSessionHasErrors([
                'modifiers.region.NA',
                'special_rank_boost_steps',
                'base_prices.Ranked Wins',
                'base_prices.Placement Matches.Iron I',
            ]);

        $this->assertDatabaseMissing('pricing_settings', [
            'key' => 'valorant',
            'version' => 2,
        ]);
    }

    public function test_pricing_cache_clears_after_update(): void
    {
        $admin = $this->admin();
        $repository = app(ValorantPricingConfigRepository::class);
        $this->assertSame(2.25, (float) data_get($repository->current(), 'config.base_prices.Placement Matches.Iron I'));

        $config = $repository->defaults();
        $config['base_prices']['Placement Matches']['Iron I'] = 9.99;

        $this->actingAs($admin)
            ->put(route('admin-pricing.update'), $this->editorPayload($config))
            ->assertRedirect(route('admin-pricing.index'));

        $this->assertSame(9.99, (float) data_get($repository->current(), 'config.base_prices.Placement Matches.Iron I'));
    }

    public function test_pricing_cache_clears_after_special_rank_update(): void
    {
        $admin = $this->admin();
        $repository = app(ValorantPricingConfigRepository::class);
        $this->assertSame(
            39.99,
            (float) data_get($repository->current(), 'config.special_rank_boost_steps.Ascendant III->Immortal I'),
        );

        $config = $repository->defaults();
        $config['special_rank_boost_steps']['Ascendant III->Immortal I'] = 55.55;

        $this->actingAs($admin)
            ->put(route('admin-pricing.update'), $this->editorPayload($config))
            ->assertRedirect(route('admin-pricing.index'));

        $this->assertSame(
            55.55,
            (float) data_get($repository->current(), 'config.special_rank_boost_steps.Ascendant III->Immortal I'),
        );
    }

    public function test_calculate_price_uses_updated_pricing(): void
    {
        $admin = $this->admin();
        $config = app(ValorantPricingConfigRepository::class)->defaults();
        $config['base_prices']['Placement Matches']['Iron I'] = 8.5;

        $this->actingAs($admin)
            ->put(route('admin-pricing.update'), $this->editorPayload($config))
            ->assertRedirect(route('admin-pricing.index'));

        $this->postJson(route('pricing.calculate'), $this->placementPayload())
            ->assertOk()
            ->assertJsonPath('basePrice', 8.5)
            ->assertJsonPath('finalPrice', 8.5);
    }

    public function test_calculate_price_uses_updated_special_rank_price(): void
    {
        $admin = $this->admin();
        $config = app(ValorantPricingConfigRepository::class)->defaults();
        $config['special_rank_boost_steps']['Ascendant III->Immortal I'] = 64.32;

        $this->actingAs($admin)
            ->put(route('admin-pricing.update'), $this->editorPayload($config))
            ->assertRedirect(route('admin-pricing.index'));

        $this->postJson(route('pricing.calculate'), $this->rankBoostPayload())
            ->assertOk()
            ->assertJsonPath('basePrice', 64.32)
            ->assertJsonPath('rankPath.0.price', 64.32)
            ->assertJsonPath('finalPrice', 64.32);
    }

    public function test_checkout_uses_updated_pricing(): void
    {
        Http::fake();
        $this->bindPendingProvider();
        $admin = $this->admin();
        $config = app(ValorantPricingConfigRepository::class)->defaults();
        $config['base_prices']['Placement Matches']['Iron I'] = 8.75;

        $this->actingAs($admin)
            ->put(route('admin-pricing.update'), $this->editorPayload($config))
            ->assertRedirect(route('admin-pricing.index'));

        $customer = User::factory()->create([
            'role' => 'customer',
            'account_status' => 'active',
        ]);

        $this->actingAs($customer)
            ->post(route('checkout.submit'), [
                'firstName' => 'Demo',
                'lastName' => 'Customer',
                'email' => $customer->email,
                'contactMethod' => 'email',
                'whatsapp' => null,
                'discord' => null,
                'paymentMethod' => 'fake-pending',
                'policy' => '1',
                'compliance' => '1',
                'orderPayload' => json_encode($this->placementPayload(), JSON_THROW_ON_ERROR),
            ])
            ->assertRedirect('https://example.test/pay/pending');

        $this->assertNotNull($this->capturedPendingCheckout);
        $this->assertSame(875, $this->capturedPendingCheckout->priceCents);
        $this->assertSame(8.75, $this->capturedPendingCheckout->total);
    }

    public function test_checkout_uses_updated_special_rank_price(): void
    {
        Http::fake();
        $this->bindPendingProvider();
        $admin = $this->admin();
        $config = app(ValorantPricingConfigRepository::class)->defaults();
        $config['special_rank_boost_steps']['Ascendant III->Immortal I'] = 72.34;

        $this->actingAs($admin)
            ->put(route('admin-pricing.update'), $this->editorPayload($config))
            ->assertRedirect(route('admin-pricing.index'));

        $customer = User::factory()->create([
            'role' => 'customer',
            'account_status' => 'active',
        ]);

        $this->actingAs($customer)
            ->post(route('checkout.submit'), [
                'firstName' => 'Demo',
                'lastName' => 'Customer',
                'email' => $customer->email,
                'contactMethod' => 'email',
                'whatsapp' => null,
                'discord' => null,
                'paymentMethod' => 'fake-pending',
                'policy' => '1',
                'compliance' => '1',
                'orderPayload' => json_encode($this->rankBoostPayload(), JSON_THROW_ON_ERROR),
            ])
            ->assertRedirect('https://example.test/pay/pending');

        $this->assertNotNull($this->capturedPendingCheckout);
        $this->assertSame(7234, $this->capturedPendingCheckout->priceCents);
        $this->assertSame(72.34, $this->capturedPendingCheckout->total);
    }

    public function test_public_pricing_config_endpoint_returns_safe_dynamic_config(): void
    {
        $admin = $this->admin();
        $config = app(ValorantPricingConfigRepository::class)->defaults();
        $config['base_prices']['Placement Matches']['Iron I'] = 6.66;

        $this->actingAs($admin)
            ->put(route('admin-pricing.update'), $this->editorPayload($config))
            ->assertRedirect(route('admin-pricing.index'));

        $this->getJson(route('pricing.config'))
            ->assertOk()
            ->assertJsonPath('version', 2)
            ->assertJsonPath('pricingPreview.basePrices.Placement Matches.Iron I', 6.66)
            ->assertJsonStructure([
                'version',
                'checksum',
                'source',
                'updatedAt',
                'pricingPreview' => [
                    'version',
                    'checksum',
                    'rankOrder',
                    'services',
                    'basePrices',
                    'specialRankBoostSteps',
                    'rrRules',
                    'addons',
                    'modifiers',
                    'labels',
                ],
            ]);
    }

    public function test_super_admin_can_reset_pricing_to_defaults(): void
    {
        $admin = $this->admin();
        $repository = app(ValorantPricingConfigRepository::class);
        $config = $repository->defaults();
        $config['base_prices']['Placement Matches']['Iron I'] = 6.66;

        $this->actingAs($admin)
            ->put(route('admin-pricing.update'), $this->editorPayload($config))
            ->assertRedirect(route('admin-pricing.index'));

        $this->actingAs($admin)
            ->post(route('admin-pricing.reset'), [
                'confirmation' => 'RESET PRICING',
            ])
            ->assertRedirect(route('admin-pricing.index'))
            ->assertSessionHas('status', 'Valorant pricing reset to config defaults.');

        $this->assertSame(2.25, (float) data_get($repository->current(), 'config.base_prices.Placement Matches.Iron I'));
        $this->assertDatabaseHas('admin_audit_logs', [
            'module' => 'system',
            'action' => 'pricing_reset',
        ]);
        $this->assertTrue(PricingSettingRevision::query()->where('action', 'reset')->exists());
    }

    protected function placementPayload(): array
    {
        return [
            'serviceType' => 'Placement Matches',
            'currentDivision' => 'Iron I',
            'numberOfPlacementGames' => 1,
            'region' => 'EU',
            'platform' => 'PC',
            'boostMode' => 'Account Shared',
            'selectedAddons' => [],
        ];
    }

    protected function rankBoostPayload(): array
    {
        return [
            'serviceType' => 'Rank Boosting',
            'currentDivision' => 'Ascendant III',
            'desiredDivision' => 'Immortal I',
            'currentRR' => 0,
            'avgRRPerWin' => '18 OR LOWER',
            'region' => 'EU',
            'platform' => 'PC',
            'boostMode' => 'Account Shared',
            'selectedAddons' => [],
        ];
    }

    protected function editorPayload(array $config): array
    {
        return [
            'base_prices' => $config['base_prices'],
            'special_rank_boost_rows' => collect($config['special_rank_boost_steps'] ?? [])
                ->map(function (mixed $price, string $key): array {
                    [$fromRank, $toRank] = array_pad(array_map('trim', explode('->', $key, 2)), 2, '');

                    return [
                        'from' => $fromRank,
                        'to' => $toRank,
                        'price' => $price,
                    ];
                })
                ->values()
                ->all(),
            'rr_rules' => $config['rr_rules'],
            'addons' => $config['addons'],
            'modifiers' => $config['modifiers'],
            'labels' => $config['labels'],
        ];
    }

    protected function admin(): User
    {
        return User::factory()->create([
            'role' => User::ROLE_SUPER_ADMIN,
            'account_status' => 'active',
        ]);
    }

    protected function bindPendingProvider(): void
    {
        $testCase = $this;

        $this->app->singleton(PaymentManager::class, function () use ($testCase) {
            return new PaymentManager([
                new class($testCase) implements PaymentProvider
                {
                    public function __construct(protected AdminPricingSettingsTest $testCase) {}

                    public function key(): string
                    {
                        return 'fake-pending';
                    }

                    public function descriptor(): PaymentProviderDescriptor
                    {
                        return new PaymentProviderDescriptor(
                            key: 'fake-pending',
                            label: 'Fake Pending',
                            description: 'Fake provider for tests.',
                            notice: 'Payment remains pending in tests.',
                            submitLabel: 'Continue',
                            isAvailable: true,
                            isDefault: true,
                        );
                    }

                    public function initialize(PendingCheckout $pendingCheckout, PaymentCheckoutData $checkoutData): PaymentInitializationResult
                    {
                        $this->testCase->capturedPendingCheckout = $pendingCheckout;

                        return PaymentInitializationResult::redirect('https://example.test/pay/pending');
                    }

                    public function verify(PendingCheckout $pendingCheckout, array $payload = []): PaymentVerificationResult
                    {
                        return new PaymentVerificationResult(false);
                    }
                },
            ]);
        });
    }
}
