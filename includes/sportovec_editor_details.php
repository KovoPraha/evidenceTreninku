<?php
declare(strict_types=1);
require_once __DIR__ . '/account_person_role.php';
require_once __DIR__ . '/kis_roster.php';
require_once __DIR__ . '/club_finance.php';

function sportovecEditorStatusLabel(?string $status): string
{
    return ['active' => 'Aktivní', 'approved' => 'Schválená', 'pending' => 'Čeká na vyřízení', 'revoked' => 'Odpojená',
        'removed' => 'Ukončené členství', 'paid' => 'Uhrazeno', 'cancelled' => 'Zrušeno', 'refunded' => 'Vráceno',
        'refund_required' => 'K vrácení', 'placed' => 'Vytvořeno', 'completed' => 'Dokončeno', 'review' => 'Čeká na kontrolu',
        'skipped' => 'Nepotvrzeno', 'contact_only' => 'Pouze kontakt bez schváleného přístupu', 'bank_transfer' => 'Bankovní převod',
        'manual_bank' => 'Ověřený převod', 'add_member' => 'Zařazení do soupisky', 'reactivate_member' => 'Obnovení členství',
        'remove_member' => 'Ukončení členství', 'manual' => 'Ruční evidence'][ $status ?? '' ] ?? ($status ?: '—');
}

function sportovecEditorMoney(int|string $minor, string $currency): string
{
    return number_format((int)$minor / 100, 2, ',', ' ') . ' ' . $currency;
}

function sportovecEditorRequireActor(int $actor): void
{
    if (personSensitiveRequireAdmin() !== $actor) throw new InvalidArgumentException('Neplatný správce změny.');
}

function sportovecEditorSaveBirthNumber(PDO $pdo, int $id, string $value, string $birthDate, string $reason, int $actor, ?array $keys = null, string $ip = ''): void
{
    sportovecEditorRequireActor($actor);
    if (mb_strlen(trim($reason), 'UTF-8') < 10) throw new InvalidArgumentException('Uveďte důvod změny RČ (alespoň 10 znaků).');
    $digits = personSensitiveNormalizeBirthNumber($value);
    $birthDate = trim($birthDate) ?: personSensitiveBirthDate($digits);
    personSensitiveValidateBirthNumber($value, $birthDate);
    if ($birthDate > date('Y-m-d')) throw new InvalidArgumentException('Datum narození nesmí být v budoucnosti.');
    $pdo->beginTransaction();
    try {
        $person = sportovecEditorPerson($pdo, $id, true);
        $stmt = $pdo->prepare('SELECT * FROM osoba_citlive_udaje WHERE sportovec_id=?');
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $token = $row ? (string)$row['record_token'] : bin2hex(random_bytes(16));
        $requestId = $row ? (int)($row['request_id'] ?? 0) : 0;
        $encrypted = personSensitiveEncrypt($digits, $token, $requestId, $keys, $id);
        if ($row) {
            $pdo->prepare("UPDATE osoba_citlive_udaje SET rc_ciphertext=?,rc_nonce=?,rc_key_version=?,rc_blind_index=?,status='approved',retention_reason=NULL,retention_until=NULL,erased_at=NULL,updated_at=CURRENT_TIMESTAMP WHERE id=?")
                ->execute([$encrypted['ciphertext'], $encrypted['nonce'], $encrypted['key_version'], $encrypted['blind_index'], $row['id']]);
        } else {
            $pdo->prepare("INSERT INTO osoba_citlive_udaje(record_token,request_id,sportovec_id,rc_ciphertext,rc_nonce,rc_key_version,rc_blind_index,contract_version,status) VALUES(?,NULL,?,?,?,?,?,'person-sensitive-person-v1','approved')")
                ->execute([$token, $id, $encrypted['ciphertext'], $encrypted['nonce'], $encrypted['key_version'], $encrypted['blind_index']]);
            $row = ['id' => (int)$pdo->lastInsertId(), 'sportovec_id' => $id, 'request_id' => null];
        }
        // The canonical value is encrypted; never copy the replacement into the legacy plaintext field.
        $pdo->prepare('UPDATE sportovci SET narozeni=?,rc=NULL WHERE id=?')->execute([$birthDate, $id]);
        personSensitiveAudit($pdo, $row, 'replace', $reason, $ip);
        sportovecLogEvent($pdo, $id, 'birth_number_edit', 'Úprava rodného čísla', ['narozeni' => $person['narozeni']], ['narozeni' => $birthDate], 'manual', $actor);
        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        if ($exception instanceof InvalidArgumentException) throw $exception;
        throw new PersonSensitiveException('Rodné číslo se nepodařilo bezpečně uložit. Zkontrolujte také, zda už není přiřazené jiné osobě.', 0, $exception);
    } finally { sodium_memzero($digits); }
}

