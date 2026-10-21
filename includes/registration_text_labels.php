<?php
declare(strict_types=1);

const ATHLETE_REGISTRATION_TEXT_PURPOSES = [
    'member_data_notice' => 'Informace o evidenci člena',
    'birth_number_legal_notice' => 'Informace k rodnému číslu',
    'photo_internal' => 'Souhlas s interním použitím fotografie',
    'photo_public' => 'Souhlas se zveřejněním fotografie',
    'csc_birth_number_sharing' => 'Volitelné předání rodného čísla ČSC',
];

/** Older schemas and versions without an explicit title retain their original name. */
function registrationTextTitle(array $term, string $purpose): string
{
    $title = trim((string)($term['title_plain'] ?? ''));
    return $title !== '' ? $title : (ATHLETE_REGISTRATION_TEXT_PURPOSES[$purpose] ?? $purpose);
}
