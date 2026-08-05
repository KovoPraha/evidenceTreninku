<?php
declare(strict_types=1);

/**
 * M3.5b contract for newly imported or newly normalized sports data.
 *
 * Legacy text values are deliberately not parsed automatically. Callers must
 * supply an explicit unit and a value matching the documented format.
 */
const SPORTS_MEASUREMENT_CONTRACT_VERSION = 'sports-measurement-v1';

/** @return list<string> */
function sportsMeasurementDistanceUnits(): array
{
    return ['m', 'km'];
}

/**
 * @param int|float|string|null $value
 */
function sportsMeasurementDistanceMeters(int|float|string|null $value, ?string $unit): ?float
{
    if ($value === null || (is_string($value) && trim($value) === '')) return null;
    $unit = strtolower(trim((string)$unit));
    if (!in_array($unit, sportsMeasurementDistanceUnits(), true)) {
        throw new InvalidArgumentException('Vzdálenost musí mít výslovnou jednotku m nebo km.');
    }

    $normalized = is_string($value) ? str_replace(',', '.', trim($value)) : (string)$value;
    if (preg_match('/^(?:0|[1-9][0-9]*)(?:\.[0-9]{1,3})?$/D', $normalized) !== 1) {
        throw new InvalidArgumentException('Vzdálenost musí být kladné číslo s nejvýše třemi desetinnými místy.');
    }
    $number = (float)$normalized;
    if (!is_finite($number) || $number <= 0) {
        throw new InvalidArgumentException('Vzdálenost musí být větší než nula.');
    }

    $meters = $unit === 'km' ? $number * 1000 : $number;
    if ($meters > 9999999999.99) {
        throw new InvalidArgumentException('Vzdálenost překračuje podporovaný rozsah.');
    }
    return round($meters, 2);
}

/**
 * Strict formats: MM:SS, MM:SS.mmm, HH:MM:SS or HH:MM:SS.mmm.
 * Minutes in the two-part form may exceed 59. Empty input means no duration.
 */
function sportsMeasurementDurationMilliseconds(?string $value): ?int
{
    $value = trim((string)$value);
    if ($value === '') return null;
    if (preg_match('/^(?:(?<hours>[0-9]{1,3}):(?<minutes>[0-5][0-9])|(?<total_minutes>[0-9]{1,5})):(?<seconds>[0-5][0-9])(?:\.(?<fraction>[0-9]{1,3}))?$/D', $value, $match) !== 1) {
        throw new InvalidArgumentException('Čas musí mít formát MM:SS(.mmm) nebo HH:MM:SS(.mmm).');
    }

    $hours = isset($match['hours']) && $match['hours'] !== '' ? (int)$match['hours'] : 0;
    $minutes = isset($match['total_minutes']) && $match['total_minutes'] !== ''
        ? (int)$match['total_minutes']
        : (int)($match['minutes'] ?? 0);
    $seconds = (int)$match['seconds'];
    $fraction = str_pad((string)($match['fraction'] ?? ''), 3, '0');
    $milliseconds = (($hours * 3600) + ($minutes * 60) + $seconds) * 1000 + (int)$fraction;
    if ($milliseconds <= 0) {
        throw new InvalidArgumentException('Čas musí být větší než nula.');
    }
    return $milliseconds;
}

/** @param int|float|string|null $value */
function sportsMeasurementRpeValue(int|float|string|null $value): ?float
{
    if ($value === null || (is_string($value) && trim($value) === '')) return null;
    $normalized = is_string($value) ? str_replace(',', '.', trim($value)) : (string)$value;
    if (preg_match('/^(?:[1-9](?:\.[0-9])?|10(?:\.0)?)$/D', $normalized) !== 1) {
        throw new InvalidArgumentException('RPE musí být číslo od 1,0 do 10,0 s nejvýše jedním desetinným místem.');
    }
    return round((float)$normalized, 1);
}

/** @return list<string> */
function sportsRaceResultStatuses(): array
{
    return ['entered', 'finished', 'dns', 'dnf', 'dsq'];
}

function sportsRaceResultStatus(?string $status): ?string
{
    $status = strtolower(trim((string)$status));
    if ($status === '') return null;
    if (!in_array($status, sportsRaceResultStatuses(), true)) {
        throw new InvalidArgumentException('Nepodporovaný stav výsledku závodu.');
    }
    return $status;
}
