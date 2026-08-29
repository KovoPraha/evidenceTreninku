<?php
declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/includes/file_mutation_transaction.php';

final class FileMutationTransactionTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'evidence-file-tx-' . bin2hex(random_bytes(6));
        mkdir($this->directory, 0700, true);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->directory . DIRECTORY_SEPARATOR . '*') ?: [] as $file) {
            @unlink($file);
        }
        foreach (glob($this->directory . DIRECTORY_SEPARATOR . '.*') ?: [] as $file) {
            if (is_file($file)) @unlink($file);
        }
        @rmdir($this->directory);
    }

    public function testRollbackRemovesNewFileAndRestoresRetiredFile(): void
    {
        $source = $this->directory . DIRECTORY_SEPARATOR . 'source.tmp';
        $final = $this->directory . DIRECTORY_SEPARATOR . 'new.jpg';
        $existing = $this->directory . DIRECTORY_SEPARATOR . 'existing.jpg';
        file_put_contents($source, 'new');
        file_put_contents($existing, 'old');
        $transaction = \fileMutationBegin();

        self::assertTrue(\fileMutationStage($transaction, $source, $final, false));
        \fileMutationRetire($transaction, $existing);
        \fileMutationFinalize($transaction);
        self::assertFileExists($final);
        self::assertFileDoesNotExist($existing);

        \fileMutationRollback($transaction);
        self::assertFileDoesNotExist($final);
        self::assertFileExists($existing);
        self::assertSame('old', file_get_contents($existing));
    }

    public function testCommitKeepsNewFileAndRemovesRetiredFile(): void
    {
        $source = $this->directory . DIRECTORY_SEPARATOR . 'source.tmp';
        $final = $this->directory . DIRECTORY_SEPARATOR . 'new.jpg';
        $existing = $this->directory . DIRECTORY_SEPARATOR . 'existing.jpg';
        file_put_contents($source, 'new');
        file_put_contents($existing, 'old');
        $transaction = \fileMutationBegin();

        self::assertTrue(\fileMutationStage($transaction, $source, $final, false));
        \fileMutationRetire($transaction, $existing);
        \fileMutationFinalize($transaction);
        \fileMutationCommitted($transaction);

        self::assertFileExists($final);
        self::assertFileDoesNotExist($existing);
        self::assertSame('new', file_get_contents($final));
    }

    public function testTrainingAndRaceWritersUseCompensatingFileTransaction(): void
    {
        $root = dirname(__DIR__, 2);
        foreach (['ulozit_trenink.php', 'update_trenink.php', 'ulozit_zavod.php', 'update_zavod.php'] as $path) {
            $source = (string)file_get_contents($root . '/' . $path);
            self::assertStringContainsString('file_mutation_transaction.php', $source, $path);
            self::assertStringContainsString('fileMutationStage(', $source, $path);
            self::assertStringContainsString('fileMutationFinalize(', $source, $path);
            self::assertStringContainsString('fileMutationCommitted(', $source, $path);
            self::assertStringContainsString('fileMutationRollback(', $source, $path);
            self::assertStringNotContainsString('move_uploaded_file(', $source, $path);
        }
        self::assertStringContainsString('fileMutationRetire(', (string)file_get_contents($root . '/update_trenink.php'));
        self::assertStringContainsString('fileMutationRetire(', (string)file_get_contents($root . '/update_zavod.php'));
    }
}
