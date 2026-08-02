<?php
require_once __DIR__ . '/includes/session_security.php';
/**
 * smazat_trenink.php
 * Smaže trénink včetně všech vazeb.
 * Vyžaduje POST (ne GET) + CSRF token → ochrana před nechtěným smazáním.
 */
app_session_start();
require_once __DIR__ . '/includes/funkce.php';
if (!isset($_SESSION['trener_id'])) {
    header('Location: login.php');
    exit;
}
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/csrf_helper.php';

function h($s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

// Pouze POST žádosti jsou akceptovány
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die('Nepovolená metoda. Smazání musí být provedeno přes formulář.');
}

// CSRF ochrana
if (!csrf_verify($_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    die('Neplatný bezpečnostní token. Stránku obnovte a zkuste znovu.');
}

$treninkId = isset($_POST['trenink_id']) ? (int)$_POST['trenink_id'] : 0;
if ($treninkId <= 0) {
    $_SESSION['flash_error'] = 'Neplatné ID tréninku.';
    header('Location: sprava_treninku.php');
    exit;
}

// Autorizace: hlavní trenér NEBO trenér přiřazený k tréninku
$stmtAuth = $pdo->prepare('SELECT 1 FROM trenink_trener WHERE trenink_id = :tid AND trener_id = :uid');
$stmtAuth->execute([':tid' => $treninkId, ':uid' => $_SESSION['trener_id']]);
if (!canAccess('sprava_treninku') && !$stmtAuth->fetch()) {
    $_SESSION['flash_error'] = 'Nemáte oprávnění tento trénink smazat.';
    header('Location: sprava_treninku.php');
    exit;
}

// Transakční smazání – SPRÁVNÉ pořadí a kompletní seznam tabulek
try {
    $pdo->beginTransaction();

    // 1) Nejdříve smaž záznamy z mereni_zaznamy (přes trenink_mereni)
    $stmtMZ = $pdo->prepare('
        DELETE mz FROM mereni_zaznamy mz
        JOIN trenink_mereni tm ON tm.mereni_id = mz.id
        WHERE tm.trenink_id = ?
    ');
    $stmtMZ->execute([$treninkId]);

    // 2) Smaž zbývající vazební tabulky
    $vazby = [
        'trenink_mereni',       // vazba trénink ↔ měření
        'trenink_sportovec',    // účastníci
        'trenink_podskupina',   // podskupiny
        'trenink_skupina',      // skupiny
        'trenink_trener',       // trenéři
        'trenink_tag',          // tagy
        'sportovec_poznamka',   // poznámky sportovců k tréninku
        'story_vygenerovane',   // vygenerované story (pokud existuje)
    ];

    foreach ($vazby as $table) {
        try {
            $pdo->prepare("DELETE FROM `{$table}` WHERE trenink_id = ?")->execute([$treninkId]);
        } catch (PDOException $e) {
            // Tabulka nemusí existovat (story_vygenerovane apod.) – ignoruj
            error_log("smazat_trenink: tabulka {$table} neexistuje nebo chyba: " . $e->getMessage());
        }
    }

    // 2b) Odpoj navázané záznamy — jinak zůstanou viset na neexistujícím tréninku
    // Plánovaný trénink zpět na 'planovany' (aby šel znovu zaevidovat a upomínka fungovala)
    try {
        $pdo->prepare("UPDATE planovane_treninky SET stav='planovany', trenink_id=NULL WHERE trenink_id = ?")
            ->execute([$treninkId]);
    } catch (PDOException $e) {
        error_log('smazat_trenink: planovane_treninky odpojeni: ' . $e->getMessage());
    }
    // Rezervace sportoviště vytvořená tréninkem — uvolni kapacitu
    try {
        $pdo->prepare("DELETE FROM rezervace_sportovist WHERE trenink_id = ?")->execute([$treninkId]);
    } catch (PDOException $e) {
        error_log('smazat_trenink: rezervace_sportovist: ' . $e->getMessage());
    }

    // 2c) Soft-delete nahraných obrázků (JSON pole v treninky.obrazky)
    try {
        $stImg = $pdo->prepare('SELECT obrazky FROM treninky WHERE id = ?');
        $stImg->execute([$treninkId]);
        $obrazkyJson = $stImg->fetchColumn();
        $obrazky = $obrazkyJson ? json_decode((string)$obrazkyJson, true) : null;
        if (is_array($obrazky)) {
            foreach ($obrazky as $img) {
                $rel = ltrim(str_replace('\\', '/', (string)$img), '/');
                $path = __DIR__ . '/' . $rel;
                // Bezpečnostní pojistka — jen v nahrane_obrazky/
                if (strpos($rel, 'nahrane_obrazky/') === 0 && is_file($path)) {
                    @rename($path, dirname($path) . '/smazano_' . basename($path));
                }
            }
        }
    } catch (Throwable $e) {
        error_log('smazat_trenink: obrazky: ' . $e->getMessage());
    }

    // 3) Smaž vlastní trénink
    $pdo->prepare('DELETE FROM treninky WHERE id = ?')->execute([$treninkId]);

    $pdo->commit();

    $_SESSION['flash_success'] = 'Trénink byl úspěšně smazán.';
    header('Location: sprava_treninku.php');
    exit;

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('smazat_trenink error: ' . $e->getMessage());
    $_SESSION['flash_error'] = 'Chyba při mazání tréninku. Zkuste to znovu.';
    header('Location: sprava_treninku.php');
    exit;
}
