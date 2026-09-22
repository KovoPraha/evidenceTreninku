<?php
declare(strict_types=1);

final class ShopProductInterestException extends RuntimeException
{
}

/** @return array{id:int,created:bool,status:string} */
function shopProductInterestSubmit(PDO $pdo, int $productId, ?int $variantId, string $email, string $sourcePath, bool $consent): array
{
    $email = strtolower(trim($email));
    $sourcePath = trim($sourcePath);
    if ($productId < 1 || !filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email, 'UTF-8') > 254) {
        throw new InvalidArgumentException('Zadejte platný e-mail.');
    }
    if (!$consent) throw new InvalidArgumentException('Potvrďte, že vás můžeme k této nabídce kontaktovat.');
    if ($sourcePath === '' || mb_strlen($sourcePath, 'UTF-8') > 255 || str_contains($sourcePath, "\n")) {
        throw new InvalidArgumentException('Neplatný zdroj kontaktního formuláře.');
    }
    $product = $pdo->prepare("SELECT id FROM shop_products WHERE id=? AND catalog_status='active'");
    $product->execute([$productId]);
    if ($product->fetchColumn() === false) throw new ShopProductInterestException('Tato nabídka už není aktivní.');
    if ($variantId !== null) {
        $variant = $pdo->prepare("SELECT id FROM shop_variants WHERE id=? AND product_id=? AND catalog_status='active'");
        $variant->execute([$variantId, $productId]);
        if ($variant->fetchColumn() === false) throw new ShopProductInterestException('Vybraný termín nebo varianta už není aktivní.');
    }
    $consentText = 'Žádám KOVO Praha o kontakt k této nabídce; kontakt bude použit pro vyřízení tohoto požadavku.';
    $pdo->beginTransaction();
    try {
        $existing = $pdo->prepare("SELECT id,status FROM shop_product_interests WHERE product_id=? AND email_normalized=? AND ((variant_id IS NULL AND ? IS NULL) OR variant_id=?) AND status IN ('new','contacted') ORDER BY id DESC LIMIT 1");
        $existing->execute([$productId, $email, $variantId, $variantId]);
        $row = $existing->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $pdo->commit();
            return ['id' => (int)$row['id'], 'created' => false, 'status' => (string)$row['status']];
        }
        $insert = $pdo->prepare('INSERT INTO shop_product_interests(product_id,variant_id,email,email_normalized,source_path,consent_text_snapshot,status) VALUES (?,?,?,?,?,?,\'new\')');
        $insert->execute([$productId, $variantId, $email, $email, $sourcePath, $consentText]);
        $id = (int)$pdo->lastInsertId();
        $pdo->prepare("INSERT INTO shop_product_interest_events(interest_id,actor_trainer_id,action,from_status,to_status,note) VALUES (?,NULL,'submit',NULL,'new','Kontakt z veřejné produktové stránky.')")
            ->execute([$id]);
        $pdo->commit();
        return ['id' => $id, 'created' => true, 'status' => 'new'];
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        if ($exception instanceof InvalidArgumentException || $exception instanceof ShopProductInterestException) throw $exception;
        throw new ShopProductInterestException('Kontakt se nepodařilo bezpečně uložit.', 0, $exception);
    }
}

/** @return list<array<string,mixed>> */
function shopProductInterestAdminList(PDO $pdo, string $status = '', int $limit = 300): array
{
    if ($status !== '' && !in_array($status, ['new','contacted','closed'], true)) throw new InvalidArgumentException('Neplatný filtr kontaktů.');
    $limit = max(1, min(500, $limit));
    $sql = 'SELECT i.*,pub.public_name,v.sku,e.action AS last_action,e.note AS last_note,e.created_at AS last_event_at '
        . 'FROM shop_product_interests i JOIN shop_product_publications pub ON pub.product_id=i.product_id '
        . 'LEFT JOIN shop_variants v ON v.id=i.variant_id '
        . 'LEFT JOIN shop_product_interest_events e ON e.id=(SELECT MAX(e2.id) FROM shop_product_interest_events e2 WHERE e2.interest_id=i.id) ';
    $parameters = [];
    if ($status !== '') {
        $sql .= 'WHERE i.status=? ';
        $parameters[] = $status;
    }
    $sql .= "ORDER BY CASE i.status WHEN 'new' THEN 0 WHEN 'contacted' THEN 1 ELSE 2 END,i.created_at DESC,i.id DESC LIMIT " . $limit;
    $statement = $pdo->prepare($sql);
    $statement->execute($parameters);
    return $statement->fetchAll(PDO::FETCH_ASSOC);
}

/** @return array{id:int,status:string,changed:bool} */
function shopProductInterestAdminTransition(PDO $pdo, int $interestId, int $actorTrainerId, string $toStatus, string $note, bool $confirmed): array
{
    $note = trim($note);
    if ($interestId < 1 || $actorTrainerId < 1 || !in_array($toStatus, ['contacted','closed'], true) || $note === '' || !$confirmed) {
        throw new InvalidArgumentException('Změna kontaktu vyžaduje záznam, cílový stav, poznámku a potvrzení.');
    }
    if (mb_strlen($note, 'UTF-8') > 1000) throw new InvalidArgumentException('Poznámka smí mít nejvýše 1000 znaků.');
    $pdo->beginTransaction();
    try {
        $sql = 'SELECT id,status FROM shop_product_interests WHERE id=?';
        if ((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') $sql .= ' FOR UPDATE';
        $statement = $pdo->prepare($sql);
        $statement->execute([$interestId]);
        $interest = $statement->fetch(PDO::FETCH_ASSOC);
        if (!$interest) throw new ShopProductInterestException('Kontakt nebyl nalezen.');
        $from = (string)$interest['status'];
        if ($from === $toStatus) {
            $pdo->commit();
            return ['id' => $interestId, 'status' => $toStatus, 'changed' => false];
        }
        if ($from === 'closed') throw new ShopProductInterestException('Uzavřený kontakt už nelze měnit.');
        if ($toStatus === 'contacted') {
            $pdo->prepare("UPDATE shop_product_interests SET status='contacted',contacted_at=CURRENT_TIMESTAMP,updated_at=CURRENT_TIMESTAMP WHERE id=?")->execute([$interestId]);
        } else {
            $pdo->prepare("UPDATE shop_product_interests SET status='closed',closed_at=CURRENT_TIMESTAMP,updated_at=CURRENT_TIMESTAMP WHERE id=?")->execute([$interestId]);
        }
        $pdo->prepare('INSERT INTO shop_product_interest_events(interest_id,actor_trainer_id,action,from_status,to_status,note) VALUES (?,?,?,?,?,?)')
            ->execute([$interestId, $actorTrainerId, $toStatus === 'contacted' ? 'mark_contacted' : 'close', $from, $toStatus, $note]);
        $pdo->commit();
        return ['id' => $interestId, 'status' => $toStatus, 'changed' => true];
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        if ($exception instanceof InvalidArgumentException || $exception instanceof ShopProductInterestException) throw $exception;
        throw new ShopProductInterestException('Stav kontaktu se nepodařilo bezpečně změnit.', 0, $exception);
    }
}
