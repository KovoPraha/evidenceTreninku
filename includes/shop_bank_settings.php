<?php
declare(strict_types=1);

require_once __DIR__ . '/shop_checkout.php';

/**
 * Bankovní účet e-shopu. Databáze je zdrojem pravdy; konstanty z config.php
 * slouží jen jako záloha, dokud v databázi žádný záznam není. Tím zůstane
 * produkce funkční mezi nasazením a prvním uložením a localhostové demo
 * konstanty dál fungují beze změny.
 */
const SHOP_BANK_SETTINGS_ROW_ID = 1;

/**
 * Ukázkové hodnoty pro kontrolní QR. Variabilní symbol je nejvyšší možný
 * desetimístný, takže nemůže kolidovat s reálnou řadou odvozenou z ID
 * objednávky, a zpráva je součástí samotného QR kódu.
 */
const SHOP_BANK_SAMPLE_VARIABLE_SYMBOL = '9999999999';
const SHOP_BANK_SAMPLE_AMOUNT_MINOR = 100;
const SHOP_BANK_SAMPLE_MESSAGE = 'UKAZKA NEPLATIT';

final class ShopBankSettingsException extends RuntimeException {}

function shopBankSettingsTableAvailable(PDO $pdo): bool
{
    if ((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') {
        $statement = $pdo->prepare(
            'SELECT 1 FROM information_schema.TABLES '
            . 'WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? LIMIT 1'
        );
        $statement->execute(['shop_bank_settings']);
        return (bool)$statement->fetchColumn();
    }
    $statement = $pdo->prepare("SELECT 1 FROM sqlite_master WHERE type='table' AND name=? LIMIT 1");
    $statement->execute(['shop_bank_settings']);
    return (bool)$statement->fetchColumn();
}

/** @return array<string,mixed>|null */
function shopBankSettingsStoredRow(PDO $pdo, bool $lock = false): ?array
{
    if (!shopBankSettingsTableAvailable($pdo)) return null;
    $sql = 'SELECT * FROM shop_bank_settings WHERE id=?';
    if ($lock && (string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') $sql .= ' FOR UPDATE';
    $statement = $pdo->prepare($sql);
    $statement->execute([SHOP_BANK_SETTINGS_ROW_ID]);
    $row = $statement->fetch(PDO::FETCH_ASSOC);
    return $row === false ? null : $row;
}

/**
 * Konstanty z config.php bez výjimky. Vrací null, když nejsou nastavené nebo
 * neprojdou stejnou validací jako uložený záznam.
 *
 * @return array{iban:string,bic:string,account_label:string,due_days:int}|null
 */
function shopBankSettingsFromConfigOrNull(): ?array
{
    try {
        return shopBankSettingsFromConfig();
    } catch (Throwable) {
        return null;
    }
}

/**
 * Úplný obraz obou zdrojů pro administraci i pro checkout.
 *
 * @return array{settings:array{iban:string,bic:string,account_label:string,due_days:int}|null,
 *               source:string,database:array<string,mixed>|null,config:array<string,mixed>|null,
 *               database_error:string,conflict:bool,updated_at:string,updated_by_trainer_id:int}
 */
function shopBankSettingsResolve(PDO $pdo): array
{
    $row = shopBankSettingsStoredRow($pdo);
    $database = null;
    $databaseError = '';
    if ($row !== null) {
        try {
            $database = shopBankValidateSettings([
                'iban' => (string)$row['iban'],
                'bic' => (string)$row['bic'],
                'account_label' => (string)$row['account_label'],
                'due_days' => (int)$row['due_days'],
            ]);
        } catch (Throwable $exception) {
            $databaseError = $exception->getMessage();
        }
    }
    $config = shopBankSettingsFromConfigOrNull();

    $conflict = $database !== null && $config !== null && $database !== $config;
    $source = $database !== null ? 'database' : ($config !== null ? 'config' : 'none');

    return [
        'settings' => $database ?? $config,
        'source' => $source,
        'database' => $database,
        'config' => $config,
        'database_error' => $databaseError,
        'conflict' => $conflict,
        'updated_at' => $row === null ? '' : (string)$row['updated_at'],
        'updated_by_trainer_id' => $row === null ? 0 : (int)$row['updated_by_trainer_id'],
    ];
}

/**
 * Platné nastavení pro checkout. Uložený, ale neplatný záznam se nikdy tiše
 * neobejde konstantami — peníze by pak chodily na starý účet.
 *
 * @return array{iban:string,bic:string,account_label:string,due_days:int}
 */
function shopBankSettingsEffective(PDO $pdo): array
{
    $resolved = shopBankSettingsResolve($pdo);
    if ($resolved['database_error'] !== '') {
        throw new ShopCheckoutException('Uložený bankovní účet e-shopu není platný. Opravte jej v administraci.');
    }
    if ($resolved['settings'] === null) {
        throw new ShopCheckoutException('Bankovní checkout není bezpečně nakonfigurován. Zkontrolujte IBAN, BIC, název účtu a splatnost.');
    }
    return $resolved['settings'];
}

function shopBankSettingsSourceLabel(string $source): string
{
    return match ($source) {
        'database' => 'z administrace',
        'config' => 'z config.php',
        default => 'nenastaveno',
    };
}

/**
 * @param array<string,mixed> $input
 * @return array{changed:bool,settings:array{iban:string,bic:string,account_label:string,due_days:int}}
 */
function shopBankSettingsSave(PDO $pdo, int $actorId, array $input, string $reason, bool $confirmed): array
{
    $pdo->beginTransaction();
    try {
        $result = shopBankSettingsSaveInTransaction($pdo, $actorId, $input, $reason, $confirmed);
        $pdo->commit();
        return $result;
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        if ($exception instanceof InvalidArgumentException
            || $exception instanceof ShopBankSettingsException
            || $exception instanceof ShopCheckoutException
        ) {
            throw $exception;
        }
        throw new ShopBankSettingsException('Bankovní účet se nepodařilo uložit bez částečného zápisu.', 0, $exception);
    }
}

/**
 * @param array<string,mixed> $input
 * @return array{changed:bool,settings:array{iban:string,bic:string,account_label:string,due_days:int}}
 */
function shopBankSettingsSaveInTransaction(PDO $pdo, int $actorId, array $input, string $reason, bool $confirmed): array
{
    if (!$pdo->inTransaction()) throw new LogicException('Uložení bankovního účtu vyžaduje otevřenou transakci.');
    $reason = trim($reason);
    if ($actorId < 1 || !$confirmed || $reason === '' || mb_strlen($reason, 'UTF-8') > 1000) {
        throw new InvalidArgumentException('Změna účtu vyžaduje správce, důvod do 1000 znaků a výslovné potvrzení.');
    }
    if (!shopBankSettingsTableAvailable($pdo)) {
        throw new ShopBankSettingsException('Databáze zatím nemá tabulku bankovního nastavení.');
    }

    // Jediný validátor pravidel je shopBankValidateSettings(); administrace
    // nezakládá druhou kopii kontrol.
    $settings = shopBankValidateSettings([
        'iban' => (string)($input['iban'] ?? ''),
        'bic' => (string)($input['bic'] ?? ''),
        'account_label' => (string)($input['account_label'] ?? ''),
        'due_days' => (int)($input['due_days'] ?? 0),
    ]);

    $current = shopBankSettingsStoredRow($pdo, true);
    $before = $current === null ? null : [
        'iban' => (string)$current['iban'],
        'bic' => (string)$current['bic'],
        'account_label' => (string)$current['account_label'],
        'due_days' => (int)$current['due_days'],
    ];
    if ($before === $settings) {
        return ['changed' => false, 'settings' => $settings];
    }

    if ($current === null) {
        $pdo->prepare(
            'INSERT INTO shop_bank_settings(id,iban,bic,account_label,due_days,updated_by_trainer_id) '
            . 'VALUES(?,?,?,?,?,?)'
        )->execute([
            SHOP_BANK_SETTINGS_ROW_ID, $settings['iban'], $settings['bic'],
            $settings['account_label'], $settings['due_days'], $actorId,
        ]);
    } else {
        $pdo->prepare(
            'UPDATE shop_bank_settings SET iban=?,bic=?,account_label=?,due_days=?,'
            . 'updated_by_trainer_id=?,updated_at=CURRENT_TIMESTAMP WHERE id=?'
        )->execute([
            $settings['iban'], $settings['bic'], $settings['account_label'],
            $settings['due_days'], $actorId, SHOP_BANK_SETTINGS_ROW_ID,
        ]);
    }

    shopBankSettingsEvent($pdo, $actorId, 'configure', $before, $settings, $reason);
    return ['changed' => true, 'settings' => $settings];
}

/**
 * @param array<string,mixed>|null $before
 * @param array<string,mixed> $after
 */
function shopBankSettingsEvent(PDO $pdo, int $actorId, string $action, ?array $before, array $after, string $reason): void
{
    if (!$pdo->inTransaction() || $actorId < 1 || !in_array($action, ['configure'], true)) {
        throw new LogicException('Audit bankovního účtu vyžaduje transakci, správce a podporovanou akci.');
    }
    $pdo->prepare(
        'INSERT INTO shop_bank_settings_events(actor_type,actor_id,action,before_json,after_json,reason) '
        . "VALUES ('trainer',?,?,?,?,?)"
    )->execute([
        $actorId,
        $action,
        $before === null ? null : json_encode($before, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
        json_encode($after, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
        $reason,
    ]);
}

/**
 * Kontrolní QR z právě uloženého nastavení. Funkce nedostává PDO, takže ze své
 * podstaty nemůže založit objednávku, platbu ani jiný záznam.
 *
 * @param array{iban:string,bic:string,account_label:string,due_days:int} $settings
 * @return array{payload:string,data_uri:string,iban:string,account_label:string,bic:string,
 *               amount_minor:int,currency:string,variable_symbol:string,due_at:string}
 */
function shopBankSampleQr(array $settings, ?DateTimeImmutable $now = null): array
{
    $settings = shopBankValidateSettings($settings);
    $now ??= new DateTimeImmutable('now', new DateTimeZone('Europe/Prague'));
    $payload = shopPaymentSpdPayload(
        $settings['iban'],
        SHOP_BANK_SAMPLE_AMOUNT_MINOR,
        'CZK',
        SHOP_BANK_SAMPLE_VARIABLE_SYMBOL,
        SHOP_BANK_SAMPLE_MESSAGE
    );

    return [
        'payload' => $payload,
        'data_uri' => shopPaymentQrDataUri($payload),
        'iban' => $settings['iban'],
        'account_label' => $settings['account_label'],
        'bic' => $settings['bic'],
        'amount_minor' => SHOP_BANK_SAMPLE_AMOUNT_MINOR,
        'currency' => 'CZK',
        'variable_symbol' => SHOP_BANK_SAMPLE_VARIABLE_SYMBOL,
        'due_at' => $now->modify('+' . $settings['due_days'] . ' days')->setTime(23, 59, 59)->format('Y-m-d H:i:s'),
    ];
}
