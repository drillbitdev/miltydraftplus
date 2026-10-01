<?php

declare(strict_types=1);

namespace App\TwilightImperium;

use App\Testing\TestCase;
use PHPUnit\Framework\Attributes\Test;

class MinorFactionsModeTest extends TestCase
{
    // check if all values are present for backwards compatibility with old drafts
    #[Test]
    public function itHasAllMinorFactionsModeValues(): void {
        $values = array_map(fn (MinorFactionsMode $mode) => $mode->value, MinorFactionsMode::cases());

        $this->assertContains('draft', $values);
        $this->assertContains('random', $values);
    }
}
