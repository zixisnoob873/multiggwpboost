<?php

namespace Tests\Feature;

use App\Models\Faq;
use App\Models\Game;
use App\Models\GameService;
use App\Models\Review;
use Database\Seeders\GameCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConversionComponentsRenderingTest extends TestCase
{
    use RefreshDatabase;

    public function test_homepage_game_service_and_category_pages_render_shared_conversion_components(): void
    {
        $this->seed(GameCatalogSeeder::class);
        $this->resetProofContent();

        Faq::query()->create([
            'question' => 'Global safety question?',
            'answer' => 'Global safety answer for reusable conversion components.',
            'order' => 1,
        ]);
        Review::query()->create([
            'author_name' => 'Global Reviewer',
            'service' => 'Global Service',
            'quote' => 'Global reusable review proof for conversion components.',
            'sort_order' => 1,
        ]);

        foreach ([
            route('home'),
            route('game.show', ['game' => 'valorant']),
            route('game.services.show', ['game' => 'valorant', 'service' => 'rank-boosting']),
            route('games.categories.show', ['category' => 'fps']),
        ] as $url) {
            $this->get($url)
                ->assertOk()
                ->assertSee('data-conversion-component="badge-strip"', false)
                ->assertSee('data-conversion-component="faq-accordion"', false)
                ->assertSee('data-conversion-component="review-section"', false)
                ->assertSee('data-conversion-component="review-card"', false);
        }
    }

    public function test_faq_and_review_scope_precedence_prefers_service_then_game_then_global(): void
    {
        $this->seed(GameCatalogSeeder::class);
        $this->resetProofContent();

        $game = Game::query()->where('slug', 'valorant')->firstOrFail();
        $rankBoosting = GameService::query()
            ->where('game_id', $game->id)
            ->where('slug', 'rank-boosting')
            ->firstOrFail();
        $placement = GameService::query()
            ->where('game_id', $game->id)
            ->where('slug', 'placement-matches')
            ->firstOrFail();

        Faq::query()->create([
            'question' => 'Global fallback FAQ?',
            'answer' => 'Global fallback answer.',
            'order' => 1,
        ]);
        Faq::query()->create([
            'game_id' => $game->id,
            'question' => 'Valorant game FAQ?',
            'answer' => 'Valorant game answer.',
            'order' => 1,
        ]);
        Faq::query()->create([
            'game_id' => $game->id,
            'service_id' => $rankBoosting->id,
            'question' => 'Rank boosting service FAQ?',
            'answer' => 'Rank boosting service answer.',
            'order' => 1,
        ]);

        Review::query()->create([
            'author_name' => 'Global Reviewer',
            'service' => 'Global Service',
            'quote' => 'Global fallback review.',
            'sort_order' => 1,
        ]);
        Review::query()->create([
            'game_id' => $game->id,
            'author_name' => 'Valorant Reviewer',
            'service' => 'Valorant Boosting',
            'quote' => 'Valorant game review.',
            'sort_order' => 1,
        ]);
        Review::query()->create([
            'game_id' => $game->id,
            'service_id' => $rankBoosting->id,
            'author_name' => 'Rank Reviewer',
            'service' => 'Rank Boosting',
            'quote' => 'Rank boosting service review.',
            'sort_order' => 1,
        ]);

        $this->get(route('home'))
            ->assertOk()
            ->assertSeeText('Global fallback FAQ?')
            ->assertSeeText('Global fallback review.')
            ->assertDontSeeText('Valorant game FAQ?')
            ->assertDontSeeText('Rank boosting service FAQ?');

        $this->get(route('game.show', ['game' => 'valorant']))
            ->assertOk()
            ->assertSeeText('Valorant game FAQ?')
            ->assertSeeText('Valorant game review.')
            ->assertDontSeeText('Global fallback FAQ?')
            ->assertDontSeeText('Rank boosting service FAQ?');

        $this->get(route('game.services.show', ['game' => 'valorant', 'service' => 'rank-boosting']))
            ->assertOk()
            ->assertSeeText('Rank boosting service FAQ?')
            ->assertSeeText('Rank boosting service review.')
            ->assertDontSeeText('Valorant game FAQ?')
            ->assertDontSeeText('Global fallback FAQ?');

        $this->get(route('game.services.show', ['game' => 'valorant', 'service' => $placement->slug]))
            ->assertOk()
            ->assertSeeText('Valorant game FAQ?')
            ->assertSeeText('Valorant game review.')
            ->assertDontSeeText('Rank boosting service FAQ?')
            ->assertDontSeeText('Global fallback FAQ?');
    }

    public function test_conversion_surfaces_expose_analytics_hooks(): void
    {
        $this->seed(GameCatalogSeeder::class);
        $this->resetProofContent();

        $home = $this->get(route('home'))->assertOk()->getContent();

        $this->assertStringContainsString('data-conversion-cta="home-primary"', $home);
        $this->assertStringContainsString('data-browse-games', $home);
        $this->assertStringContainsString('data-analytics-game-card', $home);
        $this->assertStringContainsString('data-analytics-service-card', $home);

        $service = $this->get(route('game.services.show', ['game' => 'valorant', 'service' => 'rank-boosting']))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('data-service-calculator', $service);
        $this->assertStringContainsString('data-service-addon', $service);
        $this->assertStringContainsString('data-service-checkout', $service);
        $this->assertStringContainsString('data-addon-slug', $service);

        $checkout = $this->get(route('checkout'))->assertOk()->getContent();

        $this->assertStringContainsString('id="checkoutForm"', $checkout);
        $this->assertStringContainsString('data-analytics-provider', $checkout);
    }

    public function test_contact_form_submission_flashes_privacy_safe_analytics_event(): void
    {
        $response = $this->post(route('contact.submit'), [
            'name' => 'Demo User',
            'email' => 'demo@example.com',
            'order_reference' => 'ORD-123',
            'message' => str_repeat('Help me please. ', 3),
            'website' => '',
        ]);

        $response->assertRedirect(route('contact'));

        $events = session('analyticsEvents');

        $this->assertIsArray($events);
        $this->assertSame('contact_form_submission', $events[0]['name'] ?? null);
        $this->assertSame([
            'context' => 'contact_form',
            'has_order_reference' => true,
        ], $events[0]['payload'] ?? null);
    }

    public function test_shared_faq_accordion_uses_valid_accessible_id_references(): void
    {
        $this->seed(GameCatalogSeeder::class);
        $this->resetProofContent();

        Faq::query()->create([
            'question' => 'Accessible FAQ question?',
            'answer' => 'Accessible FAQ answer.',
            'order' => 1,
        ]);

        $html = $this->get(route('home'))->assertOk()->getContent();

        $this->assertStringContainsString('id="marketplaceFaqAccordion"', $html);
        $this->assertStringContainsString('id="marketplaceFaqAccordionItem1Heading"', $html);
        $this->assertStringContainsString('aria-controls="marketplaceFaqAccordionItem1"', $html);
        $this->assertStringContainsString('id="marketplaceFaqAccordionItem1"', $html);
        $this->assertStringContainsString('aria-labelledby="marketplaceFaqAccordionItem1Heading"', $html);
        $this->assertStringContainsString('aria-expanded="true"', $html);
    }

    protected function resetProofContent(): void
    {
        Faq::query()->delete();
        Review::query()->delete();
    }
}
