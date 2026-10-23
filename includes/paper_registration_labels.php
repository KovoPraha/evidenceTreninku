<?php
declare(strict_types=1);

/** Presentation only: never infer or alter the signed version from its title. */
function paperRegistrationTermLabel(array $term): string
{
    $purpose = trim((string)($term['consent_purpose'] ?? ''));
    $kind = match ($purpose) {
        'program_cancellation' => 'Storno podmínky programu',
        'program_consent' => 'Souhlas s účastí v programu',
        'club_event_registration' => 'Podmínky přihlášení na akci',
        default => $purpose !== '' ? 'Typ: '.$purpose : 'Podmínky bez uvedeného typu',
    };
    $title = trim((string)($term['title_plain'] ?? ''));
    $version = trim((string)($term['terms_version'] ?? ''));
    $scope = trim((string)($term['scope_type'] ?? '').' '.(string)($term['scope_key'] ?? ''));
    return ($title !== '' && $title !== $kind ? $title.' · ' : '').$kind
        .' · '.($version !== '' ? $version : 'verze bez označení')
        .($scope !== '' ? ' · '.$scope : '')
        .' · #'.(int)($term['id'] ?? 0);
}
