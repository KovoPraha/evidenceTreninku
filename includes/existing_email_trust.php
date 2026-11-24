<?php

declare(strict_types=1);

function existingEmailTrustHasTable(PDO $pdo, string $table): bool
{
    $driver = (string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    if ($driver === 'sqlite') {
        $query = $pdo->prepare("SELECT 1 FROM sqlite_master WHERE type='table' AND name=?");
        $query->execute([$table]);
        return (bool)$query->fetchColumn();
    }
    if ($driver === 'mysql') {
        $query = $pdo->prepare('SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?');
        $query->execute([$table]);
        return (bool)$query->fetchColumn();
    }
    return false;
}

function existingEmailTrustHasColumn(PDO $pdo, string $table, string $column): bool
{
    if (!preg_match('/^[a-z_]+$/D', $table) || !preg_match('/^[a-z_]+$/D', $column)) {
        return false;
    }
    $driver = (string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    if ($driver === 'sqlite') {
        foreach ($pdo->query('PRAGMA table_info(' . $table . ')')->fetchAll(PDO::FETCH_ASSOC) as $field) {
            if ($field['name'] === $column) {
                return true;
            }
        }
        return false;
    }
    if ($driver === 'mysql') {
        $query = $pdo->prepare('SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?');
        $query->execute([$table, $column]);
        return (bool)$query->fetchColumn();
    }
    return false;
}

/** Only the original disabled KIS import may self-activate by its trusted address. */
function existingEmailTrustCanActivate(PDO $pdo, array $account): bool
{
    $id = (int)($account['id'] ?? 0);
    $email = mb_strtolower(trim((string)($account['email'] ?? '')), 'UTF-8');
    if ($id < 1 || (int)($account['aktivni'] ?? -1) !== 0 || (int)($account['email_overeno'] ?? 0) !== 1
        || !empty($account['trener_id']) || !filter_var($email, FILTER_VALIDATE_EMAIL)
        || !existingEmailTrustHasTable($pdo, 'existing_email_trust')
        || !existingEmailTrustHasTable($pdo, 'kis_person_contacts')
        || !existingEmailTrustHasTable($pdo, 'public_account_activation_events')) {
        return false;
    }
    $trust = $pdo->prepare("SELECT 1 FROM existing_email_trust WHERE account_id=? AND email_hash=? AND state='trusted'");
    $trust->execute([$id, hash('sha256', $email)]);
    if (!$trust->fetchColumn()) {
        return false;
    }
    $imported = $pdo->prepare("SELECT 1 FROM kis_person_contacts WHERE account_id=? AND account_state='disabled_reset_required' LIMIT 1");
    $imported->execute([$id]);
    if (!$imported->fetchColumn()) {
        return false;
    }
    $consumed = $pdo->prepare("SELECT 1 FROM public_account_activation_events WHERE account_id=? AND action IN ('email_verified_password_set','legacy_first_password','password_reset') LIMIT 1");
    $consumed->execute([$id]);
    return !$consumed->fetchColumn();
}

/** Direct athlete reset is bound to one current profile, access account and original address. */
function athleteEmailTrustCanReset(PDO $pdo, int $accessId, string $email): bool
{
    if ($accessId < 1 || !filter_var($email, FILTER_VALIDATE_EMAIL)
        || !existingEmailTrustHasTable($pdo, 'athlete_email_trust')) {
        return false;
    }
    $q = $pdo->prepare('SELECT ca.active,ca.sportovec_id,s.email,t.email_hash,t.state,t.auto_created '
        . 'FROM child_access_accounts ca JOIN sportovci s ON s.id=ca.sportovec_id '
        . 'JOIN athlete_email_trust t ON t.sportovec_id=s.id AND t.access_account_id=ca.id WHERE ca.id=?');
    $q->execute([$accessId]);
    $row = $q->fetch(PDO::FETCH_ASSOC);
    $current = mb_strtolower(trim((string)($row['email'] ?? '')), 'UTF-8');
    if (!$row || $row['state'] !== 'trusted' || $current !== mb_strtolower(trim($email), 'UTF-8')
        || !hash_equals((string)$row['email_hash'], hash('sha256', $current))) {
        return false;
    }
    if ((int)$row['active'] === 1) {
        return true;
    }
    if ((int)$row['auto_created'] !== 1) {
        return false;
    }
    $used = $pdo->prepare("SELECT 1 FROM child_access_events WHERE access_account_id=? AND action IN ('activate','deactivate','password_reset') LIMIT 1");
    $used->execute([$accessId]);
    return !$used->fetchColumn();
}
