<?php
declare(strict_types=1);

/**
 * M3.5a read-only inventory of sports-data quality.
 *
 * The report deliberately returns aggregates only. It does not return athlete
 * IDs, names, notes, measurements or health-related values.
 */

function sportsDataQualityTableExists(PDO $pdo, string $table): bool
{
    if (preg_match('/^[a-z0-9_]+$/D', $table) !== 1) return false;
    if ((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') {
        $statement = $pdo->prepare('SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? LIMIT 1');
        $statement->execute([$table]);
        return (bool)$statement->fetchColumn();
    }
    $statement = $pdo->prepare("SELECT 1 FROM sqlite_master WHERE type='table' AND name=? LIMIT 1");
    $statement->execute([$table]);
    return (bool)$statement->fetchColumn();
}

/** @param list<string> $tables */
function sportsDataQualityHasTables(PDO $pdo, array $tables): bool
{
    foreach ($tables as $table) if (!sportsDataQualityTableExists($pdo, $table)) return false;
    return true;
}

function sportsDataQualityCount(PDO $pdo, string $sql): int
{
    return (int)$pdo->query($sql)->fetchColumn();
}

/** @return array<string,mixed> */
function sportsDataQualitySource(string $key, string $label, string $classification, string $access, string $description): array
{
    return [
        'key' => $key,
        'label' => $label,
        'classification' => $classification,
        'access' => $access,
        'description' => $description,
        'available' => true,
        'record_count' => 0,
        'status' => 'empty',
        'metrics' => [],
        'findings' => [],
    ];
}

/** @param array<string,mixed> $source */
function sportsDataQualityMetric(array &$source, string $label, int $value, string $detail = ''): void
{
    $source['metrics'][] = compact('label', 'value', 'detail');
}

/** @param array<string,mixed> $source */
function sportsDataQualityFinding(array &$source, string $key, string $label, int $count, string $detail, string $severity = 'warning'): void
{
    if ($count < 1) return;
    $source['findings'][] = compact('key', 'label', 'count', 'detail', 'severity');
}

/** @param array<string,mixed> $source */
function sportsDataQualityFinish(array &$source): void
{
    if (!$source['available']) {
        $source['status'] = 'unavailable';
    } elseif ($source['findings'] !== []) {
        $source['status'] = 'warning';
    } elseif ((int)$source['record_count'] > 0) {
        $source['status'] = 'good';
    } else {
        $source['status'] = 'empty';
    }
}

/**
 * @return array{
 *   generated_at:string,
 *   sources:array<string,array<string,mixed>>,
 *   unavailable:list<string>,
 *   finding_count:int,
 *   total_records:int
 * }
 */
function sportsDataQualityInventory(PDO $pdo, ?DateTimeImmutable $now = null): array
{
    $now ??= new DateTimeImmutable('now', new DateTimeZone('Europe/Prague'));
    $sources = [];
    $unavailable = [];

    $training = sportsDataQualitySource(
        'training_attendance',
        'Tréninky a docházka',
        'interní sportovní údaje',
        'detail podle stávajících oprávnění trenéra; tento přehled pouze správce',
        'Původní Evidence: tréninky, jejich kategorie, délka a vazba přítomných sportovců.'
    );
    if (!sportsDataQualityHasTables($pdo, ['treninky', 'trenink_sportovec', 'sportovci'])) {
        $training['available'] = false;
        $unavailable[] = $training['label'];
    } else {
        $training['record_count'] = sportsDataQualityCount($pdo, 'SELECT COUNT(*) FROM treninky');
        sportsDataQualityMetric($training, 'Záznamy docházky', sportsDataQualityCount($pdo, 'SELECT COUNT(*) FROM trenink_sportovec'));
        sportsDataQualityMetric($training, 'Sportovci s docházkou', sportsDataQualityCount($pdo, 'SELECT COUNT(DISTINCT sportovec_id) FROM trenink_sportovec'));
        sportsDataQualityFinding($training, 'missing_category', 'Tréninky bez kategorie', sportsDataQualityCount($pdo, "SELECT COUNT(*) FROM treninky WHERE kategorie IS NULL OR TRIM(kategorie)=''"), 'Bez kategorie nelze bezpečně porovnávat typy tréninku.');
        sportsDataQualityFinding($training, 'missing_duration', 'Tréninky bez kladné délky', sportsDataQualityCount($pdo, 'SELECT COUNT(*) FROM treninky WHERE delka IS NULL OR delka<=0'), 'Délka se v aplikaci interpretuje jako hodiny, databáze ale jednotku samostatně neukládá.');
        sportsDataQualityFinding($training, 'broken_attendance_link', 'Neplatné vazby docházky', sportsDataQualityCount($pdo, 'SELECT COUNT(*) FROM trenink_sportovec ts LEFT JOIN treninky t ON t.id=ts.trenink_id LEFT JOIN sportovci s ON s.id=ts.sportovec_id WHERE t.id IS NULL OR s.id IS NULL'), 'Vazba neukazuje na existující trénink nebo sportovce.', 'danger');
        sportsDataQualityFinding($training, 'implicit_duration_unit', 'Jednotka délky je pouze konvence UI', 1, 'Sloupec delka nemá vlastní jednotku ani verzi měřicího kontraktu.');
    }
    sportsDataQualityFinish($training);
    $sources[$training['key']] = $training;

    $measurements = sportsDataQualitySource(
        'structured_measurements',
        'Strukturovaná měření',
        'citlivé sportovní údaje',
        'detail podle oprávnění ke sportovci; tento přehled pouze správce a bez hodnot',
        'Měření u tréninků a závodů: vzdálenost, čas, převod, zátěž, opakování a RPE.'
    );
    if (!sportsDataQualityHasTables($pdo, ['mereni_zaznamy', 'trenink_mereni', 'zavod_mereni'])) {
        $measurements['available'] = false;
        $unavailable[] = $measurements['label'];
    } else {
        $measurements['record_count'] = sportsDataQualityCount($pdo, 'SELECT COUNT(*) FROM mereni_zaznamy');
        sportsDataQualityMetric($measurements, 'Přiřazeno sportovci', sportsDataQualityCount($pdo, 'SELECT COUNT(*) FROM mereni_zaznamy WHERE sportovec_id IS NOT NULL'));
        sportsDataQualityMetric($measurements, 'Navázáno na trénink nebo závod', sportsDataQualityCount($pdo, 'SELECT COUNT(*) FROM mereni_zaznamy m WHERE EXISTS(SELECT 1 FROM trenink_mereni tm WHERE tm.mereni_id=m.id) OR EXISTS(SELECT 1 FROM zavod_mereni zm WHERE zm.mereni_id=m.id)'));
        $distanceRows = sportsDataQualityCount($pdo, 'SELECT COUNT(*) FROM mereni_zaznamy WHERE vzdalenost IS NOT NULL');
        sportsDataQualityMetric($measurements, 'Záznamy se vzdáleností', $distanceRows);
        sportsDataQualityFinding($measurements, 'unlinked_measurement', 'Měření bez tréninku nebo závodu', sportsDataQualityCount($pdo, 'SELECT COUNT(*) FROM mereni_zaznamy m WHERE NOT EXISTS(SELECT 1 FROM trenink_mereni tm WHERE tm.mereni_id=m.id) AND NOT EXISTS(SELECT 1 FROM zavod_mereni zm WHERE zm.mereni_id=m.id)'), 'Záznam není součástí žádné evidované aktivity.');
        sportsDataQualityFinding($measurements, 'missing_athlete', 'Měření bez konkrétního sportovce', sportsDataQualityCount($pdo, 'SELECT COUNT(*) FROM mereni_zaznamy WHERE sportovec_id IS NULL'), 'Skupinové měření může být záměrné, ale nelze z něj odvodit individuální vývoj.');
        sportsDataQualityFinding($measurements, 'ambiguous_distance_unit', 'Vzdálenost bez uložené jednotky', $distanceRows, 'Formulář připouští km nebo m, ale databáze jednotku nerozlišuje.', 'danger');
        sportsDataQualityFinding($measurements, 'freeform_time_rpe', 'Čas a RPE nejsou normalizované', 1, 'Čas i RPE jsou textové; nejdřív je nutný verzovaný formát a validace, teprve potom porovnávání.');
    }
    sportsDataQualityFinish($measurements);
    $sources[$measurements['key']] = $measurements;

    $legacy = sportsDataQualitySource(
        'legacy_measurements',
        'Starší textová měření',
        'citlivé sportovní údaje',
        'pouze správce; hodnoty tento přehled nezobrazuje',
        'Historická tabulka měření používající volný text pro vzdálenost a čas.'
    );
    if (!sportsDataQualityHasTables($pdo, ['mereni', 'treninky', 'sportovci'])) {
        $legacy['available'] = false;
        $unavailable[] = $legacy['label'];
    } else {
        $legacy['record_count'] = sportsDataQualityCount($pdo, 'SELECT COUNT(*) FROM mereni');
        sportsDataQualityMetric($legacy, 'Řádky s uvedenou vzdáleností', sportsDataQualityCount($pdo, "SELECT COUNT(*) FROM mereni WHERE vzdalenost IS NOT NULL AND TRIM(vzdalenost)<>''"));
        sportsDataQualityMetric($legacy, 'Řádky s uvedeným časem', sportsDataQualityCount($pdo, "SELECT COUNT(*) FROM mereni WHERE cas IS NOT NULL AND TRIM(cas)<>''"));
        sportsDataQualityFinding($legacy, 'broken_legacy_link', 'Neplatné vazby starších měření', sportsDataQualityCount($pdo, 'SELECT COUNT(*) FROM mereni m LEFT JOIN treninky t ON t.id=m.trenink_id LEFT JOIN sportovci s ON s.id=m.sportovec_id WHERE t.id IS NULL OR s.id IS NULL'), 'Historický řádek neukazuje na existující trénink nebo sportovce.', 'danger');
        sportsDataQualityFinding($legacy, 'legacy_freeform_contract', 'Vzdálenost a čas jsou volný text', (int)$legacy['record_count'] > 0 ? 1 : 0, 'Před převodem je nutné určit formát, jednotku a pravidla pro nejednoznačné hodnoty.');
    }
    sportsDataQualityFinish($legacy);
    $sources[$legacy['key']] = $legacy;

    $races = sportsDataQualitySource(
        'race_results',
        'Výsledky závodů',
        'interní a případně veřejné sportovní údaje',
        'detail podle stávajících oprávnění; tento přehled pouze správce',
        'Účasti a výsledky: pořadí, čas a body v původním modulu Evidence.'
    );
    if (!sportsDataQualityHasTables($pdo, ['zavody', 'zavod_sportovec'])) {
        $races['available'] = false;
        $unavailable[] = $races['label'];
    } else {
        $races['record_count'] = sportsDataQualityCount($pdo, 'SELECT COUNT(*) FROM zavod_sportovec');
        sportsDataQualityMetric($races, 'Řádky s výsledkem', sportsDataQualityCount($pdo, "SELECT COUNT(*) FROM zavod_sportovec WHERE poradi IS NOT NULL OR (cas IS NOT NULL AND TRIM(cas)<>'') OR body IS NOT NULL"));
        sportsDataQualityMetric($races, 'Externí účastníci bez klubového profilu', sportsDataQualityCount($pdo, 'SELECT COUNT(*) FROM zavod_sportovec WHERE sportovec_id IS NULL'));
        sportsDataQualityFinding($races, 'missing_race_result', 'Účasti bez výsledku', sportsDataQualityCount($pdo, "SELECT COUNT(*) FROM zavod_sportovec WHERE poradi IS NULL AND (cas IS NULL OR TRIM(cas)='') AND body IS NULL"), 'Může jít o startovní listinu, DNS/DNF nebo dosud nedoplněný výsledek; stav se nerozlišuje.');
        sportsDataQualityFinding($races, 'freeform_race_time', 'Čas závodu je volný text', sportsDataQualityCount($pdo, "SELECT COUNT(*) FROM zavod_sportovec WHERE cas IS NOT NULL AND TRIM(cas)<>''"), 'Bez normalizovaného času a výsledkového stavu nelze bezpečně počítat progres.');
    }
    sportsDataQualityFinish($races);
    $sources[$races['key']] = $races;

    $tests = sportsDataQualitySource(
        'stress_tests',
        'Zátěžové testy',
        'zvlášť citlivé zdravotně související údaje',
        'agregace pouze správce; detail zůstává v chráněné kartě sportovce',
        'Datum testu, věk, hmotnost, výška, interní text a soukromé přílohy.'
    );
    if (!sportsDataQualityHasTables($pdo, ['zatezove_testy', 'zatezove_testy_soubory', 'sportovci'])) {
        $tests['available'] = false;
        $unavailable[] = $tests['label'];
    } else {
        $tests['record_count'] = sportsDataQualityCount($pdo, 'SELECT COUNT(*) FROM zatezove_testy');
        sportsDataQualityMetric($tests, 'Soukromé přílohy', sportsDataQualityCount($pdo, 'SELECT COUNT(*) FROM zatezove_testy_soubory'));
        sportsDataQualityMetric($tests, 'Testy s výškou i hmotností', sportsDataQualityCount($pdo, 'SELECT COUNT(*) FROM zatezove_testy WHERE vaha_kg IS NOT NULL AND vyska_cm IS NOT NULL'));
        sportsDataQualityFinding($tests, 'broken_test_link', 'Testy bez existujícího sportovce', sportsDataQualityCount($pdo, 'SELECT COUNT(*) FROM zatezove_testy z LEFT JOIN sportovci s ON s.id=z.sportovec_id WHERE s.id IS NULL'), 'Citlivý test nesmí zůstat bez vlastníka.', 'danger');
        sportsDataQualityFinding($tests, 'orphan_test_file', 'Přílohy bez existujícího testu', sportsDataQualityCount($pdo, 'SELECT COUNT(*) FROM zatezove_testy_soubory f LEFT JOIN zatezove_testy z ON z.id=f.test_id WHERE z.id IS NULL'), 'Soukromá příloha nemá platný nadřazený záznam.', 'danger');
        sportsDataQualityFinding($tests, 'sensitive_data_policy', 'Chybí samostatný kontrakt retence a účelu', (int)$tests['record_count'] > 0 ? 1 : 0, 'Před rozšiřováním analýz je nutné schválit účel, přístupy, retenci a výmaz.');
    }
    sportsDataQualityFinish($tests);
    $sources[$tests['key']] = $tests;

    $findingCount = 0;
    $totalRecords = 0;
    foreach ($sources as $source) {
        $findingCount += count($source['findings']);
        $totalRecords += (int)$source['record_count'];
    }

    return [
        'generated_at' => $now->format('Y-m-d H:i:s'),
        'sources' => $sources,
        'unavailable' => array_values(array_unique($unavailable)),
        'finding_count' => $findingCount,
        'total_records' => $totalRecords,
    ];
}
