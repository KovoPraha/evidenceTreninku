<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/session_security.php';
app_session_start();
if (!isset($_SESSION['verejny_uzivatel_id'])) {
    header('Location: prihlaseni.php?redirect=sportovni_prehled.php');
    exit;
}

require_once dirname(__DIR__) . '/db.php';
require_once dirname(__DIR__) . '/csrf_helper.php';
require_once dirname(__DIR__) . '/includes/family_portal.php';
require_once dirname(__DIR__) . '/includes/family_calendar_feed.php';
require_once dirname(__DIR__) . '/includes/family_weekly_summary.php';
require_once dirname(__DIR__) . '/includes/member_charge_reminder.php';
require_once dirname(__DIR__) . '/includes/shop_checkout.php';

function familyPageH(mixed $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function familyPageChargeStatus(string $status): string
{
    return ['pending' => 'Čeká na úhradu', 'paid' => 'Uhrazeno', 'cancelled' => 'Zrušeno'][$status] ?? $status;
}

function familyPageItemCount(int $count): string
{
    $word = $count === 1 ? 'položka' : ($count >= 2 && $count <= 4 ? 'položky' : 'položek');
    return $count . ' ' . $word;
}

$accountId = (int)$_SESSION['verejny_uzivatel_id'];
$calendarMessage = (string)($_SESSION['family_calendar_message'] ?? '');
$calendarToken = (string)($_SESSION['family_calendar_token_once'] ?? '');
$reminderMessage = (string)($_SESSION['member_charge_reminder_message'] ?? '');
unset($_SESSION['family_calendar_message'], $_SESSION['family_calendar_token_once']);
unset($_SESSION['member_charge_reminder_message']);
$calendarError = '';
$reminderError = '';
$action = '';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!csrf_verify((string)($_POST['csrf_token'] ?? ''))) {
        $calendarError = 'Formulář vypršel. Obnovte stránku a zkuste to znovu.';
    } else {
        try {
            $action = (string)($_POST['action'] ?? '');
            if ($action === 'family_calendar_issue') {
                $issued = familyCalendarFeedIssue($pdo, $accountId);
                $_SESSION['family_calendar_token_once'] = $issued['token'];
                $_SESSION['family_calendar_message'] = $issued['created']
                    ? 'Soukromý odkaz kalendáře byl vytvořen.'
                    : 'Starý odkaz byl zrušen a nahrazen novým.';
                header('Location: sportovni_prehled.php#rodinny-kalendar', true, 303);
                exit;
            }
            if ($action === 'family_calendar_revoke') {
                familyCalendarFeedRevoke($pdo, $accountId);
                $_SESSION['family_calendar_message'] = 'Soukromý odkaz kalendáře byl zrušen.';
                header('Location: sportovni_prehled.php#rodinny-kalendar', true, 303);
                exit;
            }
            if ($action === 'member_charge_reminder_save') {
                $enabled = (string)($_POST['enabled'] ?? '0') === '1';
                $daysBefore = (int)($_POST['days_before'] ?? 7);
                memberChargeReminderSavePreference($pdo, $accountId, $enabled, $daysBefore);
                $generated = $enabled ? memberChargeReminderGenerate($pdo) : ['queued' => 0];
                $_SESSION['member_charge_reminder_message'] = $enabled
                    ? 'Připomínky plateb jsou zapnuté. Nově zařazené: ' . (int)$generated['queued'] . '.'
                    : 'Připomínky plateb jsou vypnuté a neodeslané zprávy byly zrušeny.';
                header('Location: sportovni_prehled.php#pripominky-plateb', true, 303);
                exit;
            }
            $calendarError = 'Neznámá akce kalendáře.';
        } catch (InvalidArgumentException | MemberChargeReminderException $exception) {
            if ($action === 'member_charge_reminder_save') $reminderError = $exception->getMessage();
            else $calendarError = $exception->getMessage();
        } catch (FamilyCalendarFeedException $exception) {
            $calendarError = $exception->getMessage();
        } catch (Throwable $exception) {
            error_log('booking/sportovni_prehled.php calendar action: ' . $exception->getMessage());
            if ($action === 'member_charge_reminder_save') $reminderError = 'Nastavení připomínek se nyní nepodařilo uložit.';
            else $calendarError = 'Nastavení kalendáře se nyní nepodařilo uložit.';
        }
    }
}

