<?php
declare(strict_types=1);

/**
 * Keep customer return links local to booking. Staff may use the fixed
 * workspace landing page only when no valid return link was requested.
 */
function publicLoginDestination(mixed $requested, bool $isStaff): string
{
    if (is_string($requested)
        && preg_match('~^[a-z0-9_]+\.php(?:\?[^#\r\n]*)?(?:#[a-z0-9_-]+)?$~iD', $requested)) {
        return $requested;
    }

    return $isStaff ? '../pracovni_pozice.php' : 'sportovni_prehled.php';
}
