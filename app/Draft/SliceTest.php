<?php

declare(strict_types=1);

namespace App\Draft;

use App\Testing\Factories\PlanetFactory;
use App\Testing\Factories\TileFactory;
use App\Testing\TestCase;
use App\TwilightImperium\Planet;
use App\TwilightImperium\Wormhole;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class SliceTest extends TestCase
{
    #[Test]
    public function itCalculatesTotalAndOptimalValues(): void
    {
        $planets = [
            PlanetFactory::make([
                'resources' => 4,
                'influence' => 2,
            ]),  // optimal: 4, 0
            PlanetFactory::make([
                'resources' => 3,
                'influence' => 3,
            ]), // optimal: 1.5, 1.5
            PlanetFactory::make([
                'resources' => 1,
                'influence' => 0,
            ]), // optimal: 1, 0
            PlanetFactory::make([
                'resources' => 1,
                'influence' => 2,
            ]), // optimal: 0, 2
        ];

        $totalInfluence = array_reduce($planets, fn ($sum, Planet $p) => $sum += $p->influence);
        $totalResources = array_reduce($planets, fn ($sum, Planet $p) => $sum += $p->resources);
        $optimalInfluence = array_reduce($planets, fn ($sum, Planet $p) => $sum += $p->optimalInfluence);
        $optimalResources = array_reduce($planets, fn ($sum, Planet $p) => $sum += $p->optimalResources);

        $slice = new Slice([
            TileFactory::make([$planets[0], $planets[1]]),
            TileFactory::make([$planets[2], $planets[3]]),
            TileFactory::make(),
            TileFactory::make(),
            TileFactory::make(),
        ]);

        $this->assertSame($totalResources, $slice->totalResources);
        $this->assertSame($totalInfluence, $slice->totalInfluence);
        $this->assertSame($optimalResources, $slice->optimalResources);
        $this->assertSame($optimalInfluence, $slice->optimalInfluence);
        $this->assertSame($optimalResources + $optimalInfluence, $slice->optimalTotal);
    }

    public static function tileConfigurations(): iterable
    {
        yield 'When it has no anomalies' => [
            'tiles' => [
                TileFactory::make([], [], null),
                TileFactory::make([], [], null),
                TileFactory::make([], [], null),
                TileFactory::make([], [], null),
                TileFactory::make([], [], null),
            ],
            'canBeArranged' => true,
        ];
        yield 'When it has some anomalies' => [
            'tiles' => [
                TileFactory::make([], [], 'nebula'),
                TileFactory::make([], [], 'asteroid field'),
                TileFactory::make([], [], null),
                TileFactory::make([], [], null),
                TileFactory::make([], [], null),
            ],
            'canBeArranged' => true,
        ];
        yield 'When it has too many anomalies' => [
            'tiles' => [
                TileFactory::make([], [], 'nebula'),
                TileFactory::make([], [], 'asteroid field'),
                TileFactory::make([], [], 'gravity-rift'),
                TileFactory::make([], [], 'supernova'),
                TileFactory::make([], [], null),
            ],
            'canBeArranged' => false,
        ];
    }

    #[DataProvider('tileConfigurations')]
    #[Test]
    public function itCanArrangeTiles(array $tiles, bool $canBeArranged): void
    {
        $slice = new Slice($tiles);
        $seed = new Seed(1);

        $arranged = $slice->arrange($seed);

        $this->assertSame($canBeArranged, $arranged);
        $this->assertSame($canBeArranged, $slice->tileArrangementIsValid());
    }

    public static function minorFactionTileConfigurations(): iterable
    {
        // slots 0, 2 and 4 don't neighbour each other, so 3 anomalies still fit
        yield 'When it has 3 anomalies' => [
            'tiles' => [
                TileFactory::make([], [], 'nebula'),
                TileFactory::make([], [], 'asteroid field'),
                TileFactory::make([], [], 'gravity-rift'),
                TileFactory::make([], [], null),
            ],
            'canBeArranged' => true,
        ];
        yield 'When it has only anomalies' => [
            'tiles' => [
                TileFactory::make([], [], 'nebula'),
                TileFactory::make([], [], 'asteroid field'),
                TileFactory::make([], [], 'gravity-rift'),
                TileFactory::make([], [], 'supernova'),
            ],
            'canBeArranged' => false,
        ];
    }

    #[DataProvider('minorFactionTileConfigurations')]
    #[Test]
    public function itCanArrangeTilesAroundTheMinorFactionSlot(array $tiles, bool $canBeArranged): void
    {
        $slice = new Slice($tiles);

        $arranged = $slice->arrange(new Seed(1));

        $this->assertSame($canBeArranged, $arranged);
        $this->assertSame($canBeArranged, $slice->tileArrangementIsValid());
    }

    #[Test]
    public function itKeysTilesBySlot(): void
    {
        $tiles = [TileFactory::make(), TileFactory::make(), TileFactory::make(), TileFactory::make(), TileFactory::make()];

        $slice = new Slice($tiles);

        $this->assertFalse($slice->hasMinorFactionSlot());
        $this->assertSame([0, 1, 2, 3, 4], array_keys($slice->tilesBySlot()));
    }

    #[Test]
    public function itLeavesTheMinorFactionSlotEmptyForFourTileSlices(): void
    {
        $tiles = [TileFactory::make(), TileFactory::make(), TileFactory::make(), TileFactory::make()];

        $slice = new Slice($tiles);

        $this->assertTrue($slice->hasMinorFactionSlot());
        $this->assertSame([0, 1, 2, 4], array_keys($slice->tilesBySlot()));
        $this->assertArrayNotHasKey(Slice::MINOR_FACTION_SLOT, $slice->tilesBySlot());
        $this->assertSame($tiles[3], $slice->tilesBySlot()[4]);
    }

    #[Test]
    public function itRejectsSlicesWithTheWrongNumberOfTiles(): void
    {
        $this->expectException(\Exception::class);

        new Slice([TileFactory::make(), TileFactory::make(), TileFactory::make()]);
    }

    #[Test]
    public function itWontAllowSlicesWithTooManyWormholes(): void
    {
        $slice = new Slice([
            TileFactory::make([], [Wormhole::ALPHA]),
            TileFactory::make([], [Wormhole::ALPHA]),
            TileFactory::make(),
            TileFactory::make(),
            TileFactory::make(),
        ]);

        $valid = $slice->validate(0, 0, 0, 0, true);

        $this->assertFalse($valid);
    }

    #[Test]
    public function itWontAllowSlicesWithTooManyLegendaryPlanets(): void
    {
        $slice = new Slice([
            TileFactory::make([PlanetFactory::make(['legendary' => 'Yes'])]),
            TileFactory::make([PlanetFactory::make(['legendary' => 'Yes'])]),
            TileFactory::make(),
            TileFactory::make(),
            TileFactory::make(),
        ]);

        $valid = $slice->validate(0, 0, 0, 0, false);

        $this->assertFalse($valid);
    }

    #[Test]
    public function itCanValidateMaxWormholes(): void
    {
        $slice = new Slice([
            TileFactory::make([], [Wormhole::ALPHA]),
            TileFactory::make([], [Wormhole::BETA]),
            TileFactory::make(),
            TileFactory::make(),
            TileFactory::make(),
        ]);

        $valid = $slice->validate(0, 0, 0, 0, true);

        $this->assertFalse($valid);
    }

    #[Test]
    public function itCanValidateMinimumOptimalInfluence(): void
    {
        $slice = new Slice([
            TileFactory::make([
                PlanetFactory::make([
                    'influence' => 2,
                    'resources' => 3,
                ]),
            ]),
            TileFactory::make([
                PlanetFactory::make([
                    'influence' => 1,
                    'resources' => 0,
                ]),
            ]),
            TileFactory::make(),
            TileFactory::make(),
            TileFactory::make(),
        ]);

        $valid = $slice->validate(
            2,
            0,
            0,
            0,
            false,
        );

        $this->assertFalse($valid);
    }

    #[Test]
    public function itCanValidateMinimumOptimalResources(): void
    {
        $slice = new Slice([
            TileFactory::make([
                PlanetFactory::make([
                    'influence' => 5,
                    'resources' => 2,
                ]),
            ]),
            TileFactory::make([
                PlanetFactory::make([
                    'influence' => 1,
                    'resources' => 1,
                ]),
            ]),
            TileFactory::make(),
            TileFactory::make(),
            TileFactory::make(),
        ]);

        $valid = $slice->validate(
            0,
            3,
            0,
            0,
            false,
        );
        $this->assertFalse($valid);
    }

    #[Test]
    public function itCanValidateMinimumOptimalTotal(): void
    {
        $slice = new Slice([
            TileFactory::make([
                PlanetFactory::make([
                    'influence' => 4,
                    'resources' => 2,
                ]),
            ]),
            TileFactory::make([
                PlanetFactory::make([
                    'influence' => 1,
                    'resources' => 1,
                ]),
            ]),
            TileFactory::make(),
            TileFactory::make(),
            TileFactory::make(),
        ]);

        $valid = $slice->validate(
            0,
            0,
            5,
            0,
            false,
        );

        $this->assertFalse($valid);
    }

    #[Test]
    public function itCanValidateMaximumOptimalTotal(): void
    {
        $slice = new Slice([
            TileFactory::make([
                PlanetFactory::make([
                    'influence' => 2,
                    'resources' => 4,
                ]),
            ]),
            TileFactory::make([
                PlanetFactory::make([
                    'influence' => 2,
                    'resources' => 1,
                ]),
            ]),
            TileFactory::make([
                PlanetFactory::make([
                    'influence' => 3,
                    'resources' => 1,
                ]),
            ]),
            TileFactory::make(),
            TileFactory::make(),
        ]);

        $valid = $slice->validate(
            0,
            0,
            0,
            4,
            false,
        );
        $this->assertFalse($valid);
    }

    #[Test]
    public function itCanValidateAValidSlice(): void
    {
        $slice = new Slice([
            TileFactory::make([
                PlanetFactory::make([
                    'influence' => 2,
                    'resources' => 3,
                ]),
            ]),
            TileFactory::make([
                PlanetFactory::make([
                    'influence' => 2,
                    'resources' => 1,
                ]),
            ]),
            TileFactory::make([
                PlanetFactory::make([
                    'influence' => 1,
                    'resources' => 1,
                ]),
                PlanetFactory::make([
                    'influence' => 1,
                    'resources' => 1,
                ]),
            ]),
            TileFactory::make(),
            TileFactory::make(),
        ]);

        $valid = $slice->validate(
            1,
            3,
            5,
            7,
            false,
        );

        $this->assertTrue($valid);
    }
}