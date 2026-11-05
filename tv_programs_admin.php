<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/csrf_helper.php';
require_once __DIR__ . '/includes/tv_program.php';
if (!isset($pdo) || !$pdo instanceof PDO) {
    throw new RuntimeException('Databázové připojení není dostupné.');
}
staffRequireActivePosition('tv_operator');
header('Cache-Control: no-store, private');
header('X-Robots-Tag: noindex, nofollow, noarchive');
$actorId = (int)$_SESSION['trener_id'];
$error = '';
$jsonRequest = str_contains((string)($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json');
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!is_string($_POST['csrf_token'] ?? null) || !csrf_verify($_POST['csrf_token'])) {
            throw new InvalidArgumentException('Formulář vypršel. Obnovte stránku.');
        }
        $action = is_string($_POST['action'] ?? null) ? $_POST['action'] : '';
        if ($action === 'create') {
            $id = tvCreateProgram($pdo, tvText($_POST['name'] ?? '', 120, true), $actorId);
            header('Location: ' . appUiUrl('tv_programs_admin.php?id=' . $id), true, 303);
            exit;
        }
        if ($action === 'upload') {
            $file = $_FILES['image'] ?? [];
            if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK
                || !is_string($file['tmp_name'] ?? null)) {
                throw new InvalidArgumentException('Obrázek se nepodařilo nahrát. Ověřte velikost souboru a zkuste to znovu.');
            }
            $result = tvUploadAsset($pdo, $file['tmp_name'], $actorId);
        } else {
            $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            $version = filter_var($_POST['version'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if ($id === false || $version === false) {
                throw new InvalidArgumentException('Chybí platný program nebo jeho verze.');
            }
            $document = null;
            if (in_array($action, ['save', 'publish'], true)) {
                if (!is_string($_POST['document'] ?? null)) {
                    throw new InvalidArgumentException('Chybí obsah programu.');
                }
                $document = tvDecodeDocument($_POST['document']);
            }
            $revision = filter_var($_POST['revision_id'] ?? 0, FILTER_VALIDATE_INT);
            $result = tvMutateProgram($pdo, $id, $version, $actorId, $action, $document, (int)$revision);
        }
        if ($jsonRequest) {
            header('Content-Type: application/json; charset=utf-8');
            echo tvJson(['ok' => true, 'result' => $result]);
            exit;
        }
        header('Location: ' . appUiUrl('tv_programs_admin.php'), true, 303);
        exit;
    } catch (Throwable $exception) {
        $expected = $exception instanceof InvalidArgumentException || $exception instanceof SecureUploadException
            || $exception instanceof JsonException || $exception instanceof TvConflictException;
        $error = $expected ? $exception->getMessage() : 'Operaci se nepodařilo dokončit. Ověřte stav programu a zkuste to znovu.';
        if (!$expected) {
            error_log('TV administration failed: ' . get_class($exception));
        }
        http_response_code($exception instanceof TvConflictException ? 409 : ($expected ? 400 : 500));
        if ($jsonRequest) {
            header('Content-Type: application/json; charset=utf-8');
            echo tvJson(['ok' => false, 'message' => $error]);
            exit;
        }
    }
}
$id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$program = $id === false ? null : tvProgram($pdo, $id);
if (isset($_GET['id']) && $program === null) {
    http_response_code(404);
    $error = 'Program nebyl nalezen.';
}
$programs = $pdo->query('SELECT id,name,archived,published_revision_id,lock_version,updated_at FROM tv_programs ORDER BY archived,id')
    ->fetchAll(PDO::FETCH_ASSOC);
$revisions = [];
$events = [];
$boot = null;
if ($program !== null) {
    $history = $pdo->prepare('SELECT r.id,r.created_at,t.jmeno FROM tv_program_revisions r JOIN treneri t ON t.id=r.created_by '
        . 'WHERE r.program_id=? ORDER BY r.id DESC LIMIT 30');
    $history->execute([$id]);
    $revisions = $history->fetchAll(PDO::FETCH_ASSOC);
    $history = $pdo->prepare('SELECT e.action,e.created_at,t.jmeno FROM tv_program_events e JOIN treneri t ON t.id=e.actor_id '
        . 'WHERE e.program_id=? ORDER BY e.id DESC LIMIT 20');
    $history->execute([$id]);
    $events = $history->fetchAll(PDO::FETCH_ASSOC);
    $boot = [
        'id' => (int)$program['id'], 'version' => (int)$program['lock_version'],
        'archived' => (bool)$program['archived'], 'document' => tvDecodeDocument((string)$program['draft_json']),
        'csrf' => csrf_token(), 'endpoint' => appUiUrl('tv_programs_admin.php'),
        'image_base' => appUiUrl('tv_image.php?preview=1&id='),
    ];
}
require __DIR__ . '/includes/tv_admin_page.php';
tvRenderAdminPage($pdo, $programs, $error, $program, $boot, $revisions, $events);
