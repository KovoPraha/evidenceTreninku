<?php

declare(strict_types=1);

namespace Tests\Support;

use PDO;

/** Disposable localhost HTTP harness. Only the session/bootstrap and shared header are substituted. */
final class ExternalSyncRegistrarSandbox
{
    public string $directory;
    public PDO $pdo;

    public function __construct()
    {
        $root = dirname(__DIR__, 2);
        $this->directory = sys_get_temp_dir() . '/evidence-s4-http-' . bin2hex(random_bytes(8));
        mkdir($this->directory . '/includes', 0700, true);
        mkdir($this->directory . '/assets/vendor/bootstrap', 0700, true);
        $this->pdo = new PDO('sqlite:' . $this->directory . '/synthetic.sqlite', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        ExternalSyncRegistrarFixture::schema($this->pdo);
        $token = ExternalSyncRegistrarFixture::connection($this->pdo, 'TEST-csc');
        $base = ExternalSyncRegistrarFixture::batch()['rows'][0];
        $rows = [$base];
        for ($i = 1; $i <= 55; $i++) {
            $rows[] = array_replace($base, ['license_id' => 'TEST-' . $i, 'uci_id_raw' => sprintf('%011d', 100 + $i),
                'display_name' => $i === 1 ? '<script>alert(1)</script> TEST' : 'TEST Osoba ' . $i]);
        }
        ExternalSyncRegistrarFixture::ingest($this->pdo, $token, $rows);
        copy($root . '/external_sync_admin.php', $this->directory . '/external_sync_admin.php');
        copy($root . '/external_sync_review.php', $this->directory . '/external_sync_review.php');
        copy($root . '/csrf_helper.php', $this->directory . '/csrf_helper.php');
        $panelCode = <<<'PHP'
<?php
require __DIR__ . '/includes/session_security.php';
app_session_start();
require __DIR__ . '/includes/init.php';
require_once PANEL_MODULE;
echo '<!doctype html><meta name="viewport" content="width=device-width, initial-scale=1"><link href="assets/vendor/bootstrap/bootstrap.min.css" rel="stylesheet"><main class="container py-4">';
externalSyncPersonPanelRender(externalSyncPersonPanel($pdo, 7, 1));
echo '</main>';
PHP;
        file_put_contents($this->directory . '/panel.php', str_replace('PANEL_MODULE', var_export($root . '/includes/external_sync_person_panel.php', true), $panelCode));
        copy($root . '/assets/vendor/bootstrap/bootstrap.min.css', $this->directory . '/assets/vendor/bootstrap/bootstrap.min.css');
        foreach (['external_sync_admin_read.php', 'external_sync_admin_view.php', 'external_sync_review_view.php'] as $name) {
            file_put_contents($this->directory . '/includes/' . $name, '<?php require_once ' . var_export($root . '/includes/' . $name, true) . ';');
        }
        // No real session, browser profile, config or application database is read.
        file_put_contents($this->directory . '/includes/session_security.php', <<<'PHP'
<?php
function app_session_start(): void {
    $role = $_SERVER['HTTP_X_SYNTHETIC_ROLE'] ?? ($_COOKIE['synthetic_role'] ?? ($_GET['synthetic_role'] ?? 'anonymous'));
    $_SESSION = ['csrf_token' => 'SYNTHETIC-CSRF'];
    if ($role === 'family') { $_SESSION['verejny_uzivatel_id'] = 1; }
    if (in_array($role, ['registrar', 'coach', 'inactive', 'nonregistrar'], true)) {
        $_SESSION['trener_id'] = $role === 'inactive' ? 9 : ($role === 'nonregistrar' ? 8 : 7);
        $_SESSION['staff_positions'] = ['coach', 'registrar'];
        $_SESSION['staff_active_position'] = $role === 'coach' ? 'coach' : 'registrar';
    }
}
PHP);
        $bootstrap = '<?php require_once ' . var_export($root . '/includes/staff_workspaces.php', true) . ';'
            . '$pdo=new PDO(' . var_export('sqlite:' . $this->directory . '/synthetic.sqlite', true)
            . ',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);'
            . 'if (($_SERVER["REQUEST_METHOD"] ?? "GET") !== "POST") { $pdo->exec("PRAGMA query_only=ON"); }';
        file_put_contents($this->directory . '/includes/init.php', $bootstrap);
        file_put_contents($this->directory . '/hlavicka.php', '<header class="container pt-3">KIS · TEST · syntetická data</header>');
    }

    public function cleanup(): void
    {
        // Exact files created here; never recurse into an application directory.
        foreach (['external_sync_review.php', 'csrf_helper.php', 'panel.php', 'includes/external_sync_review_view.php', 'external_sync_admin.php',
            'includes/external_sync_admin_read.php', 'includes/external_sync_admin_view.php',
            'includes/session_security.php', 'includes/init.php', 'hlavicka.php', 'server.log',
            'assets/vendor/bootstrap/bootstrap.min.css'] as $file) {
            if (is_file($this->directory . '/' . $file)) {
                unlink($this->directory . '/' . $file);
            }
        }
        unset($this->pdo);
        unlink($this->directory . '/synthetic.sqlite');
        foreach (['assets/vendor/bootstrap', 'assets/vendor', 'assets', 'includes', ''] as $dir) {
            rmdir($this->directory . '/' . $dir);
        }
    }
}
