<?php
declare(strict_types=1);

require_once __DIR__ . '/shop_checkout.php';

class SumUpGatewayException extends RuntimeException
{
}

final class SumUpGatewayDisabledException extends SumUpGatewayException
{
}

final class SumUpWebhookException extends SumUpGatewayException
{
}

interface SumUpGatewayClient
{
    /** @param array<string,mixed> $parameters @return array<string,mixed> */
    public function createCheckout(array $parameters): array;

    /** @return list<array<string,mixed>> */
    public function listCheckouts(string $checkoutReference): array;

    /** @return array<string,mixed> */
    public function retrieveCheckout(string $checkoutId): array;
}

final class SumUpApiGatewayClient implements SumUpGatewayClient
{
    private const API_BASE = 'https://api.sumup.com';

    public function __construct(private readonly string $apiKey)
    {
        if (preg_match('/^sup_sk_[A-Za-z0-9._-]+$/D', $apiKey) !== 1) {
            throw new SumUpGatewayException('SumUp API klíč nemá platný formát.');
        }
    }

    public function createCheckout(array $parameters): array
    {
        return $this->request('POST', '/v0.1/checkouts', $parameters);
    }

    public function listCheckouts(string $checkoutReference): array
    {
        $response = $this->request('GET', '/v0.1/checkouts?checkout_reference=' . rawurlencode($checkoutReference));
        if (!array_is_list($response)) throw new SumUpGatewayException('SumUp vrátil neplatný seznam plateb.');
        /** @var list<array<string,mixed>> $response */
        return $response;
    }

    public function retrieveCheckout(string $checkoutId): array
    {
        return $this->request('GET', '/v0.1/checkouts/' . rawurlencode($checkoutId));
    }

    /** @param array<string,mixed>|null $body @return array<string,mixed> */
    private function request(string $method, string $path, ?array $body = null): array
    {
        if (!function_exists('curl_init')) throw new SumUpGatewayException('Na serveru chybí rozšíření cURL potřebné pro SumUp.');
        $curl = curl_init(self::API_BASE . $path);
        if ($curl === false) throw new SumUpGatewayException('Spojení se SumUp se nepodařilo připravit.');
        $headers = ['Accept: application/json', 'Authorization: Bearer ' . $this->apiKey];
        $options = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_CUSTOMREQUEST => $method,
        ];
        if ($body !== null) {
            $encoded = json_encode($body, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION);
            $headers[] = 'Content-Type: application/json';
            $options[CURLOPT_POSTFIELDS] = $encoded;
        }
        $options[CURLOPT_HTTPHEADER] = $headers;
        curl_setopt_array($curl, $options);
        try {
            $raw = curl_exec($curl);
            $status = (int)curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
            if (!is_string($raw)) throw new SumUpGatewayException('SumUp API není dostupné.');
            if ($status < 200 || $status >= 300) throw new SumUpGatewayException('SumUp API odmítlo požadavek (HTTP ' . $status . ').');
            $decoded = json_decode($raw, true, 64, JSON_THROW_ON_ERROR);
            if (!is_array($decoded)) throw new SumUpGatewayException('SumUp API vrátilo neplatnou odpověď.');
            return $decoded;
        } catch (JsonException $exception) {
            throw new SumUpGatewayException('SumUp API vrátilo neplatnou odpověď.', 0, $exception);
        } finally {
            curl_close($curl);
        }
    }
}

/** @return array{enabled:bool,api_key:string,merchant_code:string,base_url:string} */
function sumupSettingsFromConfig(): array
{
    return [
        'enabled' => defined('SUMUP_ENABLED') && SUMUP_ENABLED === true,
        'api_key' => defined('SUMUP_API_KEY') ? trim((string)SUMUP_API_KEY) : '',
        'merchant_code' => defined('SUMUP_MERCHANT_CODE') ? strtoupper(trim((string)SUMUP_MERCHANT_CODE)) : '',
        'base_url' => defined('APP_BASE_URL') ? rtrim((string)APP_BASE_URL, '/') : '',
    ];
}