$calendarState = familyCalendarFeedState($pdo, $accountId);
$calendarUrl = $calendarToken !== '' ? familyCalendarFeedUrl($calendarToken) : '';
$reminderPreference = memberChargeReminderPreference($pdo, $accountId);
$reminderSummary = memberChargeReminderAccountSummary($pdo, $accountId);
$overview = [];
$familyOrderItems = [];
$familyAgenda = [];
$weeklySummary = null;
$agendaError = '';
$weeklyError = '';
$loadError = '';
try {
    $overview = familyPortalOverview($pdo, $accountId);
    if (shopBeneficiaryColumnExists($pdo, 'shop_order_items')) {
        $familyOrderItems = shopBeneficiaryOrderItemsForAccount(
            $pdo,
            $accountId
        );
    }
} catch (Throwable $exception) {
    error_log('booking/sportovni_prehled.php: ' . $exception->getMessage());
    $loadError = 'Sportovní přehled se nyní nepodařilo načíst.';
}
try {
    $today = new DateTimeImmutable('today', new DateTimeZone('Europe/Prague'));
    $agendaFrom = $today->format('Y-m-d');
    $familyAgenda = familyCalendarAgenda($pdo, $accountId, $agendaFrom, 30);
} catch (Throwable $exception) {
    error_log('booking/sportovni_prehled.php agenda: ' . $exception->getMessage());
    $agendaError = 'Rodinný program se nyní nepodařilo načíst.';
}
try {
    $today ??= new DateTimeImmutable('today', new DateTimeZone('Europe/Prague'));
    $weeklyStart = familyWeeklySummaryStartDate((string)($_GET['week'] ?? ''), $today);
    $weeklySummary = familyWeeklySummaryPreview($pdo, $accountId, $weeklyStart->format('Y-m-d'));
    $weeklyPrevious = $weeklyStart->modify('-7 days')->format('Y-m-d');
    $weeklyNext = $weeklyStart->modify('+7 days')->format('Y-m-d');
} catch (InvalidArgumentException $exception) {
    $weeklyError = $exception->getMessage();
} catch (Throwable $exception) {
    error_log('booking/sportovni_prehled.php weekly summary: ' . $exception->getMessage());
    $weeklyError = 'Náhled týdenního souhrnu se nyní nepodařilo načíst.';
}

$roleLabels = ['guardian' => 'rodič / zástupce', 'self' => 'vlastní profil'];
?>
<!doctype html>
<html lang="cs">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sportovní přehled — Kovopraha</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<nav class="navbar navbar-light bg-white border-bottom shadow-sm"><div class="container">
    <a class="navbar-brand fw-bold" href="kalendar.php"><i class="bi bi-bicycle me-2 text-primary"></i>Kovopraha</a>
    <div class="d-flex gap-2 flex-wrap">
        <a href="moje_osoby.php" class="btn btn-outline-secondary btn-sm">Moje osoby</a>
        <a href="krouzky.php" class="btn btn-outline-secondary btn-sm">Kroužky</a>
        <a href="moje_objednavky.php" class="btn btn-outline-success btn-sm">Objednávky</a>
        <a href="kalendar.php" class="btn btn-outline-primary btn-sm">Kalendář</a>
        <form method="post" action="odhlaseni.php" class="d-inline"><?= csrf_field() ?><button class="btn btn-outline-danger btn-sm">Odhlásit</button></form>
    </div>
