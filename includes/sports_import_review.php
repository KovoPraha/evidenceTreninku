<?php
declare(strict_types=1);

require_once __DIR__ . '/sports_measurement_contract.php';
require_once __DIR__ . '/sports_data_quality.php';

/**
 * M3.5d read-only review preparing a future one-time sports-data import.
 *
 * The review lists coverage of the sports-measurement-v1 contract and the
 * concrete legacy rows that cannot be converted deterministically. It never
 * writes, never guesses ambiguous historical values and never returns athlete
 * IDs or names. Raw technical measurement values are included on purpose so an
 * administrator can decide each ambiguous row manually.
 *
 * M3.5e adds a deterministic recognizer for the legacy free-text `mereni`
 * table (see sportsImportReviewClassifyLegacyTextRow() below). It only
 * classifies rows as recognized or ambiguous; it does not define or perform
 * any conversion.
 */

const SPORTS_IMPORT_REVIEW_ROW_LIMIT = 200;

function sportsImportReviewTrimValue(mixed $value): string
{
    $value = trim((string)$value);
    if (mb_strlen($value, 'UTF-8') > 80) {
        $value = mb_substr($value, 0, 79, 'UTF-8') . '…';
    }
    return $value;
}

/**
 * @param array<string,mixed> $row
 * @return array{fields:list<array{field:string,value:string,verdict:string,reason:string}>,ambiguous:bool,convertible:bool}
 */
function sportsImportReviewClassifyMeasurementRow(array $row): array
{
    $fields = [];
    $ambiguous = false;
    $hasValue = false;

    $distance = $row['vzdalenost'];
    if ($distance !== null && trim((string)$distance) !== '') {
        $hasValue = true;
        $ambiguous = true;
        $fields[] = [
            'field' => 'vzdalenost',
            'value' => sportsImportReviewTrimValue($distance),
            'verdict' => 'ambiguous',
            'reason' => 'Chybí výslovná jednotka m/km. Historické zobrazení používá km, převod ale vyžaduje ruční potvrzení jednotky.',
        ];
    }

    $time = trim((string)($row['cas'] ?? ''));
    if ($time !== '') {
        $hasValue = true;
        try {
            sportsMeasurementDurationMilliseconds($time);
            $fields[] = ['field' => 'cas', 'value' => sportsImportReviewTrimValue($time), 'verdict' => 'deterministic', 'reason' => 'Čas odpovídá striktnímu formátu a lze jej převést beze změny významu.'];
        } catch (InvalidArgumentException) {
            $ambiguous = true;
            $fields[] = ['field' => 'cas', 'value' => sportsImportReviewTrimValue($time), 'verdict' => 'ambiguous', 'reason' => 'Čas není ve striktním formátu MM:SS(.mmm) nebo HH:MM:SS(.mmm).'];
        }
    }

    $rpe = trim((string)($row['rpe'] ?? ''));
    if ($rpe !== '') {
        $hasValue = true;
        try {
            sportsMeasurementRpeValue($rpe);
            $fields[] = ['field' => 'rpe', 'value' => sportsImportReviewTrimValue($rpe), 'verdict' => 'deterministic', 'reason' => 'RPE je číslo 1,0–10,0 a lze je převést beze změny významu.'];
        } catch (InvalidArgumentException) {
            $ambiguous = true;
            $fields[] = ['field' => 'rpe', 'value' => sportsImportReviewTrimValue($rpe), 'verdict' => 'ambiguous', 'reason' => 'RPE není číslo od 1,0 do 10,0.'];
        }
    }

    return ['fields' => $fields, 'ambiguous' => $ambiguous, 'convertible' => $hasValue && !$ambiguous];
}

/**
 * @param array<string,mixed> $row
 * @return array{fields:list<array{field:string,value:string,verdict:string,reason:string}>}
 */
function sportsImportReviewClassifyRaceRow(array $row): array
{
    $fields = [[
        'field' => 'stav',
        'value' => '',
        'verdict' => 'ambiguous',
        'reason' => 'Chybí výslovný stav výsledku (entered/finished/dns/dnf/dsq); stav se u historie neodhaduje.',
    ]];

    $time = trim((string)($row['cas'] ?? ''));
    if ($time !== '') {
        try {
            sportsMeasurementDurationMilliseconds($time);
            $fields[] = ['field' => 'cas', 'value' => sportsImportReviewTrimValue($time), 'verdict' => 'deterministic', 'reason' => 'Čas odpovídá striktnímu formátu.'];
        } catch (InvalidArgumentException) {
            $fields[] = ['field' => 'cas', 'value' => sportsImportReviewTrimValue($time), 'verdict' => 'ambiguous', 'reason' => 'Čas závodu není ve striktním formátu MM:SS(.mmm) nebo HH:MM:SS(.mmm).'];
        }
    }
    if ($row['poradi'] !== null) {
        $fields[] = ['field' => 'poradi', 'value' => sportsImportReviewTrimValue($row['poradi']), 'verdict' => 'deterministic', 'reason' => 'Pořadí je celé číslo; u dokončeného závodu je převoditelné.'];
    }
    if ($row['body'] !== null) {
        $fields[] = ['field' => 'body', 'value' => sportsImportReviewTrimValue($row['body']), 'verdict' => 'deterministic', 'reason' => 'Body jsou číslo; u dokončeného závodu jsou převoditelné.'];
    }

    return ['fields' => $fields];
}

