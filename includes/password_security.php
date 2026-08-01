<?php
declare(strict_types=1);

/** Return true only for password formats understood by the current PHP runtime. */
function trainer_password_is_modern_hash(string $storedPassword): bool
{
    $info = password_get_info($storedPassword);

    return $info['algo'] !== null;
}

/** Verify both modern hashes and the temporary legacy plaintext representation. */
function trainer_password_verify(string $providedPassword, string $storedPassword): bool
{
    if (trainer_password_is_modern_hash($storedPassword)) {
        return password_verify($providedPassword, $storedPassword);
    }

    return $storedPassword !== '' && hash_equals($storedPassword, $providedPassword);
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
