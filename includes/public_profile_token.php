<?php
declare(strict_types=1);

/** Public profile URLs are bearer credentials and require cryptographic entropy. */
function public_profile_token_generate(): string
{
    return bin2hex(random_bytes(32));
}

function public_profile_token_is_strong(string $token): bool
{
    return preg_match('/\A[a-f0-9]{64}\z/D', $token) === 1;
}
