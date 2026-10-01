<?php

declare(strict_types=1);

namespace App\Draft;

class TilePool
{
    public function __construct(
        /** @var array<string> $highTier */
        public array $highTier,
        /** @var array<string> $midTier */
        public array $midTier,
        /** @var array<string> $lowTier */
        public array $lowTier,
        /** @var array<string> $redTier */
        public array $redTier,
    ) {
    }

    public function shuffle(): void
    {
        shuffle($this->highTier);
        shuffle($this->midTier);
        shuffle($this->lowTier);
        shuffle($this->redTier);
    }

    public function slice(int $numberOfSlices): TilePool
    {
        return new TilePool(
            array_slice($this->highTier, 0, $numberOfSlices),
            array_slice($this->midTier, 0, $numberOfSlices),
            array_slice($this->lowTier, 0, $numberOfSlices),
            array_slice($this->redTier, 0, $numberOfSlices * 2),
        );
    }

    /**
     * With minor factions slices only get 2 blue tiles: either a high and a low tier tile,
     * or two mid tier tiles.
     */
    public function sliceForMinorFactions(int $highLowSlices, int $midMidSlices): TilePool
    {
        return new TilePool(
            array_slice($this->highTier, 0, $highLowSlices),
            array_slice($this->midTier, 0, $midMidSlices * 2),
            array_slice($this->lowTier, 0, $highLowSlices),
            array_slice($this->redTier, 0, ($highLowSlices + $midMidSlices) * 2),
        );
    }

    /**
     * @return array<string>
     */
    public function allIds(): array
    {
        return array_merge($this->highTier, $this->midTier, $this->lowTier, $this->redTier);
    }
}