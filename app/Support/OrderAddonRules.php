<?php

namespace App\Support;

class OrderAddonRules
{
    public const SELF_PLAY_TARGET_MESSAGE = 'Duo / Self-Play is only available through Immortal 1.';

    public const SELF_PLAY_CURRENT_RANK_MESSAGE = 'Duo / Self-Play is unavailable from Immortal 1 onward.';

    public const SELF_PLAY_ADDON_MESSAGE = 'Duo / Self-Play only allows Bonus Win and Express Order.';

    public const SPECIFIC_VS_ONE_TRICK_MESSAGE = 'Specific Agents and One-Trick Agent cannot be selected together.';

    public const SOLO_VS_NO_FIVE_STACK_MESSAGE = 'Solo-Queue Only and No 5-Stack cannot be selected together.';

    public static function frontendConfig(): array
    {
        return [
            'selfPlayAllowedAddons' => BoostingCatalog::selfPlayAllowedAddonLabels(),
            'selfPlayDisabledAddons' => BoostingCatalog::selfPlayDisabledAddonLabels(),
            'selfPlayCurrentRankRestrictedServices' => [
                'Rank Boosting',
                'Radiant Boost',
                'Ranked Wins',
            ],
            'selfPlayTargetRankRestrictedServices' => [
                'Rank Boosting',
                'Radiant Boost',
            ],
            'messages' => [
                'selfPlayTargetRank' => self::SELF_PLAY_TARGET_MESSAGE,
                'selfPlayCurrentRank' => self::SELF_PLAY_CURRENT_RANK_MESSAGE,
                'selfPlayAddons' => self::SELF_PLAY_ADDON_MESSAGE,
                'specificVsOneTrick' => self::SPECIFIC_VS_ONE_TRICK_MESSAGE,
                'soloVsNoFiveStack' => self::SOLO_VS_NO_FIVE_STACK_MESSAGE,
            ],
            'labels' => [
                'accountShared' => BoostingCatalog::accountSharedBoostModeLabel(),
                'selfPlay' => BoostingCatalog::selfPlayBoostModeLabel(),
                'specificAgents' => BoostingCatalog::specificAgentsAddonLabel(),
                'oneTrickAgent' => BoostingCatalog::oneTrickAgentAddonLabel(),
                'soloQueueOnly' => BoostingCatalog::soloQueueOnlyAddonLabel(),
                'noFiveStack' => BoostingCatalog::noFiveStackAddonLabel(),
                'bonusWin' => BoostingCatalog::bonusWinAddonLabel(),
                'expressOrder' => BoostingCatalog::expressOrderAddonLabel(),
            ],
            'rankThresholds' => [
                'currentRankMin' => 'Immortal I',
                'targetRankMin' => 'Immortal II',
            ],
        ];
    }

