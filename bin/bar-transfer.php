<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
umask(0077);
require_once dirname(__DIR__) . '/includes/bar/transfer_package.php';
$options = getopt('', ['export', 'apply-local', 'inspect', 'images:', 'package:', 'actor:']);
try {
    if (count(array_intersect(['export', 'apply-local', 'inspect'], array_keys($options))) !== 1 || !is_string($options['package'] ?? null)) {
        throw new InvalidArgumentException('Použití: --export --images=ZDROJ --package=NOVÝ_SOUKROMÝ_ADRESÁŘ; nebo --inspect/--apply-local --package=ADRESÁŘ [--actor=ID].');
    }
    $directory = $options['package'];
    if (isset($options['export'])) {
        if (!is_string($options['images'] ?? null)) {
            throw new InvalidArgumentException('Chybí zdrojový adresář fotografií.');
        }
        $source = barReadLegacyTransferSource(barReadOnlySourceConnection());
        $bundle = barBuildTransferBundle($source, $options['images']);
        barWriteTransferPackage($bundle, $options['images'], $directory);
    } else {
        $file = $directory . '/bundle.json';
        if (!is_file($file) || is_link($file) || filesize($file) > 32 * 1024 * 1024) {
            throw new InvalidArgumentException('Chybí přiměřeně velký dokončený převodní balíček.');
        }
        $bundle = json_decode((string)file_get_contents($file), true, 32, JSON_THROW_ON_ERROR);
        if (!is_array($bundle)) {
            throw new InvalidArgumentException('Neplatný převodní balíček.');
        }
        $bundle = barValidateTransferBundle($bundle, $directory . '/images');
    }
    $summary = ['products' => count($bundle['catalog']['products']), 'categories' => count($bundle['catalog']['categories']),
        'recipes' => count($bundle['catalog']['recipes']), 'images' => count($bundle['images']), 'accounts' => 1];
    if (isset($options['apply-local'])) {
        $actor = filter_var($options['actor'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($actor === false) {
            throw new InvalidArgumentException('Chybí ID existujícího správce systému.');
        }
        $pdo = barIsolatedTransferConnection('BAR_TARGET');
        $query = $pdo->prepare('SELECT session_version FROM treneri WHERE id=? AND aktivni=1');
        $query->execute([$actor]);
        $_SESSION = ['trener_id' => $actor, 'trener_session_version' => (int)$query->fetchColumn(), 'staff_active_position' => 'system_admin'];
        $summary = barImportTransfer($pdo, $bundle, $directory . '/images');
    }
    echo json_encode(['ok' => true, 'summary' => $summary, 'production_allowed' => false], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE) . PHP_EOL;
} catch (Throwable $error) {
    // Never print credentials, hashes, source rows, a DSN or a stack trace.
    $message = $error instanceof InvalidArgumentException || $error instanceof DomainException
        ? $error->getMessage() : 'Převod se nepodařil. Ověřte izolované připojení, schéma a soukromé soubory.';
    fwrite(STDERR, json_encode(['ok' => false, 'message' => $message], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE) . PHP_EOL);
    exit(1);
}
