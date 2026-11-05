<?php

declare(strict_types=1);

/** An active finance operator approves an exact set; policy is excluded from the digest to allow safe replay. */
function kisPaymentPolicyInventory(PDO $pdo, string $email): array
{
    if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
        throw new RuntimeException('An explicit operator email is required.');
    }
    $q = $pdo->prepare('SELECT id FROM treneri WHERE LOWER(email)=LOWER(?) AND aktivni=1');
    $q->execute([$email]);
    $actors = $q->fetchAll(PDO::FETCH_COLUMN);
    if (count($actors) !== 1) {
        throw new RuntimeException('The operator must be one active staff identity.');
    }
    $actorId = (int)$actors[0];
    // The payment-methods page belongs to finance_manager; superadmin may use every workspace.
    $q = $pdo->prepare("SELECT 1 FROM staff_superadmins WHERE trainer_id=? UNION ALL "
        ."SELECT 1 FROM staff_user_positions WHERE trainer_id=? AND position_code='finance_manager'");
    $q->execute([$actorId, $actorId]);
    if (!$q->fetchColumn()) {
        throw new RuntimeException('This production batch requires existing finance or superadmin permission.');
    }
    $products = $pdo->query("SELECT id,name,offer_type,payment_method_policy FROM shop_products WHERE catalog_status='active' ORDER BY id")
        ->fetchAll(PDO::FETCH_ASSOC);
    if ($products === [] || count($products) > 1000) {
        throw new RuntimeException('Active product set is empty or exceeds the batch limit.');
    }
    $identity = [];
    foreach ($products as $product) {
        shopPaymentPolicyNormalize($product['payment_method_policy']);
        $identity[] = [(int)$product['id'], (string)$product['name'], (string)$product['offer_type']];
    }
    return ['actor_id' => $actorId, 'products' => $products, 'count' => count($products),
        'manifest_sha256' => hash('sha256', json_encode([$actorId, $identity], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR))];
}

/** Each canonical product change is atomic and audited; an interrupted batch can be resumed with the same digest. */
function kisPaymentPolicyApply(PDO $pdo, string $email, string $digest, int $count, string $reason, bool $confirmed): array
{
    $inventory = kisPaymentPolicyInventory($pdo, $email);
    if (!$confirmed || preg_match('/^[a-f0-9]{64}$/D', $digest) !== 1 || !hash_equals($inventory['manifest_sha256'], $digest)
        || $inventory['count'] !== $count || mb_strlen(trim($reason), 'UTF-8') < 10) {
        throw new RuntimeException('Explicit approval, exact product count and unchanged preview digest are required.');
    }
    $oldPayments = $pdo->query('SELECT id,accepted_payment_methods FROM payments ORDER BY id')->fetchAll(PDO::FETCH_KEY_PAIR);
    $changed = 0;
    foreach ($inventory['products'] as $product) {
        $result = shopPaymentPolicySetProduct(
            $pdo,
            $inventory['actor_id'],
            (int)$product['id'],
            SHOP_PAYMENT_POLICY_SUMUP_AND_BANK,
            $reason,
            true
        );
        $changed += $result['changed'] ? 1 : 0;
    }
    $after = kisPaymentPolicyInventory($pdo, $email);
    if (!hash_equals($digest, $after['manifest_sha256'])) {
        throw new RuntimeException('Product scope changed during the batch; inspect the per-product audit before continuing.');
    }
    foreach ($after['products'] as $product) {
        if ($product['payment_method_policy'] !== SHOP_PAYMENT_POLICY_SUMUP_AND_BANK) {
            throw new RuntimeException('Not all approved products have the requested payment policy.');
        }
    }
    $readPayment = $pdo->prepare('SELECT accepted_payment_methods FROM payments WHERE id=?');
    foreach ($oldPayments as $id => $policy) {
        $readPayment->execute([$id]);
        if ($readPayment->fetchColumn() !== $policy) {
            throw new RuntimeException('An existing payment policy changed; inspect before reporting success.');
        }
    }
    return ['ok' => true, 'products' => $count, 'changed' => $changed, 'unchanged' => $count - $changed,
        'manifest_sha256' => $digest, 'existing_payment_policies_unchanged' => true, 'existing_payments_checked' => count($oldPayments)];
}

function kisProductionPaymentPolicyMain(): void
{
    if (PHP_SAPI !== 'cli') {
        http_response_code(404);
        exit;
    }
    if (getenv('KIS_PAYMENT_POLICY_OPERATION') !== '1' || getenv('APP_HOST') !== 'kis.kovopraha.cz') {
        throw new RuntimeException('Explicit production payment-policy operation is required.');
    }
    $root = rtrim((string)getenv('APP_ROOT'), '/');
    $file = (string)getenv('POLICY_SETTINGS_FILE');
    if ($root === '' || !is_file($root.'/config.php') || !is_file($file)) {
        throw new RuntimeException('Production root or operation settings are missing.');
    }
    $input = json_decode((string)file_get_contents($file), true, 8, JSON_THROW_ON_ERROR);
    $_SERVER['HTTP_HOST'] = 'kis.kovopraha.cz';
    $_SERVER['SERVER_NAME'] = 'kis.kovopraha.cz';
    require $root.'/config.php';
    require_once $root.'/includes/shop_payment_policy.php';
    if (!defined('JE_LOKALNE') || JE_LOKALNE !== false) {
        throw new RuntimeException('The production environment is required.');
    }
    $pdo = new PDO(
        'mysql:host='.(string)constant('DB_HOST').';dbname='.(string)constant('DB_NAME').';charset=utf8mb4',
        (string)constant('DB_USER'),
        (string)constant('DB_PASS'),
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );
    $email = (string)($input['operator_email'] ?? '');
    if (($input['action'] ?? '') === 'preview') {
        $pdo->exec('SET SESSION TRANSACTION READ ONLY');
        $pdo->beginTransaction();
        try {
            $inventory = kisPaymentPolicyInventory($pdo, $email);
            $result = ['ok' => true, 'read_only' => true, 'products' => $inventory['count'],
                'manifest_sha256' => $inventory['manifest_sha256'], 'operator_verified' => true];
        } finally {
            $pdo->rollBack();
        }
    } elseif (($input['action'] ?? '') === 'apply') {
        require_once $root.'/includes/sumup_gateway.php';
        if (!sumupIsEnabled()) {
            throw new RuntimeException('Enable and verify the live SumUp gateway before changing product policies.');
        }
        $result = kisPaymentPolicyApply(
            $pdo,
            $email,
            (string)($input['manifest_sha256'] ?? ''),
            (int)($input['expected_count'] ?? 0),
            'Schválené povolení karty aktivním produktům; provozní běh '.(string)($input['run_id'] ?? ''),
            ($input['confirmed'] ?? false) === true
        );
    } else {
        throw new RuntimeException('Unsupported payment-policy action.');
    }
    echo json_encode($result, JSON_THROW_ON_ERROR).PHP_EOL;
}

if (realpath((string)($_SERVER['SCRIPT_FILENAME'] ?? '')) === __FILE__) {
    try {
        kisProductionPaymentPolicyMain();
    } catch (Throwable $error) {
        fwrite(STDERR, 'Payment policy operation failed; inspect the audit before replay: '.$error->getMessage().PHP_EOL);
        exit(1);
    }
}