function sportovecEditorParentValues(array $input, array $before = []): array
{
    $name = trim((string)($input['parent_name'] ?? ''));
    $relationship = trim((string)($input['relationship'] ?? ''));
    $emails = trim((string)($input['parent_emails'] ?? ''));
    $phones = trim((string)($input['parent_phones'] ?? ''));
    if ($name === '' || mb_strlen($name) > 255 || mb_strlen($relationship) > 255 || mb_strlen($emails) > 2000 || mb_strlen($phones) > 2000) {
        throw new InvalidArgumentException('Vyplňte jméno rodiče a zkontrolujte délku kontaktních údajů.');
    }
    $split = static fn(string $text) => array_values(array_unique(array_filter(array_map('trim', preg_split('/[\r\n;,]+/u', $text)))));
    $emails = $split($emails);
    if ($emails !== json_decode($before['emails_json'] ?? '[]', true)) {
        foreach ($emails as $email) if (!filter_var($email, FILTER_VALIDATE_EMAIL)) throw new InvalidArgumentException('E-mail rodiče není platný. Více e-mailů oddělte středníkem.');
    }
    return ['display_name' => $name, 'names_json' => kisPersonJson([$name]), 'emails_json' => kisPersonJson($emails),
        'phones_json' => kisPersonJson($split($phones)), 'relationship_label' => $relationship];
}

function sportovecEditorSaveParent(PDO $pdo, int $id, string $linkKey, array $input, int $actor): void
{
    sportovecEditorRequireActor($actor);
    $pdo->beginTransaction();
    try {
        sportovecEditorPerson($pdo, $id, true);
        $before = [];
        if ($linkKey !== '') {
            $stmt = $pdo->prepare('SELECT l.*,c.display_name,c.names_json,c.emails_json,c.phones_json FROM kis_person_links l JOIN kis_person_contacts c ON c.contact_key=l.contact_key WHERE l.source_key=? AND l.sportovec_id=? AND l.ended_at IS NULL');
            $stmt->execute([$linkKey, $id]);
            $before = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$before) throw new InvalidArgumentException('Rodič není připojen k tomuto sportovci.');
            $values = sportovecEditorParentValues($input, $before);
            $contactKey = $before['contact_key'];
            $pdo->prepare('UPDATE kis_person_contacts SET display_name=?,names_json=?,emails_json=?,phones_json=? WHERE contact_key=?')
                ->execute([$values['display_name'], $values['names_json'], $values['emails_json'], $values['phones_json'], $contactKey]);
            $pdo->prepare('UPDATE kis_person_links SET relationship_label=? WHERE sportovec_id=? AND contact_key=? AND ended_at IS NULL')->execute([$values['relationship_label'], $id, $contactKey]);
        } else {
            $contactKey = trim((string)($input['existing_contact_key'] ?? ''));
            if ($contactKey !== '') {
                $stmt = $pdo->prepare('SELECT contact_key FROM kis_person_contacts WHERE contact_key=?');
                $stmt->execute([$contactKey]);
                if (!$stmt->fetchColumn()) throw new InvalidArgumentException('Vybraný rodič nebyl nalezen.');
                $values = ['relationship_label' => mb_substr(trim((string)($input['relationship'] ?? '')), 0, 255)];
            } else {
                $values = sportovecEditorParentValues($input);
                $contactKey = 'manual-' . bin2hex(random_bytes(16));
                $pdo->prepare("INSERT INTO kis_person_contacts(contact_key,batch_fingerprint,display_name,names_json,emails_json,phones_json,linked_sportovec_id,account_id,account_state) VALUES(?,NULL,?,?,?,?,NULL,NULL,'no_account')")
                    ->execute([$contactKey, $values['display_name'], $values['names_json'], $values['emails_json'], $values['phones_json']]);
            }
            $exists = $pdo->prepare('SELECT source_key FROM kis_person_links WHERE sportovec_id=? AND contact_key=? AND ended_at IS NULL');
            $exists->execute([$id, $contactKey]);
            if ($exists->fetchColumn()) throw new InvalidArgumentException('Tento rodič už je ke sportovci připojený.');
            $linkKey = 'manual-' . bin2hex(random_bytes(16));
            $pdo->prepare("INSERT INTO kis_person_links(source_key,sportovec_id,contact_key,relationship_label,source_person_key,source_slot) VALUES(?,?,?,?,'manual',1)")
                ->execute([$linkKey, $id, $contactKey, $values['relationship_label']]);
        }
        $people = [$id];
        if ($before && array_intersect_key($before, array_flip(['display_name','names_json','emails_json','phones_json'])) !== array_intersect_key($values, array_flip(['display_name','names_json','emails_json','phones_json']))) {
            $affected = $pdo->prepare('SELECT DISTINCT sportovec_id FROM kis_person_links WHERE contact_key=? AND ended_at IS NULL');
            $affected->execute([$contactKey]); $people = $affected->fetchAll(PDO::FETCH_COLUMN);
        }
        foreach ($people as $personId) {
            sportovecLogEvent($pdo, (int)$personId, 'parent_edit', 'Úprava kontaktu rodiče',
                array_intersect_key($before, array_flip(['display_name','names_json','emails_json','phones_json'])),
                ['contact_key' => $contactKey] + array_intersect_key($values, array_flip(['display_name','names_json','emails_json','phones_json']))
                    + ((int)$personId === $id ? ['relationship_label' => $values['relationship_label']] : []), 'manual', $actor);
        }
        $pdo->commit();
    } catch (Throwable $exception) { if ($pdo->inTransaction()) $pdo->rollBack(); throw $exception; }
}

