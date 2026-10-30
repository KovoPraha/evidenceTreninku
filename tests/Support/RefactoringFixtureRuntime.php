<?php

declare(strict_types=1);

require_once __DIR__ . '/FamilyOverviewPageFixture.php';

/** Owned localhost fixtures only. Never reuse an existing database or directory. */
final class RefactoringFixtureRuntime
{
    public readonly string $schema;
    public readonly string $directory;
    private PDO $admin;
    private array $schemas = [];
    private array $connections = [];

    public function __construct()
    {
        $this->schema = 'w12_fixture_' . bin2hex(random_bytes(8));
        $this->directory = sys_get_temp_dir() . '/' . $this->schema;
        $this->admin = w72Connection();
        if (!mkdir($this->directory, 0700)) {
            throw new RuntimeException('Cannot create owned fixture directory.');
        }
    }

    public function create(string $suffix = '', bool $profile = false): PDO
    {
        if (!in_array($suffix, ['', '_restored'], true)) {
            throw new RuntimeException('Unexpected fixture suffix.');
        }
        $schema = $this->schema . $suffix;
        $this->admin->exec('CREATE DATABASE `' . $schema . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        $this->schemas[] = $schema;
        $pdo = w72Connection($schema, $profile);
        $this->connections[] = $pdo;
        return $pdo;
    }

    public function close(): void
    {
        foreach ($this->connections as $pdo) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
        }
        foreach (array_reverse($this->schemas) as $schema) {
            $this->admin->exec('DROP DATABASE `' . $schema . '`');
        }
        $this->schemas = [];
        $this->connections = [];
        $this->remove($this->directory);
    }

    private function remove(string $path): void
    {
        $normalized = str_replace('\\', '/', $path);
        $root = str_replace('\\', '/', $this->directory);
        if ($normalized !== $root && !str_starts_with($normalized, $root . '/')) {
            throw new RuntimeException('Refusing cleanup outside the owned fixture.');
        }
        if (is_link($path) || is_file($path)) {
            unlink($path);
        } elseif (is_dir($path)) {
            foreach (new FilesystemIterator($path) as $item) {
                $this->remove($item->getPathname());
            }
            rmdir($path);
        }
    }
}
