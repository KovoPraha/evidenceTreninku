<?php

declare(strict_types=1);

/** A failed checkout may retain entered data, but not acceptance of a newer text. */
function shopProgramQuickRetainedInput(array $input, array $registrationTerms, array $programTerms): array
{
    $submittedRegistration = is_array($input['term_version'] ?? null) ? $input['term_version'] : [];
    $submittedProgram = is_array($input['program_term_version'] ?? null) ? $input['program_term_version'] : [];

    foreach ($registrationTerms as $purpose => $term) {
        $version = $submittedRegistration[$purpose] ?? null;
        if (!is_string($version) || !hash_equals((string)$term['version'], $version)) {
            unset($input['registration_acknowledged'], $input['csc_birth_number_sharing']);
            return $input;
        }
    }
    foreach ($programTerms as $purpose => $term) {
        $version = $submittedProgram[$purpose] ?? null;
        if (!is_string($version) || !hash_equals((string)$term['terms_version'], $version)) {
            unset($input['registration_acknowledged'], $input['csc_birth_number_sharing']);
            return $input;
        }
    }

    return $input;
}