function sportovecEditorUnlinkParent(PDO $pdo, int $id, string $linkKey, int $actor): void
{
    sportovecEditorRequireActor($actor);
    $pdo->beginTransaction();
    try {
        sportovecEditorPerson($pdo, $id, true);
        $stmt = $pdo->prepare('SELECT l.*,c.account_id FROM kis_person_links l JOIN kis_person_contacts c ON c.contact_key=l.contact_key WHERE l.source_key=? AND l.sportovec_id=? AND l.ended_at IS NULL');
        $stmt->execute([$linkKey, $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) throw new InvalidArgumentException('Vazba rodiče nebyla nalezena u tohoto sportovce.');
        $pdo->prepare('UPDATE kis_person_links SET ended_at=CURRENT_TIMESTAMP WHERE sportovec_id=? AND contact_key=? AND ended_at IS NULL')->execute([$id, $row['contact_key']]);
        if ($row['account_id']) {
            $remaining = $pdo->prepare('SELECT COUNT(*) FROM kis_person_links l JOIN kis_person_contacts c ON c.contact_key=l.contact_key WHERE l.sportovec_id=? AND c.account_id=? AND l.ended_at IS NULL');
            $remaining->execute([$id, $row['account_id']]);
            if (!(int)$remaining->fetchColumn()) {
                $role = $pdo->prepare("SELECT id FROM account_person_roles WHERE sportovec_id=? AND account_id=? AND relation_role='guardian' AND status<>'revoked'");
                $role->execute([$id, $row['account_id']]);
                if ($relationId = $role->fetchColumn()) accountPersonRoleRevoke($pdo, (int)$relationId, $actor, 'Odpojení rodiče v detailu sportovce.');
            }
        }
        sportovecLogEvent($pdo, $id, 'parent_unlink', 'Odpojení rodiče', $row, ['source_key' => $linkKey, 'ended' => true], 'manual', $actor);
        $pdo->commit();
    } catch (Throwable $exception) { if ($pdo->inTransaction()) $pdo->rollBack(); throw $exception; }
}

function sportovecEditorGuardianAccount(PDO $pdo, int $id, int $accountId, int $actor, string $reason, string $contactKey = ''): void
{
    sportovecEditorRequireActor($actor);
    $pdo->beginTransaction();
    try {
        sportovecEditorPerson($pdo, $id, true);
        if ($contactKey !== '') {
            $stmt = $pdo->prepare('SELECT c.account_id FROM kis_person_contacts c JOIN kis_person_links l ON l.contact_key=c.contact_key WHERE l.sportovec_id=? AND l.contact_key=? AND l.ended_at IS NULL');
            $stmt->execute([$id, $contactKey]);
            $contact = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$contact) throw new InvalidArgumentException('Kontakt rodiče není připojen k tomuto sportovci.');
            if ($contact['account_id'] !== null && (int)$contact['account_id'] !== $accountId) throw new InvalidArgumentException('Kontakt už odkazuje na jiný účet. Pro změnu účtu nejprve zkontrolujte stávající propojení.');
        }
        accountPersonRoleApprove($pdo, $accountId, $id, 'guardian', $actor, $reason);
        if ($contactKey !== '') $pdo->prepare("UPDATE kis_person_contacts SET account_id=?,account_state='admin_linked' WHERE contact_key=?")->execute([$accountId, $contactKey]);
        $pdo->commit();
    } catch (Throwable $exception) { if ($pdo->inTransaction()) $pdo->rollBack(); throw $exception; }
}

