<?php

declare(strict_types=1);

namespace Tests\Support;

use PDO;
use RuntimeException;

/** Synthetic inputs and the frozen participant validator from 89edfdbe. */
final class AttendanceReadFixture
{
    public static function seed(PDO $pdo): void
    {
        $pdo->exec("CREATE TABLE treneri(id INT PRIMARY KEY,aktivni INT);
            INSERT INTO treneri VALUES(7,1),(8,0),(9,1);
            CREATE TABLE staff_user_positions(trainer_id INT,position_code VARCHAR(30));
            INSERT INTO staff_user_positions VALUES(7,'coach'),(7,'sports_lead'),(8,'coach');
            CREATE TABLE staff_superadmins(trainer_id INT PRIMARY KEY);
            INSERT INTO staff_superadmins VALUES(9);
            CREATE TABLE sportovci(id INT PRIMARY KEY)");
        $q = $pdo->prepare('INSERT INTO sportovci VALUES(?)');
        foreach (range(1, 1200) as $id) {
            $q->execute([$id]);
        }
        self::session();
    }

    public static function session(int $actor = 7, string $position = 'coach'): void
    {
        $_SESSION = ['trener_id' => $actor, 'staff_positions' => [$position], 'staff_active_position' => $position];
    }

    public static function inputs(): array
    {
        return [[], [1], [2, 1, 2, '1'], [0], [-1], ['invalid'], [1201], [1, 1201], [1201, 1],
            range(1, 10), range(1, 50), range(1, 200), range(1, 1200), [...range(1, 1000), 1201]];
    }

    public static function original(PDO $pdo, array $ids): void
    {
        \coachTrainingRequireActor($pdo);
        $q = $pdo->prepare('SELECT id FROM sportovci WHERE id=?');
        foreach (array_unique(array_map('intval', $ids)) as $id) {
            $q->execute([$id]);
            if ($id < 1 || !$q->fetchColumn()) {
                throw new RuntimeException('Vybraný sportovec neexistuje.');
            }
        }
    }

    public static function outcome(callable $operation): ?array
    {
        try {
            $operation();
            return null;
        } catch (RuntimeException $e) {
            return [get_class($e), $e->getMessage()];
        }
    }
}
