<?php
declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__,2).'/includes/athlete_registration.php';
require_once dirname(__DIR__,2).'/includes/club_program_terms.php';

final class RegistrationTermsReadinessTest extends TestCase
{
    public function testAthleteNoticesRejectSamplesAndProvisionalText(): void
    {
        $terms=[
            'member_data_notice'=>['id'=>1,'version'=>'v1','text'=>'Informace schválená klubem.'],
            'birth_number_legal_notice'=>['id'=>2,'version'=>'v1','text'=>'Právní titul a lhůty schválené klubem.'],
        ];
        self::assertTrue(\athleteRegistrationTermsReady($terms));
        $terms['member_data_notice']['text']='VZOROVÝ TEXT – doplňte';
        self::assertFalse(\athleteRegistrationTermsReady($terms));
        $terms['member_data_notice']['text']='Lhůty budou doplněny před produkční aktivací.';
        self::assertFalse(\athleteRegistrationTermsReady($terms));
    }

    public function testProgramTermsRejectImportedDrafts(): void
    {
        $terms=[
            'program_cancellation'=>['id'=>1,'terms_version'=>'v1','consent_text_plain'=>'Schválené storno podmínky.'],
            'program_consent'=>['id'=>2,'terms_version'=>'v1','consent_text_plain'=>'Schválený souhlas s účastí.'],
        ];
        self::assertTrue(\clubProgramTermsComplete($terms));
        $terms['program_cancellation']['consent_text_plain']='vzorový text – doplnit';
        self::assertFalse(\clubProgramTermsComplete($terms));
        $terms['program_cancellation']['consent_text_plain']=CLUB_PROGRAM_TERM_DEFAULTS['program_cancellation'];
        self::assertFalse(\clubProgramTermsComplete($terms));
    }
}
