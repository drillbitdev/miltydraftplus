<?php

declare(strict_types=1);

namespace App\Draft\Commands;

use App\Draft\Exceptions\InvalidDraftSettingsException;
use App\Draft\Settings;
use App\Shared\Command;
use App\TwilightImperium\Faction;

/**
 * Generates a pool of draftable factions based on settings
 */
class GenerateFactionPool implements Command
{
    public function __construct(
        private readonly Settings $settings,
    ) {
    }

    /**
     * @return array<Faction>
     */
    public function handle(): array
    {
        $this->settings->seed->setForFactions();
        $factionsFromSets = $this->gatherFactionsFromSelectedSets();

        $gatheredFactions = [];

        if (! empty($this->settings->customFactions)) {
            foreach ($this->settings->customFactions as $f) {
                $gatheredFactions[] = $factionsFromSets[$f];
                // take out the selected faction, so it doesn't get re-drawn in the next part
                unset($factionsFromSets[$f]);
            }

            $factionsStillToGather = $this->settings->numberOfFactions - count($gatheredFactions);
            if ($factionsStillToGather > 0) {
                shuffle($factionsFromSets);
                $gatheredFactions = array_merge($gatheredFactions, array_slice($factionsFromSets, 0, $factionsStillToGather));
            }
        } else {
            $gatheredFactions = $factionsFromSets;
        }

        shuffle($gatheredFactions);

        $pool = array_slice($gatheredFactions, 0, $this->settings->numberOfFactions);

        if ($this->settings->minorFactions) {
            $pool = $this->avoidKeleresConflict($pool);
        }

        return $pool;
    }

    /**
     * @return array<string, Faction>
     */
    public static function factionsFromSelectedSets(Settings $settings): array
    {
        return array_filter(
            Faction::all(),
            fn (Faction $faction) =>
                in_array($faction->edition, $settings->factionSets) ||
                $faction->name == 'The Council Keleres' && $settings->includeCouncilKeleresFaction,
        );
    }

    /**
     * With the Minor Factions event, the factions in play can't include all of Keleres and the factions it takes
     * its home system from. If this pool already has all four, swap one out so the minor factions aren't stuck.
     *
     * @param array<Faction> $pool
     * @return array<Faction>
     */
    private function avoidKeleresConflict(array $pool): array
    {
        $names = array_map(fn (Faction $f) => $f->name, $pool);

        if (! GenerateMinorFactionPool::includesKeleresConflict($names)) {
            return $pool;
        }

        $replaceable = array_diff(GenerateMinorFactionPool::KELERES_CONFLICT, $this->settings->customFactions);
        if (empty($replaceable)) {
            throw InvalidDraftSettingsException::customFactionsIncludeKeleresConflict();
        }

        $replacements = array_filter(
            self::factionsFromSelectedSets($this->settings),
            fn (Faction $f) => ! in_array($f->name, $names) && ! in_array($f->name, GenerateMinorFactionPool::KELERES_CONFLICT),
        );
        if (empty($replacements)) {
            throw InvalidDraftSettingsException::customFactionsIncludeKeleresConflict();
        }

        $pool[array_search(reset($replaceable), $names)] = $replacements[array_rand($replacements)];

        return $pool;
    }

    private function gatherFactionsFromSelectedSets(): array
    {
        return self::factionsFromSelectedSets($this->settings);
    }
}