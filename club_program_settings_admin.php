<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/csrf_helper.php';
require_once __DIR__ . '/includes/club_program.php';

if (!isset($_SESSION['trener_id'])) { header('Location: login.php'); exit; }
staffRequireActivePosition('program_coordinator');

function programSettingsH(mixed $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

$errors = [];
$actorId = (int)$_SESSION['trener_id'];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify((string)($_POST['csrf_token'] ?? ''))) {
        $errors[] = 'Formulář vypršel. Obnovte stránku.';
    } else {
        try {
            $action = (string)($_POST['action'] ?? '');
            $programId = (int)($_POST['program_id'] ?? 0);
            if ($action === 'update_program') {
                $result = clubProgramUpdate($pdo, $actorId, $programId, (string)($_POST['name'] ?? ''), (string)($_POST['description'] ?? ''), (string)($_POST['reason'] ?? ''), isset($_POST['confirmed']));
                $_SESSION['program_settings_flash'] = $result['changed'] ? 'Program byl upraven.' : 'Program se nezměnil.';
            } elseif ($action === 'archive_program') {
                $result = clubProgramArchive($pdo, $actorId, $programId, (string)($_POST['reason'] ?? ''), isset($_POST['confirmed']));
                $_SESSION['program_settings_flash'] = $result['changed'] ? 'Program byl archivován.' : 'Program už byl archivován.';
            } else {
                throw new InvalidArgumentException('Neznámá akce.');
            }
            header('Location: club_program_settings_admin.php', true, 303);exit;
        } catch (InvalidArgumentException|ClubProgramException $exception) {
            $errors[] = $exception->getMessage();
        } catch (Throwable $exception) {
            error_log('club_program_settings_admin.php: ' . $exception->getMessage());
            $errors[] = 'Operace selhala bez částečného zápisu.';
        }
    }
}
$success = (string)($_SESSION['program_settings_flash'] ?? '');unset($_SESSION['program_settings_flash']);
$programs = $pdo->query("SELECT p.*,SUM(CASE WHEN o.status IN ('draft','active') THEN 1 ELSE 0 END) AS open_offer_count,COUNT(o.id) AS offer_count FROM club_programs p LEFT JOIN club_program_offers o ON o.program_id=p.id GROUP BY p.id ORDER BY CASE p.status WHEN 'active' THEN 0 ELSE 1 END,p.name,p.id")->fetchAll(PDO::FETCH_ASSOC);
?>
<!doctype html>
<html lang="cs"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Správa stabilních programů</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"></head>
<body class="bg-light"><?php include __DIR__ . '/hlavicka.php'; ?>
<main class="container py-4" style="max-width:1100px">
    <div class="d-flex justify-content-between align-items-start mb-3"><div><h1 class="h4 mb-1">Správa stabilních programů</h1><p class="text-muted mb-0">Opravte název a popis nebo archivujte program, který už nemá otevřené nabídky.</p></div><a class="btn btn-outline-secondary btn-sm" href="club_programs_admin.php">Programy a podmínky</a></div>
    <?php foreach ($errors as $error): ?><div class="alert alert-danger"><?=programSettingsH($error)?></div><?php endforeach; ?>
    <?php if ($success !== ''): ?><div class="alert alert-success"><?=programSettingsH($success)?></div><?php endif; ?>
    <?php foreach ($programs as $program): ?>
        <div class="card border-0 shadow-sm mb-3"><div class="card-header bg-white d-flex justify-content-between"><span><strong><?=programSettingsH($program['name'])?></strong> <code><?=programSettingsH($program['code'])?></code></span><span class="badge text-bg-<?=$program['status']==='active'?'success':'secondary'?>"><?=programSettingsH($program['status'])?></span></div><div class="card-body">
            <?php if ($program['status'] === 'active'): ?><form method="post" class="row g-2 align-items-end"><?=csrf_field()?><input type="hidden" name="action" value="update_program"><input type="hidden" name="program_id" value="<?=(int)$program['id']?>"><div class="col-md-4"><label class="form-label">Název</label><input class="form-control" name="name" maxlength="160" required value="<?=programSettingsH($program['name'])?>"></div><div class="col-md-5"><label class="form-label">Popis</label><textarea class="form-control" name="description" maxlength="4000" rows="2"><?=programSettingsH($program['description'])?></textarea></div><div class="col-md-3"><label class="form-label">Důvod změny</label><input class="form-control" name="reason" maxlength="1000" required></div><div class="col-md-9 form-check ms-2"><input class="form-check-input" type="checkbox" name="confirmed" value="1" id="program-update-<?=(int)$program['id']?>" required><label class="form-check-label" for="program-update-<?=(int)$program['id']?>">Potvrzuji auditovanou změnu</label></div><div class="col-md-2 ms-auto d-grid"><button class="btn btn-outline-primary">Uložit program</button></div></form>
            <hr><form method="post" class="row g-2 align-items-center"><?=csrf_field()?><input type="hidden" name="action" value="archive_program"><input type="hidden" name="program_id" value="<?=(int)$program['id']?>"><div class="col-md-6"><input class="form-control" name="reason" maxlength="1000" required placeholder="Důvod archivace"></div><div class="col-md-3 form-check"><input class="form-check-input" type="checkbox" name="confirmed" value="1" id="program-archive-<?=(int)$program['id']?>" required><label class="form-check-label" for="program-archive-<?=(int)$program['id']?>">Potvrzuji archivaci</label></div><div class="col-md-3 d-grid"><button class="btn btn-outline-secondary" <?=(int)$program['open_offer_count']>0?'disabled':''?>>Archivovat</button></div><?php if ((int)$program['open_offer_count'] > 0): ?><div class="col-12 small text-muted">Nejprve uzavřete <?=(int)$program['open_offer_count']?> otevřených nabídek.</div><?php endif; ?></form>
            <?php else: ?><p class="text-muted mb-0">Archivovaný program zůstává v historii a nelze jej měnit.</p><?php endif; ?>
        </div></div>
    <?php endforeach; ?>
</main></body></html>