</div></nav>
<main class="container py-4" style="max-width:1100px">
    <h1 class="h4 mb-1"><i class="bi bi-person-vcard me-2 text-primary"></i>Sportovní přehled</h1>
    <p class="text-muted">Soupisky, klubové události a zaznamenaná účast na trénincích pro vaše schválené profily.</p>

    <?php if ($loadError !== ''): ?><div class="alert alert-danger"><?= familyPageH($loadError) ?></div><?php endif; ?>
    <?php if ($loadError === '' && $overview === []): ?>
        <div class="alert alert-info">Nemáte žádný aktivní schválený profil. O propojení můžete požádat v části <a href="moje_osoby.php">Moje osoby</a>.</div>
    <?php endif; ?>

    <section class="card border-0 shadow-sm mb-4" id="rodinny-program">
        <div class="card-header bg-white d-flex flex-wrap justify-content-between align-items-center gap-2"><strong><i class="bi bi-calendar2-week me-2 text-success"></i>Co nás čeká v příštích 30 dnech</strong><span class="badge text-bg-light border text-dark"><?= familyPageH(familyPageItemCount(count($familyAgenda))) ?></span></div>
        <div class="card-body">
            <p class="small text-muted">Společný read-only program všech schválených profilů: tréninky, přihlášené akce, rezervace a splatnosti. Používá stejná oprávnění jako soukromý rodinný kalendář.</p>
            <?php if ($agendaError !== ''): ?><div class="alert alert-warning mb-0"><?= familyPageH($agendaError) ?></div>
            <?php elseif ($familyAgenda === []): ?><p class="text-muted mb-0">V nejbližších 30 dnech není evidovaná žádná položka.</p>
            <?php else: ?><div class="list-group list-group-flush">
                <?php foreach ($familyAgenda as $item): ?>
                    <div class="list-group-item px-0"><div class="d-flex flex-wrap justify-content-between gap-2"><div><span class="badge text-bg-<?= $item['category'] === 'Členský předpis' ? 'warning' : ($item['category'] === 'Rodinný trénink' ? 'primary' : 'success') ?> me-2"><?= familyPageH($item['category']) ?></span><strong><?= familyPageH($item['summary']) ?></strong><?php if ($item['location'] !== ''): ?><div class="small text-muted mt-1"><i class="bi bi-geo-alt me-1"></i><?= familyPageH($item['location']) ?></div><?php endif; ?><?php if ($item['description'] !== ''): ?><div class="small text-muted"><?= familyPageH($item['description']) ?></div><?php endif; ?></div><div class="text-md-end"><strong><?= familyPageH($item['date_label']) ?></strong><div class="small text-muted"><?= familyPageH($item['time_label']) ?></div></div></div></div>
                <?php endforeach; ?>
            </div><?php endif; ?>
        </div>
    </section>

    <section class="card border-info shadow-sm mb-4" id="tydenni-souhrn">
        <div class="card-header bg-info-subtle d-flex flex-wrap justify-content-between align-items-center gap-2"><strong><i class="bi bi-envelope-paper me-2"></i>Náhled týdenního souhrnu</strong><?php if ($weeklySummary !== null): ?><span class="badge text-bg-light border text-dark"><?= familyPageH($weeklySummary['period_label']) ?></span><?php endif; ?></div>
        <div class="card-body">
            <div class="alert alert-info py-2"><strong>Jde pouze o náhled.</strong> Nic se neodesílá a odběr zatím nelze zapnout.</div>
            <?php if ($weeklyError !== ''): ?><div class="alert alert-warning"><?= familyPageH($weeklyError) ?></div>
            <?php elseif ($weeklySummary !== null): ?>
                <div class="d-flex justify-content-between gap-2 mb-3"><a class="btn btn-sm btn-outline-secondary" href="?week=<?= urlencode($weeklyPrevious) ?>#tydenni-souhrn">← Předchozí týden</a><a class="btn btn-sm btn-outline-secondary" href="?week=<?= urlencode($weeklyNext) ?>#tydenni-souhrn">Další týden →</a></div>
                <div class="d-flex flex-wrap gap-2 mb-3"><span class="badge text-bg-primary">Tréninky <?= (int)$weeklySummary['counts']['training'] ?></span><span class="badge text-bg-success">Akce <?= (int)$weeklySummary['counts']['event'] ?></span><span class="badge text-bg-success">Rezervace <?= (int)$weeklySummary['counts']['reservation'] ?></span><span class="badge text-bg-warning">Splatnosti <?= (int)$weeklySummary['counts']['charge'] ?></span></div>
                <dl class="row small"><dt class="col-sm-2">Předmět</dt><dd class="col-sm-10"><strong><?= familyPageH($weeklySummary['subject']) ?></strong></dd></dl>
                <pre class="bg-light border rounded p-3 mb-0" style="white-space:pre-wrap"><?= familyPageH($weeklySummary['body']) ?></pre>
            <?php endif; ?>
        </div>
    </section>

    <section class="card border-0 shadow-sm mb-4" id="rodinny-kalendar">
        <div class="card-header bg-white"><strong><i class="bi bi-calendar3 me-2 text-primary"></i>Rodinný kalendář v telefonu</strong></div>
        <div class="card-body">
            <p class="mb-2">Přidejte si do telefonu osobní kalendář tréninků, přihlášených akcí, rezervací a splatností za všechny schválené profily.</p>
            <p class="small text-muted">Odkaz funguje jako soukromý klíč. Neposílejte ho dalším lidem. Při podezření na sdílení vytvořte nový nebo jej zrušte.</p>
            <?php if ($calendarMessage !== ''): ?><div class="alert alert-success py-2"><?= familyPageH($calendarMessage) ?></div><?php endif; ?>
            <?php if ($calendarError !== ''): ?><div class="alert alert-danger py-2"><?= familyPageH($calendarError) ?></div><?php endif; ?>
            <?php if ($calendarUrl !== ''): ?>
                <div class="alert alert-warning">
                    <strong>Odkaz se zobrazuje pouze nyní.</strong> Zkopírujte jej do aplikace Kalendář jako odebíraný kalendář.
                    <label for="family-calendar-url" class="visually-hidden">Soukromý odkaz kalendáře</label>
                    <input id="family-calendar-url" class="form-control mt-2" type="text" readonly value="<?= familyPageH($calendarUrl) ?>" onclick="this.select()">
                </div>
            <?php endif; ?>
            <?php if ($calendarState !== null && (int)$calendarState['active'] === 1): ?>
                <p class="small mb-3">Kalendář je aktivní. Kontrolní konec odkazu: <code>…<?= familyPageH($calendarState['token_hint']) ?></code></p>
                <div class="d-flex gap-2 flex-wrap">
                    <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="family_calendar_issue"><button class="btn btn-outline-primary btn-sm">Vytvořit nový odkaz</button></form>
                    <form method="post" onsubmit="return confirm('Opravdu zrušit odběr rodinného kalendáře?');"><?= csrf_field() ?><input type="hidden" name="action" value="family_calendar_revoke"><button class="btn btn-outline-danger btn-sm">Zrušit odkaz</button></form>
                </div>
            <?php else: ?>
                <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="family_calendar_issue"><button class="btn btn-primary">Vytvořit soukromý odkaz</button></form>
            <?php endif; ?>
        </div>
    </section>

    <section class="card border-0 shadow-sm mb-4" id="pripominky-plateb">
        <div class="card-header bg-white"><strong><i class="bi bi-bell me-2 text-warning"></i>Připomínky klubových plateb</strong></div>
        <div class="card-body">
            <p class="mb-2">Dobrovolně si zapněte e-mail před splatností členského předpisu. Odkaz v e-mailu vede pouze na přihlášení a neobsahuje jméno dítěte ani identifikátor platby.</p>
            <p class="small text-muted">Nejvýše jedna připomínka za 20 hodin na jeden účet. Každý předpis se zařadí jen jednou a před odesláním se znovu kontroluje jeho stav.</p>
            <?php if ($reminderMessage !== ''): ?><div class="alert alert-success py-2"><?= familyPageH($reminderMessage) ?></div><?php endif; ?>
            <?php if ($reminderError !== ''): ?><div class="alert alert-danger py-2"><?= familyPageH($reminderError) ?></div><?php endif; ?>
            <form method="post" class="row g-2 align-items-end">
                <?= csrf_field() ?><input type="hidden" name="action" value="member_charge_reminder_save">
                <div class="col-sm-5">
                    <label for="reminder-enabled" class="form-label">E-mailové připomínky</label>
                    <select id="reminder-enabled" name="enabled" class="form-select">
                        <option value="0" <?= !$reminderPreference['enabled'] ? 'selected' : '' ?>>Vypnuté</option>
                        <option value="1" <?= $reminderPreference['enabled'] ? 'selected' : '' ?>>Zapnuté</option>
                    </select>
                </div>
                <div class="col-sm-4">
                    <label for="reminder-days" class="form-label">Připomenout předem</label>
                    <select id="reminder-days" name="days_before" class="form-select">
                        <?php foreach ([3, 7, 14] as $days): ?><option value="<?= $days ?>" <?= $reminderPreference['days_before'] === $days ? 'selected' : '' ?>><?= $days ?> dní</option><?php endforeach; ?>
                    </select>
                </div>
                <div class="col-sm-3"><button class="btn btn-outline-primary w-100">Uložit nastavení</button></div>
            </form>
            <?php if ($reminderSummary['pending'] + $reminderSummary['processing'] + $reminderSummary['sent'] + $reminderSummary['failed'] > 0): ?>
                <p class="small text-muted mt-3 mb-0">Připravené: <?= $reminderSummary['pending'] ?> · zpracovává se: <?= $reminderSummary['processing'] ?> · odeslané: <?= $reminderSummary['sent'] ?><?php if ($reminderSummary['failed'] > 0): ?> · neúspěšné: <?= $reminderSummary['failed'] ?><?php endif; ?></p>
            <?php endif; ?>
        </div>
    </section>

    <?php foreach ($overview as $profile): $person = $profile['person']; ?>
        <section class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white d-flex justify-content-between align-items-start flex-wrap gap-2">
                <div><strong class="fs-5"><?= familyPageH($person['jmeno'] . ' ' . $person['prijmeni']) ?></strong><div class="small text-muted">Datum narození: <?= familyPageH($person['narozeni'] ?: 'neuvedeno') ?></div></div>
                <div><?php foreach ($person['relation_roles'] as $role): ?><span class="badge text-bg-primary ms-1"><?= familyPageH($roleLabels[$role] ?? $role) ?></span><?php endforeach; ?></div>
            </div>
            <div class="card-body">
                <h2 class="h6">Soupisky</h2>
                <?php if ($profile['rosters'] === []): ?><p class="text-muted small">Žádná evidovaná soupiska.</p><?php else: ?>
                <div class="table-responsive mb-4"><table class="table table-sm align-middle"><thead><tr><th>Tým</th><th>Sezóna</th><th>Platnost</th><th>Stav</th></tr></thead><tbody>
                <?php foreach ($profile['rosters'] as $roster): ?><tr><td><strong><?= familyPageH($roster['team_name']) ?></strong><div class="small text-muted"><?= familyPageH(trim($roster['discipline'] . ' ' . $roster['age_label'])) ?></div></td><td><?= familyPageH($roster['season_name']) ?></td><td><?= familyPageH($roster['valid_from']) ?> – <?= familyPageH($roster['valid_to'] ?: 'dosud') ?></td><td><span class="badge <?= $roster['status'] === 'active' ? 'text-bg-success' : 'text-bg-secondary' ?>"><?= familyPageH($roster['status']) ?></span></td></tr><?php endforeach; ?>
                </tbody></table></div><?php endif; ?>

                <h2 class="h6">Klubové události</h2>
                <?php if ($profile['events'] === []): ?><p class="text-muted small">Žádná přihláška na klubovou událost.</p><?php else: ?>
                <div class="table-responsive mb-4"><table class="table table-sm align-middle"><thead><tr><th>Událost</th><th>Typ</th><th>Přihlášeno</th><th>Stav</th></tr></thead><tbody>
                <?php foreach ($profile['events'] as $event): ?><tr><td><?= familyPageH($event['event_name']) ?></td><td><?= familyPageH($event['event_type']) ?></td><td><?= familyPageH($event['registered_at']) ?></td><td><?= familyPageH($event['status']) ?></td></tr><?php endforeach; ?>
                </tbody></table></div><?php endif; ?>

                <h2 class="h6">Členské předpisy</h2>
                <?php if ($profile['member_charges'] === []): ?><p class="text-muted small">Žádný členský předpis.</p><?php else: ?>
                <div class="table-responsive mb-4"><table class="table table-sm align-middle"><thead><tr><th>Předpis</th><th>Částka</th><th>Splatnost</th><th>Stav</th></tr></thead><tbody>
                <?php foreach ($profile['member_charges'] as $charge): ?><tr>
                    <td><strong><?= familyPageH($charge['title_snapshot']) ?></strong><div class="small text-muted"><code><?= familyPageH($charge['public_code']) ?></code></div></td>
                    <td><?= familyPageH(number_format(((int)$charge['amount_minor']) / 100, 2, ',', ' ') . ' ' . $charge['currency']) ?></td>
                    <td><?= familyPageH($charge['due_on'] ?: 'neuvedeno') ?></td>
                    <td><span class="badge <?= $charge['status'] === 'paid' ? 'text-bg-success' : ($charge['status'] === 'pending' ? 'text-bg-warning' : 'text-bg-secondary') ?>"><?= familyPageH(familyPageChargeStatus((string)$charge['status'])) ?></span><?php if ($charge['paid_at']): ?><div class="small text-muted">Uhrazeno <?= familyPageH(substr((string)$charge['paid_at'], 0, 10)) ?></div><?php endif; ?></td>
                </tr><?php endforeach; ?>
                </tbody></table></div><?php endif; ?>

                <h2 class="h6">Docházka na tréninky</h2>
                <?php if ($profile['trainings'] === []): ?><p class="text-muted small mb-0">Žádná zaznamenaná účast.</p><?php else: ?>
                <div class="table-responsive"><table class="table table-sm align-middle mb-0"><thead><tr><th>Datum</th><th>Náplň</th><th>Kategorie</th><th>Délka</th></tr></thead><tbody>
                <?php foreach ($profile['trainings'] as $training): ?><tr><td><?= familyPageH($training['datum']) ?></td><td><?= familyPageH($training['napln']) ?></td><td><?= familyPageH($training['kategorie']) ?></td><td><?= familyPageH($training['delka']) ?> h</td></tr><?php endforeach; ?>
                </tbody></table></div><?php endif; ?>
            </div>
        </section>
    <?php endforeach; ?>

    <section class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white"><strong>Rodinné platby za klubové služby</strong></div>
        <div class="card-body p-0">
            <?php if ($familyOrderItems === []): ?>
                <p class="text-muted small p-3 mb-0">Zatím zde není žádná objednávková položka přiřazená konkrétnímu sportovci.</p>
            <?php else: ?>
                <div class="table-responsive"><table class="table table-sm align-middle mb-0">
                    <thead><tr><th>Objednávka</th><th>Příjemce</th><th>Položka</th><th>Částka</th><th>Platba</th></tr></thead>
                    <tbody><?php foreach ($familyOrderItems as $item): ?><tr>
                        <td><code><?= familyPageH($item['public_code']) ?></code></td>
                        <td><?= familyPageH($item['beneficiary_first_name'] . ' ' . $item['beneficiary_last_name']) ?></td>
                        <td><?= familyPageH($item['product_name_snapshot']) ?></td>
                        <td><?= familyPageH(number_format(((int)$item['line_amount_minor']) / 100, 2, ',', ' ') . ' ' . $item['currency']) ?></td>
                        <td><?= familyPageH($item['payment_status']) ?></td>
                    </tr><?php endforeach; ?></tbody>
                </table></div>
            <?php endif; ?>
        </div>
    </section>
</main>
</body>
</html>
