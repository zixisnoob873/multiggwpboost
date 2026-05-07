<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PricingCalculationTest extends TestCase
{
    use RefreshDatabase;

    public function test_calculate_price_returns_authoritative_rank_boost_breakdown(): void
    {
        $response = $this->postJson(route('pricing.calculate'), [
            'serviceType' => 'Rank Boosting',
            'currentDivision' => 'Gold II',
            'desiredDivision' => 'Platinum II',
            'currentRR' => 55,
            'avgRRPerWin' => '16 OR LOWER',
            'region' => 'NA',
            'platform' => 'PC',
            'boostMode' => 'Account Shared',
            'selectedAddons' => ['Solo-Queue Only', 'Offline Mode'],
        ]);

        $response->assertOk()
            ->assertJsonPath('basePrice', 27)
            ->assertJsonPath('subtotalAfterRR', 25.03)
            ->assertJsonPath('subtotalAfterAddons', 37.54)
            ->assertJsonPath('subtotalAfterGlobalModifiers', 41.29)
            ->assertJsonPath('finalPrice', 41.29)
            ->assertJsonPath('pricing.total', 41.29)
            ->assertJsonCount(3, 'rankPath')
            ->assertJsonPath('rankPath.0.from', 'Gold II')
            ->assertJsonPath('rankPath.2.to', 'Platinum II')
            ->assertJsonPath('addonBreakdown.0.label', 'Solo-Queue Only');
    }

    public function test_calculate_price_prices_ranked_wins_with_allowed_self_play_addons(): void
    {
        $response = $this->postJson(route('pricing.calculate'), [
            'serviceType' => 'Ranked Wins',
            'currentDivision' => 'Diamond I',
            'numberOfWins' => 3,
            'region' => 'APAC',
            'platform' => 'Console',
            'boostMode' => 'Self-Play',
            'selectedAddons' => ['Express Order'],
        ]);

        $response->assertOk()
            ->assertJsonPath('finalPrice', 74.66)
            ->assertJsonPath('addons.0', 'Express Order')
            ->assertJsonMissingPath('addons.1')
            ->assertJsonFragment(['disabledAddons' => [
                'Offline Mode',
                'Specific Agents',
                'One-Trick Agent',
                'Solo-Queue Only',
                'No 5-Stack',
                'Streaming',
                'Normalize Scores',
                'Record-Clips',
            ]])
            ->assertJsonPath('pricing.addons', 2.55)
            ->assertJsonPath('modifiers.boostMode.code', 'self_play');
    }

    public function test_calculate_price_rejects_tampered_self_play_addons_outside_the_allowed_list(): void
    {
        $response = $this->postJson(route('pricing.calculate'), [
            'serviceType' => 'Rank Boosting',
            'currentDivision' => 'Gold II',
            'desiredDivision' => 'Gold III',
            'currentRR' => 55,
            'avgRRPerWin' => '18 OR LOWER',
            'region' => 'NA',
            'platform' => 'PC',
            'boostMode' => 'Self-Play',
            'selectedAddons' => ['Bonus Win', 'Express Order', 'Offline Mode', 'Specific Agents'],
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('validationErrors.selectedAddons.0', 'Duo / Self-Play only allows Bonus Win and Express Order.');
    }

    public function test_calculate_price_rejects_ranked_wins_outside_the_public_range(): void
    {
        $response = $this->postJson(route('pricing.calculate'), [
            'serviceType' => 'Ranked Wins',
            'currentDivision' => 'Diamond I',
            'numberOfWins' => 6,
            'region' => 'APAC',
            'platform' => 'Console',
            'boostMode' => 'Self-Play',
            'selectedAddons' => [],
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('validationErrors.numberOfWins.0', 'Wins needed must be between 1 and 5.');
    }

    public function test_calculate_price_returns_validation_errors_for_invalid_rank_path(): void
    {
        $response = $this->postJson(route('pricing.calculate'), [
            'serviceType' => 'Rank Boosting',
            'currentDivision' => 'Platinum II',
            'desiredDivision' => 'Gold I',
            'currentRR' => 25,
            'avgRRPerWin' => '18 OR LOWER',
            'region' => 'EU',
            'platform' => 'PC',
            'boostMode' => 'Account Shared',
            'selectedAddons' => [],
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('finalPrice', 0)
            ->assertJsonPath('validationErrors.targetRank.0', 'Target rank must be higher than current rank.');
    }

    public function test_calculate_price_requires_specific_agents_when_the_addon_is_selected(): void
    {
        $response = $this->postJson(route('pricing.calculate'), [
            'serviceType' => 'Rank Boosting',
            'currentDivision' => 'Gold II',
            'desiredDivision' => 'Platinum II',
            'currentRR' => 55,
            'avgRRPerWin' => '18 OR LOWER',
            'region' => 'NA',
            'platform' => 'PC',
            'boostMode' => 'Account Shared',
            'selectedAddons' => ['Specific Agents'],
            'specificAgents' => [],
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('validationErrors.specificAgents.0', 'Select at least 3 specific agents.');
    }

    public function test_calculate_price_requires_at_least_three_specific_agents(): void
    {
        $response = $this->postJson(route('pricing.calculate'), [
            'serviceType' => 'Rank Boosting',
            'currentDivision' => 'Gold II',
            'desiredDivision' => 'Platinum II',
            'currentRR' => 55,
            'avgRRPerWin' => '18 OR LOWER',
            'region' => 'NA',
            'platform' => 'PC',
            'boostMode' => 'Account Shared',
            'selectedAddons' => ['Specific Agents'],
            'specificAgents' => $this->agentUuids(2),
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('validationErrors.specificAgents.0', 'Select at least 3 specific agents.');
    }

    public function test_calculate_price_rejects_invalid_specific_agent_uuids(): void
    {
        $response = $this->postJson(route('pricing.calculate'), [
            'serviceType' => 'Rank Boosting',
            'currentDivision' => 'Gold II',
            'desiredDivision' => 'Platinum II',
            'currentRR' => 55,
            'avgRRPerWin' => '18 OR LOWER',
            'region' => 'NA',
            'platform' => 'PC',
            'boostMode' => 'Account Shared',
            'selectedAddons' => ['Specific Agents'],
            'specificAgents' => ['not-a-real-agent'],
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('validationErrors.specificAgents.0', 'Specific agent selections must use supported Valorant agents.');
    }

    public function test_calculate_price_rejects_specific_agents_without_the_matching_addon(): void
    {
        $response = $this->postJson(route('pricing.calculate'), [
            'serviceType' => 'Rank Boosting',
            'currentDivision' => 'Gold II',
            'desiredDivision' => 'Platinum II',
            'currentRR' => 55,
            'avgRRPerWin' => '18 OR LOWER',
            'region' => 'NA',
            'platform' => 'PC',
            'boostMode' => 'Account Shared',
            'selectedAddons' => ['Offline Mode'],
            'specificAgents' => [config('valorant_agents.0.uuid')],
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('validationErrors.specificAgents.0', 'Specific agent selections require the Specific Agents addon.');
    }

    public function test_calculate_price_accepts_one_trick_agent_with_exactly_one_selection(): void
    {
        $response = $this->postJson(route('pricing.calculate'), [
            'serviceType' => 'Rank Boosting',
            'currentDivision' => 'Gold II',
            'desiredDivision' => 'Platinum II',
            'currentRR' => 55,
            'avgRRPerWin' => '18 OR LOWER',
            'region' => 'NA',
            'platform' => 'PC',
            'boostMode' => 'Account Shared',
            'selectedAddons' => ['One-Trick Agent'],
            'oneTrickAgent' => [$this->agentUuids(1)[0]],
        ]);

        $response->assertOk()
            ->assertJsonPath('pricing.addons', 4.55)
            ->assertJsonPath('finalPrice', 30.03)
            ->assertJsonPath('oneTrickAgent.0', $this->agentUuids(1)[0])
            ->assertJsonPath('validationErrors', []);
    }

    public function test_calculate_price_rejects_specific_agents_and_one_trick_agent_together(): void
    {
        $response = $this->postJson(route('pricing.calculate'), [
            'serviceType' => 'Rank Boosting',
            'currentDivision' => 'Gold II',
            'desiredDivision' => 'Platinum II',
            'currentRR' => 55,
            'avgRRPerWin' => '18 OR LOWER',
            'region' => 'NA',
            'platform' => 'PC',
            'boostMode' => 'Account Shared',
            'selectedAddons' => ['Specific Agents', 'One-Trick Agent'],
            'specificAgents' => $this->agentUuids(3),
            'oneTrickAgent' => [$this->agentUuids(1)[0]],
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('validationErrors.selectedAddons.0', 'Specific Agents and One-Trick Agent cannot be selected together.');
    }

    public function test_calculate_price_rejects_solo_queue_only_with_no_five_stack(): void
    {
        $response = $this->postJson(route('pricing.calculate'), [
            'serviceType' => 'Rank Boosting',
            'currentDivision' => 'Gold II',
            'desiredDivision' => 'Platinum II',
            'currentRR' => 55,
            'avgRRPerWin' => '18 OR LOWER',
            'region' => 'NA',
            'platform' => 'PC',
            'boostMode' => 'Account Shared',
            'selectedAddons' => ['Solo-Queue Only', 'No 5-Stack'],
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('validationErrors.selectedAddons.0', 'Solo-Queue Only and No 5-Stack cannot be selected together.');
    }

    public function test_calculate_price_rejects_self_play_at_immortal_one_current_rank(): void
    {
        $response = $this->postJson(route('pricing.calculate'), [
            'serviceType' => 'Ranked Wins',
            'currentDivision' => 'Immortal I',
            'numberOfWins' => 2,
            'region' => 'NA',
            'platform' => 'PC',
            'boostMode' => 'Self-Play',
            'selectedAddons' => ['Express Order'],
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('validationErrors.boostMode.0', 'Duo / Self-Play is unavailable from Immortal 1 onward.');
    }

    public function test_calculate_price_rejects_self_play_above_immortal_one_target_rank(): void
    {
        $response = $this->postJson(route('pricing.calculate'), [
            'serviceType' => 'Rank Boosting',
            'currentDivision' => 'Ascendant III',
            'desiredDivision' => 'Immortal II',
            'currentRR' => 55,
            'avgRRPerWin' => '18 OR LOWER',
            'region' => 'NA',
            'platform' => 'PC',
            'boostMode' => 'Self-Play',
            'selectedAddons' => ['Express Order'],
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('validationErrors.boostMode.0', 'Duo / Self-Play is only available through Immortal 1.');
    }

    public function test_calculate_price_requires_one_trick_agent_when_the_addon_is_selected(): void
    {
        $response = $this->postJson(route('pricing.calculate'), [
            'serviceType' => 'Rank Boosting',
            'currentDivision' => 'Gold II',
            'desiredDivision' => 'Platinum II',
            'currentRR' => 55,
            'avgRRPerWin' => '18 OR LOWER',
            'region' => 'NA',
            'platform' => 'PC',
            'boostMode' => 'Account Shared',
            'selectedAddons' => ['One-Trick Agent'],
            'oneTrickAgent' => [],
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('validationErrors.oneTrickAgent.0', 'Select exactly 1 one-trick agent.');
    }

    public function test_calculate_price_rejects_multiple_one_trick_agents(): void
    {
        $response = $this->postJson(route('pricing.calculate'), [
            'serviceType' => 'Rank Boosting',
            'currentDivision' => 'Gold II',
            'desiredDivision' => 'Platinum II',
            'currentRR' => 55,
            'avgRRPerWin' => '18 OR LOWER',
            'region' => 'NA',
            'platform' => 'PC',
            'boostMode' => 'Account Shared',
            'selectedAddons' => ['One-Trick Agent'],
            'oneTrickAgent' => $this->agentUuids(2),
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('validationErrors.oneTrickAgent.0', 'Select exactly 1 one-trick agent.');
    }

    public function test_calculate_price_route_survives_a_normal_rapid_burst(): void
    {
        $payload = [
            'serviceType' => 'Rank Boosting',
            'currentDivision' => 'Gold II',
            'desiredDivision' => 'Platinum II',
            'currentRR' => 55,
            'avgRRPerWin' => '18 OR LOWER',
            'region' => 'NA',
            'platform' => 'PC',
            'boostMode' => 'Account Shared',
            'selectedAddons' => ['Offline Mode'],
        ];

        for ($attempt = 1; $attempt <= 50; $attempt++) {
            $this->postJson(route('pricing.calculate'), $payload)
                ->assertOk()
                ->assertJsonPath('validationErrors', []);
        }
    }

    protected function agentUuids(int $count): array
    {
        return collect(config('valorant_agents', []))
            ->pluck('uuid')
            ->filter()
            ->take($count)
            ->values()
            ->all();
    }
}
