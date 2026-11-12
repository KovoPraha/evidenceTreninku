<?php

declare(strict_types=1);

// Included only by the authenticated hierarchy controller.
if (!isset($pdo, $teams, $today) || !function_exists('krha')) {
    http_response_code(403);
    exit;
}
$memberPreview ??= null;
$selectedTeamId = max(0, (int) ($_GET['team_id'] ?? $_POST['team_id'] ?? 0));
$selectedDate = (string) ($_GET['on'] ?? $_POST['return_on'] ?? $today);
$memberView = null;
try {
    $selectedDate = kisRosterDate($selectedDate);
    if ($selectedTeamId) {
        $memberView = memberRosterRead($pdo, $selectedTeamId, $selectedDate);
    }
} catch (InvalidArgumentException|KisRosterException $error) {
    echo '<div class="alert alert-danger">' . krha($error->getMessage()) . '</div>';
}
$search = mb_substr(trim((string) ($_GET['person_search'] ?? $_POST['return_person_search'] ?? '')), 0, 100);
$people = [];
if ($memberView && $search !== '') {
    $query = $pdo->prepare("SELECT id,jmeno,prijmeni FROM sportovci WHERE stav_clenstvi<>'archiv' "
        . 'AND (jmeno LIKE ? OR prijmeni LIKE ?) ORDER BY prijmeni,jmeno,id LIMIT 50');
    $query->execute(['%' . $search . '%', '%' . $search . '%']);
    $people = $query->fetchAll(PDO::FETCH_ASSOC);
}

?>
<section class="card mt-3" id="clenove">
    <div class="card-header">Členové skupin a soupisek</div>
    <div class="card-body">
        <?php
if ($memberPreview):
    ?>
            <div class="alert alert-info"><h2 class="h5">Náhled změny členství</h2>
                <p>Akce: <?=
     krha(['grant' => 'Zařazení','end_member' => 'Ukončení','transfer' => 'Přestup'][$memberPreview['request']['action']])

    ?>.
                Důvod: <?=
     krha($memberPreview['request']['reason'])
    ?>.</p>
                <p>Platnost členství po změně:</p>
                <ul aria-label="Platnost členství po změně">
                    <?php foreach ($memberPreview['periods'] as $period): ?>
                        <li><?= krha($period['name']) ?>:
                            <?php if (!empty($period['cancelled'])): ?>
                                Zrušení před začátkem; členství nebude platné.
                            <?php else: ?>
                                <?= krha($period['valid_from'].' – '.($period['valid_to'] ?? 'bez konce')) ?>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
                <ul><?php
foreach ($memberPreview['people'] as $person):
    ?><li><?=
     krha($person['prijmeni'].
                ' '.$person['jmeno'])
    ?> (#<?=
     (int)$person['id']
    ?>)</li><?php
endforeach;
?></ul>
                <p>Dotčené budoucí tréninky: <?=
 count($memberPreview['impact']['plans'])
?>; akce: <?=
 count($memberPreview['impact']['events'])

?>.</p>
                <?php
foreach ($memberPreview['impact']['plans'] as $plan):
    ?><div><a href="training_rsvps_admin.php?plan_id=<?=

                (int)$plan['id']
    ?>"><?=
     krha($plan['date'].' '.$plan['name'])
    ?></a> — odpovědi sportovce: <?=
     (int)$plan['affected_responses']

    ?></div><?php
endforeach;
?>
                <?php
foreach ($memberPreview['impact']['events'] as $event):
    ?><div><?=
     krha($event['date'].
                        ' '.$event['name'])
    ?> (#<?=
     (int)$event['id']
    ?>) — přihlášky sportovce: <?=
     (int)$event['affected_responses']
    ?></div><?php
endforeach;

?>
                <?php
if ($memberPreview['impact']['blocked']):
    ?><p class="text-danger">Nejdřív vyřešte existující odpovědi/přihlášky u dotčených aktivit a načtěte nový náhled.</p>
                <?php else:
                    ?><form method="post" class="mt-3"><?=
                     csrf_field()
                    ?>
                    <?php
