<?php
declare(strict_types=1);

/** Only the two read-only entry points may be resumed after staff login. */
function staffLoginReturnTarget(mixed $target): string
{
    $fallback = 'pracovni_pozice.php';
    if (!is_string($target) || strlen($target) > 2048) return $fallback;
    $parts = explode('?', $target, 2);
    $allowed = [
        'zavod_vysledky.php' => ['id' => 'number'],
        'kis_review_admin.php' => ['run_id' => 'number', 'plan_id' => 'number', 'fingerprint' => 'hash'],
    ];
    if (!isset($allowed[$parts[0]])) return $fallback;
    parse_str($parts[1] ?? '', $query);
    $safe = [];
    foreach ($query as $key => $value) {
        $type = $allowed[$parts[0]][$key] ?? null;
        if (!is_string($value) || $type === null) return $fallback;
        $pattern = $type === 'number' ? '/^[0-9]{1,10}$/D' : '/^[a-f0-9]{64}$/D';
        if (!preg_match($pattern, $value)) return $fallback;
        $safe[$key] = $value;
    }
    return $parts[0] . ($safe ? '?' . http_build_query($safe, '', '&', PHP_QUERY_RFC3986) : '');
}