/**
 * M3.5e deterministic recognizer for the legacy free-text `mereni` table.
 *
 * Recognized patterns only: "<number> km", "<number> m", a strict duration
 * (same parser as sports-measurement-v1) or "<number> min". Anything else —
 * ranges, "cca" prefixes, multiple values, or an empty value — is ambiguous.
 * This never converts or stores a normalized value; it only classifies.
 */
const SPORTS_IMPORT_REVIEW_LEGACY_NUMBER = '(?:0|[1-9][0-9]*)(?:[.,][0-9]{1,3})?';

/** @return array{verdict:string,reason:string} */
function sportsImportReviewClassifyLegacyDistance(string $rawValue): array
{
    $value = trim($rawValue);
    if ($value === '') {
        return ['verdict' => 'ambiguous', 'reason' => 'Prázdná hodnota je vždy nejednoznačná a čeká na ruční rozhodnutí.'];
    }
    if (preg_match('/^' . SPORTS_IMPORT_REVIEW_LEGACY_NUMBER . '[ ]?(?:km|m)$/D', $value) === 1) {
        return ['verdict' => 'recognized', 'reason' => 'Hodnota odpovídá uznanému vzoru „<číslo> km“ nebo „<číslo> m“.'];
    }
    return ['verdict' => 'ambiguous', 'reason' => 'Hodnota neodpovídá vzoru „<číslo> km“ ani „<číslo> m“ (rozsah, „cca“, více hodnot nebo jiný zápis se nepřevádí odhadem).'];
}

/** @return array{verdict:string,reason:string} */
function sportsImportReviewClassifyLegacyTime(string $rawValue): array
{
    $value = trim($rawValue);
    if ($value === '') {
        return ['verdict' => 'ambiguous', 'reason' => 'Prázdná hodnota je vždy nejednoznačná a čeká na ruční rozhodnutí.'];
    }
    try {
        sportsMeasurementDurationMilliseconds($value);
        return ['verdict' => 'recognized', 'reason' => 'Čas odpovídá striktnímu formátu MM:SS(.mmm) nebo HH:MM:SS(.mmm).'];
    } catch (InvalidArgumentException) {
        // Not a strict duration; fall through to the "<number> min" pattern.
    }
    if (preg_match('/^' . SPORTS_IMPORT_REVIEW_LEGACY_NUMBER . '[ ]?min$/D', $value) === 1) {
        return ['verdict' => 'recognized', 'reason' => 'Hodnota odpovídá uznanému vzoru „<číslo> min“.'];
    }
    return ['verdict' => 'ambiguous', 'reason' => 'Hodnota neodpovídá striktnímu času ani vzoru „<číslo> min“ (rozsah, „cca“, více hodnot nebo jiný zápis se nepřevádí odhadem).'];
}

/**
 * @param array<string,mixed> $row
 * @return array{verdict:string,fields:list<array{field:string,value:string,verdict:string,reason:string}>}
 */
function sportsImportReviewClassifyLegacyTextRow(array $row): array
{
    $distance = sportsImportReviewClassifyLegacyDistance((string)($row['vzdalenost'] ?? ''));
    $time = sportsImportReviewClassifyLegacyTime((string)($row['cas'] ?? ''));

    $fields = [
        ['field' => 'vzdalenost', 'value' => sportsImportReviewTrimValue($row['vzdalenost'] ?? ''), 'verdict' => $distance['verdict'], 'reason' => $distance['reason']],
        ['field' => 'cas', 'value' => sportsImportReviewTrimValue($row['cas'] ?? ''), 'verdict' => $time['verdict'], 'reason' => $time['reason']],
    ];

    return [
        'verdict' => ($distance['verdict'] === 'recognized' && $time['verdict'] === 'recognized') ? 'recognized' : 'ambiguous',
        'fields' => $fields,
    ];
}

/**
 * @return array{
 *   generated_at:string,
 *   available:bool,
 *   measurements:array<string,mixed>,
 *   races:array<string,mixed>,
 *   legacy_text_table:array{available:bool,total:int,recognized_count:int,ambiguous_count:int,rows:list<array<string,mixed>>,truncated:bool}
 * }
 */
