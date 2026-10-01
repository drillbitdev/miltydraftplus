<?php

declare(strict_types=1);

namespace App\Draft;

use App\Draft\Commands\GenerateDraft;
use App\Testing\Factories\DraftSettingsFactory;
use App\Testing\TestCase;
use App\Testing\TestDrafts;
use App\TwilightImperium\Faction;
use App\TwilightImperium\MinorFactionsMode;
use App\TwilightImperium\Tile;
use PHPUnit\Framework\Attributes\DataProviderExternal;
use PHPUnit\Framework\Attributes\Test;

/**
 * We shouldn't worry about players/settings getting initialised or serialised again
 * That should be tested in their own class. Just checking that they're in the right place.
 */
class DraftTest extends TestCase
{
    #[DataProviderExternal(TestDrafts::class, 'provideTestDrafts')]
    #[Test]
    public function ìtCanBeInitialisedFromJson($data): void
    {
        $draft = Draft::fromJson($data);

        $this->assertNotEmpty($draft->players);
        $this->assertSame($data['config']['name'], (string) $draft->settings->name);
        $this->assertSame($draft->id, $data['id']);
        $this->assertSame($draft->isDone, $data['done']);

        $factionPoolNames = array_map(fn (Faction $f) => $f->name, $draft->factionPool);

        foreach($data['factions'] as $faction) {
            $this->assertContains($faction, $factionPoolNames);
        }
        $this->assertSame($draft->currentPlayerId->value, $data['draft']['current']);
    }

    #[Test]
    public function itCanBeConvertedToArray(): void
    {
        $factions = Faction::all();
        $tiles = Tile::all();
        $player = new Player(
            PlayerId::fromString('player_123'),
            'Alice',
        );
        $draft = new Draft(
            '1243',
            true,
            [$player->id->value => $player],
            DraftSettingsFactory::make(),
            new Secrets(
                'secret123',
            ),
            [
               new Slice([
                   $tiles['64'],
                   $tiles['33'],
                   $tiles['42'],
                   $tiles['67'],
                   $tiles['59'],
               ]),
            ],
            [
                $factions['The Barony of Letnev'],
                $factions['The Embers of Muaat'],
                $factions['The Clan of Saar'],
            ],
            [new Pick($player->id, PickCategory::FACTION, 'Vulraith')],
            $player->id,
        );

        $data = $draft->toArray();

        $this->assertSame($draft->settings->toArray(), $data['config']);
        $this->assertSame($draft->id, $data['id']);
        $this->assertSame($draft->isDone, $data['done']);
        $this->assertSame($player->name, $data['draft']['players'][$player->id->value]['name']);
        $this->assertSame($player->id->value, $data['draft']['current']);
        $this->assertSame('Vulraith', $data['draft']['log'][0]['value']);
        foreach($draft->factionPool as $faction) {
            $this->assertContains($faction->name, $data['factions']);
        }
        foreach($draft->slicePool as $slice) {
            $this->assertContains(['tiles' => $slice->tileIds()], $data['slices']);
        }
    }

    #[Test]
    public function itCanUpdatePlayerData(): void
    {
        $draft = (new GenerateDraft(DraftSettingsFactory::make()))->handle();

        $playerId = PlayerId::fromString(array_keys($draft->players)[3]);

        $player = $draft->playerById($playerId);

        $newPlayerData = $player->pick(new Pick($playerId, PickCategory::FACTION, 'Xxcha'));

        $draft->updatePlayerData($newPlayerData);

        $this->assertSame($draft->playerById($playerId)->toArray(), $newPlayerData->toArray());
        $this->assertSame($draft->playerById($playerId)->getPick(PickCategory::FACTION), 'Xxcha');
    }

    #[Test]
    public function itCanUpdateCurrentPlayerInSnakeDraft(): void
    {
        $draft = (new GenerateDraft(DraftSettingsFactory::make()))->handle();

        // the order for three rounds: Regular + Reverse + Regular
        $order = array_merge(array_keys($draft->players), array_keys(array_reverse($draft->players)), array_keys($draft->players));

        foreach($order as $expectedCurrentPlayer) {
            $this->assertSame($draft->currentPlayerId->value, $expectedCurrentPlayer);
            $draft->log[] = new Pick($draft->currentPlayerId, PickCategory::FACTION, 'foo');
            $draft->updateCurrentPlayer();
        }

        // it sets the draft to done at the end
        $this->assertNull($draft->currentPlayerId);
        $this->assertTrue($draft->isDone);
    }

