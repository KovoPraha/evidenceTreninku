<?php

declare(strict_types=1);

require_once __DIR__ . '/external_sync_admin_read.php';
require_once __DIR__ . '/external_sync_admin_view.php';

/** Accepted snapshots only; fixed query count across sources and licences. */
function externalSyncPersonPanel(PDO $pdo, int $trainerId, int $personId, int $page = 1): array
{
    operationalCaseActor($pdo, $trainerId, 'registrar');
    if ($pdo->inTransaction()) {
        throw new LogicException('external_sync_read_requires_own_transaction');
    }
    $pdo->beginTransaction();
    try {
        $people = externalSyncPeople($pdo);
        $indexed = array_column($people, null, 'id');
        if (!isset($indexed[$personId])) {
            externalSyncReject('unknown_person', 404);
        }
        $uci = externalIdentityUci($indexed[$personId]['uciid']);
        $same = array_filter($people, static fn (array $p): bool => externalIdentityUci($p['uciid'])['uci'] === $uci['uci']);
        $result = ['person_id' => $personId, 'page' => max(1, min(999999, $page)), 'uci_id' => $uci['uci'],
            'rows' => [], 'total' => 0, 'rejected' => 0, 'has_next' => false, 'identity' => 'matched'];
        if ($uci['uci'] === null || count($same) !== 1) {
            $result['identity'] = $uci['uci'] === null ? 'missing_or_invalid' : 'duplicate_kis_uci';
            $pdo->commit();
            return $result;
        }
        $from = ' FROM external_sync_connections c JOIN external_sync_runs r ON r.id=c.latest_run_id AND r.connection_id=c.id
            JOIN external_sync_records e ON e.run_id=r.id
            WHERE r.status=\'complete\' AND c.collection<>\'sportsoft_events\' AND e.uci_id=?';
        // Here each run is a separate conflict group; no conflict is inferred across two providers.
        $groups = (static function () use ($pdo, $from, $uci): Generator {
            $query = externalSyncQuery($pdo, 'SELECT e.run_id,e.row_json' . $from, [$uci['uci']]);
            while ($row = $query->fetch(PDO::FETCH_ASSOC)) {
                yield ['uci_id' => $row['run_id'], 'row_json' => $row['row_json']];
            }
        })();
        $conflicts = externalSyncSourceConflicts($groups);
        $links = externalSyncQuery($pdo, 'SELECT l.connection_id,l.entity_key,l.uci_id,l.sportovec_id,l.revision
            FROM external_identity_links l JOIN external_sync_connections c ON c.id=l.connection_id
            WHERE c.latest_run_id IS NOT NULL')->fetchAll(PDO::FETCH_ASSOC);
        $byConnection = [];
        foreach ($links as $link) {
            $byConnection[$link['connection_id']][$link['entity_key']] = $link;
        }
        $query = externalSyncQuery($pdo, 'SELECT e.*,c.id AS connection_id,c.collection,c.scope_id,c.event_id,c.enabled,r.finished_at'
            . $from . ' ORDER BY c.provider,c.collection,c.id,e.ordinal_no', [$uci['uci']]);
        $offset = ($result['page'] - 1) * 25;
        while ($record = $query->fetch(PDO::FETCH_ASSOC)) {
            $match = externalSyncResolveRecord(
                $record,
                $people,
                $byConnection[$record['connection_id']] ?? [],
                isset($conflicts[$record['run_id']]) ? [$uci['uci'] => true] : [],
                $record['collection']
            );
            if ($match['status'] !== 'matched' || (int)$match['sportovec_id'] !== $personId) {
                $result['rejected']++;
                continue;
            }
            $result['total']++;
            if ($result['total'] <= $offset || count($result['rows']) >= 25) {
                continue;
            }
            $source = json_decode($record['row_json'], true, 16, JSON_THROW_ON_ERROR);
            $result['rows'][] = array_intersect_key($record, array_flip(['connection_id', 'collection', 'scope_id', 'event_id', 'run_id', 'ordinal_no', 'part_no', 'finished_at']))
                + ['source' => array_intersect_key($source, externalSyncAdminFields($record['collection'])),
                    'stale' => !(int)$record['enabled'] || (int)$record['finished_at'] < time() - 86400, 'observed_at' => null];
        }
        $pairs = [];
        foreach ($result['rows'] as $row) {
            $pairs[$row['run_id'] . ':' . $row['part_no']] = [$row['run_id'], (int)$row['part_no']];
        }
        $observed = [];
        if ($pairs !== []) {
            $params = array_merge(...array_values($pairs));
            $query = externalSyncQuery($pdo, 'SELECT run_id,part_no,payload_json FROM external_sync_parts WHERE '
                . implode(' OR ', array_fill(0, count($pairs), '(run_id=? AND part_no=?)')), $params);
            while ($part = $query->fetch(PDO::FETCH_ASSOC)) {
                $batch = json_decode($part['payload_json'], true, 32, JSON_THROW_ON_ERROR);
                $observed[$part['run_id'] . ':' . $part['part_no']] = $batch['observed_at'] ?? null;
            }
        }
        foreach ($result['rows'] as &$row) {
            $row['observed_at'] = $observed[$row['run_id'] . ':' . $row['part_no']] ?? null;
        }
        unset($row);
        $result['has_next'] = $result['total'] > $offset + 25;
        $pdo->commit();
        return $result;
    } catch (Throwable $error) {
        $pdo->rollBack();
        throw $error;
    }
}

function externalSyncPersonPanelRender(array $panel): void
{
    $h = 'externalSyncAdminH';
    $url = static fn (int $page): string => 'sportovec_karta.php?' . http_build_query(['id' => $panel['person_id'], 'tab' => 'kis', 'sync_page' => $page]);
    ?>
    <section class="card mb-3" id="external-sync-person"><div class="card-body text-break">
        <h3 class="h5">ČSC a Sportsoft · přijatá data</h3>
        <p>Údaje zdrojů pro UCI ID <?= $h($panel['uci_id'] ?? 'neuvedené / neplatné') ?>. Kmenová evidence a platby KIS se tím nemění.</p>
        <?php if ($panel['identity'] !== 'matched') : ?>
            <p class="alert alert-warning">UCI ID chybí, je neplatné nebo duplicitní v KIS. Data nelze bezpečně přiřadit.</p>
        <?php elseif ($panel['total'] === 0) : ?>
            <p>Dosud nejsou přijatá data s jednoznačnou shodou pro tuto osobu.</p>
        <?php endif; ?>
        <?php if ($panel['rejected'] > 0) : ?><p class="alert alert-warning">Některé řádky se stejným UCI ID vyžadují prověření identity v přehledu synchronizací.</p><?php endif; ?>
        <?php foreach ($panel['rows'] as $row) : ?>
            <article class="border rounded p-3 mb-3 sync-person-record">
                <h4 class="h6"><?= $h(externalSyncAdminCollection($row['collection'])) ?></h4>
                <p>Rozsah <?= $h($row['scope_id']) ?><?php if ($row['event_id'] !== null) : ?> · závod <?= $h($row['event_id']) ?><?php endif; ?>
                    <br>Pozorováno: <?= $h($row['observed_at'] ?? 'čas není dostupný') ?> · přijato <?= $h(externalSyncAdminTime($row['finished_at'])) ?></p>
                <?php if ($row['stale']) : ?><p class="badge text-bg-warning text-wrap">Data starší než 24 hodin nebo připojení vypnuto</p><?php endif; ?>
                <dl class="row mb-0">
                    <?php foreach (externalSyncAdminFields($row['collection']) as $field => $label) : ?>
                        <dt class="col-sm-4"><?= $h($label) ?></dt><dd class="col-sm-8"><?= $h(externalSyncAdminValue($row['source'][$field] ?? null)) ?></dd>
                    <?php endforeach; ?>
                </dl>
                <?php if ($row['collection'] === 'csc_licenses') : ?><p>Rok ve zdroji sám o sobě nepotvrzuje platnost licence.</p><?php endif; ?>
                <?php if ($row['collection'] === 'sportsoft_registrations') : ?><p>Údaj o zaplacení ve Sportsoftu nepotvrzuje platbu v KIS.</p><?php endif; ?>
                <a href="external_sync_review.php?<?= $h(http_build_query(['run' => $row['run_id'], 'ordinal' => $row['ordinal_no']])) ?>">Detail a historie řešení</a>
            </article>
        <?php endforeach; ?>
        <nav class="d-flex flex-wrap gap-3" aria-label="Data osoby ze zdrojů">
            <a href="external_sync_admin.php">Přehled synchronizací</a>
            <span><?= (int)$panel['total'] ?> řádků · stránka <?= (int)$panel['page'] ?></span>
            <?php if ($panel['page'] > 1) : ?><a href="<?= $h($url($panel['page'] - 1)) ?>">Předchozí data osoby</a><?php endif; ?>
            <?php if ($panel['has_next']) : ?><a href="<?= $h($url($panel['page'] + 1)) ?>">Další data osoby</a><?php endif; ?>
        </nav>
    </div></section>
    <?php
}
