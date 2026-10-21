<?php
declare(strict_types=1);

require_once __DIR__ . '/staff_workspaces.php';
require_once __DIR__ . '/registration_text_labels.php';

function registrationTextsRequireRegistrar(): int
{
    $actorId = (int)($_SESSION['trener_id'] ?? 0);
    if ($actorId < 1 || !staffActivePositionIs('registrar')) {
        throw new RuntimeException('Správa textů je dostupná pouze v pracovní pozici Registrář členů a KIS.');
    }
    return $actorId;
}

function registrationTextsRequirePurpose(string $purpose): void
{
    if (!isset(ATHLETE_REGISTRATION_TEXT_PURPOSES[$purpose])) {
        throw new InvalidArgumentException('Neznámý typ registračního textu.');
    }
}

function registrationTextsCurrent(PDO $pdo, string $purpose, bool $lock = false): array
{
    registrationTextsRequirePurpose($purpose);
    $sql = "SELECT * FROM club_event_term_versions WHERE scope_type='athlete_registration' "
        . "AND scope_key='athlete-registration-v1' AND consent_purpose=? ORDER BY created_at DESC,id DESC LIMIT 1";
    if ($lock && $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') $sql .= ' FOR UPDATE';
    $statement = $pdo->prepare($sql);
    $statement->execute([$purpose]);
    $row = $statement->fetch(PDO::FETCH_ASSOC);
    if (!$row) throw new RuntimeException('Výchozí registrační text chybí. Kontaktujte správce systému.');
    return $row;
}

/** Every save is a new immutable draft; it never writes the public terms registry. */
function registrationTextsSaveDraft(PDO $pdo, string $purpose, string $text, string $reason, int $baseTermId, ?string $title = null): int
{
    $actorId = registrationTextsRequireRegistrar();
    registrationTextsRequirePurpose($purpose);
    $text = trim($text);
    $reason = trim($reason);
    if ($text === '' || mb_strlen($text) > 12000 || $reason === '' || mb_strlen($reason) > 1000) {
        throw new InvalidArgumentException('Vyplňte text do 12 000 znaků a důvod změny do 1 000 znaků.');
    }
    $current = registrationTextsCurrent($pdo, $purpose);
    $title = $title === null ? registrationTextTitle($current, $purpose) : trim($title);
    if ($title === '' || mb_strlen($title) > 200 || preg_match('/[\r\n\x00-\x1f\x7f]/u', $title) === 1) {
        throw new InvalidArgumentException('Vyplňte název do 200 znaků na jednom řádku.');
    }
    if ((int)$current['id'] !== $baseTermId) {
        throw new InvalidArgumentException('Mezitím bylo zveřejněno nové znění. Porovnejte ho se svým textem a uložte nový koncept.');
    }
    if ($text === trim((string)$current['consent_text_plain']) && $title === registrationTextTitle($current, $purpose)) {
        throw new InvalidArgumentException('Název i text se shodují s aktuálně zveřejněným zněním.');
    }
    $statement = $pdo->prepare('INSERT INTO athlete_registration_text_drafts(purpose,text_plain,reason,base_term_id,created_by,title_plain) VALUES(?,?,?,?,?,?)');
    $statement->execute([$purpose, $text, $reason, $baseTermId, $actorId, $title]);
    return (int)$pdo->lastInsertId();
}

/** Publish the saved draft, never POSTed text. Serialize competing publications. */
function registrationTextsPublish(PDO $pdo, int $draftId, bool $confirmed): int
{
    $actorId = registrationTextsRequireRegistrar();
    if (!$confirmed || $draftId < 1) throw new InvalidArgumentException('Výslovně potvrďte zveřejnění uloženého konceptu.');
    if ($pdo->inTransaction()) throw new LogicException('Zveřejnění vyžaduje samostatnou transakci.');
    $pdo->beginTransaction();
    try {
        $lock = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql' ? ' FOR UPDATE' : '';
        $statement = $pdo->prepare('SELECT * FROM athlete_registration_text_drafts WHERE id=?'.$lock);
        $statement->execute([$draftId]);
        $draft = $statement->fetch(PDO::FETCH_ASSOC);
        if (!$draft) throw new InvalidArgumentException('Koncept nebyl nalezen.');
        if ($draft['published_term_id'] !== null) {
            $pdo->commit();
            return (int)$draft['published_term_id'];
        }
        if ($lock !== '') {
            // Lock the stable base row first. A waiting publisher must perform
            // its current-version read only after the competing commit.
            $baseLock = $pdo->prepare('SELECT id FROM club_event_term_versions WHERE id=? FOR UPDATE');
            $baseLock->execute([(int)$draft['base_term_id']]);
            $baseLock->fetchColumn();
        }
        $current = registrationTextsCurrent($pdo, (string)$draft['purpose'], true);
        if ((int)$current['id'] !== (int)$draft['base_term_id']) {
            throw new InvalidArgumentException('Koncept vychází ze staršího znění. Porovnejte aktuální text a uložte nový koncept.');
        }
        $text = trim((string)$draft['text_plain']);
        $title = registrationTextTitle($draft, (string)$draft['purpose']);
        if ($text === '' || mb_strlen($text) > 12000 || preg_match('/^\s*vzor(?:ový)?\s+text\b|před produkční aktivací/iu', $text) === 1) {
            throw new InvalidArgumentException('Vzorový nebo neúplný text nelze zveřejnit. Doplňte konečné znění do nového konceptu.');
        }
        $version = 'registrace-'.$draftId;
        // Existing versions and acceptance snapshots remain immutable.
        $insert = $pdo->prepare("INSERT INTO club_event_term_versions(scope_type,scope_key,consent_purpose,event_id,terms_version,consent_text_plain,cancellation_policy_plain,cancellation_deadline_at,actor_trainer_id,actor_type,actor_id,status,title_plain) VALUES('athlete_registration','athlete-registration-v1',?,NULL,?,?,NULL,NULL,?,'trainer',?,'active',?)");
        $insert->execute([$draft['purpose'], $version, $text, $actorId, $actorId, $title]);
        $termId = (int)$pdo->lastInsertId();
        $pdo->prepare('UPDATE athlete_registration_text_drafts SET published_term_id=?,published_by=?,published_at=CURRENT_TIMESTAMP WHERE id=? AND published_term_id IS NULL')
            ->execute([$termId, $actorId, $draftId]);
        $pdo->commit();
        return $termId;
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $exception;
    }
}

/** Includes imported/seeded versions and named authors of subsequent publications. */
function registrationTextsHistory(PDO $pdo, string $purpose): array
{
    registrationTextsRequireRegistrar();
    registrationTextsRequirePurpose($purpose);
    $statement = $pdo->prepare("SELECT t.*,d.reason,d.created_by AS draft_created_by,d.published_by,d.published_at FROM club_event_term_versions t LEFT JOIN athlete_registration_text_drafts d ON d.published_term_id=t.id WHERE t.scope_type='athlete_registration' AND t.scope_key='athlete-registration-v1' AND t.consent_purpose=? ORDER BY t.created_at DESC,t.id DESC");
    $statement->execute([$purpose]);
    return $statement->fetchAll(PDO::FETCH_ASSOC);
}