    #[Test]
    public function itHasAFourthRoundWhenMinorFactionsAreDrafted(): void
    {
        $draft = (new GenerateDraft(DraftSettingsFactory::make([
            'numberOfPlayers' => 4,
            'minorFactions' => true,
            'minorFactionsMode' => MinorFactionsMode::DRAFT,
        ])))->handle();

        $this->assertSame(4, $draft->picksPerPlayer());

        // snake draft: regular + reverse + regular + reverse
        $order = array_merge(...array_fill(0, 2, [...array_keys($draft->players), ...array_keys(array_reverse($draft->players))]));
        foreach ($order as $expectedCurrentPlayer) {
            $this->assertFalse($draft->isDone);
            $this->assertSame($expectedCurrentPlayer, $draft->currentPlayerId->value);
            $draft->log[] = new Pick($draft->currentPlayerId, PickCategory::FACTION, 'foo');
            $draft->updateCurrentPlayer();
        }

        $this->assertTrue($draft->isDone);
    }

    #[Test]
    public function itHasThreeRoundsWhenMinorFactionsAreRandom(): void
    {
        $draft = (new GenerateDraft(DraftSettingsFactory::make([
            'minorFactions' => true,
            'minorFactionsMode' => MinorFactionsMode::RANDOM,
        ])))->handle();

        $this->assertSame(3, $draft->picksPerPlayer());
    }

    #[Test]
    public function itAssignsEachPlayerADifferentRandomMinorFaction(): void
    {
        $draft = (new GenerateDraft(DraftSettingsFactory::make([
            'numberOfPlayers' => 6,
            'minorFactions' => true,
            'minorFactionsMode' => MinorFactionsMode::RANDOM,
        ])))->handle();

        $assigned = array_map(fn (Player $p) => $p->pickedMinorFaction, array_values($draft->players));
        $pool = array_map(fn (Faction $f) => $f->name, $draft->minorFactionPool);

        $this->assertCount(6, array_unique(array_filter($assigned)));
        $this->assertEqualsCanonicalizing($pool, $assigned);
    }

    #[Test]
    public function itDoesNotAssignMinorFactionsWhenTheyAreDrafted(): void
    {
        $draft = (new GenerateDraft(DraftSettingsFactory::make([
            'minorFactions' => true,
            'minorFactionsMode' => MinorFactionsMode::DRAFT,
        ])))->handle();

        foreach ($draft->players as $player) {
            $this->assertNull($player->pickedMinorFaction);
        }
    }

    #[Test]
    public function itKeepsMinorFactionsWhenSavedAndLoaded(): void
    {
        $draft = (new GenerateDraft(DraftSettingsFactory::make([
            'minorFactions' => true,
            'minorFactionsMode' => MinorFactionsMode::RANDOM,
        ])))->handle();
        $draft->minorFactionHomeSystems = ['The Council Keleres' => '14'];

        $loaded = Draft::fromJson($draft->toArray(true));

        $this->assertSame(
            array_map(fn (Faction $f) => $f->name, $draft->minorFactionPool),
            array_map(fn (Faction $f) => $f->name, $loaded->minorFactionPool),
        );
        $this->assertSame(['The Council Keleres' => '14'], $loaded->minorFactionHomeSystems);
        foreach ($draft->players as $id => $player) {
            $this->assertSame($player->pickedMinorFaction, $loaded->players[$id]->pickedMinorFaction);
        }
    }

    #[Test]
    public function itLooksUpMinorFactionHomeSystems(): void
    {
        $factions = Faction::all();
        $draft = (new GenerateDraft(DraftSettingsFactory::make()))->handle();
        $draft->minorFactionHomeSystems = ['The Council Keleres' => '58'];

        $this->assertSame('58', $draft->minorFactionHomeSystem($factions['The Council Keleres']));
        $this->assertSame('17', $draft->minorFactionHomeSystem($factions['The Ghosts of Creuss']));
    }
}
