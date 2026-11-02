<?php
declare(strict_types=1);
require_once __DIR__.'/includes/init.php';
require_once __DIR__.'/includes/season_preparation.php';
require_once __DIR__.'/includes/ui_shell.php';
header('Cache-Control: private, no-store');
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    http_response_code(405);
    exit('Tento průvodce pouze zobrazuje přípravu.');
}
try {
    seasonPreparationActor($pdo);
    $teams = $pdo->query('SELECT t.id,t.name FROM club_teams t ORDER BY t.name,t.id')->fetchAll(PDO::FETCH_ASSOC);
    $id = (int)($_GET['team_id'] ?? 0);
    $data = $id ? seasonPreparationRead($pdo, $id, $_GET) : null;
} catch (DomainException $e) {
    http_response_code(403);
    exit(staffListH($e->getMessage()));
} catch (InvalidArgumentException|KisRosterException $e) {
    http_response_code(422);
    exit(staffListH($e->getMessage()));
} catch (PDOException) {
    http_response_code(503);
    exit('Podklady období nelze načíst. Nejde o potvrzení připravenosti.');
}
$registrar = staffActivePosition() === 'registrar';
$labels = ['continuing' => 'Zařazen dnes i k vybranému dni','leaving' => 'K vybranému dni není zařazen',

    'joining' => 'K vybranému dni přibývá'];

?><!doctype html><html lang="cs"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Příprava dalšího období</title><?php
appUiAssets();
?></head><body><?php
include
    __DIR__.'/hlavicka.php';
?><?php
echo('<main class="container py-4"><h1>Příprava dalšího období</h1><p>Trvalé skupiny pokračují podle platnosti ' .

    'zařazení. Tento průvodce nepřevádí osoby ani neobnovuje placenou účast.</p><form method="get" class="row ' .

    'g-2"><label class="col-md-5">Skupina nebo soupiska<select class="form-select" name="team_id" required><option ' .

    'value="">Vyberte</option>');
?><?php
foreach ($teams as $t):
    ?><option value="<?=
    (int)$t['id']
    ?>" <?=
    $id === (int)$t['id']
        ? 'selected' : ''
    ?>><?=
    staffListH($t['name'])
    ?></option><?php
endforeach;
?></select></label><label class="col-md-3">Den kontroly<input class="form-control" name="on" type="date" value="<?=
staffListH($data['on']
    ?? date('Y-m-d'))
?>" required></label><div class="col align-self-end"><button class="btn btn-primary">Zkontrolovat období</button></div></form>
<?php
if ($data):$d = $data;
    ?><h2 class="h4 mt-4"><?=
    staffListH($d['team']['name'])
    ?> · <?=
    staffListH($d['on'])
    ?></h2><p>Porovnání evidovaných intervalů dnes (<?=
    staffListH($d['today'])
    ?>) a k vybranému dni. Zahrnuje časově platné podskupiny; není to seznam kmenových členů ani potvrzení nákupu.</p>
