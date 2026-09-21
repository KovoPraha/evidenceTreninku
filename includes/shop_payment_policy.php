<?php
declare(strict_types=1);

require_once __DIR__.'/individual_lesson_context.php';

const SHOP_PAYMENT_POLICY_BANK_ONLY = 'bank_transfer';
const SHOP_PAYMENT_POLICY_SUMUP_AND_BANK = 'bank_transfer_sumup';

function shopPaymentPolicyNormalize(mixed $value): string
{
    $value = trim((string)$value);
    if (!in_array($value, [SHOP_PAYMENT_POLICY_BANK_ONLY, SHOP_PAYMENT_POLICY_SUMUP_AND_BANK], true)) {
        throw new InvalidArgumentException('Zvolte platbu převodem, nebo převodem a kartou přes SumUp.');
    }
    return $value;
}

function shopPaymentPolicyLabel(string $policy): string
{
    return shopPaymentPolicyNormalize($policy) === SHOP_PAYMENT_POLICY_SUMUP_AND_BANK
        ? 'QR / převod + karta přes SumUp'
        : 'Pouze QR / bankovní převod';
}

function shopPaymentPolicyColumnExists(PDO $pdo, string $table, string $column): bool
{
    if (preg_match('/^[a-z0-9_]+$/D', $table) !== 1 || preg_match('/^[a-z0-9_]+$/D', $column) !== 1) {
        throw new InvalidArgumentException('Neplatná identita sloupce platebního režimu.');
    }
    if ((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') {
        $statement = $pdo->prepare(
            'SELECT 1 FROM information_schema.COLUMNS '
            . 'WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=? LIMIT 1'
        );
        $statement->execute([$table, $column]);
        return (bool)$statement->fetchColumn();
    }
    foreach ($pdo->query('PRAGMA table_info(' . $table . ')')->fetchAll(PDO::FETCH_ASSOC) as $definition) {
        if ((string)($definition['name'] ?? '') === $column) return true;
    }
    return false;
}

function shopPaymentPolicyProductSelect(PDO $pdo, string $alias = 'p'): string
{
    if (preg_match('/^[a-z][a-z0-9_]*$/D', $alias) !== 1) throw new InvalidArgumentException('Neplatný alias produktu.');
    return shopPaymentPolicyColumnExists($pdo, 'shop_products', 'payment_method_policy')
        ? $alias . '.payment_method_policy'
        : "'" . SHOP_PAYMENT_POLICY_BANK_ONLY . "'";
}

function shopPaymentPolicyLessonSelect(PDO $pdo, string $alias = 'il'): string
{
    if (preg_match('/^[a-z][a-z0-9_]*$/D', $alias) !== 1) throw new InvalidArgumentException('Neplatný alias termínu.');
    return shopPaymentPolicyColumnExists($pdo, 'individualni_lekce', 'payment_method_policy')
        ? $alias . '.payment_method_policy'
        : "'" . SHOP_PAYMENT_POLICY_BANK_ONLY . "'";
}

function shopPaymentPolicyPaymentSelect(PDO $pdo, string $alias = 'p'): string
{
    if (preg_match('/^[a-z][a-z0-9_]*$/D', $alias) !== 1) throw new InvalidArgumentException('Neplatný alias platby.');
    // Starší testovací/minimální schémata před migrací zachovají dosavadní SumUp chování.
    // Produkce má po migraci vždy explicitní fail-closed snapshot.
    return shopPaymentPolicyColumnExists($pdo, 'payments', 'accepted_payment_methods')
        ? $alias . '.accepted_payment_methods'
        : "'" . SHOP_PAYMENT_POLICY_SUMUP_AND_BANK . "'";
}

/**
 * Celá objednávka může na SumUp pouze tehdy, když jej povolují všechny položky.
 * Smíšený košík se tím bezpečně zúží na bankovní převod.
 *
 * @param list<array<string,mixed>> ...$groups
 */
function shopPaymentPolicyForItemGroups(array ...$groups): string
{
    $seen = false;
    foreach ($groups as $items) {
        foreach ($items as $item) {
            $seen = true;
            $policy = (string)($item['payment_method_policy'] ?? SHOP_PAYMENT_POLICY_BANK_ONLY);
            if ($policy !== SHOP_PAYMENT_POLICY_SUMUP_AND_BANK) return SHOP_PAYMENT_POLICY_BANK_ONLY;
        }
    }
    return $seen ? SHOP_PAYMENT_POLICY_SUMUP_AND_BANK : SHOP_PAYMENT_POLICY_BANK_ONLY;
}

function shopPaymentPolicyAllowsSumUp(mixed $policy): bool
{
    return (string)$policy === SHOP_PAYMENT_POLICY_SUMUP_AND_BANK;
}

/** @return list<array<string,mixed>> */
function shopPaymentPolicyProducts(PDO $pdo): array
{
    if (!shopPaymentPolicyColumnExists($pdo, 'shop_products', 'payment_method_policy')) return [];
    return $pdo->query(
        'SELECT p.id,p.name,p.offer_type,p.catalog_status,p.payment_method_policy,'
        . 'pub.status AS publication_status,pub.public_name '
        . 'FROM shop_products p LEFT JOIN shop_product_publications pub ON pub.product_id=p.id '
        . "WHERE p.catalog_status<>'inactive' ORDER BY COALESCE(pub.public_name,p.name),p.id"
    )->fetchAll(PDO::FETCH_ASSOC);
}

/** @return list<array<string,mixed>> */
function shopPaymentPolicyVelodromeSlots(PDO $pdo): array
{
    if (!shopPaymentPolicyColumnExists($pdo, 'individualni_lekce', 'payment_method_policy')) return [];
    $context=individualLessonContextCondition($pdo,'il',INDIVIDUAL_LESSON_CONTEXT_VELODROME);
    return $pdo->query(
        'SELECT il.id,il.nazev,il.datum,il.cas_od,il.cas_do,il.cena_kc,il.stav,il.payment_method_policy '
        . 'FROM individualni_lekce il JOIN sportovist s ON s.id=il.sportoviste_id '
        . "WHERE s.kod='velodrom' AND ".$context." AND il.datum>=CURRENT_DATE AND il.cena_kc>0 "
        . 'ORDER BY il.datum,il.cas_od,il.id'
    )->fetchAll(PDO::FETCH_ASSOC);
}

/** @return array{id:int,changed:bool,policy:string} */
function shopPaymentPolicySetProduct(
    PDO $pdo,
    int $actorId,
    int $productId,
    string $policy,
    string $reason,
    bool $confirmed
): array {
    $policy = shopPaymentPolicyNormalize($policy);
    $reason = trim($reason);
    if ($actorId < 1 || $productId < 1 || $reason === '' || mb_strlen($reason, 'UTF-8') > 1000 || !$confirmed) {
        throw new InvalidArgumentException('Změna plateb produktu vyžaduje produkt, důvod a výslovné potvrzení.');
    }
    if (!shopPaymentPolicyColumnExists($pdo, 'shop_products', 'payment_method_policy')) {
        throw new RuntimeException('Databáze ještě nepodporuje nastavení platebních metod.');
    }
    $pdo->beginTransaction();
    try {
        $sql = 'SELECT id,name,payment_method_policy FROM shop_products WHERE id=?';
        if ((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') $sql .= ' FOR UPDATE';
        $statement = $pdo->prepare($sql);$statement->execute([$productId]);$before = $statement->fetch(PDO::FETCH_ASSOC);
        if (!$before) throw new RuntimeException('Produkt nebyl nalezen.');
        $changed = (string)$before['payment_method_policy'] !== $policy;
        if ($changed) {
            $pdo->prepare('UPDATE shop_products SET payment_method_policy=?,updated_at=CURRENT_TIMESTAMP WHERE id=?')
                ->execute([$policy, $productId]);
            $after = $before;$after['payment_method_policy'] = $policy;
            $pdo->prepare(
                'INSERT INTO shop_catalog_admin_events(product_id,variant_id,actor_type,actor_id,action,before_json,after_json,reason) '
                . "VALUES(?,NULL,'trainer',?,'update_payment_policy',?,?,?)"
            )->execute([
                $productId,$actorId,
                json_encode($before,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR),
                json_encode($after,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR),
                $reason,
            ]);
        }
        $pdo->commit();
        return ['id'=>$productId,'changed'=>$changed,'policy'=>$policy];
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        if ($exception instanceof InvalidArgumentException || $exception instanceof RuntimeException) throw $exception;
        throw new RuntimeException('Platební režim produktu se nepodařilo uložit bez částečné změny.',0,$exception);
    }
}

/** @return array{id:int,changed:bool,policy:string} */
function shopPaymentPolicySetVelodromeSlot(
    PDO $pdo,
    int $actorId,
    int $lessonId,
    string $policy,
    string $reason,
    bool $confirmed
): array {
    $policy = shopPaymentPolicyNormalize($policy);
    $reason = trim($reason);
    if ($actorId < 1 || $lessonId < 1 || $reason === '' || mb_strlen($reason, 'UTF-8') > 1000 || !$confirmed) {
        throw new InvalidArgumentException('Změna plateb termínu vyžaduje termín, důvod a výslovné potvrzení.');
    }
    if (!shopPaymentPolicyColumnExists($pdo, 'individualni_lekce', 'payment_method_policy')) {
        throw new RuntimeException('Databáze ještě nepodporuje nastavení platebních metod termínu.');
    }
    $pdo->beginTransaction();
    try {
        $sql = 'SELECT il.id,il.nazev,il.cena_kc,il.payment_method_policy,s.kod FROM individualni_lekce il '
            . 'JOIN sportovist s ON s.id=il.sportoviste_id WHERE il.id=?';
        if ((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') $sql .= ' FOR UPDATE';
        $statement = $pdo->prepare($sql);$statement->execute([$lessonId]);$before = $statement->fetch(PDO::FETCH_ASSOC);
        if (!$before || (string)$before['kod'] !== 'velodrom' || (float)$before['cena_kc'] <= 0.0) {
            throw new RuntimeException('Placený termín velodromu nebyl nalezen.');
        }
        $changed = (string)$before['payment_method_policy'] !== $policy;
        if ($changed) {
            $pdo->prepare('UPDATE individualni_lekce SET payment_method_policy=? WHERE id=?')->execute([$policy,$lessonId]);
            $payload = ['from'=>(string)$before['payment_method_policy'],'to'=>$policy];
            if (function_exists('venueOperationAudit')) {
                venueOperationAudit($pdo,'lesson',$lessonId,$actorId,'update_payment_policy',$reason,$payload);
            }
        }
        $pdo->commit();
        return ['id'=>$lessonId,'changed'=>$changed,'policy'=>$policy];
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        if ($exception instanceof InvalidArgumentException || $exception instanceof RuntimeException) throw $exception;
        throw new RuntimeException('Platební režim termínu se nepodařilo uložit bez částečné změny.',0,$exception);
    }
}
