<?php
declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__,2).'/includes/club_program_terms.php';

final class ClubProgramTermDefaultsTest extends TestCase
{
    /**
     * The prepared text is a draft. If it were published unchanged, the club
     * would rely on cancellation terms nobody wrote, so the marker has to be
     * part of the document text that ends up in the parent's snapshot.
     */
    public function testEveryPreparedTermTextIsMarkedAsADraft():void
    {
        self::assertSame(['program_cancellation','program_consent'],CLUB_PROGRAM_TERM_PURPOSES);
        self::assertSame('VZOR — před publikováním upravte.',CLUB_PROGRAM_TERM_DRAFT_MARKER);

        foreach(CLUB_PROGRAM_TERM_PURPOSES as $purpose){
            $text=CLUB_PROGRAM_TERM_DEFAULTS[$purpose]??'';
            self::assertIsString($text);
            self::assertStringStartsWith(CLUB_PROGRAM_TERM_DRAFT_MARKER,$text,$purpose.' must open with the draft marker.');
            self::assertGreaterThan(mb_strlen(CLUB_PROGRAM_TERM_DRAFT_MARKER,'UTF-8')+40,mb_strlen($text,'UTF-8'),$purpose.' must keep its wording.');
        }
    }

    public function testAdministrationKeepsDraftEditingSeparateFromSimplePublication():void
    {
        $root=dirname(__DIR__,2);
        $wizard=(string)file_get_contents($root.'/club_program_wizard_admin.php');
        $programs=(string)file_get_contents($root.'/club_programs_admin.php');
        self::assertStringNotContainsString('CLUB_PROGRAM_TERM_DEFAULTS',$wizard);
        self::assertStringContainsString("'_source'] = 'existing'",$wizard);
        self::assertStringContainsString('CLUB_PROGRAM_TERM_DEFAULTS',$programs);
    }
}
