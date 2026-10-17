<?php
declare(strict_types=1);

/** @return array{0:int,1:int}|null */
function clubLandingAgeRange(string $label): ?array
{
    if (preg_match('/(?<!\d)(\d{1,2})\s*[–-]\s*(\d{1,2})(?!\d)/u', $label, $match) === 1) {
        $from=(int)$match[1];$to=(int)$match[2];
        return $from <= $to && $to <= 99 ? [$from,$to] : null;
    }
    return null;
}
