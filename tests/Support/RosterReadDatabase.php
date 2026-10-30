<?php

declare(strict_types=1);

namespace Tests\Support;

use PDO;

trait RosterReadDatabase
{
    /** @var list<string> */
    private array $schemas = [];
    private ?PDO $admin = null;

    private function database(): QueryProfilePdo
    {
        if (getenv('EVIDENCE_ROSTER_READ_MARIADB') !== '1') {
            return new QueryProfilePdo('sqlite::memory:');
        }
        $port = getenv('EVIDENCE_MARIADB_SMOKE_PORT') ?: '3306';
        if (!ctype_digit($port) || (int) $port < 1 || (int) $port > 65535) {
            throw new \RuntimeException('Invalid isolated localhost port.');
        }
        $dsn = 'mysql:host=127.0.0.1;port=' . $port . ';charset=utf8mb4';
        $this->admin ??= new PDO($dsn, 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $schema = 'w82_contract_' . bin2hex(random_bytes(8));
        $this->admin->exec('CREATE DATABASE `' . $schema . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        $this->schemas[] = $schema;
        $pdo = new QueryProfilePdo($dsn . ';dbname=' . $schema, 'root', '');
        $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
        return $pdo;
    }

    protected function tearDown(): void
    {
        foreach ($this->schemas as $schema) {
            $this->admin?->exec('DROP DATABASE `' . $schema . '`');
        }
    }
}
