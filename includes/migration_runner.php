<?php
declare(strict_types=1);

require_once __DIR__ . '/schema_version.php';

final class EvidenceMigrationExit
{
    public const OK = 0;
    public const ERROR = 1;
    public const PENDING = 2;
    public const LOCK = 3;
    public const INTEGRITY = 4;
    public const USAGE = 64;
}

final class EvidenceMigrationException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly int $exitCode,
        public readonly string $reason
    ) {
        parent::__construct($message);
    }
}

final class EvidenceMigrationCatalog
{
    /** @return array<string, array{id:string, checksum:string, up:Closure, verify:Closure}> */
    public static function load(string $directory): array
    {
        if (!is_dir($directory)) {
            throw new EvidenceMigrationException(
                'Adresar s migracemi neexistuje.',
                EvidenceMigrationExit::INTEGRITY,
                'catalog_missing'
            );
        }

        $files = glob(rtrim($directory, '/\\') . DIRECTORY_SEPARATOR . '*.php') ?: [];
        sort($files, SORT_STRING);
        $catalog = [];

        foreach ($files as $file) {
            $stem = pathinfo($file, PATHINFO_FILENAME);
            if (!preg_match('/^\d{14}_[a-z0-9_]+$/D', $stem)) {
                throw new EvidenceMigrationException(
                    'Neplatny nazev migrace: ' . basename($file),
                    EvidenceMigrationExit::INTEGRITY,
                    'catalog_filename_invalid'
                );
            }

            $definition = require $file;
            if (!is_array($definition)
                || ($definition['id'] ?? null) !== $stem
                || !isset($definition['up'])
                || !$definition['up'] instanceof Closure
                || !isset($definition['verify'])
                || !$definition['verify'] instanceof Closure
            ) {
                throw new EvidenceMigrationException(
                    'Neplatna definice migrace: ' . basename($file),
                    EvidenceMigrationExit::INTEGRITY,
                    'catalog_definition_invalid'
                );
            }

            if (isset($catalog[$stem])) {
                throw new EvidenceMigrationException(
                    'Duplicitni ID migrace: ' . $stem,
                    EvidenceMigrationExit::INTEGRITY,
                    'catalog_duplicate'
                );
            }

            $checksum = hash_file('sha256', $file);
            if (!is_string($checksum) || strlen($checksum) !== 64) {
                throw new EvidenceMigrationException(
                    'Nelze spocitat checksum migrace: ' . basename($file),
                    EvidenceMigrationExit::INTEGRITY,
                    'catalog_checksum_failed'
                );
            }

            $catalog[$stem] = [
                'id' => $stem,
                'checksum' => $checksum,
                'up' => $definition['up'],
                'verify' => $definition['verify'],
            ];
        }

        return $catalog;
    }
}

final class EvidenceMigrationRunner
{
    public const LEDGER_TABLE = 'evidence_schema_migrations';
    public const BASELINE_ID = '0000_legacy_2_20_2';
    private const BASELINE_CHECKSUM_SOURCE = 'legacy-schema:2.20.2';

    private string $driver;

