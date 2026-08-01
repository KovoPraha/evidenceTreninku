<?php
declare(strict_types=1);

namespace Tests\Support;

use PDO;

final class KisMatcherDatabase
{
    /**
     * @param list<array<string, mixed>> $athletes
     */
    public static function create(array $athletes = []): PDO
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $pdo->exec(<<<'SQL'
            CREATE TABLE sportovci (
                id INTEGER PRIMARY KEY,
                jmeno TEXT NOT NULL DEFAULT '',
                prijmeni TEXT NOT NULL DEFAULT '',
                first_name_norm TEXT,
                last_name_norm TEXT,
                narozeni TEXT,
                email TEXT,
                uciid TEXT
            )
            SQL);

        foreach ($athletes as $athlete) {
            self::insert($pdo, $athlete);
        }

        return $pdo;
    }

    /**
     * @param array<string, mixed> $athlete
     */
    public static function insert(PDO $pdo, array $athlete): void
    {
        $stmt = $pdo->prepare(<<<'SQL'
            INSERT INTO sportovci
                (id, jmeno, prijmeni, first_name_norm, last_name_norm, narozeni, email, uciid)
            VALUES
                (:id, :jmeno, :prijmeni, :first_name_norm, :last_name_norm, :narozeni, :email, :uciid)
            SQL);
        $stmt->execute([
            ':id' => $athlete['id'],
            ':jmeno' => $athlete['jmeno'] ?? '',
            ':prijmeni' => $athlete['prijmeni'] ?? '',
            ':first_name_norm' => $athlete['first_name_norm'] ?? null,
            ':last_name_norm' => $athlete['last_name_norm'] ?? null,
            ':narozeni' => $athlete['narozeni'] ?? null,
            ':email' => $athlete['email'] ?? null,
            ':uciid' => $athlete['uciid'] ?? null,
        ]);
    }
}
