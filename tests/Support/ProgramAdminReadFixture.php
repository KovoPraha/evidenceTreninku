<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli' || getenv('EVIDENCE_PERFORMANCE_CONFIRM') !== 'SYNTHETIC-ONLY') {
    http_response_code(404);
    exit;
}

/** Additional synthetic offers on the owned FullHttpReadFixture schema only. */
function programAdminReadPopulate(PDO $pdo, int $total): void
{
    $schema = (string) $pdo->query('SELECT DATABASE()')->fetchColumn();
    if (!preg_match('/^p11_read_[a-f0-9]{16}$/D', $schema) || $total < 2 || $total > 401) {
        throw new RuntimeException('Owned read fixture and bounded size required.');
    }
    $program = $pdo->prepare('INSERT INTO club_programs(id,code,name,status,created_by_trainer_id) VALUES(?,?,?,?,7)');
    $team = $pdo->prepare("INSERT INTO club_teams(id,season_id,code,name,discipline,age_label,status,created_by_trainer_id)
        VALUES(?,1,?,?,'all','synthetic','active',7)");
    $variant = $pdo->prepare("INSERT INTO shop_variants(id,product_id,source_candidate_id,sku,attributes_json,price_mode,
        amount_minor,currency,visible,catalog_status) VALUES(?,501,NULL,?,'{}','fixed',12500,'CZK',1,'active')");
    $offer = $pdo->prepare('INSERT INTO club_program_offers(id,program_id,season_id,team_id,product_id,variant_id,code,name,
        starts_on,ends_on,sales_open_at,capacity,status,created_by_trainer_id) VALUES(?,?,1,?,501,?,?,?,?,?,?,?,?,7)');
    $physical = $pdo->prepare('INSERT INTO club_program_physical_teams(program_id,team_id) VALUES(?,?)');
    $term = $pdo->prepare('INSERT INTO club_event_term_versions(scope_type,scope_key,consent_purpose,event_id,terms_version,
        consent_text_plain,cancellation_policy_plain,cancellation_deadline_at,actor_trainer_id,actor_type,actor_id,status)
        VALUES(?,?,?,NULL,?,?,NULL,NULL,7,\'trainer\',7,?)');
    $existing = $pdo->prepare('SELECT code FROM club_programs WHERE id=?');
    $pdo->beginTransaction();
    try {
        for ($i = 0; $i < $total - 2; $i++) {
            $id = 10000 + $i;
            $code = 'PADMIN-' . $id;
            $existing->execute([$id]);
            $found = $existing->fetchColumn();
            if ($found !== false) {
                if ($found !== $code) {
                    throw new RuntimeException('Fixture ID collision.');
                }
                continue;
            }
            $program->execute([$id, $code, 'LOCALHOST program ' . $id, $i % 10 === 0 ? 'closed' : 'active']);
            $team->execute([$id, $code, 'LOCALHOST roster ' . $id]);
            $variant->execute([$id, $code]);
            $expired = $i % 11 === 0;
            $offer->execute([$id, $id, $i % 3 === 0 ? 1 : $id, $id, $code, 'LOCALHOST offer ' . $id,
                $expired ? '2000-01-01' : '2026-01-01', $expired ? '2001-12-31' : '2099-12-31',
                $i % 7 === 0 ? '2099-01-01 00:00:00' : null,
                $i % 9 === 0 ? null : ($i % 8 === 0 ? 0 : 20), ['active', 'closed', 'draft', 'active'][$i % 4]]);
            if ($i % 4 === 0) {
                $physical->execute([$id, 1]);
                $physical->execute([$id, 2]);
            }
            foreach (CLUB_PROGRAM_TERM_PURPOSES as $purpose) {
                // Native collation must keep matching the canonical case-insensitive lookup.
                $scope = ($i % 13 === 0 ? 'PROGRAM:' : 'program:') . $id;
                $term->execute(['club_program', $scope, $purpose, 'v1', 'Published LOCALHOST NEPLATIT terms', 'active']);
            }
            if ($i % 4 !== 3) {
                $text = ['Published offer consent', '', 'VZOR — pracovní text'][$i % 4];
                $term->execute(['club_program_offer', 'offer:' . $id, 'program_consent', 'v1', $text, 'active']);
                $term->execute(['club_program_offer', 'offer:' . $id, 'program_consent', 'v2', 'Archived later row', 'archived']);
            }
        }
        $pdo->commit();
    } catch (Throwable $error) {
        $pdo->rollBack();
        throw $error;
    }
}

function programAdminReadRemoveAdditional(PDO $pdo): void
{
    $ids = $pdo->query("SELECT id FROM club_programs WHERE code LIKE 'PADMIN-%'")->fetchAll(PDO::FETCH_COLUMN);
    foreach ($ids as $id) {
        if ((int) $id < 10000 || (int) $id > 10398) {
            throw new RuntimeException('Unexpected owned fixture ID.');
        }
        $pdo->prepare('DELETE FROM club_event_term_versions WHERE scope_key IN (?,?)')
            ->execute(['program:' . $id, 'offer:' . $id]);
        $pdo->prepare('DELETE FROM club_program_physical_teams WHERE program_id=?')->execute([$id]);
        foreach (['club_program_offers', 'club_programs', 'club_teams', 'shop_variants'] as $table) {
            $pdo->prepare('DELETE FROM ' . $table . ' WHERE id=?')->execute([$id]);
        }
    }
}
