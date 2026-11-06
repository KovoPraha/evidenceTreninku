<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/csrf_helper.php';
require_once __DIR__ . '/includes/staff_notifications.php';
require_once __DIR__ . '/includes/staff_workspaces.php';
require_once __DIR__ . '/includes/ui_shell.php';
header('Cache-Control: no-store, private');
if (!isset($_SESSION['trener_id'])) {
    $returnTo = 'staff_notifications.php';
    if (isset($_GET['notice']) && is_string($_GET['notice']) && preg_match('/^[0-9]{1,10}$/D', $_GET['notice'])) {
        $returnTo .= '?notice=' . $_GET['notice'];
    }
    header('Location: login.php?return_to=' . rawurlencode($returnTo), true, 302);
    exit;
}
$actor = (int)$_SESSION['trener_id'];
if (!isset($pdo) || !$pdo instanceof PDO) {
    http_response_code(503);
    exit('Upozornění nyní nejsou dostupná.');
}
$active = $pdo->prepare('SELECT 1 FROM treneri WHERE id=? AND aktivni=1');
$active->execute([$actor]);
if (!$active->fetchColumn()) {
    http_response_code(403);
    exit('Pracovní účet není aktivní.');
}
$error = '';
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!csrf_verify((string)($_POST['csrf_token'] ?? ''))) {
        http_response_code(403);
        exit('Formulář vypršel. Obnovte stránku.');
    }
    try {
        $action = (string)($_POST['action'] ?? '');
        if (!in_array($action, ['read', 'unread', 'open'], true)) {
            throw new DomainException('Neplatná akce.');
        }
        $notice = staffNotificationRead($pdo, $actor, (int)($_POST['notice_id'] ?? 0), $action !== 'unread');
        if ($action === 'open') {
            $def = staffNotificationType($notice['event_type']);
            staffWorkspaceRefreshSession($pdo, $actor);
            staffSwitchPosition($pdo, $actor, $def['position'], 'Otevření pracovního upozornění');
            header('Location: ' . $def['route'] . '?request_id=' . (int)$notice['source_id'], true, 303);
            exit;
        }
        header('Location: staff_notifications.php', true, 303);
        exit;
    } catch (DomainException | InvalidArgumentException $e) {
        http_response_code(403);
        $error = $e->getMessage();
    }
}
$page = max(1, (int)($_GET['page'] ?? 1));
$unreadOnly = ($_GET['filter'] ?? '') === 'unread';
try {
    if (($_GET['format'] ?? '') === 'json') {
        $inbox = staffNotificationInbox($pdo, $actor, true, 1, 5);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'unread' => $inbox['unread'],
            'items' => array_map(static fn ($n) => ['id' => (int)$n['id'], 'title' => $n['title']], $inbox['items']),
        ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        exit;
    }
    $inbox = staffNotificationInbox($pdo, $actor, $unreadOnly, $page);
    if (isset($_GET['notice'])) {
        $selected = staffNotificationGet($pdo, $actor, (int)$_GET['notice']);
        $selected['title'] = staffNotificationType($selected['event_type'])['title'] . ' #' . $selected['source_id'];
        $selected['text'] = staffNotificationType($selected['event_type'])['text'];
        $selected['actionable'] = $selected['resolved_at'] === null && staffNotificationSourcePending($pdo, $selected);
        $inbox['items'] = [$selected];
        $inbox['has_next'] = false;
    }
} catch (DomainException $e) {
    http_response_code(404);
    $error = $e->getMessage();
    $inbox = ['unread' => 0, 'items' => [], 'has_next' => false];
}
$h = static fn ($v): string => htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
?>
<!doctype html><html lang="cs"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Pracovní upozornění – KOVO Praha</title><?php appUiAssets(); ?></head><body>
<?php require __DIR__ . '/hlavicka.php'; ?>
<main class="container py-4">
    <h1>Pracovní upozornění</h1>
    <p>Co potřebuje vaši pozornost. Přečtení upozornění neznamená vyřízení úkolu.</p>
    <?php if ($error !== ''): ?><div class="alert alert-danger"><?= $h($error) ?></div><?php endif; ?>
    <nav class="mb-3" aria-label="Filtr upozornění">
        <a class="btn btn-outline-primary" href="staff_notifications.php">Všechna</a>
        <a class="btn btn-outline-primary" href="staff_notifications.php?filter=unread">Nepřečtená (<?= (int)$inbox['unread'] ?>)</a>
    </nav>
    <?php if (!$inbox['items']): ?><div class="alert alert-light">Nemáte žádná upozornění v tomto přehledu.</div><?php endif; ?>
    <?php foreach ($inbox['items'] as $notice): ?>
    <article class="card mb-3 <?= $notice['read_at'] === null && $notice['actionable'] ? 'border-primary' : '' ?>">
        <div class="card-body">
            <h2 class="h5"><?= $h($notice['title']) ?></h2>
            <p><?= $h($notice['text']) ?></p>
            <p class="small text-muted"><?= $h($notice['created_at']) ?> ·
                <?= !$notice['actionable'] ? 'Vyřízeno' : ($notice['read_at'] === null ? 'Nepřečteno · Čeká na vyřízení' : 'Přečteno · Čeká na vyřízení') ?>
            </p>
            <form method="post" class="d-flex flex-wrap gap-2">
                <?= csrf_field() ?><input type="hidden" name="notice_id" value="<?= (int)$notice['id'] ?>">
                <button name="action" value="open" class="btn btn-primary"><?= $notice['actionable'] ? 'Otevřít k vyřízení' : 'Zobrazit žádost' ?></button>
                <button name="action" value="<?= $notice['read_at'] === null ? 'read' : 'unread' ?>" class="btn btn-outline-secondary">
                    <?= $notice['read_at'] === null ? 'Označit jako přečtené' : 'Označit jako nepřečtené' ?>
                </button>
            </form>
        </div>
    </article>
    <?php endforeach; ?>
    <nav aria-label="Stránky upozornění">
        <?php if ($page > 1): ?><a class="btn btn-outline-secondary" href="?page=<?= $page - 1 ?>&amp;filter=<?= $unreadOnly ? 'unread' : 'all' ?>">Novější</a><?php endif; ?>
        <?php if ($inbox['has_next']): ?><a class="btn btn-outline-secondary" href="?page=<?= $page + 1 ?>&amp;filter=<?= $unreadOnly ? 'unread' : 'all' ?>">Starší</a><?php endif; ?>
    </nav>
</main></body></html>
