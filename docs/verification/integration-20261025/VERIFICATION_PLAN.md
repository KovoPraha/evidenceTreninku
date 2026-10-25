# Integration isolated verification 2026-10-25

Checkout: C:/xampp/htdocs/evidencePavel-integration-20261025.
Only synthetic data; no production service, real source import, provider, email or deploy.

## Isolation
The existing B1 harness deliberately hardcodes ports 33426 / 18086 and rejects foreign runtime ownership/datadir. Do not relax those guards. The old owned sandbox is gracefully stopped (data preserved), then every command gets a process-local LOCALAPPDATA override:

$env:LOCALAPPDATA='C:/Users/Marek/AppData/Local/EvidencePavelIntegration20261025'

New runtime resolves to C:/Users/Marek/AppData/Local/EvidencePavelIntegration20261025/EvidencePavelB1/finance-b1-20261022. Config, DB files, sessions, private files and outbox are new. This is not a persistent user/system environment change. Never invoke a harness from a shell missing this override.

## Commands (from new checkout, only after source stable)
composer install --no-interaction --prefer-dist --no-progress
& ./bin/setup-finance-b1-sandbox.ps1
& C:/xampp/php/php.exe -c "$env:LOCALAPPDATA/EvidencePavelB1/finance-b1-20261022/php-web.ini" tests/Support/FinanceB1IsolationCheck.php
& C:/xampp/php/php.exe vendor/phpunit/phpunit/phpunit --colors=never --log-junit output/integration-20261025/phpunit.xml
& tests/Support/FinalSystemAcceptance.ps1

Further guarded scripts, sequential so evidence is attributable:
PaymentVariableSymbolMigrationMariaDb.php
ShopFulfillmentMariaDbRace.php
ShopReturnsMariaDbRace.php
AccountingExportSnapshotMariaDbTest.php
AccountingStockSnapshotMariaDbTest.php
TrainingClosureMariaDbRace.php
MembershipAuthorizationMariaDbRace.php

Backup and restore after HTTP writes:
$env:APP_HOST='localhost'
& C:/xampp/php/php.exe -c "$env:LOCALAPPDATA/EvidencePavelB1/finance-b1-20261022/php-web.ini" bin/db-backup.php --app-root=C:/xampp/htdocs/evidencePavel-integration-20261025 --backup-dir="$env:LOCALAPPDATA/EvidencePavelB1/finance-b1-20261022/backups-r38-r44" --json
& C:/xampp/php/php.exe tests/Support/ClubMigrationBackupRestore.php

Restore creates a NEW uniquely named DB/user and separate private tree on the dedicated instance, verifies rows, triggers, storage hashes and domain invariants. It never replaces the source test DB.

## Linux
WSL executable exists but WSL is not installed; Docker is unavailable. Existing .github/workflows/tests.yml workflow_dispatch is safe test-only Linux PHP 8.2 plus MariaDB 10.3/11.4 isolated services. Run against exact integration SHA after coordinator commits/pushes branch. No deployment workflow is called. Linux unix-specific filter uses --fail-on-skipped. Local Windows skips do not replace Linux proof.

## Sources
Read current final-closure RESULTS, final-system RESULTS, FINANCE_B1_SANDBOX, setup/stop/process guards, isolated descriptor checks, workflow definitions and restore source. Prior evidence is historical only; new runs need exact source SHA and their own outputs.