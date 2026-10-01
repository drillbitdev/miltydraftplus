<?php

declare(strict_types=1);

namespace App\Draft\Commands;

use App\Draft\Exceptions\InvalidDraftSettingsException;
use App\Shared\Command;
use App\Testing\Factories\DraftSettingsFactory;
use App\Testing\TestCase;
use App\TwilightImperium\Edition;
use App\TwilightImperium\Faction;
use App\TwilightImperium\MinorFactionsMode;
use PHPUnit\Framework\Attributes\Test;

class GenerateMinorFactionPoolTest extends TestCase
{
    #[Test]
    public function itImplementsCommand(): void
    {
        $cmd = new GenerateMinorFactionPool(DraftSettingsFactory::make(), []);
        $this->assertInstanceOf(Command::class, $cmd);
    }

    #[Test]
    public function itGeneratesNothingWithoutMinorFactions(): void
    {
        $settings = DraftSettingsFactory::make(['minorFactions' => false]);

        $this->assertSame([], (new GenerateMinorFactionPool($settings, []))->handle());
    }

    #[Test]
    public function itGeneratesThePoolToDraftFrom(): void
    {
        $settings = DraftSettingsFactory::make([
            'numberOfPlayers' => 6,
            'minorFactions' => true,
            'minorFactionsMode' => MinorFactionsMode::DRAFT,
            'numberOfMinorFactions' => 8,
        ]);
        $factions = (new GenerateFactionPool($settings))->handle();

        $minorFactions = (new GenerateMinorFactionPool($settings, $factions))->handle();

        $this->assertCount(8, $minorFactions);
    }

    #[Test]
    public function itGeneratesOneMinorFactionPerPlayerWhenRandom(): void
    {
        $settings = DraftSettingsFactory::make([
            'numberOfPlayers' => 5,
            'minorFactions' => true,
            'minorFactionsMode' => MinorFactionsMode::RANDOM,
        ]);
        $factions = (new GenerateFactionPool($settings))->handle();

        $minorFactions = (new GenerateMinorFactionPool($settings, $factions))->handle();

        $this->assertCount(5, $minorFactions);
    }

    #[Test]
    public function itDoesNotOverlapWithThePlayerFactions(): void
    {
        $settings = DraftSettingsFactory::make([
            'numberOfPlayers' => 6,
            'numberOfFactions' => 8,
            'factionSets' => [Edition::BASE_GAME],
            'minorFactions' => true,
            'numberOfMinorFactions' => 9,
        ]);
        $factions = (new GenerateFactionPool($settings))->handle();

        $minorFactions = (new GenerateMinorFactionPool($settings, $factions))->handle();

        $factionNames = array_map(fn (Faction $f) => $f->name, $factions);
        $minorFactionNames = array_map(fn (Faction $f) => $f->name, $minorFactions);
        $this->assertEmpty(array_intersect($factionNames, $minorFactionNames));
        $this->assertCount(9, array_unique($minorFactionNames));
        foreach ($minorFactions as $f) {
            $this->assertSame(Edition::BASE_GAME, $f->edition);
        }
    }

    #[Test]
    public function itNeverHasArgentKeleresMentakAndXxchaInPlay(): void
    {
        for ($seed = 1; $seed <= 30; $seed++) {
            // 30 of 31 factions in play, so all four would almost always be included
            $settings = DraftSettingsFactory::make([
                'seed' => $seed,
                'numberOfPlayers' => 8,
                'numberOfFactions' => 15,
                'factionSets' => [Edition::BASE_GAME, Edition::PROPHECY_OF_KINGS, Edition::THUNDERS_EDGE],
                'minorFactions' => true,
                'numberOfMinorFactions' => 14,
            ]);
            $factions = (new GenerateFactionPool($settings))->handle();
            $minorFactions = (new GenerateMinorFactionPool($settings, $factions))->handle();

            $names = array_map(fn (Faction $f) => $f->name, [...$factions, ...$minorFactions]);
            $this->assertFalse(GenerateMinorFactionPool::includesKeleresConflict($names), 'seed ' . $seed);
        }
    }

    #[Test]
    public function itGeneratesTheSamePoolFromTheSameSeed(): void
    {
        $settings = DraftSettingsFactory::make([
            'seed' => 123,
            'minorFactions' => true,
        ]);
        $factions = (new GenerateFactionPool($settings))->handle();

        $first = (new GenerateMinorFactionPool($settings, $factions))->handle();
        $second = (new GenerateMinorFactionPool($settings, $factions))->handle();

        $this->assertSame(
            array_map(fn (Faction $f) => $f->name, $first),
            array_map(fn (Faction $f) => $f->name, $second),
        );
    }

    #[Test]
    public function itThrowsWhenThereAreNotEnoughFactionsLeft(): void
    {
        $settings = DraftSettingsFactory::make([
            'numberOfPlayers' => 6,
            'numberOfFactions' => 12,
            'factionSets' => [Edition::BASE_GAME],
            'minorFactions' => true,
            'numberOfMinorFactions' => 6,
        ]);
        $factions = (new GenerateFactionPool($settings))->handle();

        $this->expectException(InvalidDraftSettingsException::class);
        $this->expectExceptionMessage(InvalidDraftSettingsException::notEnoughFactionsForMinorFactions()->getMessage());

        (new GenerateMinorFactionPool($settings, $factions))->handle();
    }

    #[Test]
    public function itGivesKeleresAHomeSystemThatIsNotInPlay(): void
    {
        $all = Faction::all();
        $settings = DraftSettingsFactory::make(['minorFactions' => true]);
        $generator = new GenerateMinorFactionPool($settings, [$all['The Argent Flight'], $all['The Arborec']]);

        $homeSystems = $generator->homeSystems([$all['The Council Keleres'], $all['The Mentak Coalition']]);

        // Argent and Mentak are in play, so Keleres gets the Xxcha home system
        $this->assertSame(['The Council Keleres' => $all['The Xxcha Kingdom']->homesystem()], $homeSystems);
    }

    #[Test]
    public function itPicksRandomlyFromTheAvailableKeleresHomeSystems(): void
    {
        $all = Faction::all();
        $options = [
            $all['The Argent Flight']->homesystem(),
            $all['The Mentak Coalition']->homesystem(),
            $all['The Xxcha Kingdom']->homesystem(),
        ];
        $settings = DraftSettingsFactory::make(['minorFactions' => true]);
        $generator = new GenerateMinorFactionPool($settings, [$all['The Arborec']]);

        $picked = [];
        for ($seed = 1; $seed <= 30; $seed++) {
            mt_srand($seed);
            $picked[] = $generator->homeSystems([$all['The Council Keleres']])['The Council Keleres'];
        }

        $this->assertEmpty(array_diff($picked, $options));
        $this->assertGreaterThan(1, count(array_unique($picked)));
    }

    #[Test]
    public function itOnlyOverridesHomeSystemsForKeleres(): void
    {
        $all = Faction::all();
        $settings = DraftSettingsFactory::make(['minorFactions' => true]);
        $generator = new GenerateMinorFactionPool($settings, []);

        // Creuss and the Rebellion place their gate / Sorrow, which is what the faction data has
        $this->assertSame([], $generator->homeSystems([$all['The Ghosts of Creuss'], $all['The Crimson Rebellion']]));
        $this->assertSame('17', $all['The Ghosts of Creuss']->homesystem());
        $this->assertSame('94', $all['The Crimson Rebellion']->homesystem());
    }
}