    /**
     * @param array<string, array{id:string, checksum:string, up:Closure, verify:Closure}> $catalog
     * @param null|Closure(PDO):void $legacyApplier
     */
    public function __construct(
        private readonly PDO $pdo,
        private readonly array $catalog,
        private readonly ?Closure $legacyApplier = null
    ) {
        $this->driver = (string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    }

    /**
     * Jen SELECTy. Tato metoda nikdy nevytvari tabulku ani zamek.
     *
     * @return array<string, mixed>
     */
    public function check(): array
    {
        $legacyVersion = $this->readLegacyVersion();
        $legacyState = evidence_legacy_schema_state($legacyVersion);

        if ($legacyState === 'ahead') {
            throw new EvidenceMigrationException(
                'Databaze ma vyssi legacy verzi nez tento kod; odmitam downgrade.',
                EvidenceMigrationExit::INTEGRITY,
                'legacy_ahead'
            );
        }
        if ($legacyState === 'invalid') {
            throw new EvidenceMigrationException(
                'Legacy schema_version nema platny format; odmitam databazi menit.',
                EvidenceMigrationExit::INTEGRITY,
                'legacy_invalid'
            );
        }
        if ($legacyState !== 'current') {
            return $this->status(
                false,
                $legacyVersion,
                $legacyState,
                false,
                array_keys($this->catalog),
                'legacy_baseline_pending'
            );
        }

        if (!$this->tableExists(self::LEDGER_TABLE)) {
            return $this->status(
                false,
                $legacyVersion,
                $legacyState,
                false,
                array_keys($this->catalog),
                'ledger_initialization_pending'
            );
        }

        $applied = $this->readApplied();
        $pending = self::reconcile($this->catalog, $applied);

        if (!isset($applied[self::BASELINE_ID])) {
            return $this->status(
                false,
                $legacyVersion,
                $legacyState,
                true,
                [self::BASELINE_ID],
                'baseline_ledger_pending'
            );
        }

        return $this->status(
            $pending === [],
            $legacyVersion,
            $legacyState,
            true,
            $pending,
            $pending === [] ? 'current' : 'numbered_migrations_pending'
        );
    }

    /** @return array<string, mixed> */
    public function apply(): array
    {
        $this->acquireLock();

        try {
            $legacyVersion = $this->readLegacyVersion();
            $legacyState = evidence_legacy_schema_state($legacyVersion);

            if ($legacyState === 'ahead' || $legacyState === 'invalid') {
                // check() vytvori jednotnou fail-closed chybu.
                return $this->check();
            }

            if ($legacyState !== 'current') {
                if ($this->legacyApplier === null) {
                    throw new EvidenceMigrationException(
                        'Legacy baseline 2.20.2 chybi a nebyl predan jeho aplikator.',
                        EvidenceMigrationExit::PENDING,
                        'legacy_baseline_pending'
                    );
                }

                ($this->legacyApplier)($this->pdo);
                $legacyVersion = $this->readLegacyVersion();
                if (evidence_legacy_schema_state($legacyVersion) !== 'current') {
                    throw new EvidenceMigrationException(
                        'Legacy auto-migrace nedokoncila baseline 2.20.2.',
                        EvidenceMigrationExit::PENDING,
                        'legacy_baseline_incomplete'
                    );
                }
            }

            if ($this->tableExists(self::LEDGER_TABLE)) {
                // Fail closed pred jakymkoli zapisem, pokud stavajici ledger
                // obsahuje nezname ID nebo zmeneny checksum.
                self::reconcile($this->catalog, $this->readApplied());
            }

            $this->ensureLedger();
            $applied = $this->readApplied();
            $pending = self::reconcile($this->catalog, $applied);

            foreach ($pending as $id) {
                $migration = $this->catalog[$id];
                $started = microtime(true);
                ($migration['up'])($this->pdo);
                if (($migration['verify'])($this->pdo) !== true) {
                    throw new EvidenceMigrationException(
                        'Postcondition migrace nebyla splnena: ' . $id,
                        EvidenceMigrationExit::INTEGRITY,
                        'migration_postcondition_failed'
                    );
                }
                $durationMs = max(0, (int)round((microtime(true) - $started) * 1000));

                $statement = $this->pdo->prepare(
                    'INSERT INTO ' . self::LEDGER_TABLE
                    . ' (id, checksum, execution_ms) VALUES (:id, :checksum, :execution_ms)'
                );
                $statement->execute([
                    'id' => $id,
                    'checksum' => $migration['checksum'],
                    'execution_ms' => $durationMs,
                ]);
            }

            return $this->check();
        } finally {
            $this->releaseLock();
        }
    }

    /**
     * @param array<string, array{id:string, checksum:string, up:Closure, verify:Closure}> $catalog
     * @param array<string, string> $applied
     * @return list<string>
     */
    public static function reconcile(array $catalog, array $applied): array
    {
        if (isset($applied[self::BASELINE_ID])) {
            if (!hash_equals(self::baselineChecksum(), $applied[self::BASELINE_ID])) {
                throw new EvidenceMigrationException(
                    'Checksum legacy baseline v ledgeru nesouhlasi.',
                    EvidenceMigrationExit::INTEGRITY,
                    'baseline_checksum_mismatch'
                );
            }
            unset($applied[self::BASELINE_ID]);
        }

        foreach ($applied as $id => $checksum) {
            if (!isset($catalog[$id])) {
                throw new EvidenceMigrationException(
                    'Databaze obsahuje neznamou aplikovanou migraci: ' . $id,
                    EvidenceMigrationExit::INTEGRITY,
                    'unknown_applied_migration'
                );
            }
            if (!hash_equals($catalog[$id]['checksum'], $checksum)) {
                throw new EvidenceMigrationException(
                    'Checksum aplikovane migrace nesouhlasi: ' . $id,
                    EvidenceMigrationExit::INTEGRITY,
                    'applied_checksum_mismatch'
                );
            }
        }

        return array_values(array_diff(array_keys($catalog), array_keys($applied)));
    }

    public static function baselineChecksum(): string
    {
        return hash('sha256', self::BASELINE_CHECKSUM_SOURCE);
    }

    private function readLegacyVersion(): string
    {
        if (!$this->tableExists('nastaveni')) {
            return '';
        }

        $statement = $this->pdo->query(
            "SELECT hodnota FROM nastaveni WHERE klic = 'schema_version'"
        );
        $value = $statement === false ? false : $statement->fetchColumn();
        return $value === false ? '' : (string)$value;
    }

    private function tableExists(string $table): bool
    {
        if ($this->driver === 'mysql') {
            $statement = $this->pdo->prepare(
                'SELECT 1 FROM information_schema.TABLES '
                . 'WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table LIMIT 1'
            );
            $statement->execute(['table' => $table]);
            return (bool)$statement->fetchColumn();
        }

        if ($this->driver === 'sqlite') {
            $statement = $this->pdo->prepare(
                "SELECT 1 FROM sqlite_master WHERE type = 'table' AND name = :table LIMIT 1"
            );
            $statement->execute(['table' => $table]);
            return (bool)$statement->fetchColumn();
        }

        throw new EvidenceMigrationException(
            'Nepodporovany databazovy ovladac.',
            EvidenceMigrationExit::ERROR,
            'unsupported_driver'
        );
    }

    /** @return array<string, string> */
    private function readApplied(): array
    {
        $statement = $this->pdo->query(
            'SELECT id, checksum FROM ' . self::LEDGER_TABLE . ' ORDER BY id'
        );
        $applied = [];
        foreach ($statement ?: [] as $row) {
            $applied[(string)$row['id']] = (string)$row['checksum'];
        }
        return $applied;
    }

    private function ensureLedger(): void
    {
        if ($this->driver === 'mysql') {
            $this->pdo->exec(
                'CREATE TABLE IF NOT EXISTS ' . self::LEDGER_TABLE . ' ('
                . 'id VARCHAR(190) NOT NULL PRIMARY KEY,'
                . 'checksum CHAR(64) NOT NULL,'
                . 'applied_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,'
                . 'execution_ms INT UNSIGNED NOT NULL DEFAULT 0'
                . ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
            );
            $statement = $this->pdo->prepare(
                'INSERT IGNORE INTO ' . self::LEDGER_TABLE
                . ' (id, checksum, execution_ms) VALUES (:id, :checksum, 0)'
            );
            $statement->execute([
                'id' => self::BASELINE_ID,
                'checksum' => self::baselineChecksum(),
            ]);
            return;
        }

        if ($this->driver === 'sqlite') {
            $this->pdo->exec(
                'CREATE TABLE IF NOT EXISTS ' . self::LEDGER_TABLE . ' ('
                . 'id TEXT NOT NULL PRIMARY KEY,'
                . 'checksum TEXT NOT NULL,'
                . "applied_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,"
                . 'execution_ms INTEGER NOT NULL DEFAULT 0'
                . ')'
            );
            $statement = $this->pdo->prepare(
                'INSERT OR IGNORE INTO ' . self::LEDGER_TABLE
                . ' (id, checksum, execution_ms) VALUES (:id, :checksum, 0)'
            );
            $statement->execute([
                'id' => self::BASELINE_ID,
                'checksum' => self::baselineChecksum(),
            ]);
            return;
        }

        throw new EvidenceMigrationException(
            'Nepodporovany databazovy ovladac.',
            EvidenceMigrationExit::ERROR,
            'unsupported_driver'
        );
    }

    private function acquireLock(): void
    {
        if ($this->driver === 'sqlite') {
            return;
        }
        if ($this->driver !== 'mysql') {
            throw new EvidenceMigrationException(
                'Nepodporovany databazovy ovladac pro migracni zamek.',
                EvidenceMigrationExit::LOCK,
                'lock_unsupported'
            );
        }

        try {
            $statement = $this->pdo->prepare('SELECT GET_LOCK(:name, 10)');
            $statement->execute(['name' => $this->lockName()]);
            if ((int)$statement->fetchColumn() !== 1) {
                throw new EvidenceMigrationException(
                    'Migracni zamek je obsazen.',
                    EvidenceMigrationExit::LOCK,
                    'lock_unavailable'
                );
            }
        } catch (EvidenceMigrationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw new EvidenceMigrationException(
                'Migracni zamek nelze bezpecne ziskat.',
                EvidenceMigrationExit::LOCK,
                'lock_failed'
            );
        }
    }

    private function releaseLock(): void
    {
        if ($this->driver !== 'mysql') {
            return;
        }

        try {
            $statement = $this->pdo->prepare('SELECT RELEASE_LOCK(:name)');
            $statement->execute(['name' => $this->lockName()]);
        } catch (Throwable $exception) {
            error_log('migration_runner: release lock failed');
        }
    }

    private function lockName(): string
    {
        $database = $this->pdo->query('SELECT DATABASE()')->fetchColumn();
        if (!is_string($database) || $database === '') {
            throw new EvidenceMigrationException(
                'Nelze odvodit databazove specificky migracni zamek.',
                EvidenceMigrationExit::LOCK,
                'lock_scope_failed'
            );
        }

        return 'evidence:migrations:' . substr(hash('sha256', $database), 0, 16);
    }

    /**
     * @param list<string> $pending
     * @return array<string, mixed>
     */
    private function status(
        bool $current,
        string $legacyVersion,
        string $legacyState,
        bool $ledgerExists,
        array $pending,
        string $reason
    ): array {
        return [
            'ok' => true,
            'current' => $current,
            'reason' => $reason,
            'legacy_version' => $legacyVersion === '' ? null : $legacyVersion,
            'legacy_target' => LEGACY_SCHEMA_VERSION,
            'legacy_state' => $legacyState,
            'ledger_exists' => $ledgerExists,
            'catalog_count' => count($this->catalog),
            'pending' => $pending,
        ];
    }
}
