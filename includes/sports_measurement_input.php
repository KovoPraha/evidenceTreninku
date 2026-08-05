<?php
declare(strict_types=1);

require_once __DIR__ . '/sports_measurement_contract.php';

/** @return list<string> */
function sportsMeasurementTypes(): array
{
    return ['kolo', 'beh', 'posilovna', 'kolo_krouzek', 'kolo_silnice', 'kolo_mtb'];
}

function sportsMeasurementOptionalText(mixed $value, int $maxLength, string $label): ?string
{
    $value = trim((string)$value);
    if ($value === '') return null;
    if (mb_strlen($value, 'UTF-8') > $maxLength) {
        throw new InvalidArgumentException($label . ' je příliš dlouhé.');
    }
    return $value;
}

function sportsMeasurementPositiveId(mixed $value): ?int
{
    if ($value === null || $value === '') return null;
    if (!ctype_digit((string)$value) || (int)$value < 1) {
        throw new InvalidArgumentException('Identifikátor musí být kladné celé číslo.');
    }
    return (int)$value;
}

/**
 * @param list<array<string,mixed>> $inputRows
 * @return list<array<string,mixed>>
 */
function sportsMeasurementRows(array $inputRows): array
{
    $result = [];
    foreach ($inputRows as $index => $input) {
        $rowNumber = $index + 1;
        if (!is_array($input)) {
            throw new InvalidArgumentException('Měření na řádku ' . $rowNumber . ': Řádek musí být datový objekt.');
        }
        try {
            $type = trim((string)($input['typ'] ?? ''));
            if ($type === '') continue;
            if (!in_array($type, sportsMeasurementTypes(), true)) {
                throw new InvalidArgumentException('Nepodporovaný typ měření.');
            }

            $athleteId = sportsMeasurementPositiveId($input['sportovec_id'] ?? null);
            $note = sportsMeasurementOptionalText($input['poznamka'] ?? null, 4000, 'Poznámka');
            $row = [
                'typ' => $type,
                'sportovec_id' => $athleteId,
                'vzdalenost' => null,
                'cas' => null,
                'prevod' => null,
                'cvik_id' => null,
                'segment_id' => null,
                'vaha' => null,
                'opakovani' => null,
                'rpe' => null,
                'poznamka' => $note,
                'contract_version' => SPORTS_MEASUREMENT_CONTRACT_VERSION,
                'distance_unit' => null,
                'distance_meters' => null,
                'duration_ms' => null,
                'rpe_value' => null,
            ];

            if ($type === 'kolo' || $type === 'beh') {
                $distanceRaw = trim((string)($input['vzdalenost'] ?? ''));
                $timeRaw = sportsMeasurementOptionalText($input['cas'] ?? null, 50, 'Čas');
                $gear = sportsMeasurementOptionalText($input['prevod'] ?? null, 100, 'Převod');
                $unit = strtolower(trim((string)($input['distance_unit'] ?? $input['vzdalenost_jednotka'] ?? '')));
                if ($distanceRaw === '' && $unit !== '') {
                    throw new InvalidArgumentException('Jednotka vzdálenosti je vyplněná bez vzdálenosti.');
                }
                if ($distanceRaw !== '' && preg_match('/^(?:0|[1-9][0-9]*)(?:[.,][0-9]{1,2})?$/D', $distanceRaw) !== 1) {
                    throw new InvalidArgumentException('Vzdálenost musí být kladné číslo s nejvýše dvěma desetinnými místy.');
                }
                if ($athleteId === null && $distanceRaw === '' && $timeRaw === null && $gear === null && $note === null) continue;

                $row['vzdalenost'] = $distanceRaw === '' ? null : (float)str_replace(',', '.', $distanceRaw);
                $row['cas'] = $timeRaw;
                $row['prevod'] = $type === 'kolo' ? $gear : null;
                $row['distance_unit'] = $distanceRaw === '' ? null : $unit;
                $row['distance_meters'] = sportsMeasurementDistanceMeters($distanceRaw, $distanceRaw === '' ? null : $unit);
                $row['duration_ms'] = sportsMeasurementDurationMilliseconds($timeRaw);
                $result[] = $row;
                continue;
            }

            if (in_array($type, ['kolo_krouzek', 'kolo_silnice', 'kolo_mtb'], true)) {
                $segmentId = sportsMeasurementPositiveId($input['segment_id'] ?? null);
                $timeRaw = sportsMeasurementOptionalText($input['cas'] ?? null, 50, 'Čas');
                if ($athleteId === null && $segmentId === null && $timeRaw === null && $note === null) continue;
                $row['segment_id'] = $segmentId;
                $row['cas'] = $timeRaw;
                $row['duration_ms'] = sportsMeasurementDurationMilliseconds($timeRaw);
                $result[] = $row;
                continue;
            }

            $exerciseId = sportsMeasurementPositiveId($input['cvik_id'] ?? null);
            $weightRaw = trim((string)($input['vaha'] ?? ''));
            if ($weightRaw !== '' && preg_match('/^(?:0|[1-9][0-9]*)(?:[.,][0-9]{1,2})?$/D', $weightRaw) !== 1) {
                throw new InvalidArgumentException('Váha musí být nezáporné číslo s nejvýše dvěma desetinnými místy.');
            }
            $repetitionsRaw = trim((string)($input['opakovani'] ?? ''));
            if ($repetitionsRaw !== '' && (!ctype_digit($repetitionsRaw) || (int)$repetitionsRaw < 1)) {
                throw new InvalidArgumentException('Počet opakování musí být kladné celé číslo.');
            }
            $rpeRaw = sportsMeasurementOptionalText($input['rpe'] ?? null, 50, 'RPE');
            if ($athleteId === null && $exerciseId === null && $weightRaw === '' && $repetitionsRaw === '' && $rpeRaw === null && $note === null) continue;
            $row['cvik_id'] = $exerciseId;
            $row['vaha'] = $weightRaw === '' ? null : (float)str_replace(',', '.', $weightRaw);
            $row['opakovani'] = $repetitionsRaw === '' ? null : (int)$repetitionsRaw;
            $row['rpe'] = $rpeRaw;
            $row['rpe_value'] = sportsMeasurementRpeValue($rpeRaw);
            $result[] = $row;
        } catch (InvalidArgumentException $exception) {
            throw new InvalidArgumentException('Měření na řádku ' . $rowNumber . ': ' . $exception->getMessage(), 0, $exception);
        }
    }
    return $result;
}

