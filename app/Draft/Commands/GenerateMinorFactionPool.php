<?php

declare(strict_types=1);

namespace App\Draft\Commands;

use App\Draft\Exceptions\InvalidDraftSettingsException;
use App\Draft\Settings;
use App\Shared\Command;
use App\TwilightImperium\Faction;

/**
 * Generates the minor factions for the Minor Factions event: the ones up for drafting,
 * or one per player when they're randomly assigned.
 *
 * Minor factions come from the selected faction sets, but never overlap with the player faction pool.
 */
class GenerateMinorFactionPool implements Command
{
    /**
     * Keleres takes its home system from one of the other three,
     * so the player factions and minor factions together can't include all four
     */
    public const KELERES = 'The Council Keleres';

    public const KELERES_CONFLICT = [
        'The Argent Flight',
        self::KELERES,
        'The Mentak Coalition',
        'The Xxcha Kingdom',
    ];

    public function __construct(
        private readonly Settings $settings,
        /** @var array<Faction> $factionPool */
        private readonly array $factionPool,
    ) {
    }

    /**
     * @return array<Faction>
     */
    public function handle(): array
    {
        if (! $this->settings->minorFactions) {
            return [];
        }

        $this->settings->seed->setForMinorFactions();

        $inPlay = array_map(fn (Faction $f) => $f->name, $this->factionPool);
        $candidates = array_values(array_filter(
            GenerateFactionPool::factionsFromSelectedSets($this->settings),
            fn (Faction $f) => ! in_array($f->name, $inPlay),
        ));
        shuffle($candidates);

        $poolSize = $this->settings->minorFactionPoolSize();
        $pool = [];
        foreach ($candidates as $faction) {
            if (count($pool) == $poolSize) {
                break;
            }

            if (self::includesKeleresConflict([...$inPlay, $faction->name])) {
                continue;
            }

            $pool[] = $faction;
            $inPlay[] = $faction->name;
        }

        if (count($pool) < $poolSize) {
            throw InvalidDraftSettingsException::notEnoughFactionsForMinorFactions();
        }

        return $pool;
    }

    /**
     * Keleres has no home system of its own: as a minor faction it takes a random one from the Argent, Mentak or Xxcha,
     * excluding any of those that are in play. Other minor factions use their own (for Creuss that's the gate, tile 17,
     * and for the Rebellion it's Sorrow, tile 94, which is what the faction data has).
     * Call this after handle(), so it's covered by the same seed.
     *
     * @param array<Faction> $minorFactionPool
     * @return array<string, string> faction name => tile
     */
    public function homeSystems(array $minorFactionPool): array
    {
        $homeSystems = [];

        $inPlay = array_map(fn (Faction $f) => $f->name, [...$this->factionPool, ...$minorFactionPool]);
        if (in_array(self::KELERES, array_map(fn (Faction $f) => $f->name, $minorFactionPool))) {
            $available = array_values(array_diff(self::KELERES_CONFLICT, [self::KELERES], $inPlay));
            // the pool never contains all four, so there's always one left
            $homeSystems[self::KELERES] = Faction::all()[$available[mt_rand(0, count($available) - 1)]]->homesystem();
        }

        return $homeSystems;
    }

    /**
     * @param array<string> $factionNames
     */
    public static function includesKeleresConflict(array $factionNames): bool
    {
        return empty(array_diff(self::KELERES_CONFLICT, $factionNames));
    }
}