foreach ($memberPreview['request'] as $key => $value): foreach (is_array($value) ? $value : [$value] as $item):
    ?>
                        <input type="hidden" name="<?=
     krha($key.(is_array($value) ? '[]' : ''))
    ?>" value="<?=
     krha($item)

    ?>">
                    <?php
endforeach; endforeach;
                    ?>
                    <input type="hidden" name="fingerprint" value="<?=
                     krha($memberPreview['fingerprint'])
                    ?>">
                    <button class="btn btn-success">Potvrdit uvedenou změnu</button>
                </form><?php
                endif;
?>
            </div>
        <?php
endif;
?>
        <form method="get" class="row g-2 align-items-end">
            <div class="col-md-6"><label class="form-label" for="member-team">Skupina nebo soupiska</label>
                <select class="form-select" name="team_id" id="member-team" required>
                    <option value="">Vyberte</option>
                    <?php
foreach ($teams as $item):
    ?>
                        <option value="<?=
     (int) $item['id']
    ?>" <?=
     $selectedTeamId === (int) $item['id'] ? 'selected' :
                            ''
    ?>>
                            <?=
     krha($item['name'])
    ?> · <?=
     $item['team_kind'] === 'group' ? 'skupina' : 'soupiska'
    ?>
                        </option>
                    <?php
endforeach;
?>
                </select>
            </div>
            <div class="col-md-3"><label class="form-label" for="member-on">Členství k datu</label>
                <input class="form-control" type="date" id="member-on" name="on" value="<?=
 krha($selectedDate)
?>" required>
            </div>
            <div class="col-md-3"><button class="btn btn-primary">Zobrazit členy</button></div>
        </form>
        <?php
if ($memberView):
    ?>
            <details class="mt-3"><summary>Upravit název a zaměření</summary>
                <form method="post" class="row g-2 mt-1">
                    <?=
     csrf_field()
    ?>
                    <input type="hidden" name="action" value="update_team">
                    <input type="hidden" name="team_id" value="<?=
     $selectedTeamId
    ?>">
                    <div class="col-md-4"><label>Název<input class="form-control" name="name" maxlength="160"
                        value="<?=
     krha($memberView['team']['name'])
    ?>" required></label></div>
                    <div class="col-md-3"><label>Zaměření<select class="form-select" name="activity_kind" required>
                        <option value="">Vyberte</option>
                        <?php
foreach (['circle' => 'Kroužek / kurz', 'competitive' => 'Závodní oddíl',
                             'other' => 'Jiná činnost'] as $value => $label):
    ?>
                            <option value="<?=
     krha($value)
    ?>" <?=
     $memberView['team']['activity_kind'] === $value ? 'selected'
                            : ''
    ?>><?=
     krha($label)
    ?></option>
                        <?php
endforeach;
    ?>
                    </select></label></div>
                    <div class="col-md-4"><label>Důvod<input class="form-control" name="reason" maxlength="1000" required></label></div>
                    <div><button class="btn btn-outline-primary">Uložit nastavení</button></div>
                </form>
            </details>
            <?php
if ($memberView['team']['team_kind'] === 'roster'):
    $ageQuery = $pdo->prepare('SELECT * FROM member_roster_age_rules WHERE team_id=?');
    $ageQuery->execute([$selectedTeamId]);
    $ageRule = $ageQuery->fetch(PDO::FETCH_ASSOC) ?: [];

    ?>
                <details class="mt-3"><summary>Věkové pravidlo a návrh pokračování</summary>
                    <p>Pravidlo pouze navrhuje kontrolu. Osoby automaticky nepřesouvá. Narození bez úplného data vyžaduje ruční ověření.</p>
                    <form method="post" class="row g-2"><?=
     csrf_field()
    ?><input type="hidden" name="action" value="age_rule"><input type="hidden" name="team_id" value="<?=

        $selectedTeamId
    ?>">
                        <label class="col-md-4">Rozhodný den<select class="form-select" name="reference_mode"><?php
