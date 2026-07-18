<?php
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/funkce.php';
require_once __DIR__ . '/../csrf_helper.php';

if (!isset($_SESSION['trener_id']) || !canAccess('uctenky')) {
    header('Location: ../login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_verify($_POST['csrf_token'] ?? '')) {
    $_SESSION['flash_error'] = 'Neplatný požadavek.';
    header('Location: seznam.php');
    exit;
}

$id = (int)($_POST['id'] ?? 0);

if ($id > 0) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM ucto_uctenky WHERE id = ?");
        $stmt->execute([$id]);
        $zaznam = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($zaznam) {
            // Přejmenuj soubor (soft delete)
            if (!empty($zaznam['obrazek_path'])) {
                $filePath = __DIR__ . '/../' . ltrim($zaznam['obrazek_path'], '/');
                if (file_exists($filePath)) {
                    $smazano = dirname($filePath) . '/smazano_' . basename($filePath);
                    rename($filePath, $smazano);
                }
            }

            $pdo->prepare("DELETE FROM ucto_uctenky WHERE id = ?")->execute([$id]);
            zapisAuditLog($pdo, $_SESSION['trener_id'], 'Smazání účtenky', 'ucto_uctenky', $id, json_encode($zaznam));
            $_SESSION['flash_success'] = 'Účtenka smazána.';
        }
    } catch (Exception $e) {
        error_log('uctenky/smazat.php: ' . $e->getMessage());
        $_SESSION['flash_error'] = 'Chyba při mazání.';
    }
}

header('Location: seznam.php');
exit;
