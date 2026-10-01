<?php

declare(strict_types=1);

namespace App\Draft\Commands;

use App\Draft\Exceptions\InvalidDraftSettingsException;
use App\Draft\Settings;
use App\Draft\Slice;
use App\Draft\TilePool;
use App\Shared\Command;
use App\TwilightImperium\Tile;
use App\TwilightImperium\TileTier;
use App\TwilightImperium\Wormhole;

class GenerateSlicePool implements Command
{
    const MAX_TILE_SELECTION_TRIES = 100;
    const MAX_SLICES_FROM_SELECTION_TRIES = 400;

    /**
     * @var array<string, Tile>
     */
    private readonly array $tileData;

    /** @var array<Tile> */
    private readonly array $allGatheredTiles;
    private readonly TilePool $gatheredTiles;

    public int $tries;

    public function __construct(
        private readonly Settings $settings,
    ) {
        $this->tileData = Tile::all();

        // make pre-selection based on tile sets
        $this->allGatheredTiles = array_filter(
            $this->tileData,
            fn (Tile $tile) =>
                in_array($tile->edition, $this->settings->tileSets) &&
                // tier none is mec rex and such...
                $tile->tier != TileTier::NONE,
        );

        // sort pre-selected tiles in tiers
        $highTier = [];
        $midTier = [];
        $lowTier = [];
        $redTier = [];

        foreach($this->allGatheredTiles as $tile) {
            switch($tile->tier) {
                case TileTier::HIGH:
                    $highTier[] = $tile->id;

                    break;
                case TileTier::MEDIUM:
                    $midTier[] = $tile->id;

                    break;
                case TileTier::LOW:
                    $lowTier[] = $tile->id;

                    break;
                case TileTier::RED:
                    $redTier[] = $tile->id;

                    break;
            };
        }

        $this->gatheredTiles = new TilePool(
            $highTier,
            $midTier,
            $lowTier,
            $redTier,
        );
    }

    /** @return array<Slice> */
    public function handle(): array
    {
        if (! empty($this->settings->customSlices)) {
            return $this->slicesFromCustomSlices();
        } else {
            return $this->attemptToGenerate();
        }
    }

    private function attemptToGenerate($previousTries = 0): array
    {
        $slices = [];

        if ($previousTries > self::MAX_TILE_SELECTION_TRIES) {
            throw InvalidDraftSettingsException::cannotGenerateSlices();
        }

        $this->settings->seed->setForSlices($previousTries);
        $this->gatheredTiles->shuffle();
        $tilePool = $this->settings->minorFactions ?
            $this->minorFactionsTilePool() :
            $this->gatheredTiles->slice($this->settings->numberOfSlices);

        $tilePoolIsValid = $this->validateTileSelection($tilePool->allIds());

        if (! $tilePoolIsValid) {
            return $this->attemptToGenerate($previousTries + 1);
        }

        $validSlicesFromPool = $this->makeSlicesFromPool($tilePool);
        if (empty($validSlicesFromPool)) {
            unset($validSlicesFromPool);

            return $this->attemptToGenerate($previousTries + 1);
        } else {
            return $validSlicesFromPool;
        }
    }

    private function makeSlicesFromPool(TilePool $pool, $previousTries = 0): array
    {
        if ($previousTries > self::MAX_SLICES_FROM_SELECTION_TRIES) {
            return [];
        }

        $this->settings->seed->setForSlices($previousTries);
        $pool->shuffle();

        $slices = [];

        for ($i = 0; $i < $this->settings->numberOfSlices; $i++) {
            $slice = new Slice(
                $this->settings->minorFactions ?
                    $this->minorFactionsSliceTiles($pool, $i) :
                    [
                        $this->tileData[$pool->highTier[$i]],
                        $this->tileData[$pool->midTier[$i]],
                        $this->tileData[$pool->lowTier[$i]],
                        $this->tileData[$pool->redTier[$i * 2]],
                        $this->tileData[$pool->redTier[($i * 2) + 1]],
                    ],
            );

            $sliceIsValid = $slice->validate(
                $this->settings->minimumOptimalInfluence,
                $this->settings->minimumOptimalResources,
                $this->settings->minimumOptimalTotal,
                $this->settings->maximumOptimalTotal,
                $this->settings->maxOneWormholesPerSlice,
            );

            if (! $sliceIsValid) {
                unset($slice);
                unset($slices);

                return $this->makeSlicesFromPool($pool, $previousTries + 1);
            }

            if(! $slice->arrange($this->settings->seed)) {
                unset($slice);
                unset($slices);

                return $this->makeSlicesFromPool($pool, $previousTries);
            }

            $slices[] = $slice;
        }

        if ($this->settings->minorFactions) {
            // high+low and mid+mid slices are generated in order, mix them up
            $this->settings->seed->setForSlices($previousTries);
            shuffle($slices);
        }

        return $slices;
    }

