<?php /** @var array $displayRows Authorized, validated revision rows supplied by the caller. */ ?>
<div class="table-responsive"><table class="table">
<thead><tr><th>Účastník</th><th>Klub</th><th>Disciplína / jízda</th><th>Stav</th><th>Čas</th><th>Pořadí</th></tr></thead>
<tbody>
<?php foreach ($displayRows as $r): ?>
    <tr><?php foreach ([
        $r['name'] . ' (' . $r['key'] . ')',
        $r['club'],
        $r['discipline'] . ' / ' . $r['heat'],
        ['entered' => 'Přihlášen', 'finished' => 'Dokončil', 'dns' => 'DNS', 'dnf' => 'DNF', 'dsq' => 'DSQ'][$r['status']] ?? $r['status'],
        $r['time'],
        $r['rank'],
    ] as $v): ?>
        <td><?= htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></td>
    <?php endforeach; ?></tr>
<?php endforeach; ?>
</tbody></table></div>