<?php
    echo('<ol><li>Ověřte pokračující osoby a končící zařazení níže. Rozhodnutí o členství řeší registrář ' .

        'zvlášť.</li><li>Zkontrolujte svěření trenérům. Přístup se novým obdobím automaticky nepřenáší.</li><li>Ověřte ' .

        'budoucí lekce a zdravotní podklady ke dni aktivity.</li><li>U placeného pokračování vyčkejte na samostatné ' .

        'nové přijetí. Samotná soupiska je nenahrazuje.</li></ol>
');
    ?><p><?php
if ($registrar):
    ?><a href="kis_roster_hierarchy_admin.php">Upravit trvalé skupiny</a><?php else:
        ?><a href="coach_scope_admin.php">Svěření trenérům</a> · <a href="planovac.php">Rozvrh a lekce</a><?php
    endif;
?> · <a href="medical_activity_admin.php?team_id=<?=
$id
?>&amp;on=<?=
staffListH($d['on'])
?>">Podklady před aktivitou</a></p>
<?php
foreach ($d['counts'] as $key => $count):
    ?><span class="badge text-bg-secondary me-2"><?=
    staffListH($labels[$key])
    ?>: <?=
    $count
    ?></span><?php
endforeach;
?>
<form method="get" class="row g-2 my-3"><?php
foreach (['team_id' => $id,'on' => $d['on']] as $key => $value):
    ?><input type="hidden" name="<?=
    $key
    ?>" value="<?=
    staffListH($value)
    ?>"><?php
endforeach;
?><label class="col">Jméno<input name="q" class="form-control" value="<?=
staffListH($d['search'])
?>"></label><label class="col">Zařazení<select class="form-select" name="state"><option value="">Všechny změny</option><?php
foreach ($labels as $key => $label):
    ?><option value="<?=
    $key
    ?>" <?=
    $d['state'] === $key ? 'selected' : ''
    ?>><?=
    staffListH($label)
    ?></option><?php
endforeach;
?></select></label><button class="btn btn-outline-primary col-auto align-self-end">Filtrovat</button></form>
<p>Nalezeno: <?=
$d['total']
?> · strana <?=
$d['page']
?> z <?=
$d['pages']
?></p><table class="table"><tr><th>Osoba</th><th>Zařazení</th><th>Vyřízení</th></tr><?php
foreach ($d['rows'] as $r):
    ?><tr><td><?=
    staffListH($r['prijmeni'].' '.$r['jmeno'])
    ?></td><td><?=
    staffListH($labels[$r['change']])
    ?></td><td><a href="<?=
    $registrar
        ? 'person_lifecycle_admin.php?id=' : 'sports_activity_admin.php?person_id='
    ?><?=
    (int)$r['id']
    ?>">Otevřít osobu</a></td></tr><?php
endforeach;
?></table><?php
foreach (['Předchozí' => $d['page'] - 1,'Další' => $d['page'] + 1] as $label => $page) {
    if ($page > 0 && $page <= $d['pages']):
        ?><a class="btn btn-outline-secondary me-2" href="?<?=
        staffListH(http_build_query(['team_id'
                => $id,'on' => $d['on'],'q' => $d['search'],'state' => $d['state'],'page' => $page]))
        ?>"><?=
        $label
        ?></a><?php
    endif;
}
?>
<h3 class="h5 mt-4">Věková kontrola</h3>
<?php
if (!$d['age_review']['rule']):
    ?><p>Soupiska nemá nastavené věkové pravidlo. Žádný přestup se neodvozuje z názvu.</p><?php else:$ageLabels
            = ['unknown' => 'Ověřit datum narození','eligible' => 'Odpovídá kategorii','too_young' => 'Pod věkovou hranicí – ruční kontrola',

            'too_old' => 'Nad věkovou hranicí – návrh přestupu'];
        ?><p>Věk k <?=
        staffListH($d['age_review']['on'])
        ?>; povolené rozmezí <?=
        (int)$d['age_review']['rule']['age_min']
        ?>–<?=
        (int)$d['age_review']['rule']['age_max']
        ?> let. Návrh nemění členství ani placený nárok.</p><table class="table"><tr><th>Sportovec</th><th>Věk</th><th>Výsledek</th><th>Navazující soupiska</th></tr><?php
        $teamNames
                = array_column($teams, 'name', 'id');
        foreach ($d['age_review']['rows'] as $row):
            ?><tr><td><?=
            staffListH($row['prijmeni'].' '.$row['jmeno'])
            ?></td><td><?=
            staffListH($row['age']
                ?? '—')
            ?></td><td><?=
            staffListH($ageLabels[$row['review']])
            ?></td><td><?=
            staffListH($teamNames[$row['suggested_team_id']
                ?? 0] ?? 'Ruční rozhodnutí')
            ?></td></tr><?php
        endforeach;
        ?></table><?php
    endif;
?>
<?php
echo('<h3 class="h5 mt-4">Svěření trenérům k začátku vybraného dne</h3><p>Každá soupiska má vlastní oprávnění; ' .

    'svěření nadřazené skupině samo nezpřístupňuje její podskupiny.</p>');
?><?php
if (!$d['grants']):
    ?><p>Pro tyto skupiny není doložené aktuální nebo budoucí týmové svěření. Prověřte s vedoucím sportu.</p><?php
endif;
?><ul><?php
foreach ($d['grants'] as $g):
    ?><li><?=
    staffListH($g['jmeno'].' · '.$g['name'].' · '.$g['valid_from'].' – '.
        ($g['valid_to'] ?? 'bez konce'))
    ?> — <?=
    $g['active_on'] ? 'platí k vybranému dni' : 'k vybranému dni neplatí'
    ?></li><?php
endforeach;
?></ul>
<h3 class="h5">Navazující plánované lekce do <?=
staffListH($d['until'])
?></h3><?php
if (!$d['plans']):
    ?><p>V tomto období nejsou evidované lekce s přímou vazbou na vybranou skupinu a její podskupiny.</p><?php
endif;
?><ul><?php
foreach ($d['plans'] as $p):
    ?><li><?=
    staffListH($p['datum'].' '.$p['cas_od'].' · '.$p['nazev'].' · '.$p['jmeno'])
    ?><?php
if (!$registrar):
    ?> · <a href="training_closure_admin.php?plan_id=<?=
    (int)$p['id']
    ?>">Otevřít lekci</a><?php
endif;
    ?></li><?php
endforeach;
?></ul>
<?php
endif;
?></main></body></html>
