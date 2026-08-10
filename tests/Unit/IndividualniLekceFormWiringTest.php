<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class IndividualniLekceFormWiringTest extends TestCase
{
    public function testWeeklyRepeatIsSubmittedOnlyWhenTheVisibleSwitchIsEnabled(): void
    {
        $source = (string)file_get_contents(dirname(__DIR__, 2) . '/individualni_lekce_form.php');

        self::assertStringContainsString('name="opakovat" value="1"', $source);
        self::assertStringContainsString('$opakovaniTydnu = $opakovat', $source);
        self::assertStringContainsString('opakovaniSelect.disabled = !opakovaniToggle.checked;', $source);
        self::assertStringContainsString('<h1 class="h4', $source);
        self::assertStringContainsString('for="lekce-nazev"', $source);
    }
}
