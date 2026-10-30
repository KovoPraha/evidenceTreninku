<?php

declare(strict_types=1);

namespace Tests\Unit;

use PDO;
use PDOException;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/includes/notification_claim_retry.php';

final class NotificationClaimRetryTest extends TestCase
{
    public function testRetriesOnlyRolledBackMysqlDeadlocksAndReturnsClaim(): void
    {
        $pdo = $this->createMock(PDO::class);
        $pdo->method('getAttribute')->willReturn('mysql');
        $pdo->method('inTransaction')->willReturn(false);
        $calls = 0;
        $result = \notificationClaimWithRetry($pdo, function () use (&$calls): array {
            if (++$calls < 3) {
                throw $this->deadlock();
            }
            return ['id' => 7];
        });
        self::assertSame(3, $calls);
        self::assertSame(['id' => 7], $result);
    }

    public function testBoundedRetriesAndNoRetryForOtherFailuresOrActiveTransaction(): void
    {
        foreach ([['mysql', false, '40001', 1213, 3], ['mysql', true, '40001', 1213, 1],
            ['sqlite', false, '40001', 1213, 1], ['mysql', false, 'HY000', 1205, 1],
            ['mysql', false, '23000', 1062, 1], ['mysql', false, 'HY000', 2006, 1]] as $case) {
            [$driver, $active, $state, $code, $expected] = $case;
            $pdo = $this->createMock(PDO::class);
            $pdo->method('getAttribute')->willReturn($driver);
            $pdo->method('inTransaction')->willReturn($active);
            $failure = new PDOException('Synthetic database failure');
            $failure->errorInfo = [$state, $code];
            $property = new \ReflectionProperty(\Exception::class, 'code');
            $property->setValue($failure, $state);
            $calls = 0;
            try {
                \notificationClaimWithRetry($pdo, static function () use (&$calls, $failure): never {
                    $calls++;
                    throw $failure;
                });
                self::fail('Failure must propagate.');
            } catch (PDOException $exception) {
                self::assertSame($failure, $exception);
            }
            self::assertSame($expected, $calls);
        }
    }

    private function deadlock(): PDOException
    {
        $exception = new PDOException('Synthetic InnoDB deadlock', 40001);
        $exception->errorInfo = ['40001', 1213];
        return $exception;
    }
}