/** @param array<string,mixed>|null $settings */
function sumupIsEnabled(?array $settings = null): bool
{
    $settings ??= sumupSettingsFromConfig();
    $baseUrl = (string)($settings['base_url'] ?? '');
    $parts = parse_url($baseUrl);
    $host = strtolower((string)($parts['host'] ?? ''));
    $scheme = strtolower((string)($parts['scheme'] ?? ''));
    $trustedBase = is_array($parts)
        && !isset($parts['user'], $parts['pass'], $parts['query'], $parts['fragment'])
        && ($scheme === 'https' || ($scheme === 'http' && in_array($host, ['localhost', '127.0.0.1'], true)));
    return ($settings['enabled'] ?? false) === true
        && preg_match('/^sup_sk_[A-Za-z0-9._-]+$/D', (string)($settings['api_key'] ?? '')) === 1
        && preg_match('/^[A-Z0-9]{8}$/D', (string)($settings['merchant_code'] ?? '')) === 1
        && $trustedBase;
}

function sumupAmountToMinor(mixed $amount): int
{
    if (!is_int($amount) && !is_float($amount) && !is_string($amount)) throw new SumUpGatewayException('SumUp částka nemá platný formát.');
    $decimal = is_float($amount) ? number_format($amount, 2, '.', '') : trim((string)$amount);
    if (preg_match('/^(0|[1-9][0-9]{0,9})(?:\.([0-9]{1,2}))?$/D', $decimal, $matches) !== 1) {
        throw new SumUpGatewayException('SumUp částka nemá platný formát.');
    }
    $fraction = str_pad((string)($matches[2] ?? ''), 2, '0');
    return ((int)$matches[1] * 100) + (int)$fraction;
}

function sumupCheckoutReference(int $orderId, int $paymentId): string
{
    return 'KIS-' . $orderId . '-' . $paymentId;
}

/**
 * Creates or safely reuses a SumUp Hosted Checkout from the immutable order snapshot.
 *
 * @param array<string,mixed>|null $settings
 * @return array{id:string,url:string,reference:string,order_id:int,payment_id:int,amount_total:int,currency:string}
 */
