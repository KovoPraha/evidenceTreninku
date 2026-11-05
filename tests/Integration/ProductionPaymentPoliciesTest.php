<?php

declare(strict_types=1);

namespace Tests\Integration;

use PDO;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2).'/includes/shop_payment_policy.php';
require_once dirname(__DIR__, 2).'/bin/configure-production-payment-policies.php';

final class ProductionPaymentPoliciesTest extends TestCase
{
    public function testOnlyApprovedActiveProductsChangeAndReplayKeepsAuditAndOldPayments(): void
    {
        $pdo = $this->database();
        $preview = \kisPaymentPolicyInventory($pdo, 'owner@example.invalid');
        $result = \kisPaymentPolicyApply($pdo, 'owner@example.invalid', $preview['manifest_sha256'], 2, 'LOCAL explicit card approval', true);
        self::assertSame(2, $result['changed']);
        self::assertTrue($result['existing_payment_policies_unchanged']);
        self::assertSame('bank_transfer', $pdo->query('SELECT accepted_payment_methods FROM payments')->fetchColumn());
        self::assertSame('bank_transfer', $pdo->query('SELECT payment_method_policy FROM shop_products WHERE id=3')->fetchColumn());
        self::assertSame([100, 200, 300], array_map('intval', $pdo->query('SELECT price_minor FROM shop_products ORDER BY id')->fetchAll(PDO::FETCH_COLUMN)));
        self::assertSame(2, (int)$pdo->query('SELECT COUNT(*) FROM shop_catalog_admin_events')->fetchColumn());
        $again = \kisPaymentPolicyApply($pdo, 'owner@example.invalid', $preview['manifest_sha256'], 2, 'LOCAL explicit card approval', true);
        self::assertSame(0, $again['changed']);
        self::assertSame(2, (int)$pdo->query('SELECT COUNT(*) FROM shop_catalog_admin_events')->fetchColumn());
    }

    public function testScopeOrApprovalChangesFailBeforeWriting(): void
    {
        foreach (['scope', 'count', 'confirmation', 'operator'] as $case) {
            $pdo = $this->database();
            $preview = \kisPaymentPolicyInventory($pdo, 'owner@example.invalid');
            if ($case === 'scope') {
                $pdo->exec("UPDATE shop_products SET name='LOCAL renamed' WHERE id=1");
            }
            if ($case === 'operator') {
                $pdo->exec('DELETE FROM staff_superadmins');
            }
            $error = null;
            try {
                \kisPaymentPolicyApply(
                    $pdo,
                    'owner@example.invalid',
                    $preview['manifest_sha256'],
                    $case === 'count' ? 3 : 2,
                    'LOCAL explicit card approval',
                    $case !== 'confirmation'
                );
            } catch (\RuntimeException $caught) {
                $error = $caught;
            }
            self::assertInstanceOf(\RuntimeException::class, $error, $case);
            self::assertSame(0, (int)$pdo->query('SELECT COUNT(*) FROM shop_catalog_admin_events')->fetchColumn());
            self::assertSame(0, (int)$pdo->query("SELECT COUNT(*) FROM shop_products WHERE payment_method_policy='bank_transfer_sumup'")->fetchColumn());
        }
    }

    public function testInterruptedCanonicalBatchCanResumeWithoutDuplicatingEarlierAudit(): void
    {
        $pdo = $this->database();
        $preview = \kisPaymentPolicyInventory($pdo, 'owner@example.invalid');
        $pdo->exec("CREATE TRIGGER fail_second BEFORE UPDATE ON shop_products WHEN NEW.id=2 BEGIN SELECT RAISE(ABORT,'LOCAL injected failure'); END");
        $error = null;
        try {
            \kisPaymentPolicyApply($pdo, 'owner@example.invalid', $preview['manifest_sha256'], 2, 'LOCAL explicit card approval', true);
        } catch (\RuntimeException $caught) {
            $error = $caught;
        }
        self::assertInstanceOf(\RuntimeException::class, $error);
        self::assertFalse($pdo->inTransaction());
        self::assertSame(1, (int)$pdo->query('SELECT COUNT(*) FROM shop_catalog_admin_events')->fetchColumn());
        $pdo->exec('DROP TRIGGER fail_second');
        $result = \kisPaymentPolicyApply($pdo, 'owner@example.invalid', $preview['manifest_sha256'], 2, 'LOCAL explicit card approval', true);
        self::assertSame(1, $result['changed']);
        self::assertSame(2, (int)$pdo->query('SELECT COUNT(*) FROM shop_catalog_admin_events')->fetchColumn());
        self::assertSame('bank_transfer', $pdo->query('SELECT accepted_payment_methods FROM payments')->fetchColumn());
    }

    private function database(): PDO
    {
        $pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
        $pdo->exec('CREATE TABLE treneri(id INTEGER PRIMARY KEY,email TEXT,aktivni INT); CREATE TABLE staff_superadmins(trainer_id INT);'
            ."INSERT INTO treneri VALUES(7,'owner@example.invalid',1); INSERT INTO staff_superadmins VALUES(7)");
        $pdo->exec('CREATE TABLE shop_products(id INTEGER PRIMARY KEY,name TEXT,offer_type TEXT,payment_method_policy TEXT,'
            .'catalog_status TEXT,price_minor INT,updated_at TEXT)');
        $pdo->exec("INSERT INTO shop_products VALUES(1,'LOCAL A','goods','bank_transfer','active',100,NULL),"
            ."(2,'LOCAL B','course','bank_transfer','active',200,NULL),(3,'LOCAL hidden','goods','bank_transfer','inactive',300,NULL)");
        $pdo->exec('CREATE TABLE shop_catalog_admin_events(id INTEGER PRIMARY KEY,product_id INT,variant_id INT,actor_type TEXT,'
            .'actor_id INT,action TEXT,before_json TEXT,after_json TEXT,reason TEXT)');
        $pdo->exec("CREATE TABLE payments(id INTEGER PRIMARY KEY,accepted_payment_methods TEXT); INSERT INTO payments VALUES(8,'bank_transfer')");
        return $pdo;
    }
}
