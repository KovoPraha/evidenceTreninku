<?php

declare(strict_types=1);

require_once __DIR__ . '/trainer_password_reset.php';
require_once __DIR__ . '/transactional_notification_worker.php';

/** Counts only; no identities, recipients, tokens or secrets in the operational report. */
function trainerAccessReadiness(PDO $pdo, string $host, ?array $keys = null): array
{
    $result = ['active_staff' => 0, 'reset_eligible' => 0, 'reset_blocked' => 0, 'linked_reset_eligible' => 0];
    $rows = $pdo->query('SELECT id,email FROM treneri WHERE aktivni=1')->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $row) {
        $result['active_staff']++;
        $target = trainerPasswordResetTarget($pdo, (string)$row['email']);
        if ($target !== null && $target['trainer_id'] === (int)$row['id']) {
            $result['reset_eligible']++;
            $result['linked_reset_eligible'] += $target['public_account_id'] !== null ? 1 : 0;
        } else {
            $result['reset_blocked']++;
        }
    }
    $result['coaches'] = (int)$pdo->query(
        "SELECT COUNT(DISTINCT p.trainer_id) FROM staff_user_positions p JOIN treneri t ON t.id=p.trainer_id AND t.aktivni=1 "
        . "WHERE p.position_code='coach'"
    )->fetchColumn();
    $result['coaches_without_current_grant'] = (int)$pdo->query(
        "SELECT COUNT(DISTINCT p.trainer_id) FROM staff_user_positions p JOIN treneri t ON t.id=p.trainer_id AND t.aktivni=1 WHERE p.position_code='coach' "
        . 'AND NOT EXISTS(SELECT 1 FROM coach_scope_grants g WHERE g.trainer_id=t.id AND g.revoked_at IS NULL AND g.valid_from<=CURRENT_TIMESTAMP '
        . 'AND (g.valid_to IS NULL OR g.valid_to>CURRENT_TIMESTAMP))'
    )->fetchColumn();
    return [
        'read_only' => true,
        'accounts' => $result,
        'mail' => transactionalNotificationStatus($pdo, 'mail', $host, null, $keys),
        'trainer_queue' => $pdo->query("SELECT status,COUNT(*) total FROM auth_mail_outbox WHERE target_type='trainer' AND kind='reset' GROUP BY status")->fetchAll(PDO::FETCH_ASSOC),
        'scope_assignments_changed' => false,
        'full_trainer_uat_verified' => false,
    ];
}