foreach (['none' => 'Bez věkového pravidla','on_date' => 'Věk ke dni kontroly','year_end' => 'Věk k 31. 12. roku kontroly'] as $key => $label):
    ?><option value="<?=
     krha($key)
    ?>" <?=
     ($ageRule['reference_mode'] ?? 'none') === $key ? 'selected' :
        ''
    ?>><?=
     krha($label)
    ?></option><?php
endforeach;
    ?></select></label>
                        <label class="col-md-2">Věk od<input class="form-control" type="number" min="0" max="120" name="age_min" value="<?=

        krha($ageRule['age_min'] ?? '')
    ?>"></label>
                        <label class="col-md-2">Věk do<input class="form-control" type="number" min="0" max="120" name="age_max" value="<?=

        krha($ageRule['age_max'] ?? '')
    ?>"></label>
<?php
    echo('                        <label class="col-md-4">Navržená soupiska po překročení věku<select ' .
        'class="form-select" name="next_team_id"><option value="">Ruční rozhodnutí</option>');
    ?><?php
foreach ($teams as $target):if ($target['team_kind'] !== 'roster' || (int)$target['id'] === $selectedTeamId) {
    continue;
}
    ?><option value="<?=
     (int)$target['id']
    ?>" <?=
     (int)($ageRule['next_team_id'] ?? 0) === (int)$target['id']
        ? 'selected' : ''
    ?>><?=
     krha($target['name'])
    ?></option><?php
endforeach;
    ?></select></label>
