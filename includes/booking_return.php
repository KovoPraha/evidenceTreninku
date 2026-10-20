<?php
declare(strict_types=1);

/**
 * A return is an allowlisted booking page, never an external URL or auth endpoint.
 * Query values are decoded once; a remaining percent escape is not accepted.
 */
function bookingReturnDestination(mixed $value, string $fallback = ''): string
{
    if (!is_string($value) || strlen($value) > 1500
        || preg_match('~^([a-z_]+\.php)(?:\?([^#]*))?(?:#([a-z0-9_-]+))?$~D', $value, $parts) !== 1) {
        return $fallback;
    }
    $pages = ['produkt.php', 'eshop.php', 'moje_osoby.php', 'registrace_sportovce.php',
        'sportovni_prehled.php', 'moje_programy.php', 'moje_objednavky.php', 'objednavka.php',
        'moje_rezervace.php', 'verejny_profil.php', 'kalendar.php', 'klubovy_kalendar.php',
        'krouzky.php', 'cyklisticke_krouzky.php', 'velodrom.php', 'rezervovat.php',
        'nahrady.php', 'treninky.php'];
    if (!in_array($parts[1], $pages, true)) {
        return $fallback;
    }
    $seen = [];
    foreach (($parts[2] ?? '') === '' ? [] : explode('&', $parts[2]) as $pair) {
        if (preg_match('/^([a-z_]+)=([^=]*)$/D', $pair, $parameter) !== 1
            || isset($seen[$parameter[1]])) {
            return $fallback;
        }
        $seen[$parameter[1]] = true;
        $decoded = rawurldecode($parameter[2]);
        if ($parameter[1] === 'return') {
            if (!in_array($parts[1], ['moje_osoby.php', 'registrace_sportovce.php'], true)
                || bookingPurchaseReturn($decoded) === '') {
                return $fallback;
            }
        } elseif (in_array($parameter[1], ['redirect', 'url', 'next'], true)
            || preg_match('/^[\p{L}\p{M}\p{N}_.:+ (),-]*$/uD', $decoded) !== 1) {
            return $fallback;
        }
    }
    return $value;
}

function bookingPurchaseReturn(mixed $value): string
{
    return is_string($value)
        && preg_match('~^(?:eshop\.php(?:#kosik)?|produkt\.php\?id=[1-9][0-9]*(?:&variant_id=[1-9][0-9]*)?(?:#varianta-[1-9][0-9]*)?)$~D', $value) === 1
        ? $value : '';
}

function bookingRegistrationDestination(mixed $value, string $purpose): string
{
    return bookingReturnDestination($value, $purpose === 'rodina' ? 'moje_osoby.php' : 'sportovni_prehled.php');
}