/** @return list<array<string,mixed>> */
function sportsMeasurementRowsFromPost(array $post): array
{
    if (array_key_exists('mereni_json', $post) && trim((string)$post['mereni_json']) !== '') {
        try {
            $decoded = json_decode((string)$post['mereni_json'], true, 128, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new InvalidArgumentException('Měření mají neplatný datový formát.', 0, $exception);
        }
        if (!is_array($decoded) || !array_is_list($decoded)) {
            throw new InvalidArgumentException('Měření musí být seznam řádků.');
        }
        return sportsMeasurementRows($decoded);
    }

    $types = $post['mereni_typ'] ?? [];
    if (!is_array($types)) return [];
    $fields = [
        'sportovec_id' => $post['mereni_sportovec_id'] ?? [],
        'vzdalenost' => $post['mereni_vzdalenost'] ?? $post['vzdalenost'] ?? [],
        'distance_unit' => $post['mereni_distance_unit'] ?? $post['vzdalenost_jednotka'] ?? [],
        'cas' => $post['mereni_cas'] ?? $post['cas'] ?? [],
        'prevod' => $post['mereni_prevod'] ?? $post['prevod'] ?? [],
        'cvik_id' => $post['cvik_id'] ?? $post['mereni_cvik_id'] ?? [],
        'segment_id' => $post['segment_id'] ?? $post['mereni_segment_id'] ?? [],
        'vaha' => $post['vaha'] ?? [],
        'opakovani' => $post['opakovani'] ?? [],
        'rpe' => $post['rpe'] ?? [],
        'poznamka' => $post['poznamka_posilovna'] ?? $post['poznamka_cviku'] ?? [],
    ];
    $rows = [];
    foreach (array_values($types) as $index => $type) {
        $row = ['typ' => $type];
        foreach ($fields as $key => $values) $row[$key] = is_array($values) ? ($values[$index] ?? null) : null;
        $rows[] = $row;
    }
    return sportsMeasurementRows($rows);
}

function sportsMeasurementInsertSql(): string
{
    return <<<'SQL'
        INSERT INTO mereni_zaznamy
            (typ, sportovec_id, vzdalenost, cas, prevod, cvik_id, segment_id, vaha, opakovani, rpe, poznamka,
             contract_version, distance_unit, distance_meters, duration_ms, rpe_value)
        VALUES
            (:typ, :sportovec_id, :vzdalenost, :cas, :prevod, :cvik_id, :segment_id, :vaha, :opakovani, :rpe, :poznamka,
             :contract_version, :distance_unit, :distance_meters, :duration_ms, :rpe_value)
        SQL;
}

/** @param array<string,mixed> $row @return array<string,mixed> */
function sportsMeasurementInsertParameters(array $row): array
{
    $parameters = [];
    foreach (['typ', 'sportovec_id', 'vzdalenost', 'cas', 'prevod', 'cvik_id', 'segment_id', 'vaha', 'opakovani', 'rpe', 'poznamka', 'contract_version', 'distance_unit', 'distance_meters', 'duration_ms', 'rpe_value'] as $key) {
        $parameters[':' . $key] = $row[$key] ?? null;
    }
    return $parameters;
}

/**
 * Fail-closed mapper for a future reviewed race-results import.
 * It does not write to the database.
 *
 * @return array{poradi:?int,cas:?string,body:?float,result_contract_version:string,result_status:string,result_time_ms:?int}
 */
function sportsRaceResultInput(array $input): array
{
    $status = sportsRaceResultStatus((string)($input['status'] ?? ''));
    if ($status === null) throw new InvalidArgumentException('Výsledek závodu musí mít výslovný stav.');
    $time = sportsMeasurementOptionalText($input['time'] ?? $input['cas'] ?? null, 50, 'Čas závodu');
    $timeMs = sportsMeasurementDurationMilliseconds($time);
    $positionRaw = trim((string)($input['position'] ?? $input['poradi'] ?? ''));
    if ($positionRaw !== '' && (!ctype_digit($positionRaw) || (int)$positionRaw < 1)) {
        throw new InvalidArgumentException('Pořadí musí být kladné celé číslo.');
    }
    if ($status !== 'finished' && ($timeMs !== null || $positionRaw !== '')) {
        throw new InvalidArgumentException('Čas a pořadí lze uložit pouze u dokončeného závodu.');
    }
    $pointsRaw = trim((string)($input['points'] ?? $input['body'] ?? ''));
    if ($pointsRaw !== '' && preg_match('/^-?(?:0|[1-9][0-9]*)(?:[.,][0-9]{1,2})?$/D', $pointsRaw) !== 1) {
        throw new InvalidArgumentException('Body musí být číslo s nejvýše dvěma desetinnými místy.');
    }
    return [
        'poradi' => $positionRaw === '' ? null : (int)$positionRaw,
        'cas' => $time,
        'body' => $pointsRaw === '' ? null : (float)str_replace(',', '.', $pointsRaw),
        'result_contract_version' => SPORTS_MEASUREMENT_CONTRACT_VERSION,
        'result_status' => $status,
        'result_time_ms' => $timeMs,
    ];
}
