<?php
declare(strict_types=1);

/**
 * Compensating filesystem transaction for files referenced by a DB transaction.
 *
 * New uploads remain under hidden pending names until all SQL succeeds. Existing
 * files selected for deletion are first moved under hidden retired names. The
 * caller finalizes files immediately before PDO::commit(), calls committed()
 * after a successful commit and calls rollback() from every exception path.
 *
 * @return array{staged:list<array{pending:string,final:string}>,finalized:list<string>,retired:list<array{original:string,retired:string}>}
 */
function fileMutationBegin(): array
{
    return ['staged' => [], 'finalized' => [], 'retired' => []];
}

/** @param array{staged:array,finalized:array,retired:array} $transaction */
function fileMutationStage(
    array &$transaction,
    string $source,
    string $finalPath,
    bool $uploaded = true
): bool {
    if (!is_file($source) || ($uploaded && !is_uploaded_file($source))) {
        return false;
    }
    $directory = dirname($finalPath);
    $name = basename($finalPath);
    if ($name === '' || $name === '.' || $name === '..') {
        throw new InvalidArgumentException('Neplatny cil souboru.');
    }
    if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
        throw new RuntimeException('Cilovy adresar souboru nelze vytvorit.');
    }
    if (is_file($finalPath)) {
        throw new RuntimeException('Cilovy soubor jiz existuje.');
    }
    $pending = $directory . DIRECTORY_SEPARATOR . '.pending-' . bin2hex(random_bytes(12)) . '-' . $name;
    $moved = $uploaded ? move_uploaded_file($source, $pending) : rename($source, $pending);
    if (!$moved) {
        return false;
    }
    @chmod($pending, 0600);
    $transaction['staged'][] = ['pending' => $pending, 'final' => $finalPath];
    return true;
}

/** @param array{staged:array,finalized:array,retired:array} $transaction */
function fileMutationRetire(array &$transaction, string $path): void
{
    if (!is_file($path)) {
        return;
    }
    $directory = dirname($path);
    $retired = $directory . DIRECTORY_SEPARATOR . '.retired-' . bin2hex(random_bytes(12)) . '-' . basename($path);
    if (!rename($path, $retired)) {
        throw new RuntimeException('Existujici soubor nelze pripravit k odstraneni.');
    }
    @chmod($retired, 0600);
    $transaction['retired'][] = ['original' => $path, 'retired' => $retired];
}

/** @param array{staged:array,finalized:array,retired:array} $transaction */
function fileMutationFinalize(array &$transaction): void
{
    foreach ($transaction['staged'] as $item) {
        if (!is_file($item['pending']) || is_file($item['final']) || !rename($item['pending'], $item['final'])) {
            throw new RuntimeException('Novy soubor nelze finalizovat.');
        }
        @chmod($item['final'], 0640);
        $transaction['finalized'][] = $item['final'];
    }
    $transaction['staged'] = [];
}

/** @param array{staged:array,finalized:array,retired:array} $transaction */
function fileMutationCommitted(array &$transaction): void
{
    foreach ($transaction['retired'] as $item) {
        if (is_file($item['retired'])) {
            @unlink($item['retired']);
        }
    }
    $transaction = fileMutationBegin();
}

/** @param array{staged:array,finalized:array,retired:array} $transaction */
function fileMutationRollback(array &$transaction): void
{
    foreach (array_reverse($transaction['finalized']) as $path) {
        if (is_file($path)) {
            @unlink($path);
        }
    }
    foreach ($transaction['staged'] as $item) {
        if (is_file($item['pending'])) {
            @unlink($item['pending']);
        }
    }
    foreach (array_reverse($transaction['retired']) as $item) {
        if (is_file($item['retired']) && !is_file($item['original'])) {
            @rename($item['retired'], $item['original']);
        }
    }
    $transaction = fileMutationBegin();
}