    /**
     * With minor factions, roughly half the slices get a high and a low tier tile, the other half two mid tier tiles.
     * That uses each tier at about the same rate, so no tier is left out of the draft entirely.
     */
    private function minorFactionsTilePool(): TilePool
    {
        $numberOfSlices = $this->settings->numberOfSlices;
        $pool = $this->gatheredTiles;

        // odd number of slices: randomly pick which kind gets the extra slice
        $highLowSlices = intdiv($numberOfSlices, 2) + ($numberOfSlices % 2 == 1 ? mt_rand(0, 1) : 0);

        // stay within what the tile sets can provide
        $highLowSlices = min($highLowSlices, count($pool->highTier), count($pool->lowTier));
        $highLowSlices = max($highLowSlices, $numberOfSlices - intdiv(count($pool->midTier), 2));

        return $pool->sliceForMinorFactions($highLowSlices, $numberOfSlices - $highLowSlices);
    }

    /**
     * The first slices in the pool are high+low, the rest are mid+mid
     *
     * @return array<Tile>
     */
    private function minorFactionsSliceTiles(TilePool $pool, int $i): array
    {
        $highLowSlices = count($pool->highTier);
        $blueTiles = $i < $highLowSlices ?
            [$pool->highTier[$i], $pool->lowTier[$i]] :
            [$pool->midTier[($i - $highLowSlices) * 2], $pool->midTier[(($i - $highLowSlices) * 2) + 1]];

        return [
            $this->tileData[$blueTiles[0]],
            $this->tileData[$blueTiles[1]],
            $this->tileData[$pool->redTier[$i * 2]],
            $this->tileData[$pool->redTier[($i * 2) + 1]],
        ];
    }

    /**
     * @param array $tileIds
     * @return bool
     */
    private function validateTileSelection(array $tileIds): bool
    {
        $tileInfo = array_map(fn (string $id) => $this->tileData[$id], $tileIds);

        $alphaWormholeCount = 0;
        $betaWormholeCount = 0;
        $legendaryPlanetCount = 0;

        foreach($tileInfo as $t) {
            if ($t->hasWormhole(Wormhole::ALPHA)) {
                $alphaWormholeCount++;
            }
            if ($t->hasWormhole(Wormhole::BETA)) {
                $betaWormholeCount++;
            }
            if ($t->hasLegendaryPlanet()) {
                $legendaryPlanetCount++;
            }
        }

        if ($legendaryPlanetCount < $this->settings->minimumLegendaryPlanets) {
            return false;
        }

        if (
            $this->settings->minimumTwoAlphaAndBetaWormholes &&
            ($alphaWormholeCount < 2 || $betaWormholeCount < 2)
        ) {
            return false;
        }

        return true;
    }

    /**
     * @return array<Slice>
     */
    private function slicesFromCustomSlices(): array
    {
        return array_map(function (array $sliceData) {
            $tileData = [];
            foreach ($sliceData as $tileId) {
                if (! isset($this->tileData[$tileId])) {
                    throw InvalidDraftSettingsException::unknownTileInCustomSlice($tileId);
                }
                $tileData[] = $this->tileData[$tileId];
            }

            return new Slice($tileData);
        }, $this->settings->customSlices);
    }

    /**
     * Debug and test methods
     */
    public function gatheredTiles(): array
    {
        return $this->allGatheredTiles;
    }

    public function gatheredTileTiers(): TilePool
    {
        return $this->gatheredTiles;
    }
}