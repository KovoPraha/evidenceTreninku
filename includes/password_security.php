<?php
declare(strict_types=1);

/** Return true only for password formats understood by the current PHP runtime. */
function trainer_password_is_modern_hash(string $storedPassword): bool
{
    $info = password_get_info($storedPassword);

    return $info['algo'] !== null;
}

/** Fail closed: plaintext and malformed legacy values are never login credentials. */
function trainer_password_verify(string $providedPassword, string $storedPassword): bool
{
    return trainer_password_is_modern_hash($storedPassword)
        && password_verify($providedPassword, $storedPassword);
}

/** Legacy values and hashes using outdated parameters both need replacement. */
function trainer_password_needs_rehash(string $storedPassword): bool
{
    if (!trainer_password_is_modern_hash($storedPassword)) {
        return true;
    }

    return password_needs_rehash($storedPassword, PASSWORD_DEFAULT);
}

function trainer_password_hash(string $plainPassword): string
{
    return password_hash($plainPassword, PASSWORD_DEFAULT);
}
