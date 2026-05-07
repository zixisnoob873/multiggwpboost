<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HomeProductConfigurationTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_page_uses_new_default_ranks_and_global_addon_catalog(): void
    {
        $response = $this->get(route('home'));

        $response->assertOk();
        $response->assertSee('value="Gold III" selected', false);
        $response->assertSee('value="Platinum III" selected', false);
        $response->assertSeeText('Offline Mode');
        $response->assertSeeText('Specific Agents');
        $response->assertSeeText('One-Trick Agent');
        $response->assertSeeText('Solo-Queue Only');
        $response->assertSeeText('No 5-Stack');
        $response->assertSeeText('Bonus Win');
        $response->assertSeeText('Streaming');
        $response->assertSeeText('Express Order');
        $response->assertSeeText('Normalize Scores');
        $response->assertSeeText('Record-Clips');
        $response->assertSee('data-agent-selector-modal-root', false);
        $response->assertSee('data-agent-selector-field-id="agent-selector-field-specific-agents-boost"', false);
        $response->assertSee('data-agent-selector-addon-input-id="boost-addon-specific-agents"', false);
        $response->assertSee('data-agent-selector-field-id="agent-selector-field-one-trick-agent-boost"', false);
        $response->assertSee('data-agent-selector-addon-input-id="boost-addon-one-trick-agent"', false);
        $response->assertDontSeeText('Radiant triangle');
        $response->assertDontSeeText('Record VOD');
    }

    public function test_home_page_renders_cryptomus_verification_meta_tag(): void
    {
        $this->get(route('home'))
            ->assertOk()
            ->assertSee('<meta name="cryptomus" content="fdcccf04" />', false);
    }
}
