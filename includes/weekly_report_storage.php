<?php
declare(strict_types=1);
require_once __DIR__ . '/private_storage.php';
require_once __DIR__ . '/coach_scope.php';

function weeklyReportDirectory(): string
{
    $directory = privateStorageRoot() . '/week-reports';
    if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) throw new RuntimeException('Soukromé úložiště reportů není dostupné.');
    $resolved = realpath($directory);
    $root = realpath(privateStorageRoot());
    if ($resolved === false || $root === false || strcasecmp(str_replace('\\', '/', $resolved), str_replace('\\', '/', $root) . '/week-reports') !== 0) throw new RuntimeException('Neplatné soukromé úložiště reportů.');
    return $resolved;
}

function weeklyReportAssertAccess(PDO $pdo, array $trainingIds, array $personIds): void
{
    $actor = (int)($_SESSION['trener_id'] ?? 0);
    $position = staffActivePosition();
    if ($actor < 1 || !in_array($position, ['coach','sports_lead'], true)) throw new DomainException('Report není v této pracovní pozici dostupný.');
    // An explicit superadmin may select sports_lead without a duplicate position grant.
    // Verify the live database grant; the session flag alone never authorizes an archive.
    $statement = $pdo->prepare("SELECT 1 FROM treneri t WHERE t.id=? AND t.aktivni=1 AND (EXISTS(SELECT 1 FROM staff_user_positions p WHERE p.trainer_id=t.id AND p.position_code=?) OR (?='sports_lead' AND EXISTS(SELECT 1 FROM staff_superadmins a WHERE a.trainer_id=t.id)))");
    $statement->execute([$actor, $position, $position]);
    if (!$statement->fetchColumn()) throw new DomainException('Pracovní oprávnění již neplatí.');
    foreach (array_unique(array_map('intval', $personIds)) as $person) {
        if (!coachScopeCanViewPerson($pdo, $person)) throw new DomainException('Report obsahuje sportovce mimo aktuální pracovní rozsah.');
    }
    foreach (array_unique(array_map('intval', $trainingIds)) as $training) {
        $statement = $pdo->prepare('SELECT 1 FROM treninky t WHERE t.id=? AND ' . coachScopeTrainingPredicate($pdo));
        $statement->execute([$training]);
        if (!$statement->fetchColumn()) throw new DomainException('Report obsahuje trénink mimo aktuální pracovní rozsah.');
    }
}

/** Returns only a verified PDF; arbitrary paths and HTML downloads are not accepted. */
function weeklyReportFile(PDO $pdo, string $id): array
{
    if (!preg_match('/^[a-f0-9]{32}$/D', $id)) throw new DomainException('Report není dostupný.');
    $directory = weeklyReportDirectory() . '/' . $id;
    $real = realpath($directory);
    if ($real === false || strcasecmp(str_replace('\\', '/', $real), str_replace('\\', '/', $directory)) !== 0) throw new DomainException('Report není dostupný.');
    $manifest = json_decode((string)@file_get_contents($directory . '/manifest.json'), true);
    if (!is_array($manifest) || ($manifest['contract'] ?? '') !== 'weekly-report-private-v1' || ($manifest['id'] ?? '') !== $id || !is_array($manifest['training_ids'] ?? null) || !is_array($manifest['person_ids'] ?? null)) throw new DomainException('Report není dostupný.');
    weeklyReportAssertAccess($pdo, $manifest['training_ids'], $manifest['person_ids']);
    $path = $directory . '/report.pdf';
    if (!is_file($path) || is_link($path) || !hash_equals((string)($manifest['pdf_sha256'] ?? ''), (string)hash_file('sha256', $path))) throw new DomainException('Obsah reportu neodpovídá uloženému otisku.');
    return ['path' => $path, 'manifest' => $manifest];
}
