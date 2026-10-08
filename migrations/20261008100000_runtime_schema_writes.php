<?php
declare(strict_types=1);

$tableExists = static function (PDO $pdo, string $table): bool {
    if ((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') {
        $statement = $pdo->prepare('SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?');
    } else {
        $statement = $pdo->prepare("SELECT 1 FROM sqlite_master WHERE type='table' AND name=?");
    }
    $statement->execute([$table]);
    return (bool)$statement->fetchColumn();
};

return [
    'id' => '20261008100000_runtime_schema_writes',
    'up' => static function (PDO $pdo) use ($tableExists): void {
        $driver = (string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        if (!$tableExists($pdo, 'email_log')) {
            if ($driver === 'mysql') {
                $pdo->exec(
                    "CREATE TABLE email_log ("
                    . "id INT AUTO_INCREMENT PRIMARY KEY, sportovec_id INT NOT NULL, email VARCHAR(255) NULL, "
                    . "predmet VARCHAR(500) NULL, stav ENUM('odeslano','chyba','bez_emailu') NOT NULL, "
                    . "odeslano_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, trener_id INT NULL, "
                    . "INDEX idx_email_log_sent (odeslano_at), INDEX idx_email_log_person (sportovec_id)"
                    . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
                );
            } else {
                $pdo->exec(
                    'CREATE TABLE email_log ('
                    . 'id INTEGER PRIMARY KEY AUTOINCREMENT, sportovec_id INTEGER NOT NULL, email TEXT NULL, '
                    . 'predmet TEXT NULL, stav TEXT NOT NULL, odeslano_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP, trener_id INTEGER NULL)'
                );
                $pdo->exec('CREATE INDEX idx_email_log_sent ON email_log(odeslano_at)');
                $pdo->exec('CREATE INDEX idx_email_log_person ON email_log(sportovec_id)');
            }
        }
        if ($tableExists($pdo, 'opravneni')) {
            $sql = $driver === 'mysql'
                ? 'INSERT IGNORE INTO opravneni (klic,nazev,popis,min_role,skupina,poradi) VALUES (?,?,?,?,?,?)'
                : 'INSERT OR IGNORE INTO opravneni (klic,nazev,popis,min_role,skupina,poradi) VALUES (?,?,?,?,?,?)';
            $pdo->prepare($sql)->execute([
                'prehled_popisu',
                'Přehled popisů tréninků',
                'Zobrazení popisů tréninků dle skupiny/podskupiny za zvolené období',
                'trener',
                'Přehledy',
                32,
            ]);
        }
    },
    'verify' => static function (PDO $pdo) use ($tableExists): bool {
        if (!$tableExists($pdo, 'email_log')) return false;
        if (!$tableExists($pdo, 'opravneni')) return true;
        $statement = $pdo->prepare('SELECT COUNT(*) FROM opravneni WHERE klic=?');
        $statement->execute(['prehled_popisu']);
        return (int)$statement->fetchColumn() === 1;
    },
];