function sumupCreateCheckout(PDO $pdo, int $orderId, int $accountId, SumUpGatewayClient $client, ?array $settings = null): array
{
    $settings ??= sumupSettingsFromConfig();
    if (!sumupIsEnabled($settings)) throw new SumUpGatewayDisabledException('SumUp je vypnutý nebo neúplně nakonfigurovaný.');
    if ($orderId < 1 || $accountId < 1) throw new InvalidArgumentException('SumUp checkout vyžaduje platnou objednávku a účet.');
    $statement = $pdo->prepare("SELECT o.id,o.public_code,o.account_id,o.status,o.payment_status,o.total_minor,o.currency,p.id AS payment_id,p.payable_type,p.payable_id,p.status AS payment_record_status,p.amount_minor,p.currency AS payment_currency,p.sumup_checkout_id,p.sumup_checkout_reference FROM shop_orders o JOIN payments p ON p.payable_type='shop_order' AND p.payable_id=o.id WHERE o.id=? AND o.account_id=?");
    $statement->execute([$orderId, $accountId]);
    $snapshot = $statement->fetch(PDO::FETCH_ASSOC);
    sumupAssertPendingOrderSnapshot($snapshot);
    $reference = sumupCheckoutReference((int)$snapshot['id'], (int)$snapshot['payment_id']);
    $storedId = trim((string)($snapshot['sumup_checkout_id'] ?? ''));
    if ($storedId !== '') {
        $checkout = $client->retrieveCheckout($storedId);
    } else {
        $matches = array_values(array_filter(
            $client->listCheckouts($reference),
            static fn(mixed $candidate): bool => is_array($candidate) && (string)($candidate['checkout_reference'] ?? '') === $reference
        ));
        if (count($matches) > 1) throw new SumUpGatewayException('SumUp eviduje pro objednávku více Checkoutů; je nutná ruční kontrola.');
        $checkout = $matches[0] ?? $client->createCheckout([
            'checkout_reference' => $reference,
            'amount' => number_format((int)$snapshot['total_minor'] / 100, 2, '.', ''),
            'currency' => (string)$snapshot['currency'],
            'merchant_code' => (string)$settings['merchant_code'],
            'description' => 'Objednávka ' . (string)$snapshot['public_code'],
            'return_url' => rtrim((string)$settings['base_url'], '/') . '/booking/sumup_webhook.php',
            'redirect_url' => rtrim((string)$settings['base_url'], '/') . '/booking/objednavka.php?code=' . rawurlencode((string)$snapshot['public_code']) . '&sumup=return',
            'hosted_checkout' => ['enabled' => true],
        ]);
    }
    $verified = sumupValidateCheckout($checkout, $reference, (int)$snapshot['total_minor'], (string)$snapshot['currency'], (string)$settings['merchant_code'], true);
    if ((string)$verified['status'] !== 'PENDING') throw new SumUpGatewayException('SumUp Checkout není ve stavu čekajícím na platbu.');

    $pdo->beginTransaction();
    try {
        $lockSql = "SELECT o.id,o.account_id,o.status,o.payment_status,o.total_minor,o.currency,p.id AS payment_id,p.payable_type,p.payable_id,p.status AS payment_record_status,p.amount_minor,p.currency AS payment_currency,p.sumup_checkout_id,p.sumup_checkout_reference FROM shop_orders o JOIN payments p ON p.payable_type='shop_order' AND p.payable_id=o.id WHERE o.id=? AND o.account_id=?";
        if ((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') $lockSql .= ' FOR UPDATE';
        $lock = $pdo->prepare($lockSql);
        $lock->execute([$orderId, $accountId]);
        $current = $lock->fetch(PDO::FETCH_ASSOC);
        sumupAssertPendingOrderSnapshot($current);
        if ((int)$current['total_minor'] !== (int)$snapshot['total_minor'] || (string)$current['currency'] !== (string)$snapshot['currency']) {
            throw new SumUpGatewayException('Snapshot objednávky se během SumUp checkoutu změnil.');
        }
        $currentId = trim((string)($current['sumup_checkout_id'] ?? ''));
        if ($currentId !== '' && !hash_equals($currentId, (string)$verified['id'])) throw new SumUpGatewayException('Objednávka už má jiný SumUp Checkout.');
        if ($currentId === '') {
            $pdo->prepare('UPDATE payments SET sumup_checkout_id=?,sumup_checkout_reference=?,updated_at=CURRENT_TIMESTAMP WHERE id=?')
                ->execute([(string)$verified['id'], $reference, (int)$current['payment_id']]);
        }
        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        if ($exception instanceof SumUpGatewayException || $exception instanceof ShopCheckoutException) throw $exception;
        throw new SumUpGatewayException('SumUp Checkout se nepodařilo bezpečně navázat.', 0, $exception);
    }
    return [
        'id' => (string)$verified['id'],
        'url' => (string)$verified['hosted_checkout_url'],
        'reference' => $reference,
        'order_id' => $orderId,
        'payment_id' => (int)$snapshot['payment_id'],
        'amount_total' => (int)$snapshot['total_minor'],
        'currency' => (string)$snapshot['currency'],
    ];
}

/** @param array<string,mixed>|false $snapshot */
function sumupAssertPendingOrderSnapshot(array|false $snapshot): void
{
    if (!$snapshot || $snapshot['payable_type'] !== 'shop_order' || (int)$snapshot['payable_id'] !== (int)$snapshot['id']) throw new SumUpGatewayException('Objednávka nebo její platba nebyla nalezena.');
    if ($snapshot['status'] !== 'placed' || $snapshot['payment_status'] !== 'pending' || $snapshot['payment_record_status'] !== 'pending') throw new SumUpGatewayException('SumUp Checkout lze vytvořit pouze pro čekající objednávku.');
    if ((int)$snapshot['total_minor'] < 1 || (int)$snapshot['amount_minor'] !== (int)$snapshot['total_minor'] || (string)$snapshot['currency'] !== (string)$snapshot['payment_currency'] || preg_match('/^[A-Z]{3}$/D', (string)$snapshot['currency']) !== 1) {
        throw new SumUpGatewayException('Částka nebo měna platebního snapshotu není konzistentní.');
    }
}

/** @param array<string,mixed> $checkout @return array<string,mixed> */
function sumupValidateCheckout(array $checkout, string $reference, int $amountMinor, string $currency, string $merchantCode, bool $requireHostedUrl): array
{
    $id = trim((string)($checkout['id'] ?? ''));
    $status = strtoupper(trim((string)($checkout['status'] ?? '')));
    if (preg_match('/^[A-Za-z0-9-]{8,64}$/D', $id) !== 1) throw new SumUpGatewayException('SumUp Checkout nemá platnou identitu.');
    if (!in_array($status, ['PENDING', 'PAID', 'FAILED', 'EXPIRED'], true)) throw new SumUpGatewayException('SumUp Checkout má neznámý stav.');
    if ((string)($checkout['checkout_reference'] ?? '') !== $reference) throw new SumUpGatewayException('SumUp reference neodpovídá objednávce.');
    if (sumupAmountToMinor($checkout['amount'] ?? null) !== $amountMinor || strtoupper((string)($checkout['currency'] ?? '')) !== $currency) throw new SumUpGatewayException('SumUp částka nebo měna neodpovídá objednávce.');
    if (strtoupper((string)($checkout['merchant_code'] ?? '')) !== $merchantCode) throw new SumUpGatewayException('SumUp obchodník neodpovídá konfiguraci.');
    if ($requireHostedUrl) {
        $url = trim((string)($checkout['hosted_checkout_url'] ?? ''));
        $parts = parse_url($url);
        if (!is_array($parts) || strtolower((string)($parts['scheme'] ?? '')) !== 'https' || strtolower((string)($parts['host'] ?? '')) !== 'checkout.sumup.com' || isset($parts['user'], $parts['pass'])) {
            throw new SumUpGatewayException('SumUp vrátil neplatnou adresu platební stránky.');
        }
    }
    $checkout['id'] = $id;
    $checkout['status'] = $status;
    return $checkout;
}

/** @param array<string,mixed> $checkout @return array<string,mixed>|null */
function sumupSuccessfulTransaction(array $checkout): ?array
{
    $transactions = $checkout['transactions'] ?? [];
    if (!is_array($transactions)) return null;
    foreach ($transactions as $transaction) {
        if (is_array($transaction) && strtoupper((string)($transaction['status'] ?? '')) === 'SUCCESSFUL') return $transaction;
    }
    return null;
}

/**
 * Processes a SumUp status callback only after retrieving the checkout from SumUp API.
 * No raw payload or API key is persisted.
 *
 * @param array<string,mixed>|null $settings
 * @return array{status:string,checkout_id:string,checkout_status:string,duplicate:bool,changed:bool}
 */
function sumupHandleWebhook(PDO $pdo, string $payload, SumUpGatewayClient $client, ?array $settings = null): array
{
    $settings ??= sumupSettingsFromConfig();
    if (!sumupIsEnabled($settings)) throw new SumUpGatewayDisabledException('SumUp je vypnutý nebo neúplně nakonfigurovaný.');
    if ($payload === '' || strlen($payload) > 16384) throw new SumUpWebhookException('SumUp callback nemá platné tělo.');
    try {
        $event = json_decode($payload, true, 8, JSON_THROW_ON_ERROR);
    } catch (JsonException $exception) {
        throw new SumUpWebhookException('SumUp callback nemá platnou strukturu.', 0, $exception);
    }
    if (!is_array($event)) throw new SumUpWebhookException('SumUp callback nemá platný formát.');
    $eventType = strtoupper(trim((string)($event['event_type'] ?? '')));
    $checkoutId = trim((string)($event['id'] ?? ''));
    if ($eventType !== 'CHECKOUT_STATUS_CHANGED') {
        return ['status' => 'ignored', 'checkout_id' => '', 'checkout_status' => '', 'duplicate' => false, 'changed' => false];
    }
    if (preg_match('/^[A-Za-z0-9-]{8,64}$/D', $checkoutId) !== 1) throw new SumUpWebhookException('SumUp callback nemá platnou identitu Checkoutu.');

    // SumUp callback není podepsaný. Autoritativním důkazem je až tento serverový GET.
    $checkout = $client->retrieveCheckout($checkoutId);
    $status = strtoupper(trim((string)($checkout['status'] ?? '')));
    $transaction = sumupSuccessfulTransaction($checkout);
    $transactionId = is_array($transaction) ? trim((string)($transaction['id'] ?? '')) : '';
    $eventKey = hash('sha256', $checkoutId . '|' . $status . '|' . $transactionId);

    $pdo->beginTransaction();
    try {
        $paymentSql = "SELECT p.*,o.id AS order_id,o.status AS order_status,o.payment_status AS order_payment_status,o.total_minor AS order_total_minor,o.currency AS order_currency FROM payments p JOIN shop_orders o ON o.id=p.payable_id AND p.payable_type='shop_order' WHERE p.sumup_checkout_id=?";
        if ((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') $paymentSql .= ' FOR UPDATE';
        $statement = $pdo->prepare($paymentSql);
        $statement->execute([$checkoutId]);
        $payment = $statement->fetch(PDO::FETCH_ASSOC);
        if (!$payment) throw new SumUpGatewayException('SumUp Checkout není navázán na žádnou platbu.');
        $verified = sumupValidateCheckout(
            $checkout,
            (string)$payment['sumup_checkout_reference'],
            (int)$payment['amount_minor'],
            (string)$payment['currency'],
            (string)$settings['merchant_code'],
            false
        );
        if ((int)$payment['amount_minor'] !== (int)$payment['order_total_minor'] || (string)$payment['currency'] !== (string)$payment['order_currency']) throw new SumUpGatewayException('SumUp platba neodpovídá serverovému snapshotu objednávky.');
        try {
            $pdo->prepare("INSERT INTO sumup_webhook_events(event_key,checkout_id,checkout_status,transaction_id,payment_id,payload_sha256,processing_status) VALUES (?,?,?,?,?,?,'processing')")
                ->execute([$eventKey, $checkoutId, (string)$verified['status'], $transactionId !== '' ? $transactionId : null, (int)$payment['id'], hash('sha256', $payload)]);
        } catch (PDOException $exception) {
            if ((string)$exception->getCode() !== '23000') throw $exception;
            $pdo->rollBack();
            return ['status' => 'duplicate', 'checkout_id' => $checkoutId, 'checkout_status' => (string)$verified['status'], 'duplicate' => true, 'changed' => false];
        }
        if ((string)$verified['status'] !== 'PAID' || !is_array($transaction)) {
            $pdo->prepare("UPDATE sumup_webhook_events SET processing_status='ignored',processed_at=CURRENT_TIMESTAMP WHERE event_key=?")->execute([$eventKey]);
            $pdo->commit();
            return ['status' => 'ignored', 'checkout_id' => $checkoutId, 'checkout_status' => (string)$verified['status'], 'duplicate' => false, 'changed' => false];
        }
        if (preg_match('/^[A-Za-z0-9-]{8,64}$/D', $transactionId) !== 1) throw new SumUpGatewayException('SumUp transakce nemá platnou identitu.');
        if (sumupAmountToMinor($transaction['amount'] ?? null) !== (int)$payment['amount_minor'] || strtoupper((string)($transaction['currency'] ?? '')) !== (string)$payment['currency']) throw new SumUpGatewayException('SumUp transakce neodpovídá částce nebo měně objednávky.');
        if (strtoupper((string)($transaction['merchant_code'] ?? '')) !== (string)$settings['merchant_code']) throw new SumUpGatewayException('SumUp transakce patří jinému obchodníkovi.');
        if (strtoupper((string)($transaction['payment_type'] ?? '')) !== 'ECOM') throw new SumUpGatewayException('SumUp transakce není online platba.');
        $pdo->prepare('UPDATE payments SET sumup_transaction_id=? WHERE id=?')->execute([$transactionId, (int)$payment['id']]);
        $transition = shopOrderConfirmPaymentInTransaction($pdo, (int)$payment['id'], 'sumup', 'system', null, 'SumUp callback potvrdil Checkout ' . $checkoutId . ' a transakci ' . $transactionId . '.');
        $pdo->prepare("UPDATE sumup_webhook_events SET processing_status='processed',processed_at=CURRENT_TIMESTAMP WHERE event_key=?")->execute([$eventKey]);
        $pdo->commit();
        return ['status' => 'processed', 'checkout_id' => $checkoutId, 'checkout_status' => (string)$verified['status'], 'duplicate' => false, 'changed' => (bool)$transition['changed']];
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        if ($exception instanceof SumUpGatewayException || $exception instanceof ShopCheckoutException || $exception instanceof InvalidArgumentException) throw $exception;
        throw new SumUpGatewayException('SumUp callback selhal bez částečného zápisu.', 0, $exception);
    }
}
