<?php

declare(strict_types=1);

namespace App\Draft\Commands;

use App\Draft\Exceptions\InvalidDraftSettingsException;
use App\Shared\Command;
use App\Testing\Factories\DraftSettingsFactory;
use App\Testing\TestCase;
use App\Testing\TestSets;
use App\TwilightImperium\Edition;
use App\TwilightImperium\Faction;
use PHPUnit\Framework\Attributes\DataProviderExternal;
use PHPUnit\Framework\Attributes\Test;

class GenerateFactionPoolTest extends TestCase
{
    #[Test]
    public function itImplementsCommand(): void
    {
        $cmd = new GenerateFactionPool(DraftSettingsFactory::make());
        $this->assertInstanceOf(Command::class, $cmd);
    }
    
    #[Test]
    #[DataProviderExternal(TestSets::class, 'setCombinations')]
    public function itCanGenerateChoicesFromFactionSets($sets): void
    {
        $generator = new GenerateFactionPool(DraftSettingsFactory::make([
            'factionSets' => $sets,
            'numberOfFactions' => 10,
        ]));

        $choices = $generator->handle();
        $choicesNames = array_map(fn (Faction $faction) => $faction->name, $choices);

        $this->assertCount(10, $choices);
        $this->assertCount(10, array_unique($choicesNames));
        foreach($choices as $choice) {
            $this->assertContains($choice->edition, $sets);
        }
    }

    #[Test]
    public function itUsesOnlyCustomFactionsWhenEnoughAreProvided(): void
    {
        $customFactions = [
            'The Barony of Letnev',
            'The Clan of Saar',
            'The Emirates of Hacan',
            'The Ghosts of Creuss',
        ];
        $generator = new GenerateFactionPool(DraftSettingsFactory::make([
            'customFactions' => $customFactions,
            'factionSets' => [Edition::BASE_GAME],
            'numberOfFactions' => 3,
        ]));

        $choices = $generator->handle();

        $this->assertCount(3, $choices);
        foreach($choices as $choice) {
            $this->assertContains($choice->name, $customFactions);
        }
    }

    #[Test]
    public function itGeneratesTheSameFactionsFromTheSameSeed(): void
    {
        $generator = new GenerateFactionPool(DraftSettingsFactory::make([
            'seed' => 123,
            'factionSets' => [Edition::BASE_GAME],
            'numberOfFactions' => 3,
        ]));
        $previouslyGeneratedChoices = [
            'The Ghosts of Creuss',
            'The Emirates of Hacan',
            'The Yssaril Tribes',
        ];

        $choices = $generator->handle();

        foreach($previouslyGeneratedChoices as $i => $name) {
            $this->assertSame($name, $choices[$i]->name);
        }
    }

    #[Test]
    public function itTakesFromSetsWhenNotEnoughCustomFactionsAreProvided(): void
    {
        $customFactions = [
            'The Ghosts of Creuss',
            'The Emirates of Hacan',
            'The Yssaril Tribes',
        ];
        $generator = new GenerateFactionPool(DraftSettingsFactory::make([
            'factionSets' => [Edition::BASE_GAME],
            'customFactions' => $customFactions,
            'numberOfFactions' => 10,
        ]));

        $choices = $generator->handle();
        $choicesNames = array_map(fn (Faction $faction) => $faction->name, $choices);

        foreach($customFactions as $f) {
            $this->assertContains($f, $choicesNames);
        }

        foreach($choices as $c) {
            $this->assertEquals($c->edition, Edition::BASE_GAME);
        }
    }

    #[Test]
    public function itAvoidsHavingArgentKeleresMentakAndXxchaWithMinorFactions(): void
    {
        for ($seed = 1; $seed <= 20; $seed++) {
            $generator = new GenerateFactionPool(DraftSettingsFactory::make([
                'seed' => $seed,
                'customFactions' => ['The Argent Flight', 'The Mentak Coalition', 'The Xxcha Kingdom'],
                'factionSets' => [Edition::BASE_GAME, Edition::PROPHECY_OF_KINGS, Edition::THUNDERS_EDGE],
                'numberOfFactions' => 25,
                'minorFactions' => true,
            ]));

            $names = array_map(fn (Faction $f) => $f->name, $generator->handle());

            $this->assertFalse(GenerateMinorFactionPool::includesKeleresConflict($names));
            $this->assertCount(25, array_unique($names));
        }
    }

    #[Test]
    public function itRejectsCustomFactionsWithArgentKeleresMentakAndXxchaWithMinorFactions(): void
    {
        $generator = new GenerateFactionPool(DraftSettingsFactory::make([
            'customFactions' => ['The Argent Flight', 'The Council Keleres', 'The Mentak Coalition', 'The Xxcha Kingdom'],
            'factionSets' => [Edition::BASE_GAME, Edition::PROPHECY_OF_KINGS, Edition::THUNDERS_EDGE],
            'numberOfFactions' => 6,
            'minorFactions' => true,
        ]));

        $this->expectException(InvalidDraftSettingsException::class);
        $this->expectExceptionMessage(InvalidDraftSettingsException::customFactionsIncludeKeleresConflict()->getMessage());

        $generator->handle();
    }
}
