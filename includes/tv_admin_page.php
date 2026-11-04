<?php

declare(strict_types=1);

function tvRenderAdminPage(PDO $pdo, array $programs, string $error, ?array $program, ?array $boot, array $revisions, array $events): void
{
    ?>
<!doctype html>
<html lang="cs">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>TV programy | Evidence</title>
    <?php appUiAssets(); ?>
    <link rel="stylesheet" href="<?=publicShellH(appUiUrl('assets/tv.css'))?>?v=1">
    <?php if ($boot !== null): ?>
        <script nonce="<?=publicShellH(app_csp_nonce())?>" defer src="<?=publicShellH(appUiUrl('assets/tv-canvas.js'))?>?v=1"></script>
        <script nonce="<?=publicShellH(app_csp_nonce())?>" defer src="<?=publicShellH(appUiUrl('assets/tv-editor.js'))?>?v=1"></script>
    <?php endif; ?>
</head>
<body>
<?php include dirname(__DIR__) . '/hlavicka.php'; ?>
<main class="container-fluid px-lg-4 py-4">
    <?php if ($error !== ''): ?><div class="alert alert-danger" role="alert"><?=publicShellH($error)?></div><?php endif; ?>
    <?php if ($program === null): ?>
        <h1 class="h3">TV programy</h1>
        <p class="text-body-secondary">Připravte snímky a zveřejněte je. Stejný odkaz programu můžete otevřít na více televizích.</p>
        <section class="card mb-4"><div class="card-body">
            <h2 class="h5">Nový program</h2>
            <form method="post" class="d-flex gap-2 flex-wrap align-items-end">
                <?=csrf_field()?><input type="hidden" name="action" value="create">
                <div class="flex-grow-1"><label class="form-label" for="tv-name">Název programu</label>
                    <input class="form-control" id="tv-name" name="name" maxlength="120" placeholder="Například Recepce" required>
                </div>
                <button class="btn btn-primary" type="submit">Vytvořit program</button>
            </form>
        </div></section>
        <div class="table-responsive"><table class="table align-middle">
            <thead><tr><th>Program</th><th>Veřejný odkaz</th><th>Stav</th><th>Akce</th></tr></thead><tbody>
            <?php foreach ($programs as $item): ?>
                <tr>
                    <td><a href="<?=publicShellH(appUiUrl('tv_programs_admin.php?id=' . (int)$item['id']))?>"><?=publicShellH($item['name'])?></a></td>
                    <td class="tv-program-link">
                        <a target="_blank" rel="noopener" href="<?=publicShellH(appUrl('tv/' . (int)$item['id']))?>">
                            <?=publicShellH(appUrl('tv/' . (int)$item['id']))?>
                        </a>
                    </td>
                    <td><?=(int)$item['archived'] ? 'Archivovaný' : ($item['published_revision_id'] ? 'Zveřejněný' : 'Koncept')?></td>
                    <td><form method="post">
                        <?=csrf_field()?><input type="hidden" name="id" value="<?=(int)$item['id']?>">
                        <input type="hidden" name="version" value="<?=(int)$item['lock_version']?>">
                        <input type="hidden" name="action" value="<?=(int)$item['archived'] ? 'unarchive' : 'archive'?>">
                        <button class="btn btn-sm btn-outline-secondary" type="submit">
                            <?=(int)$item['archived'] ? 'Obnovit jako koncept' : 'Archivovat a zastavit vysílání'?>
                        </button>
                    </form></td>
                </tr>
            <?php endforeach; ?>
            <?php if ($programs === []): ?><tr><td colspan="4">Zatím zde není žádný program. Vytvořte první program nahoře.</td></tr><?php endif; ?>
            </tbody>
        </table></div>
    <?php else: ?>
        <a href="<?=publicShellH(appUiUrl('tv_programs_admin.php'))?>">← Všechny TV programy</a>
        <h1 class="h3 mt-3">Editor TV programu č. <?=(int)$program['id']?></h1>
        <p class="tv-program-link mb-2">Veřejná adresa:
            <a target="_blank" rel="noopener" href="<?=publicShellH(appUrl('tv/' . (int)$program['id']))?>">
                <?=publicShellH(appUrl('tv/' . (int)$program['id']))?>
            </a>
        </p>
        <p class="small text-body-secondary">Na TV je vidět pouze zveřejněná verze. Koncept můžete nejprve upravit a prohlédnout.</p>
        <?php if ((int)$program['archived']): ?>
            <div class="alert alert-warning">Program je archivovaný. Obnovte jej v seznamu programů a potom jej znovu zveřejněte.</div>
        <?php endif; ?>
        <div class="tv-editor-toolbar">
            <div class="d-flex gap-2 flex-wrap align-items-end">
                <div class="flex-grow-1"><label for="tv-program-name" class="form-label">Název programu</label>
                    <input id="tv-program-name" class="form-control" maxlength="120" value="<?=publicShellH($program['name'])?>">
                </div>
                <button id="tv-save" class="btn btn-primary" type="button">Uložit koncept</button>
                <button id="tv-preview" class="btn btn-outline-primary" type="button">Přehrát náhled</button>
                <button id="tv-publish" class="btn btn-success" type="button">Zveřejnit změny</button>
            </div>
            <div id="tv-status" class="tv-status small mt-2" role="status" aria-live="polite">
                <?=$program['published_revision_id'] ? 'Program má zveřejněnou verzi.' : 'Program dosud není zveřejněný.'?>
            </div>
        </div>
        <div class="tv-editor-layout">
            <section aria-label="Snímky programu">
                <h2 class="h5">Snímky</h2>
                <p class="small text-body-secondary">Pořadí změňte přetažením nebo šipkami.</p>
                <div id="tv-slide-list" class="tv-slide-list"></div>
                <button id="tv-add-slide" class="btn btn-outline-primary w-100 mt-3" type="button">+ Přidat snímek</button>
            </section>
            <section aria-label="Plátno snímku">
                <h2 class="h5">Plátno 16 : 9</h2>
                <div id="tv-editor-canvas" class="tv-viewport tv-editor-viewport"></div>
                <div id="tv-overflow" class="tv-overflow-warning" role="status" hidden></div>
                <p class="small text-body-secondary mt-2">Text přesunete tažením. Modrým úchytem změníte šířku.
                    Vybraný blok lze posouvat také šipkami, se Shiftem po 10 bodech.</p>
            </section>
            <section class="card tv-properties"><div class="card-body">
                <h2 class="h5">Vlastnosti snímku</h2>
                <p id="tv-no-slide" class="text-body-secondary">Přidejte první snímek.</p>
                <div id="tv-properties" hidden class="tv-properties-fields">
                    <div>
                        <label for="tv-slide-title">Název snímku</label>
                        <input id="tv-slide-title" class="form-control" data-slide="title" maxlength="120">
                        <label for="tv-duration">Délka zobrazení (sekundy)</label>
                        <input id="tv-duration" class="form-control" data-slide="duration" type="number" min="1" max="3600">
                        <label class="mb-3"><input id="tv-enabled" data-slide="enabled" type="checkbox"> Snímek zařadit do vysílání</label>
                        <label for="tv-slide-background">Barva pozadí</label>
                        <input id="tv-slide-background" class="form-control form-control-color" data-slide="background" type="color">
                        <label for="tv-fit">Zobrazení obrázku</label>
                        <select id="tv-fit" class="form-select" data-slide="fit">
                            <option value="contain">Celý obrázek</option><option value="cover">Vyplnit plochu ořezem</option>
                        </select>
                        <label for="tv-image" class="form-label">Nahrát / nahradit obrázek</label>
                        <input id="tv-image" type="file" class="form-control" accept="image/jpeg,image/png,image/webp,image/gif">
                        <p class="small text-body-secondary">JPG, PNG, WEBP nebo GIF, nejvýše 5 MB a 24 milionů bodů. Animace se uloží jako statický obrázek.</p>
                        <button id="tv-remove-image" class="btn btn-sm btn-outline-secondary mb-3" type="button">Odebrat obrázek</button>
                    </div>
                    <div>
                        <h3 class="h6">Textové bloky</h3>
                        <label for="tv-block-select">Vybraný blok</label>
                        <select id="tv-block-select" class="form-select tv-block-select"></select>
                        <div class="d-flex gap-2 mb-3">
                            <button id="tv-add-text" class="btn btn-sm btn-outline-primary" type="button">+ Přidat text</button>
                            <button id="tv-delete-text" class="btn btn-sm btn-outline-danger" type="button">Smazat blok</button>
                        </div>
                        <div id="tv-block-properties" hidden>
                            <label for="tv-text">Text</label>
                            <textarea id="tv-text" class="form-control" data-block="text" rows="4" maxlength="4000"></textarea>
                            <div class="tv-inline-fields">
                                <div><label for="tv-x">Pozice X</label><input id="tv-x" class="form-control" data-block="x" type="number" min="0" max="1900"></div>
                                <div><label for="tv-y">Pozice Y</label><input id="tv-y" class="form-control" data-block="y" type="number" min="0" max="1079"></div>
                                <div><label for="tv-width">Šířka bloku</label><input id="tv-width" class="form-control" data-block="width" type="number" min="20" max="1920"></div>
                                <div><label for="tv-font-size">Velikost písma</label><input id="tv-font-size" class="form-control" data-block="font_size" type="number" min="8" max="400"></div>
                            </div>
                            <label for="tv-text-color">Barva písma</label>
                            <input id="tv-text-color" class="form-control form-control-color" data-block="color" type="color">
                            <label class="mb-3"><input id="tv-bold" data-block="bold" type="checkbox"> Tučné písmo</label>
                            <label for="tv-align">Zarovnání</label>
                            <select id="tv-align" class="form-select" data-block="align">
                                <option value="left">Vlevo</option><option value="center">Na střed</option><option value="right">Vpravo</option>
                            </select>
                            <label for="tv-text-background">Barva podkladu textu</label>
                            <input id="tv-text-background" class="form-control form-control-color" data-block="background" type="color">
                            <label for="tv-opacity">Krytí podkladu (0 = průhledný, 1 = plný)</label>
                            <input id="tv-opacity" class="form-control" data-block="background_opacity" type="number" min="0" max="1" step="0.1">
                        </div>
                    </div>
                </div>
            </div></section>
        </div>
        <details class="card mt-4"><summary class="card-header">Historie zveřejnění a změn</summary><div class="card-body">
            <p class="small text-body-secondary">Předchozí zveřejnění můžete načíst do konceptu. Na televizích se projeví až po novém zveřejnění.</p>
            <?php foreach ($revisions as $revision): ?>
                <div class="d-flex gap-3 flex-wrap align-items-center mb-2">
                    <span>Verze č. <?=(int)$revision['id']?> · <?=publicShellH($revision['created_at'])?> · <?=publicShellH($revision['jmeno'])?></span>
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-restore="<?=(int)$revision['id']?>">Načíst do konceptu</button>
                </div>
            <?php endforeach; ?>
            <?php if ($revisions === []): ?><p>Zatím není zveřejněna žádná verze.</p><?php endif; ?>
            <h3 class="h6 mt-4">Poslední změny</h3>
            <?php $labels = ['created' => 'Vytvoření', 'save' => 'Uložení konceptu', 'publish' => 'Zveřejnění',
                    'archive' => 'Archivace', 'unarchive' => 'Obnovení', 'restore_revision' => 'Načtení předchozí verze']; ?>
            <?php foreach ($events as $event): ?>
                <p class="small mb-1"><?=publicShellH($event['created_at'])?> · <?=publicShellH($event['jmeno'])?> · <?=publicShellH($labels[$event['action']] ?? $event['action'])?></p>
            <?php endforeach; ?>
        </div></details>
        <dialog id="tv-preview-dialog" class="tv-preview-dialog">
            <div class="tv-preview-toolbar"><span>Náhled konceptu</span><button id="tv-close-preview" class="btn btn-light btn-sm" type="button">Zavřít náhled</button></div>
            <div id="tv-preview-canvas" class="tv-viewport"></div>
        </dialog>
        <script type="application/json" id="tv-editor-boot" nonce="<?=publicShellH(app_csp_nonce())?>"><?=
                json_encode($boot, JSON_THROW_ON_ERROR | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT)
    ?></script>
    <?php endif; ?>
</main>
</body>
</html>
<?php
}
