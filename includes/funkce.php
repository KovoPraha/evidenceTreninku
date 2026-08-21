<?php

/**
 * Kontrola úrovně role — hierarchická.
 * Vrací true pokud aktuální uživatel má roli >= požadovaná.
 * Úrovně: trener(1) < hlavni(2) < admin(3)
 */
function roleAtLeast(string $requiredRole): bool {
    static $levels = ['trener' => 1, 'hlavni' => 2, 'admin' => 3];
    $current = $_SESSION['role'] ?? 'trener';
    if (function_exists('staffEffectiveLegacyRole')) {
        $current = staffEffectiveLegacyRole((string)$current);
    }
    return ($levels[$current] ?? 0) >= ($levels[$requiredRole] ?? 99);
}

/**
 * Kontrola oprávnění dle konfigurovatelné tabulky.
 * Čte z $_SESSION['opravneni'] (načteno při loginu).
 * Neznamy klic je fail-closed. Pracovni pozice resi vlastnictvi cele trasy;
 * legacy tabulka zde docasne ridi pouze vnitrni funkce starych obrazovek.
 */
function canAccess(string $key): bool {
    $perms = $_SESSION['opravneni'] ?? [];
    if (!array_key_exists($key, $perms)) return false;
    $minRole = $perms[$key];
    return roleAtLeast($minRole);
}

/**
 * Vrátí minimální roli pro daný klíč oprávnění (pro badge zobrazení).
 */
function getMinRole(string $key): string {
    $perms = $_SESSION['opravneni'] ?? [];
    return $perms[$key] ?? 'zakazano';
}

/**
 * HTML badge pro roli oprávnění na dashboardu.
 */
function roleBadge(string $key): string {
    $role = getMinRole($key);
    $map = [
        'trener' => ['všichni', 'role-all'],
        'hlavni' => ['správce', 'role-mgr'],
        'admin'  => ['admin', 'role-admin'],
    ];
    $map['zakazano'] = ['zakázáno', 'role-admin'];
    $m = $map[$role] ?? $map['zakazano'];
    return '<span class="role-badge ' . $m[1] . '">' . $m[0] . '</span>';
}

function auditRedactSensitive(mixed $value, ?string $key = null): mixed {
    if ($key !== null && preg_match('/(?:password|heslo|secret|token|authorization|cookie|csrf)/i', $key)) return '[REDACTED]';
    if (!is_array($value)) return $value;
    $clean=[];foreach($value as$childKey=>$childValue)$clean[$childKey]=auditRedactSensitive($childValue,(string)$childKey);return$clean;
}

function auditSanitizeDetail(mixed $detail): string {
    if (is_array($detail)) return json_encode(auditRedactSensitive($detail),JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_INVALID_UTF8_SUBSTITUTE);
    $text=(string)$detail;$decoded=json_decode($text,true);if(is_array($decoded))return json_encode(auditRedactSensitive($decoded),JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_INVALID_UTF8_SUBSTITUTE);
    return mb_substr($text,0,10000,'UTF-8');
}

function zapisAuditLog($pdo, $uzivatel_id, $akce, $tabulka, $zaznam_id, $detail) {
    $stmt = $pdo->prepare("INSERT INTO ucto_audit_log (uzivatel_id, akce, tabulka, zaznam_id, detail, ip_adresa, user_agent)
                           VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([
        $uzivatel_id,
        $akce,
        $tabulka,
        $zaznam_id,
        auditSanitizeDetail($detail),
        $_SERVER['REMOTE_ADDR'] ?? '',
        $_SERVER['HTTP_USER_AGENT'] ?? ''
    ]);
}
