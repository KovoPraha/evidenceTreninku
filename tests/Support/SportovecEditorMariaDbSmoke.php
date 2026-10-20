<?php
declare(strict_types=1);
require_once __DIR__ . '/SportovecEditorDetailsFixture.php';
$port = getenv('EVIDENCE_MARIADB_SMOKE_PORT') ?: '3306';
if (!ctype_digit($port)) throw new RuntimeException('Invalid test port.');
$server = new PDO('mysql:host=127.0.0.1;port=' . $port . ';charset=utf8mb4', 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$database = 'evidence_athlete_editor_smoke_test';
$server->exec("DROP DATABASE IF EXISTS `$database`");
$server->exec("CREATE DATABASE `$database` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
$check = static function (bool $value, string $label): void { if (!$value) throw new RuntimeException($label); };
try {
    $pdo = new PDO('mysql:host=127.0.0.1;port=' . $port . ';dbname=' . $database . ';charset=utf8mb4', 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false]);
    $fixture = sportovecEditorDetailsFixture($pdo);
    $migration = require dirname(__DIR__, 2) . '/migrations/20261020220000_athlete_editor_relations.php';
    $migration['up']($pdo); $check($migration['verify']($pdo), 'idempotent migration');
    $check(personSensitiveAdminReveal($pdo, $fixture['old_record_id'], 'LOCALHOST původní šifrovaný údaj', $fixture['keys']) === '850101/0001', 'original registration context');
    $digits = null;
    for ($n = 0; $n < 10000; $n++) { $candidate = '100102' . str_pad((string)$n, 4, '0', STR_PAD_LEFT); if ((int)$candidate % 11 === 0) { $digits = $candidate; break; } }
    if ($digits === null) throw new RuntimeException('No test number');
    sportovecEditorSaveBirthNumber($pdo, 10, $digits, '', 'LOCALHOST doplnění RČ v detailu', 7, $fixture['keys']);
    $row = $pdo->query('SELECT * FROM osoba_citlive_udaje WHERE sportovec_id=10')->fetch();
    $check($row['request_id'] === null && personSensitiveDecryptForAdmin($row, $fixture['keys']) === $digits, 'canonical person context');
    try { sportovecEditorSaveBirthNumber($pdo, 11, $digits, '', 'LOCALHOST duplicitní RČ', 7, $fixture['keys']); throw new RuntimeException('Duplicate accepted'); }
    catch (PersonSensitiveException) { $check(sportovecEditorPerson($pdo, 11)['narozeni'] === null, 'duplicate rollback'); }
    sportovecEditorSaveParent($pdo, 10, 'link10', ['parent_name' => 'LOCALHOST Změna', 'parent_emails' => 'contact@example.test', 'parent_phones' => '456', 'relationship' => 'matka'], 7);
    $check(count(sportovecEditorContext($pdo, 10)['parents']) === 1, 'parent alias grouping');
    sportovecEditorSaveParent($pdo, 10, '', ['parent_name' => 'LOCALHOST Nový', 'parent_emails' => 'manual@example.test', 'parent_phones' => '123', 'relationship' => 'otec'], 7);
    $check((int)$pdo->query('SELECT COUNT(*) FROM kis_person_contacts WHERE batch_fingerprint IS NULL')->fetchColumn() === 1, 'manual parent without fake source');
    sportovecEditorUnlinkParent($pdo, 10, 'link10', 7);
    $check($pdo->query('SELECT status FROM account_person_roles WHERE sportovec_id=10')->fetchColumn() === 'revoked', 'parent access revoked');
    $check($pdo->query('SELECT status FROM account_person_roles WHERE sportovec_id=11')->fetchColumn() === 'approved', 'sibling access preserved');
    sportovecEditorAddRoster($pdo, 10, 1, 7, '2026-10-20', 'LOCALHOST soupiska');
    sportovecEditorAddRoster($pdo, 10, 1, 7, '2026-10-20', 'LOCALHOST opakování');
    $check((int)$pdo->query('SELECT COUNT(*) FROM club_roster_members')->fetchColumn() === 1, 'roster idempotency');
    sportovecEditorPaymentFixture($pdo);
    $payments = sportovecEditorPayments($pdo, 10);
    $check(count($payments['charges']) === 2 && count($payments['orders']) === 2 && count($payments['receipts']) === 1, 'all assigned payment scopes');
    $check((int)$payments['orders'][0]['athlete_items_minor'] === 3000, 'shared order attribution');
    echo json_encode(['ok' => true, 'migration' => true, 'encrypted_birth_number' => true, 'parents' => true, 'roster' => true, 'payments' => true], JSON_THROW_ON_ERROR) . PHP_EOL;
} finally { $server->exec("DROP DATABASE IF EXISTS `$database`"); }
