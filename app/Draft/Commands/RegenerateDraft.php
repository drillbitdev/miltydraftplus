<?php

declare(strict_types=1);

namespace App\Draft\Commands;

use App\Draft\Draft;
use App\Draft\Seed;
use App\Shared\Command;

class RegenerateDraft implements Command
{
    public function __construct(
        public Draft $draft,
        public bool $regenerateSlices,
        public bool $regenerateFactions,
        public bool $regenerateOrder,
    ) {

    }

    public function handle(): Draft
    {
        if (count($this->draft->log) > 0) {
            throw new \Exception('Cannot regenerate ongoing draft');
        }

        // generate new seed to use for the reshuffle
        $seed = new Seed();

        if ($this->regenerateOrder) {
            $seed->setForPlayerOrder();
            $order = array_keys($this->draft->players);
            shuffle($order);
            $newPlayers = [];
            foreach ($order as $key) {
                $newPlayers[$key] = $this->draft->players[$key];
            }
            $this->draft->players = $newPlayers;
            $this->draft->updateCurrentPlayer();
        }

        if ($this->regenerateSlices) {
            $slices = (new GenerateSlicePool($this->draft->settings->withNewSeed($seed)))->handle();
            $this->draft->slicePool = $slices;
        }

        if ($this->regenerateFactions) {
            $factions = (new GenerateFactionPool($this->draft->settings->withNewSeed($seed)))->handle();
            $this->draft->factionPool = $factions;
            // minor factions can't overlap with the new faction pool, so they're redrawn too
            $minorFactionGenerator = new GenerateMinorFactionPool($this->draft->settings->withNewSeed($seed), $factions);
            $this->draft->minorFactionPool = $minorFactionGenerator->handle();
            $this->draft->minorFactionHomeSystems = $minorFactionGenerator->homeSystems($this->draft->minorFactionPool);
            $this->draft->assignRandomMinorFactions();
        }

        app()->repository->save($this->draft);

        return $this->draft;
    }
}