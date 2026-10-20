<?php
declare(strict_types=1);

require_once __DIR__ . '/club_program_terms.php';

// Owner-approved working wording for the 2026/27 club-program functional rollout.
// This does not replace the separate athlete data-processing notices.
const CLUB_PROGRAM_APPROVED_WORKING_TERMS = [
    'program_cancellation' => 'Přihlášku lze před zahájením programu zrušit písemným oznámením klubu. Způsob případného vrácení již uhrazené ceny klub posoudí podle okamžiku zrušení a vzniklých nákladů.',
    'program_consent' => 'Zákonný zástupce souhlasí s přihlášením vybraného dítěte do uvedeného kroužku, jeho účastí na programu a s organizační komunikací klubu k této účasti.',
];

/**
 * Preview or activate one shared pair of versioned terms for every real club
 * program. Existing offer-specific valid terms are a conflict: silently
 * replacing them could change the terms for an already published offer.
 *
 * @return array{programs:int,offers:int,terms_created:int,draft_overrides_archived:int,conflicts:list<string>,applied:bool}
 */
function clubProgramTermsActivateWorkingVersions(PDO $pdo, int $actorId, bool $apply): array
{
    if ($actorId < 1 || !clubProgramTermsRegistryAvailable($pdo)) {
        throw new InvalidArgumentException('Aktivace vyžaduje správce a registr verzovaných podmínek.');
    }
    $programs = $pdo->query(
        "SELECT id,name FROM club_programs WHERE status IN ('active','draft') "
        . "AND name NOT LIKE 'TEST - %' AND code NOT LIKE 'TEST-%' ORDER BY id"
    )->fetchAll(PDO::FETCH_ASSOC);
    $programIds = array_map(static fn (array $row): int => (int)$row['id'], $programs);
    $offers = [];
    $draftOverrides = [];
    $conflicts = [];
    $termsToCreate = 0;

    $offerQuery = $pdo->prepare('SELECT id FROM club_program_offers WHERE program_id=? ORDER BY id');
    $activeQuery = $pdo->prepare(
        "SELECT id,consent_text_plain FROM club_event_term_versions "
        . "WHERE scope_type=? AND scope_key=? AND consent_purpose=? AND status='active' ORDER BY id"
    );
    foreach ($programIds as $programId) {
        foreach (CLUB_PROGRAM_TERM_PURPOSES as $purpose) {
            $current = clubProgramTermsCurrent($pdo, 'program', $programId, $purpose);
            if (!$current || trim((string)$current['consent_text_plain']) !== CLUB_PROGRAM_APPROVED_WORKING_TERMS[$purpose]) {
                ++$termsToCreate;
            }
        }
        $offerQuery->execute([$programId]);
        foreach ($offerQuery->fetchAll(PDO::FETCH_COLUMN) as $offerId) {
            $offerId = (int)$offerId;
            $offers[] = $offerId;
            foreach (CLUB_PROGRAM_TERM_PURPOSES as $purpose) {
                $activeQuery->execute(['club_program_offer', 'offer:' . $offerId, $purpose]);
                foreach ($activeQuery->fetchAll(PDO::FETCH_ASSOC) as $term) {
                    if (clubProgramTermTextReady((string)$term['consent_text_plain'])) {
                        $conflicts[] = 'offer:' . $offerId . ':' . $purpose;
                    } else {
                        $draftOverrides[] = (int)$term['id'];
                    }
                }
            }
        }
    }

    $result = [
        'programs' => count($programIds),
        'offers' => count($offers),
        'terms_created' => $termsToCreate,
        'draft_overrides_archived' => count($draftOverrides),
        'conflicts' => array_values(array_unique($conflicts)),
        'applied' => false,
    ];
    if (!$apply) return $result;
    if ($programIds === []) throw new RuntimeException('Nenalezen žádný skutečný kroužkový program.');
    if ($conflicts !== []) throw new RuntimeException('Nabídky mají vlastní platné podmínky: ' . implode(', ', $conflicts));

    $pdo->beginTransaction();
    try {
        foreach ($programIds as $programId) {
            foreach (CLUB_PROGRAM_TERM_PURPOSES as $purpose) {
                clubProgramTermsConfigureInTransaction(
                    $pdo, $actorId, 'program', $programId, $purpose,
                    CLUB_PROGRAM_APPROVED_WORKING_TERMS[$purpose], true
                );
            }
        }
        if ($draftOverrides !== []) {
            $archive = $pdo->prepare(
                "UPDATE club_event_term_versions SET status='archived',"
                . "archived_at=CURRENT_TIMESTAMP,archived_by_trainer_id=? WHERE id=? AND status='active'"
            );
            foreach ($draftOverrides as $id) $archive->execute([$actorId, $id]);
        }
        foreach ($offers as $offerId) {
            $lookup = $pdo->prepare('SELECT program_id FROM club_program_offers WHERE id=?');
            $lookup->execute([$offerId]);
            if (!clubProgramTermsComplete(clubProgramTermsEffective($pdo, (int)$lookup->fetchColumn(), $offerId))) {
                throw new RuntimeException('Nabídka ' . $offerId . ' nemá po aktivaci úplné podmínky.');
            }
        }
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
    $result['applied'] = true;
    return $result;
}
