<?php

declare(strict_types=1);

namespace App\TwilightImperium;

use App\Testing\TestCase;
use PHPUnit\Framework\Attributes\Test;

class EditionTest extends TestCase
{
    #[Test]
    public function itCanCheckForValidTileSets(): void
    {
        foreach(Edition::cases() as $edition) {
            if ($edition == Edition::DISCORDANT_STARS) {
                $this->assertFalse($edition->hasValidTileSet());
            } else {
                $this->assertTrue($edition->hasValidTileSet());
            }
        }
    }

    #[Test]
    public function itReturnsTheCorrectNumbersForBaseGame(): void
    {
        $this->assertSame(20, Edition::BASE_GAME->blueTileCount());
        $this->assertSame(8, Edition::BASE_GAME->highTierTileCount());
        $this->assertSame(5, Edition::BASE_GAME->midTierTileCount());
        $this->assertSame(7, Edition::BASE_GAME->lowTierTileCount());
        $this->assertSame(12, Edition::BASE_GAME->redTileCount());
        $this->assertSame(0, Edition::BASE_GAME->legendaryPlanetCount());
        $this->assertSame(17, Edition::BASE_GAME->factionCount());
    }

    #[Test]
    public function itReturnsTheCorrectNumbersForPoK(): void
    {
        $this->assertSame(16, Edition::PROPHECY_OF_KINGS->blueTileCount());
        $this->assertSame(4, Edition::PROPHECY_OF_KINGS->highTierTileCount());
        $this->assertSame(7, Edition::PROPHECY_OF_KINGS->midTierTileCount());
        $this->assertSame(5, Edition::PROPHECY_OF_KINGS->lowTierTileCount());
        $this->assertSame(6, Edition::PROPHECY_OF_KINGS->redTileCount());
        $this->assertSame(2, Edition::PROPHECY_OF_KINGS->legendaryPlanetCount());
        $this->assertSame(7, Edition::PROPHECY_OF_KINGS->factionCount());
    }

    #[Test]
    public function itReturnsTheCorrectNumbersForThundersEdge(): void
    {
        $this->assertSame(15, Edition::THUNDERS_EDGE->blueTileCount());
        $this->assertSame(6, Edition::THUNDERS_EDGE->highTierTileCount());
        $this->assertSame(6, Edition::THUNDERS_EDGE->midTierTileCount());
        $this->assertSame(3, Edition::THUNDERS_EDGE->lowTierTileCount());
        $this->assertSame(5, Edition::THUNDERS_EDGE->redTileCount());
        $this->assertSame(5, Edition::THUNDERS_EDGE->legendaryPlanetCount());
        $this->assertSame(6, Edition::THUNDERS_EDGE->factionCount());
    }

    #[Test]
    public function itReturnsTheCorrectNumbersForDiscordantStars(): void
    {
        $this->assertSame(0, Edition::DISCORDANT_STARS->blueTileCount());
        $this->assertSame(0, Edition::DISCORDANT_STARS->highTierTileCount());
        $this->assertSame(0, Edition::DISCORDANT_STARS->midTierTileCount());
        $this->assertSame(0, Edition::DISCORDANT_STARS->lowTierTileCount());
        $this->assertSame(0, Edition::DISCORDANT_STARS->redTileCount());
        $this->assertSame(0, Edition::DISCORDANT_STARS->legendaryPlanetCount());
        $this->assertSame(24, Edition::DISCORDANT_STARS->factionCount());
    }

    #[Test]
    public function itReturnsTheCorrectNumbersForDiscordantStarsPlus(): void
    {
        $this->assertSame(16, Edition::DISCORDANT_STARS_PLUS->blueTileCount());
        $this->assertSame(6, Edition::DISCORDANT_STARS_PLUS->highTierTileCount());
        $this->assertSame(9, Edition::DISCORDANT_STARS_PLUS->midTierTileCount());
        $this->assertSame(1, Edition::DISCORDANT_STARS_PLUS->lowTierTileCount());
        $this->assertSame(8, Edition::DISCORDANT_STARS_PLUS->redTileCount());
        $this->assertSame(5, Edition::DISCORDANT_STARS_PLUS->legendaryPlanetCount());
        $this->assertSame(10, Edition::DISCORDANT_STARS_PLUS->factionCount());
    }

}