function sportovecEditorRevokeAccount(PDO $pdo, int $id, int $relationId, int $actor, string $reason): void
{
    sportovecEditorRequireActor($actor);
    $stmt = $pdo->prepare("SELECT id FROM account_person_roles WHERE id=? AND sportovec_id=? AND relation_role='guardian'");
    $stmt->execute([$relationId, $id]);
    if (!$stmt->fetchColumn()) throw new InvalidArgumentException('Rodičovská vazba nepatří k tomuto sportovci.');
    accountPersonRoleRevoke($pdo, $relationId, $actor, $reason);
}

function sportovecEditorAddRoster(PDO $pdo, int $id, int $teamId, int $actor, string $validFrom, string $reason): void
{
    sportovecEditorRequireActor($actor);
    $temporal = clubFinanceTableExists($pdo,'club_team_edges');
    $stmt = $pdo->prepare("SELECT s.starts_on,s.ends_on,s.status AS season_status FROM club_teams t LEFT JOIN club_seasons s ON s.id=t.season_id WHERE t.id=? AND t.status='active'");
    $stmt->execute([$teamId]);
    $season = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$season || (!$temporal && ($season['season_status'] !== 'active' || $season['ends_on'] < date('Y-m-d')))) throw new InvalidArgumentException('Vyberte aktivní soupisku.');
    $validFrom = trim($validFrom) ?: max(date('Y-m-d'), (string)($season['starts_on'] ?? ''));
    if (!$temporal && ($validFrom < $season['starts_on'] || $validFrom > $season['ends_on'])) throw new InvalidArgumentException('Začátek členství musí patřit do vybrané sezony.');
    kisRosterAddMember($pdo, $teamId, $id, $actor, 'manual', $validFrom, trim($reason) ?: 'Zařazení ze stránky detailu sportovce.');
}

