<?php
// Included by the authenticated staff header only.
if (!isset($_SESSION['trener_id'])) {
    return;
}
require_once __DIR__ . '/staff_notifications.php';
$staffBell = null;
try {
    if (isset($pdo) && $pdo instanceof PDO) {
        $staffBell = staffNotificationInbox($pdo, (int)$_SESSION['trener_id'], true, 1, 5);
    }
} catch (PDOException $e) {
    error_log('staff_notification_bell: ' . $e->getMessage());
}
$staffBellUrl = appUiUrl('staff_notifications.php');
$staffBellH = static fn ($v): string => htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
?>
<div class="dropdown staff-notification-bell" data-staff-notifications data-url="<?= $staffBellH($staffBellUrl) ?>">
    <button class="btn btn-outline-light position-relative" type="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Pracovní upozornění" id="staff-notification-toggle">
        <i class="bi bi-bell" aria-hidden="true"></i>
        <span class="badge bg-danger" data-staff-notification-count aria-live="polite" <?= ($staffBell['unread'] ?? 0) === 0 ? 'hidden' : '' ?>><?= (int)($staffBell['unread'] ?? 0) ?></span>
        <span class="visually-hidden">nepřečtených upozornění</span>
    </button>
    <div class="dropdown-menu dropdown-menu-end p-2 staff-notification-menu" aria-labelledby="staff-notification-toggle">
        <strong class="d-block px-2 py-1">Pracovní upozornění</strong>
        <div data-staff-notification-items>
            <?php foreach ($staffBell['items'] ?? [] as $staffBellItem): ?>
                <a class="dropdown-item text-wrap py-2" href="<?= $staffBellH($staffBellUrl . '?notice=' . $staffBellItem['id']) ?>"><?= $staffBellH($staffBellItem['title']) ?></a>
            <?php endforeach; ?>
            <?php if (empty($staffBell['items'])): ?><p class="small text-muted px-2 my-2"><?= $staffBell === null ? 'Upozornění se nepodařilo načíst.' : 'Žádná nová upozornění.' ?></p><?php endif; ?>
        </div>
        <div class="dropdown-divider"></div>
        <a class="dropdown-item" href="<?= $staffBellH($staffBellUrl) ?>">Všechna upozornění</a>
    </div>
</div>
<script nonce="<?= $staffBellH(app_csp_nonce()) ?>" src="<?= $staffBellH(appUiUrl('assets/staff-notifications.js')) ?>" defer></script>
