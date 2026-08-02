<?php
require_once dirname(__DIR__) . '/includes/session_security.php';
require_once dirname(__DIR__) . '/includes/auth_session.php';
/**
 * auth/sso_bridge.php
 * Legacy SSO bridge — čte Velocota session a mapuje na Evidence session.
 * Evidence je samostatný produkt; bridge není součástí cílové architektury a
 * nesmí se zapnout bez nového explicitního rozhodnutí a security review.
 *
 * INCLUDOVAT přes db.php (podmíněně dle VELOCOTA_INTEGRATION):
 *   if (defined('VELOCOTA_INTEGRATION') && VELOCOTA_INTEGRATION) {
 *       require_once __DIR__ . '/auth/sso_bridge.php';
 *   }
 *
 * KONTRAKT (Velocota musí zapsat tyto session klíče):
 *   $_SESSION['velo_user_id']  — int, ID uživatele ve Velocota DB
 *   $_SESSION['velo_role']     — string: 'trener'|'hlavni_trener'|'admin'|'clen'
 *   $_SESSION['velo_jmeno']    — string, zobrazované jméno
 *   $_SESSION['velo_email']    — string, email (pro DB lookup)
 *   $_SESSION['velo_klub_id']  — int, ID klubu (pro budoucí multi-tenant)
 *
 * VÝSTUP (Evidence session klíče po úspěšném bridgi):
 *   $_SESSION['trener_id']     — int, ID z tabulky treneri
 *   $_SESSION['trener_jmeno']  — string
 *   $_SESSION['role']          — 'trener'|'hlavni'|'admin'
 *   $_SESSION['opravneni']     — array z tabulky opravneni
 */

require_once dirname(__DIR__) . '/includes/password_security.php';

// ── Mapování Velocota rolí na Evidence role ───────────────────────────────────
const VELO_ROLE_MAP = [
    'admin'          => 'admin',
    'hlavni_trener'  => 'hlavni',
    'trener'         => 'trener',
    // Ostatní role (clen, verejny, atd.) → žádný přístup do Evidence
];

/**
 * Hlavní bridge funkce — volána z db.php při každém requestu.
 * Pokud Velocota session neexistuje, nastaví prázdné Evidence session (logout).
 */
function velocotaSsoBridge(PDO $pdo): bool
{
    $veloId    = $_SESSION['velo_user_id'] ?? null;
    $veloRole  = $_SESSION['velo_role']    ?? '';
    $veloJmeno = $_SESSION['velo_jmeno']   ?? '';
    $veloEmail = $_SESSION['velo_email']   ?? '';

    // Velocota session neexistuje nebo role nemá přístup → clear Evidence session
    if (!$veloId || !isset(VELO_ROLE_MAP[$veloRole])) {
        return !_clearEvidenceSession();
    }
    $evidenceRole = VELO_ROLE_MAP[$veloRole];

    // Najít nebo vytvořit záznam v treneri tabulce
    $trenerId = _syncTrener($pdo, (int)$veloId, $veloJmeno, $veloEmail);
    if (!$trenerId) {
        return !_clearEvidenceSession();
    }

    // Aktivitu účtu ověřujeme při každém requestu, i u již přihlášeného uživatele.
    if (
        isset($_SESSION['trener_id'], $_SESSION['velo_user_id_cached'])
        && (int)$_SESSION['trener_id'] === $trenerId
        && (int)$_SESSION['velo_user_id_cached'] === (int)$veloId
        && ($_SESSION['role'] ?? null) === $evidenceRole
    ) {
        return true;
    }

    $sessionVersion = auth_session_active_version($pdo, 'trainer', $trenerId);
    if ($sessionVersion === null) {
        return !_clearEvidenceSession();
    }

    app_session_mark_authenticated();

    // Zapsat Evidence session
    auth_session_bind_trainer($trenerId, $sessionVersion);
    $_SESSION['trener_jmeno']       = $veloJmeno;
    $_SESSION['role']               = $evidenceRole;
    $_SESSION['velo_user_id_cached']= (int)$veloId;

    // Načíst oprávnění
    try {
        $stmt = $pdo->query("SELECT klic, min_role FROM opravneni");
        $_SESSION['opravneni'] = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    } catch (PDOException $e) {
        $_SESSION['opravneni'] = [];
    }

    return true;
}

/**
 * Najde trenera v DB podle velo_user_id nebo emailu.
 * Pokud neexistuje, vytvoří nový záznam (shadow account).
 * Vrátí treneri.id nebo null při chybě.
 */
function _syncTrener(PDO $pdo, int $veloId, string $jmeno, string $email): ?int
{
    try {
        // Zkus najít podle velo_user_id
        if (_colExists($pdo, 'treneri', 'velo_user_id')) {
            $stmt = $pdo->prepare("SELECT id, aktivni FROM treneri WHERE velo_user_id=? LIMIT 1");
            $stmt->execute([$veloId]);
            $trener = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($trener) {
                return (int)$trener['aktivni'] === 1 ? (int)$trener['id'] : null;
            }
        }

        // Fallback: hledat podle emailu
        if ($email) {
            $stmt = $pdo->prepare("SELECT id, aktivni FROM treneri WHERE email=? LIMIT 1");
            $stmt->execute([$email]);
            $trener = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($trener) {
                if ((int)$trener['aktivni'] !== 1) {
                    return null;
                }

                $id = (int)$trener['id'];
                // Zpětně propojit velo_user_id
                if (_colExists($pdo, 'treneri', 'velo_user_id')) {
                    $pdo->prepare("UPDATE treneri SET velo_user_id=? WHERE id=?")
                        ->execute([$veloId, $id]);
                }
                return (int)$id;
            }
        }

        // Vytvořit nový shadow účet
        // Heslo: náhodný hash (login přes SSO, heslo se nevyužívá)
        $fakeHash = trainer_password_hash(bin2hex(random_bytes(16)));
        $cols = 'jmeno, email, heslo, role, aktivni';
        $vals = [substr($jmeno, 0, 80), $email ?: null, $fakeHash, 'trener', 1];

        if (_colExists($pdo, 'treneri', 'velo_user_id')) {
            $cols .= ', velo_user_id';
            $vals[] = $veloId;
            $phs = '?,?,?,?,?,?';
        } else {
            $phs = '?,?,?,?,?';
        }

        $pdo->prepare("INSERT INTO treneri ($cols) VALUES ($phs)")->execute($vals);
        return (int)$pdo->lastInsertId();

    } catch (PDOException $e) {
        error_log('sso_bridge._syncTrener: ' . $e->getMessage());
        return null;
    }
}

/** Zjistí existenci sloupce (cache v static proměnné). */
function _colExists(PDO $pdo, string $table, string $col): bool
{
    static $cache = [];
    $key = "$table.$col";
    if (!isset($cache[$key])) {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?
        ");
        $stmt->execute([$table, $col]);
        $cache[$key] = (bool)$stmt->fetchColumn();
    }
    return $cache[$key];
}

/** Vymaže Evidence session (udrží ostatní klíče z Velocoty). */
function _clearEvidenceSession(): bool
{
    $hadEvidenceIdentity = isset($_SESSION['trener_id']);
    auth_session_clear_identity('trainer');
    if ($hadEvidenceIdentity) {
        app_session_mark_identity_changed();
    }

    return $hadEvidenceIdentity;
}