function sportovecEditorRosters(PDO $pdo, int $id, ?string $today = null): array
{
    $today ??= date('Y-m-d');
    $result = ['current' => [], 'planned' => [], 'history' => [], 'claims' => [], 'current_claims' => [], 'events' => [], 'choices' => []];
    if (!clubFinanceTableExists($pdo, 'club_roster_members')) return $result;
    $temporal = clubFinanceTableExists($pdo,'club_team_edges');
    $stmt = $pdo->prepare("SELECT m.*,t.name AS team_name,t.status AS team_status,COALESCE(s.name,'Průběžně') AS season_name,s.starts_on,s.ends_on,s.status AS season_status FROM club_roster_members m JOIN club_teams t ON t.id=m.team_id LEFT JOIN club_seasons s ON s.id=t.season_id WHERE m.sportovec_id=? ORDER BY COALESCE(s.starts_on,'9999-12-31') DESC,t.name,m.id");
    $stmt->execute([$id]);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $active = $row['status'] === 'active' && $row['team_status'] === 'active'
            && ($temporal || ($row['season_status'] === 'active' && $row['ends_on'] >= $today))
            && ($row['valid_to'] === null || $row['valid_to'] >= $today);
        $bucket = !$active ? 'history' : ((($row['starts_on'] ?? '') > $today && !$temporal) || $row['valid_from'] > $today ? 'planned' : 'current');
        $result[$bucket][] = $row;
    }
    if (clubFinanceTableExists($pdo, 'kis_legacy_roster_claims')) {
        $stmt = $pdo->prepare("SELECT c.*,t.name AS team_name,s.name AS season_name FROM kis_legacy_roster_claims c JOIN club_teams t ON t.id=c.team_id JOIN club_seasons s ON s.id=t.season_id WHERE c.sportovec_id=? AND c.member_id IS NULL ORDER BY s.starts_on DESC,t.name");
        $stmt->execute([$id]); $result['claims'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    if (clubFinanceTableExists($pdo, 'kis_current_roster_claims')) {
        $stmt = $pdo->prepare('SELECT c.*,COALESCE(t.name,legacy.legacy_label) AS team_name,s.name AS season_name FROM kis_current_roster_claims c JOIN kis_current_roster_runs run ON run.id=c.run_id JOIN club_seasons s ON s.id=run.season_id LEFT JOIN club_teams t ON t.id=COALESCE(c.target_team_id,c.suggested_team_id) LEFT JOIN kis_legacy_roster_claims legacy ON legacy.id=c.legacy_claim_id WHERE c.sportovec_id=? AND c.member_id IS NULL ORDER BY s.starts_on DESC,c.id');
        $stmt->execute([$id]); $result['current_claims'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    if (clubFinanceTableExists($pdo, 'club_roster_events')) {
        $stmt = $pdo->prepare('SELECT e.id,e.created_at,e.action,e.note,e.before_json,e.after_json,t.name AS team_name FROM club_roster_events e JOIN club_roster_members m ON m.id=e.roster_member_id JOIN club_teams t ON t.id=e.team_id WHERE m.sportovec_id=? ORDER BY e.created_at DESC,e.id DESC');
        $stmt->execute([$id]); $result['events'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    $choicesSql = "SELECT t.id,t.name,COALESCE(s.name,'Průběžně') AS season_name,s.starts_on FROM club_teams t LEFT JOIN club_seasons s ON s.id=t.season_id WHERE t.status='active'";
    if ($temporal) $choicesSql .= " AND t.team_kind='roster'";
    if (!$temporal) $choicesSql .= " AND s.status='active' AND s.ends_on>=?";
    $choicesSql .= " ORDER BY COALESCE(s.starts_on,'9999-12-31') DESC,t.name";
    $stmt = $pdo->prepare($choicesSql);
    $stmt->execute($temporal?[]:[$today]); $result['choices'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    return $result;
}

/** All statuses and years; only explicit beneficiaries/financial ownership, never siblings' account activity. */
function sportovecEditorPayments(PDO $pdo, int $id): array
{
    $result = ['charges' => [], 'orders' => [], 'receipts' => [], 'allocations' => []];
    if (clubFinanceTableExists($pdo, 'club_member_charges') && clubFinanceTableExists($pdo, 'payments')) {
        $stmt = $pdo->prepare("SELECT c.id,c.public_code,c.title_snapshot,c.amount_minor,c.currency,c.status,c.period_from,c.period_to,c.due_on,c.source_system,p.id AS payment_id,p.status AS payment_status,p.method,p.variable_symbol,p.paid_at FROM club_member_charges c LEFT JOIN payments p ON p.payable_type='member_charge' AND p.payable_id=c.id WHERE c.sportovec_id=? ORDER BY c.created_at DESC,c.id DESC");
        $stmt->execute([$id]); $result['charges'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    if (clubFinanceTableExists($pdo, 'shop_orders') && clubFinanceTableExists($pdo, 'shop_order_items') && clubFinanceTableExists($pdo, 'payments')) {
        $owner = clubFinanceColumnExists($pdo, 'shop_orders', 'finance_sportovec_id') ? 'o.finance_sportovec_id' : 'NULL';
        $stmt = $pdo->prepare("SELECT o.id,o.public_code,o.total_minor,o.currency,o.status,o.payment_status,o.placed_at,$owner AS owner_id,p.id AS payment_id,p.status AS ledger_status,p.method,p.variable_symbol,p.paid_at,(SELECT SUM(i.line_amount_minor) FROM shop_order_items i WHERE i.order_id=o.id AND i.beneficiary_sportovec_id=?) AS athlete_items_minor FROM shop_orders o LEFT JOIN payments p ON p.payable_type='shop_order' AND p.payable_id=o.id WHERE $owner=? OR EXISTS(SELECT 1 FROM shop_order_items i WHERE i.order_id=o.id AND i.beneficiary_sportovec_id=?) ORDER BY o.placed_at DESC,o.id DESC");
        $stmt->execute([$id, $id, $id]); $result['orders'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    if (clubFinanceTableExists($pdo, 'club_finance_receipts')) {
        $stmt = $pdo->prepare('SELECT id,kind,reference,amount_minor,currency,paid_on FROM club_finance_receipts WHERE sportovec_id=? ORDER BY paid_on DESC,id DESC');
        $stmt->execute([$id]); $result['receipts'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (clubFinanceTableExists($pdo, 'club_finance_allocations')) {
            $stmt = $pdo->prepare('SELECT a.receipt_id,a.charge_id,a.order_id,a.amount_minor FROM club_finance_allocations a JOIN club_finance_receipts r ON r.id=a.receipt_id WHERE r.sportovec_id=? ORDER BY a.receipt_id,a.id');
            $stmt->execute([$id]); $result['allocations'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    }
    return $result;
}
