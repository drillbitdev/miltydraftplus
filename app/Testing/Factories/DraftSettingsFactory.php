<?php

declare(strict_types=1);

namespace App\Testing\Factories;

use App\Draft\Name;
use App\Draft\Seed;
use App\Draft\Settings;
use App\TwilightImperium\AllianceTeamMode;
use App\TwilightImperium\AllianceTeamPosition;
use App\TwilightImperium\Edition;
use App\TwilightImperium\MinorFactionsMode;
use Faker\Factory;

class DraftSettingsFactory
{
    public static function make(array $properties = []): Settings
    {
        $faker = Factory::create();

        if (isset($properties['numberOfPlayers'])) {
            $numberOfPlayers = $properties['numberOfPlayers'];
        } elseif (isset($properties['playerNames'])) {
            $numberOfPlayers = count($properties['playerNames']);
        } else {
            $numberOfPlayers = 6;
        }

        $names = $properties['playerNames'] ?? array_map(fn () => $faker->name(), range(1, $numberOfPlayers));

        $allianceMode = $properties['allianceMode'] ?? false;
        $minorFactions = $properties['minorFactions'] ?? false;
        $minorFactionsMode = $minorFactions ? $properties['minorFactionsMode'] ?? MinorFactionsMode::DRAFT : null;

        return new Settings(
            $names,
            $properties['presetDraftOrder'] ?? $faker->boolean(),
            new Name($properties['name'] ?? null),
            new Seed($properties['seed'] ?? null),
            $properties['numberOfSlices'] ?? $numberOfPlayers + 2,
            $properties['numberOfFactions'] ?? $numberOfPlayers + 2,
            $properties['tileSets'] ?? [
                Edition::BASE_GAME,
                Edition::PROPHECY_OF_KINGS,
                Edition::THUNDERS_EDGE,
            ],
            $properties['factionSets'] ?? [
                Edition::BASE_GAME,
                Edition::PROPHECY_OF_KINGS,
                Edition::THUNDERS_EDGE,
            ],
            $properties['includeCouncilKeleresFaction'] ?? false,
            $properties['minimumTwoAlphaBetaWormholes'] ?? $faker->boolean(),
            $properties['maxOneWormholePerSlice'] ?? $faker->boolean(),
            $properties['minimumLegendaryPlanets'] ?? $faker->numberBetween(0, 1),
            // minor faction slices have one less tile, so they get the lower defaults from the generate form
            $properties['minimumOptimalInfluence'] ?? ($minorFactions ? 2.5 : 4),
            $properties['minimumOptimalResources'] ?? ($minorFactions ? 2 : 2.5),
            $properties['minimumOptimalTotal'] ?? ($minorFactions ? 6 : 9),
            $properties['maximumOptimalTotal'] ?? ($minorFactions ? 10 : 13),
            $properties['customFactions'] ?? [],
            $properties['customSlices'] ?? [],
            $allianceMode,
            $allianceMode ? $properties['allianceTeamMode'] ?? AllianceTeamMode::RANDOM : null,
            $allianceMode ? $properties['allianceTeamPosition'] ?? AllianceTeamPosition::OPPOSITES : null,
            $allianceMode ? $properties['allianceForceDoublePicks'] ?? false : null,
            $minorFactions,
            $minorFactionsMode,
            $minorFactionsMode == MinorFactionsMode::DRAFT ? $properties['numberOfMinorFactions'] ?? $numberOfPlayers + 2 : null,
        );
    }
}