<?php
declare(strict_types=1);

require_once __DIR__ . '/file_mutation_transaction.php';

/**
 * Preserve the receipt store's existing soft-delete archive after the SQL commit.
 * Unlike generic fileMutationCommitted(), this never unlinks retired evidence.
 * If archival rename fails, the hidden retired file remains recoverable and the
 * committed DB result stays committed; it must not enter the rollback handler.
 */
function financialReceiptFilesCommitted(array &$transaction): void
{
    foreach ($transaction['retired'] as $item) {
        try {
            if (!is_file($item['retired'])) continue;
            $deleted = dirname($item['retired']) . DIRECTORY_SEPARATOR . '.deleted-' . bin2hex(random_bytes(12));
            if (!@rename($item['retired'], $deleted)) {
                throw new RuntimeException('Retired receipt retained; archive rename failed.');
            }
            @chmod($deleted, 0600);
        } catch (Throwable $exception) {
            error_log('Receipt archive cleanup: ' . $exception->getMessage());
        }
    }
    $transaction = fileMutationBegin();
}
