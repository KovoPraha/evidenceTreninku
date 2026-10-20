<?php
declare(strict_types=1);
require_once __DIR__ . '/booking_return.php';

/**
 * Keep customer return links local to booking. Staff may use the fixed
 * workspace landing page only when no valid return link was requested.
 */
function publicLoginDestination(mixed $requested, bool $isStaff): string
{
    return bookingReturnDestination($requested, $isStaff ? '../pracovni_pozice.php' : 'sportovni_prehled.php');
}