function sportsImportReview(PDO $pdo, int $rowLimit = SPORTS_IMPORT_REVIEW_ROW_LIMIT, ?DateTimeImmutable $now = null): array
{
    $now ??= new DateTimeImmutable('now', new DateTimeZone('Europe/Prague'));
    $rowLimit = max(1, $rowLimit);
    $quotedVersion = $pdo->quote(SPORTS_MEASUREMENT_CONTRACT_VERSION);

    $measurements = [
        'available' => false,
        'total' => 0,
        'v1_total' => 0,
        'v1_units' => ['m' => 0, 'km' => 0],
        'v1_with_duration' => 0,
        'v1_with_rpe' => 0,
        'legacy_with_values' => 0,
        'legacy_without_values' => 0,
        'convertible_count' => 0,
        'ambiguous_count' => 0,
        'ambiguous_rows' => [],
        'truncated' => false,
    ];
    $measurementsReady = sportsDataQualityHasTables($pdo, ['mereni_zaznamy', 'trenink_mereni', 'zavod_mereni', 'treninky', 'zavody'])
        && sportsDataQualityColumnExists($pdo, 'mereni_zaznamy', 'contract_version');
    if ($measurementsReady) {
        $measurements['available'] = true;
        $measurements['total'] = sportsDataQualityCount($pdo, 'SELECT COUNT(*) FROM mereni_zaznamy');
        $measurements['v1_total'] = sportsDataQualityCount($pdo, 'SELECT COUNT(*) FROM mereni_zaznamy WHERE contract_version=' . $quotedVersion);
        foreach (sportsMeasurementDistanceUnits() as $unit) {
            $measurements['v1_units'][$unit] = sportsDataQualityCount($pdo, 'SELECT COUNT(*) FROM mereni_zaznamy WHERE contract_version=' . $quotedVersion . ' AND distance_unit=' . $pdo->quote($unit));
        }
        $measurements['v1_with_duration'] = sportsDataQualityCount($pdo, 'SELECT COUNT(*) FROM mereni_zaznamy WHERE contract_version=' . $quotedVersion . ' AND duration_ms IS NOT NULL');
        $measurements['v1_with_rpe'] = sportsDataQualityCount($pdo, 'SELECT COUNT(*) FROM mereni_zaznamy WHERE contract_version=' . $quotedVersion . ' AND rpe_value IS NOT NULL');

        $legacyValueCondition = "contract_version IS NULL AND (vzdalenost IS NOT NULL OR (cas IS NOT NULL AND TRIM(cas)<>'') OR (rpe IS NOT NULL AND TRIM(rpe)<>''))";
        $measurements['legacy_with_values'] = sportsDataQualityCount($pdo, 'SELECT COUNT(*) FROM mereni_zaznamy WHERE ' . $legacyValueCondition);
        $measurements['legacy_without_values'] = sportsDataQualityCount($pdo, 'SELECT COUNT(*) FROM mereni_zaznamy WHERE contract_version IS NULL') - $measurements['legacy_with_values'];

        $statement = $pdo->query(
            'SELECT m.id, m.typ, m.vzdalenost, m.cas, m.rpe,'
            . ' (SELECT MIN(t.datum) FROM trenink_mereni tm JOIN treninky t ON t.id=tm.trenink_id WHERE tm.mereni_id=m.id) AS trenink_datum,'
            . ' (SELECT MIN(z.datum) FROM zavod_mereni zm JOIN zavody z ON z.id=zm.zavod_id WHERE zm.mereni_id=m.id) AS zavod_datum'
            . ' FROM mereni_zaznamy m WHERE ' . $legacyValueCondition . ' ORDER BY m.id'
        );
        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $classified = sportsImportReviewClassifyMeasurementRow($row);
            if ($classified['convertible']) {
                $measurements['convertible_count']++;
                continue;
            }
            if (!$classified['ambiguous']) continue;
            $measurements['ambiguous_count']++;
            if (count($measurements['ambiguous_rows']) >= $rowLimit) {
                $measurements['truncated'] = true;
                continue;
            }
            $measurements['ambiguous_rows'][] = [
                'id' => (int)$row['id'],
                'typ' => (string)$row['typ'],
                'kontext' => $row['trenink_datum'] !== null
                    ? ('trénink ' . (string)$row['trenink_datum'])
                    : ($row['zavod_datum'] !== null ? ('závod ' . (string)$row['zavod_datum']) : 'bez vazby'),
                'fields' => $classified['fields'],
            ];
        }
    }

    $races = [
        'available' => false,
        'total' => 0,
        'v1_total' => 0,
        'v1_statuses' => array_fill_keys(sportsRaceResultStatuses(), 0),
        'legacy_with_result' => 0,
        'legacy_without_result' => 0,
        'ambiguous_count' => 0,
        'ambiguous_rows' => [],
        'truncated' => false,
    ];
    $racesReady = sportsDataQualityHasTables($pdo, ['zavody', 'zavod_sportovec'])
        && sportsDataQualityColumnExists($pdo, 'zavod_sportovec', 'result_contract_version');
    if ($racesReady) {
        $races['available'] = true;
        $races['total'] = sportsDataQualityCount($pdo, 'SELECT COUNT(*) FROM zavod_sportovec');
        $races['v1_total'] = sportsDataQualityCount($pdo, 'SELECT COUNT(*) FROM zavod_sportovec WHERE result_contract_version=' . $quotedVersion);
        foreach (sportsRaceResultStatuses() as $status) {
            $races['v1_statuses'][$status] = sportsDataQualityCount($pdo, 'SELECT COUNT(*) FROM zavod_sportovec WHERE result_contract_version=' . $quotedVersion . ' AND result_status=' . $pdo->quote($status));
        }
        $legacyResultCondition = "result_contract_version IS NULL AND (poradi IS NOT NULL OR (cas IS NOT NULL AND TRIM(cas)<>'') OR body IS NOT NULL)";
        $races['legacy_with_result'] = sportsDataQualityCount($pdo, 'SELECT COUNT(*) FROM zavod_sportovec WHERE ' . $legacyResultCondition);
        $races['legacy_without_result'] = sportsDataQualityCount($pdo, 'SELECT COUNT(*) FROM zavod_sportovec WHERE result_contract_version IS NULL') - $races['legacy_with_result'];

        // Legacy zavod_sportovec has no surrogate primary key. Rows are referenced
        // by race ID plus a deterministic ordinal inside that race.
        $statement = $pdo->query(
            'SELECT zs.zavod_id, zs.poradi, zs.cas, zs.body, z.datum AS zavod_datum, z.kategorie AS zavod_kategorie'
            . ' FROM zavod_sportovec zs JOIN zavody z ON z.id=zs.zavod_id'
            . ' WHERE zs.result_contract_version IS NULL'
            . ' ORDER BY zs.zavod_id, zs.poradi IS NULL, zs.poradi, zs.cas IS NULL, zs.cas, zs.body IS NULL, zs.body'
        );
        $raceOrdinals = [];
        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $raceId = (int)$row['zavod_id'];
            $raceOrdinals[$raceId] = ($raceOrdinals[$raceId] ?? 0) + 1;
            $races['ambiguous_count']++;
            if (count($races['ambiguous_rows']) >= $rowLimit) {
                $races['truncated'] = true;
                continue;
            }
            $classified = sportsImportReviewClassifyRaceRow($row);
            $races['ambiguous_rows'][] = [
                'zavod_id' => $raceId,
                'radek' => $raceOrdinals[$raceId],
                'kontext' => trim('závod #' . $raceId . ' · ' . (string)$row['zavod_datum'] . ' ' . (string)($row['zavod_kategorie'] ?? '')),
                'fields' => $classified['fields'],
            ];
        }
    }

    $legacyTextTable = [
        'available' => false,
        'total' => 0,
        'recognized_count' => 0,
        'ambiguous_count' => 0,
        'rows' => [],
        'truncated' => false,
    ];
    if (sportsDataQualityHasTables($pdo, ['mereni', 'treninky'])) {
        $legacyTextTable['available'] = true;
        $legacyTextTable['total'] = sportsDataQualityCount($pdo, 'SELECT COUNT(*) FROM mereni');

        // Deterministic recognition only — see sportsImportReviewClassifyLegacyTextRow().
        // Never selects sportovec_id; the training date is the only linkage shown.
        $statement = $pdo->query(
            'SELECT m.id, m.vzdalenost, m.cas, t.datum AS trenink_datum'
            . ' FROM mereni m LEFT JOIN treninky t ON t.id = m.trenink_id'
            . ' ORDER BY m.id'
        );
        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $classified = sportsImportReviewClassifyLegacyTextRow($row);
            if ($classified['verdict'] === 'recognized') {
                $legacyTextTable['recognized_count']++;
            } else {
                $legacyTextTable['ambiguous_count']++;
            }
            if (count($legacyTextTable['rows']) >= $rowLimit) {
                $legacyTextTable['truncated'] = true;
                continue;
            }
            $legacyTextTable['rows'][] = [
                'id' => (int)$row['id'],
                'kontext' => 'trénink ' . (string)($row['trenink_datum'] ?? '—'),
                'verdict' => $classified['verdict'],
                'fields' => $classified['fields'],
            ];
        }
    }

    return [
        'generated_at' => $now->format('Y-m-d H:i:s'),
        'available' => $measurements['available'] && $races['available'],
        'measurements' => $measurements,
        'races' => $races,
        'legacy_text_table' => $legacyTextTable,
    ];
}