    public static function evaluate(array $payload): array
    {
        $serviceType = BoostingCatalog::normalizeServiceType($payload['serviceType'] ?? $payload['orderType'] ?? null);
        $boostMode = BoostingCatalog::normalizeBoostModeLabel(
            $payload['boostMode'] ?? $payload['accountType'] ?? $payload['playType'] ?? null
        );
        $addons = BoostingCatalog::normalizeAddons($payload['selectedAddons'] ?? $payload['addons'] ?? []);
        $currentRank = BoostingCatalog::canonicalRankLabel(
            $payload['currentDivision'] ?? $payload['currentRank'] ?? $payload['current_rank'] ?? null
        );
        $targetRank = $serviceType === 'Radiant Boost'
            ? 'Radiant'
            : BoostingCatalog::canonicalRankLabel(
                $payload['targetDivision']
                ?? $payload['desiredDivision']
                ?? $payload['desired_division']
                ?? $payload['targetRank']
                ?? $payload['desiredRank']
                ?? $payload['target_rank']
                ?? null
            );

        $disabledAddons = [];
        $disabledAddonReasons = [];
        $errors = [];

        if ($boostMode === BoostingCatalog::selfPlayBoostModeLabel()) {
            foreach (BoostingCatalog::selfPlayDisabledAddonLabels() as $addonLabel) {
                $disabledAddons[] = $addonLabel;
                $disabledAddonReasons[$addonLabel] = 'Unavailable for Duo / Self-Play.';
            }

            $invalidSelfPlayAddons = array_values(array_intersect($addons, BoostingCatalog::selfPlayDisabledAddonLabels()));
            if ($invalidSelfPlayAddons !== []) {
                $errors['selectedAddons'][] = self::SELF_PLAY_ADDON_MESSAGE;
            }
        }

        $specificAgents = BoostingCatalog::specificAgentsAddonLabel();
        $oneTrickAgent = BoostingCatalog::oneTrickAgentAddonLabel();
        $soloQueueOnly = BoostingCatalog::soloQueueOnlyAddonLabel();
        $noFiveStack = BoostingCatalog::noFiveStackAddonLabel();

        if (in_array($specificAgents, $addons, true)) {
            $disabledAddons[] = $oneTrickAgent;
            $disabledAddonReasons[$oneTrickAgent] = 'Unavailable while Specific Agents is selected.';
        }

        if (in_array($oneTrickAgent, $addons, true)) {
            $disabledAddons[] = $specificAgents;
            $disabledAddonReasons[$specificAgents] = 'Unavailable while One-Trick Agent is selected.';
        }

        if (in_array($specificAgents, $addons, true) && in_array($oneTrickAgent, $addons, true)) {
            $errors['selectedAddons'][] = self::SPECIFIC_VS_ONE_TRICK_MESSAGE;
        }

        if (in_array($soloQueueOnly, $addons, true)) {
            $disabledAddons[] = $noFiveStack;
            $disabledAddonReasons[$noFiveStack] = 'Unavailable while Solo-Queue Only is selected.';
        }

        if (in_array($soloQueueOnly, $addons, true) && in_array($noFiveStack, $addons, true)) {
            $errors['selectedAddons'][] = self::SOLO_VS_NO_FIVE_STACK_MESSAGE;
        }

        $selfPlayDisabledByCurrentRank = self::serviceHasSelfPlayRankRestriction($serviceType)
            && BoostingCatalog::rankAtOrAbove($currentRank, 'Immortal I');
        $selfPlayDisabledByTargetRank = self::serviceHasSelfPlayTargetRestriction($serviceType)
            && BoostingCatalog::rankAtOrAbove($targetRank, 'Immortal II');
        $selfPlayUnavailableMessage = $selfPlayDisabledByTargetRank
            ? self::SELF_PLAY_TARGET_MESSAGE
            : ($selfPlayDisabledByCurrentRank ? self::SELF_PLAY_CURRENT_RANK_MESSAGE : null);

        if ($boostMode === BoostingCatalog::selfPlayBoostModeLabel() && $selfPlayUnavailableMessage) {
            $errors['boostMode'][] = $selfPlayUnavailableMessage;
        }

        return [
            'serviceType' => $serviceType,
            'boostMode' => $boostMode,
            'currentRank' => $currentRank,
            'targetRank' => $targetRank,
            'selectedAddons' => $addons,
            'disabledAddons' => BoostingCatalog::normalizeAddons($disabledAddons),
            'disabledAddonReasons' => $disabledAddonReasons,
            'selfPlayUnavailable' => $selfPlayUnavailableMessage !== null,
            'selfPlayDisabledByCurrentRank' => $selfPlayDisabledByCurrentRank,
            'selfPlayDisabledByTargetRank' => $selfPlayDisabledByTargetRank,
            'selfPlayUnavailableMessage' => $selfPlayUnavailableMessage,
            'validationErrors' => self::uniqueValidationErrors($errors),
        ];
    }

    public static function stripInactiveSelections(array $payload): array
    {
        $evaluation = self::evaluate($payload);
        $addons = BoostingCatalog::normalizeAddons($payload['addons'] ?? $payload['selectedAddons'] ?? []);
        $disabledAddons = $evaluation['disabledAddons'] ?? [];

        $payload['specificAgents'] = BoostingCatalog::normalizeSpecificAgents($payload['specificAgents'] ?? $payload['specific_agents'] ?? []);
        $payload['oneTrickAgent'] = BoostingCatalog::normalizeOneTrickAgent($payload['oneTrickAgent'] ?? $payload['one_trick_agent'] ?? []);

        if (! in_array(BoostingCatalog::specificAgentsAddonLabel(), $addons, true)
            || in_array(BoostingCatalog::specificAgentsAddonLabel(), $disabledAddons, true)) {
            $payload['specificAgents'] = [];
        }

        if (! in_array(BoostingCatalog::oneTrickAgentAddonLabel(), $addons, true)
            || in_array(BoostingCatalog::oneTrickAgentAddonLabel(), $disabledAddons, true)) {
            $payload['oneTrickAgent'] = [];
        }

        unset($payload['specific_agents']);
        unset($payload['one_trick_agent']);

        return $payload;
    }

    public static function uniqueValidationErrors(array $errors): array
    {
        return collect($errors)
            ->map(fn ($messages) => array_values(array_unique(array_filter((array) $messages))))
            ->filter(fn ($messages) => $messages !== [])
            ->toArray();
    }

    protected static function serviceHasSelfPlayRankRestriction(?string $serviceType): bool
    {
        return in_array($serviceType, ['Rank Boosting', 'Radiant Boost', 'Ranked Wins'], true);
    }

    protected static function serviceHasSelfPlayTargetRestriction(?string $serviceType): bool
    {
        return in_array($serviceType, ['Rank Boosting', 'Radiant Boost'], true);
    }
}
