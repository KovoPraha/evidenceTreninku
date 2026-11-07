<?php

declare(strict_types=1);

/** Explicit, scoped mandate; does not create an authenticated session or grant roles. */
final class AccountingDocumentWorker
{
    private static ?int $actor = null;
    private static ?array $orderSource = null;
    private static ?PDO $orderConnection = null;

    public static function orderSource(PDO $pdo, int $orderId): ?array
    {
        return self::$actor !== null && self::$orderConnection === $pdo
            && (int)(self::$orderSource['source_id'] ?? 0) === $orderId ? self::$orderSource : null;
    }

    /** Frozen checkout source, only for a registered and actually paid order, including a later cancellation. */
    public static function runOrder(PDO $pdo, int $orderId, callable $operation): mixed
    {
        $q = $pdo->prepare(
            ('SELECT s.*,p.approved_by FROM shop_order_documents s JOIN shop_document_policies p ON ' .
    'p.id=s.policy_id WHERE s.order_id=? AND s.paid_at IS NOT NULL')
        );
        $q->execute([$orderId]);
        $row = $q->fetch(PDO::FETCH_ASSOC);
        if (!$row || !hash_equals($row['source_sha256'], hash('sha256', $row['source_json']))) {
            throw new DomainException('Chybí ověřený podklad zaplacené objednávky.');
        }
        $source = json_decode($row['source_json'], true, 512, JSON_THROW_ON_ERROR);
        if (($source['source_type'] ?? '') !== 'order' || (int)$source['source_id'] !== $orderId) {
            throw new DomainException('Podklad patří jiné objednávce.');
        }
        return self::run($pdo, (int)$row['approved_by'], static function () use ($pdo, $source, $operation): mixed {
            $previous = self::$orderSource;
            $connection = self::$orderConnection;
            self::$orderSource = $source;
            self::$orderConnection = $pdo;
            try {
                return $operation();
            } finally {
                self::$orderSource = $previous;
                self::$orderConnection = $connection;
            }
        });
    }

    public static function actor(): ?int
    {
        return self::$actor;
    }

    public static function assertMandate(PDO $pdo, int $approvedBy): void
    {
        $q = $pdo->prepare('SELECT aktivni FROM treneri WHERE id=?');
        $q->execute([$approvedBy]);
        if ($approvedBy < 1 || (int)$q->fetchColumn() !== 1) {
            throw new DomainException('Schválení tarifu již nemá aktivního vlastníka.');
        }
        $q = $pdo->prepare(
            "SELECT trainer_id FROM staff_user_positions WHERE trainer_id=? AND position_code='finance_manager'"
        );
        $q->execute([$approvedBy]);
        $finance = (bool)$q->fetchColumn();
        $q = $pdo->prepare('SELECT trainer_id FROM staff_superadmins WHERE trainer_id=?');
        $q->execute([$approvedBy]);
        if (!$finance && !$q->fetchColumn()) {
            throw new DomainException('Finanční pověření tarifu již není platné.');
        }
    }

    public static function run(PDO $pdo, int $approvedBy, callable $operation): mixed
    {
        if (PHP_SAPI !== 'cli') {
            throw new DomainException('Automatické vydávání je dostupné pouze workeru.');
        }
        self::assertMandate($pdo, $approvedBy);
        $previous = self::$actor;
        self::$actor = $approvedBy;
        try {
            accountingDocumentRequireFinance($pdo);
            return $operation();
        } finally {
            self::$actor = $previous;
        }
    }
}
