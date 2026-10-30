<?php

declare(strict_types=1);

/**
 * Retry only an InnoDB deadlock in a claim transaction that has already rolled back.
 * The callback must contain only the claim, never validation, transport or completion.
 *
 * @template T
 * @param callable():T $claim
 * @return T
 */
function notificationClaimWithRetry(PDO $pdo, callable $claim): mixed
{
    for ($attempt = 0; ; $attempt++) {
        try {
            return $claim();
        } catch (PDOException $exception) {
            if ($attempt >= 2 || $pdo->inTransaction()
                || $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'mysql'
                || (string) $exception->getCode() !== '40001'
                || (int) ($exception->errorInfo[1] ?? 0) !== 1213) {
                throw $exception;
            }
            usleep(10000 * ($attempt + 1));
        }
    }
}
