<?php

/**
 * Kontrola úrovně role — hierarchická.
 * Vrací true pokud aktuální uživatel má roli >= požadovaná.
 * Úrovně: trener(1) < hlavni(2) < admin(3)
 */
function roleAtLeast(string $requiredRole): bool {
    static $levels = ['trener' => 1, 'hlavni' => 2, 'admin' => 3];
    $current = $_SESSION['role'] ?? 'trener';
    return ($levels[$current] ?? 0) >= ($levels[$requiredRole] ?? 99);
}

/**
 * Kontrola oprávnění dle konfigurovatelné tabulky.
 * Čte z $_SESSION['opravneni'] (načteno při loginu).
 * Pokud klíč neexistuje → fallback na roleAtLeast('hlavni').
 */
function canAccess(string $key): bool {
    $perms = $_SESSION['opravneni'] ?? [];
    $minRole = $perms[$key] ?? 'hlavni';
    return roleAtLeast($minRole);
}

/**
 * Vrátí minimální roli pro daný klíč oprávnění (pro badge zobrazení).
 */
function getMinRole(string $key): string {
    $perms = $_SESSION['opravneni'] ?? [];
    return $perms[$key] ?? 'hlavni';
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
    $m = $map[$role] ?? $map['hlavni'];
    return '<span class="role-badge ' . $m[1] . '">' . $m[0] . '</span>';
}

function zapisAuditLog($pdo, $uzivatel_id, $akce, $tabulka, $zaznam_id, $detail) {
    $stmt = $pdo->prepare("INSERT INTO ucto_audit_log (uzivatel_id, akce, tabulka, zaznam_id, detail, ip_adresa, user_agent)
                           VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([
        $uzivatel_id,
        $akce,
        $tabulka,
        $zaznam_id,
        $detail,
        $_SERVER['REMOTE_ADDR'] ?? '',
        $_SERVER['HTTP_USER_AGENT'] ?? ''
    ]);
}
