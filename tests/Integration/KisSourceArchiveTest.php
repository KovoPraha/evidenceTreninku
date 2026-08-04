<?php
declare(strict_types=1);

namespace Tests\Integration;

use PDO;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/includes/kis_source_archive.php';
require_once dirname(__DIR__, 2) . '/includes/kis_import_run_lib.php';

final class KisSourceArchiveTest extends TestCase
{
    private array $cleanup = [];

    protected function tearDown(): void
    {
        foreach (array_reverse($this->cleanup) as $path) {
            if (is_file($path) || is_link($path)) {
                @unlink($path);
            } elseif (is_dir($path)) {
                @rmdir($path);
            }
        }
    }

    public function testArchiveIsImmutableIdempotentAndReferencedByPreviewManifest(): void
    {
        $pdo = $this->database();
        [$source, $archive] = $this->paths("id,name\nKIS-1,Anna\n");

        $first = \kisSourceArchive($pdo, $source, 'users', 'kis-export-2026.1', $archive, 7);
        $second = \kisSourceArchive($pdo, $source, 'users', 'kis-export-2026.1', $archive, 7);
        self::assertTrue($first['created_file']);
        self::assertTrue($first['created_record']);
        self::assertFalse($second['created_file']);
        self::assertFalse($second['created_record']);
        self::assertSame($first['id'], $second['id']);
        self::assertSame(1, (int)$pdo->query('SELECT COUNT(*) FROM kis_import_source_artifacts')->fetchColumn());
        self::assertFileExists($archive . DIRECTORY_SEPARATOR . $first['storage_key']);
        $this->cleanup[] = $archive . DIRECTORY_SEPARATOR . $first['storage_key'];
        self::assertSame($first['sha256'], hash_file('sha256', $archive . DIRECTORY_SEPARATOR . $first['storage_key']));

        $runId = \kisImportCreateRun(
            $pdo,
            [['jmeno' => 'Anna', 'prijmeni' => 'Test', 'narozeni' => '2012-01-01']],
            [],
            [],
            ['users' => 'users.csv'],
            7,
            ['users' => $first['id']]
        );
        $manifest = json_decode((string)$pdo->query('SELECT source_manifest_json FROM kis_import_runs WHERE id=' . $runId)->fetchColumn(), true, 32, JSON_THROW_ON_ERROR);
        self::assertSame(\KIS_SOURCE_MANIFEST_CONTRACT, $manifest['contract']);
        self::assertSame($first['sha256'], $manifest['sources']['users']['sha256']);
        self::assertMatchesRegularExpression('/^[a-f0-9]{64}$/D', $manifest['fingerprint']);
        self::assertSame('{}', $pdo->query('SELECT raw_json FROM kis_import_rows WHERE run_id=' . $runId)->fetchColumn());
    }

    public function testArchiveRejectsWebrootSymlinkAndChangedExistingContent(): void
    {
        $pdo = $this->database();
        [$source, $archive] = $this->paths('first');
        $inspection = \kisSourceInspect($source, 'users', 'contract-v1');
        $storageKey = 'users-' . substr(hash('sha256', 'contract-v1'), 0, 8) . '-' . $inspection['sha256'] . '.raw';
        file_put_contents($archive . DIRECTORY_SEPARATOR . $storageKey, 'tampered');
        $this->cleanup[] = $archive . DIRECTORY_SEPARATOR . $storageKey;

        try {
            \kisSourceArchive($pdo, $source, 'users', 'contract-v1', $archive);
            self::fail('Tampered archive must be rejected.');
        } catch (\KisSourceArchiveException $exception) {
            self::assertStringContainsString('jinou velikost', $exception->getMessage());
        }

        $this->expectException(\KisSourceArchiveException::class);
        \kisSourceArchiveRoot(dirname(__DIR__, 2));
    }

    public function testManifestRejectsArtifactAssignedToWrongSourceKind(): void
    {
        $pdo = $this->database();
        [$source, $archive] = $this->paths('payments');
        $artifact = \kisSourceArchive($pdo, $source, 'payments', 'contract-v1', $archive);
        $this->expectException(\KisSourceArchiveException::class);
        \kisSourceManifest($pdo, ['users' => $artifact['id']]);
    }

    private function database(): PDO
    {
        $pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
        $pdo->exec('CREATE TABLE sportovci(id INTEGER PRIMARY KEY,jmeno TEXT,prijmeni TEXT,narozeni TEXT,email TEXT,uciid TEXT)');
        $pdo->exec("INSERT INTO sportovci VALUES(1,'Anna','Test','2012-01-01',NULL,NULL)");
        $pdo->exec('CREATE TABLE kis_import_runs(id INTEGER PRIMARY KEY AUTOINCREMENT,created_by INTEGER,status TEXT,source_users TEXT,source_payments TEXT,source_rosters TEXT,stats_json TEXT,warnings_json TEXT)');
        $pdo->exec('CREATE TABLE kis_import_rows(id INTEGER PRIMARY KEY AUTOINCREMENT,run_id INTEGER,person_key TEXT,jmeno TEXT,prijmeni TEXT,narozeni TEXT,email TEXT,uciid TEXT,oddil TEXT,kis_aktivni INTEGER,kis_platebne_aktivni INTEGER,kis_neuhrazeno REAL,kis_posledni_uhrada TEXT,kis_soupisky TEXT,raw_json TEXT)');
        $pdo->exec('CREATE TABLE kis_import_matches(id INTEGER PRIMARY KEY AUTOINCREMENT,run_id INTEGER,row_id INTEGER,sportovec_id INTEGER,match_status TEXT,confidence INTEGER,reason TEXT,candidate_json TEXT)');
        $migration = require dirname(__DIR__, 2) . '/migrations/20260804233000_kis_import_source_artifacts.php';
        $migration['up']($pdo);
        $migration['up']($pdo);
        self::assertTrue($migration['verify']($pdo));
        return $pdo;
    }

    /** @return array{string,string} */
    private function paths(string $contents): array
    {
        $root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'kis-archive-test-' . bin2hex(random_bytes(6));
        $archive = $root . DIRECTORY_SEPARATOR . 'archive';
        mkdir($root, 0700, true);
        mkdir($archive, 0700, true);
        $source = $root . DIRECTORY_SEPARATOR . 'source.csv';
        file_put_contents($source, $contents);
        $this->cleanup[] = $root;
        $this->cleanup[] = $archive;
        $this->cleanup[] = $source;
        return [$source, $archive];
    }
}