<?php
    echo('                        <label class="col-md-8">Důvod<input class="form-control" name="reason" ' .

        'maxlength="1000" required></label><div class="col align-self-end"><button class="btn ' .
        'btn-outline-primary">Uložit pravidlo</button></div>
');
    ?>                    </form><a class="btn btn-link" href="season_preparation_admin.php?team_id=<?=
     $selectedTeamId
    ?>&amp;on=<?=

        krha($selectedDate)
    ?>">Zkontrolovat věk a pokračování</a>
                </details>
            <?php
endif;
    ?>
            <p class="mt-3">Počet členů k <?=
     krha($selectedDate)
    ?>: <strong><?=
     count($memberView['effective_ids'])
    ?></strong></p>
            <?php
if ($memberView['team']['team_kind'] === 'group'):
    ?>
                <p><?=
     krha(implode(', ', krhPeople($pdo, $memberView['effective_ids'])))
    ?></p>
                <?php
if (count($memberView['effective_ids']) > 100):
    ?><p>Zobrazeno prvních 100 jmen. Úplný seznam je v podřízených soupiskách.</p><?php
endif;

    ?>
                <p class="text-muted">Členy přidávejte do podřízených soupisek. Zde se počítá každý člověk jednou.</p>
            <?php else:
                ?>
                <form method="get" class="d-flex gap-2 my-3">
                    <input type="hidden" name="team_id" value="<?=
                 $selectedTeamId
                ?>">
                    <input type="hidden" name="on" value="<?=
                 krha($selectedDate)
                ?>">
                    <label for="person-search" class="visually-hidden">Jméno sportovce k zařazení</label>
                    <input id="person-search" class="form-control" name="person_search" value="<?=
                 krha($search)
                ?>" placeholder="Jméno sportovce k zařazení">
                    <button class="btn btn-outline-primary">Vyhledat</button>
                </form>
                <?php
if ($search !== ''):
    ?>
                    <form method="post" class="row g-2 border rounded p-2 mb-3">
                        <?=
     csrf_field()
    ?>
                        <input type="hidden" name="action" value="grant">
                        <input type="hidden" name="team_id" value="<?=
     $selectedTeamId
    ?>">
                        <div class="col-md-5"><label class="form-label" for="member-people">Sportovci (nejvýše 50 výsledků hledání)</label>
                            <select id="member-people" class="form-select" name="person_ids[]" multiple required>
                                <?php
foreach ($people as $person):
    ?>
                                    <option value="<?=
     (int) $person['id']
    ?>"><?=
     krha($person['prijmeni'] .
                     ' ' . $person['jmeno'])
    ?> (#<?=
     (int) $person['id']
    ?>)</option>
                                <?php
endforeach;
    ?>
                            </select>
                        </div>
                        <div class="col-md-3"><label class="form-label" for="grant-from">Platí od</label>
                            <input id="grant-from" class="form-control" type="date" name="valid_from" value="<?=
     krha($selectedDate)

    ?>" required>
                        </div>
                        <div class="col-md-3"><label class="form-label" for="grant-to">Platí do (volitelné)</label>
                            <input id="grant-to" class="form-control" type="date" name="valid_to">
                        </div>
                        <div class="col-12"><label class="form-label" for="grant-reason">Důvod ručního zařazení</label>
                            <input id="grant-reason" class="form-control" name="reason" maxlength="1000" required>
                        </div>
                        <div><button class="btn btn-success">Zobrazit náhled zařazení</button></div>
                    </form>
                <?php
endif;
    ?>
                <div class="table-responsive"><table class="table table-sm align-middle">
                    <thead><tr><th>Sportovec</th><th>Platnost</th><th>Stav k vybranému dni</th><th>Zdroj</th><th>Změna</th></tr></thead>
                    <tbody>
                    <?php
foreach ($memberView['members'] as $member):
    $cancelled = $member['status'] === 'cancelled';
    $state = $cancelled ? 'Zrušeno' : ($member['valid_from'] > $selectedDate ? 'Budoucí' :
        ($member['valid_to'] !== null && $member['valid_to'] < $selectedDate ? 'Ukončené' : 'Platné'));

    ?>
                        <tr>
                            <td><?=
     krha($member['prijmeni'] . ' ' . $member['jmeno'])
    ?></td>
                            <td><?=
     krha($member['valid_from'] . ' – ' . ($member['valid_to'] ?? 'bez konce'))
    ?></td>
                            <td><?=
     krha($state)
    ?></td><td><?=
     krha($member['source'])
    ?></td>
                            <td>
                                <?php
if ($member['source'] === 'manual' && !$cancelled):
    ?>
                                    <details><summary>Ukončit nebo převést</summary>
                                        <form method="post" class="vstack gap-2 mt-2">
                                            <?=
     csrf_field()
    ?>
                                            <input type="hidden" name="team_id" value="<?=
     $selectedTeamId
    ?>">
                                            <input type="hidden" name="member_id" value="<?=
     (int) $member['id']
    ?>">
                                            <label>Důvod<input class="form-control" name="reason" maxlength="1000" required></label>
                                            <label>Poslední den členství<input class="form-control" type="date" name="valid_to" value="<?=

        krha($today)
    ?>"></label>
                                            <button class="btn btn-outline-danger" name="action" value="end_member">Náhled ukončení</button>
                                            <label>Přestup od<input class="form-control" type="date" name="valid_from" value="<?=

        krha($today)
    ?>"></label>
                                            <label>Cílová soupiska<select class="form-select" name="target_team_id">
                                                <option value="">Vyberte</option>
                                                <?php
foreach ($teams as $target): if ($target['team_kind'] !== 'roster'
        || (int) $target['id'] === $selectedTeamId) {
    continue;
}
    ?>
                                                    <option value="<?=
     (int) $target['id']
    ?>"><?=
     krha($target['name'])
    ?></option>
                                                <?php
endforeach;
    ?>
                                            </select></label>
                                            <button class="btn btn-outline-primary" name="action" value="transfer">Náhled přestupu</button>
                                        </form>
                                    </details>
                                <?php else:
                                    ?>
                                    <span class="text-muted">Správa přes zdroj nároku</span>
                                <?php
                                endif;
    ?>
                            </td>
                        </tr>
                    <?php
endforeach;
?>
                    </tbody>
                </table></div>
            <?php
endif;
?>
        <?php
endif;
?>
    </div>
</section>
