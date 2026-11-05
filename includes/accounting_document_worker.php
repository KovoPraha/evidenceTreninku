<?php

declare(strict_types=1);

/** Explicit, scoped mandate; does not create an authenticated session or grant roles. */
final class AccountingDocumentWorker
{
    private static ?int $actor = null;

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
        $q = $pdo->prepare("SELECT trainer_id FROM staff_user_positions WHERE trainer_id=? AND position_code='finance_manager'");
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
