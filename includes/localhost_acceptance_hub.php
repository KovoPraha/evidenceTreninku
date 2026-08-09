<?php
declare(strict_types=1);

/**
 * M1 acceptance hub security and scenario catalogue.
 *
 * The hub is intentionally stricter than the application's general local-mode
 * detection: the HTTP host, server address and client address must all be a
 * loopback value. An explicitly configured APP_HOST must be loopback as well.
 */
function localhostAcceptanceNormalizeHost(string $value): ?string
{
    $value = strtolower(trim($value));
    if ($value === '' || str_contains($value, '/') || str_contains($value, ',') || str_contains($value, '@')) {
        return null;
    }

    if ($value[0] === '[') {
        if (preg_match('/^\[([^\]]+)\](?::[0-9]{1,5})?$/D', $value, $match) !== 1) {
            return null;
        }
        return $match[1];
    }

    if (substr_count($value, ':') === 1 && preg_match('/^([^:]+):[0-9]{1,5}$/D', $value, $match) === 1) {
        return $match[1];
    }

    return $value;
}

function localhostAcceptanceIsLoopbackHost(string $value): bool
{
    return in_array(localhostAcceptanceNormalizeHost($value), ['localhost', '127.0.0.1', '::1'], true);
}

/** @param array<string,mixed> $server */
function localhostAcceptanceRequestIsAllowed(array $server, mixed $configuredHost): bool
{
    if (is_string($configuredHost) && trim($configuredHost) !== '' && !localhostAcceptanceIsLoopbackHost($configuredHost)) {
        return false;
    }

    foreach (['HTTP_HOST', 'SERVER_ADDR', 'REMOTE_ADDR'] as $key) {
        if (!isset($server[$key]) || !is_string($server[$key]) || !localhostAcceptanceIsLoopbackHost($server[$key])) {
            return false;
        }
    }

    return true;
}

function localhostAcceptanceTestCustomerEmail(): string
{
    return 'rodic@localhost.test';
}

/**
 * @param array<string,mixed> $server
 * @return array{id:int,email:string,created:bool}
 */
function localhostAcceptanceResetTestCustomer(
    PDO $pdo,
    string $password,
    array $server,
    mixed $configuredHost
): array {
    if (!localhostAcceptanceRequestIsAllowed($server, $configuredHost)) {
        throw new RuntimeException('Testovacího zákazníka lze obnovit pouze z loopbacku.');
    }
    $passwordLength = mb_strlen($password, 'UTF-8');
    if ($passwordLength < 12 || $passwordLength > 128) {
        throw new InvalidArgumentException('Testovací heslo musí mít 12 až 128 znaků.');
    }

    $email = localhostAcceptanceTestCustomerEmail();
    $pdo->beginTransaction();
    try {
        $sql = 'SELECT id,trener_id FROM verejni_uzivatele WHERE LOWER(email)=?';
        if ((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') {
            $sql .= ' FOR UPDATE';
        }
        $statement = $pdo->prepare($sql);
        $statement->execute([$email]);
        $account = $statement->fetch(PDO::FETCH_ASSOC);
        $created = false;
        if ($account) {
            if ($account['trener_id'] !== null) {
                throw new RuntimeException('Testovací e-mail je propojený s trenérem a nelze jej bezpečně resetovat.');
            }
            $accountId = (int)$account['id'];
            $pdo->prepare(
                'UPDATE verejni_uzivatele SET jmeno=?,prijmeni=?,heslo_hash=?,email_overeno=1,aktivni=1,'
                . 'verifikacni_token=NULL,verifikacni_token_expires_at=NULL,session_version=session_version+1 '
                . 'WHERE id=? AND trener_id IS NULL'
            )->execute(['Testovací', 'Rodič', password_hash($password, PASSWORD_DEFAULT), $accountId]);
        } else {
            $pdo->prepare(
                'INSERT INTO verejni_uzivatele '
                . '(jmeno,prijmeni,email,heslo_hash,email_overeno,aktivni,session_version,trener_id) '
                . 'VALUES (?,?,?,?,1,1,1,NULL)'
            )->execute(['Testovací', 'Rodič', $email, password_hash($password, PASSWORD_DEFAULT)]);
            $accountId = (int)$pdo->lastInsertId();
            $created = true;
        }
        if ($accountId < 1) {
            throw new RuntimeException('Testovací zákaznický účet se nepodařilo bezpečně uložit.');
        }
        $pdo->commit();
        return ['id' => $accountId, 'email' => $email, 'created' => $created];
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $exception;
    }
}

/** @return array{available:bool,reason:string} */
function localhostAcceptanceSeedResetAvailability(string $root): array
{
    if (!function_exists('proc_open')) {
        return ['available' => false, 'reason' => 'Spouštění lokálního procesu není v PHP dostupné.'];
    }
    if (localhostAcceptanceCliBinary($root) === null) {
        return ['available' => false, 'reason' => 'PHP nemá dostupnou bezpečnou cestu ke svému CLI programu.'];
    }
    if (!is_file($root . '/bin/seed-local-demo.php')) {
        return ['available' => false, 'reason' => 'Lokální seed skript nebyl nalezen.'];
    }
    if (!is_file($root . '/config.php')) {
        return ['available' => false, 'reason' => 'Chybí lokální config.php potřebný pro bezpečný seed.'];
    }

    return ['available' => true, 'reason' => 'Opakovatelné demo lze bezpečně obnovit.'];
}

function localhostAcceptanceCliBinary(?string $root = null): ?string
{
    $suffix = DIRECTORY_SEPARATOR === '\\' ? '.exe' : '';
    $candidates = [
        rtrim((string)PHP_BINDIR, '/\\') . DIRECTORY_SEPARATOR . 'php' . $suffix,
        PHP_BINARY,
    ];
    if ($root !== null && DIRECTORY_SEPARATOR === '\\') {
        // XAMPP's Apache module may report httpd.exe as PHP_BINARY and a stale
        // compile-time PHP_BINDIR. Derive php.exe only from the already trusted
        // application root: <xampp>/htdocs/<app> -> <xampp>/php/php.exe.
        $candidates[] = dirname(dirname($root)) . DIRECTORY_SEPARATOR . 'php' . DIRECTORY_SEPARATOR . 'php.exe';
    }
    foreach (array_unique($candidates) as $candidate) {
        $basename = strtolower(pathinfo($candidate, PATHINFO_FILENAME));
        if ($basename === 'php' && is_file($candidate)) {
            return $candidate;
        }
    }
    return null;
}

/** @return array{ok:bool,reason:string} */
function localhostAcceptanceRunSeedReset(string $root, int $timeoutSeconds = 45): array
{
    $availability = localhostAcceptanceSeedResetAvailability($root);
    if (!$availability['available']) {
        return ['ok' => false, 'reason' => $availability['reason']];
    }
    if ($timeoutSeconds < 1 || $timeoutSeconds > 120) {
        return ['ok' => false, 'reason' => 'Neplatný časový limit obnovy.'];
    }

    $cliBinary = localhostAcceptanceCliBinary($root);
    if ($cliBinary === null) {
        return ['ok' => false, 'reason' => 'PHP nemá dostupnou bezpečnou cestu ke svému CLI programu.'];
    }
    $environment = getenv();
    if (!is_array($environment)) {
        $environment = [];
    }
    $environment['APP_HOST'] = 'localhost';
    $descriptorSpec = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];

    try {
        $process = proc_open(
            [$cliBinary, $root . '/bin/seed-local-demo.php'],
            $descriptorSpec,
            $pipes,
            $root,
            $environment,
            ['bypass_shell' => true]
        );
    } catch (Throwable $exception) {
        error_log('localhost acceptance seed reset could not start: ' . $exception->getMessage());
        return ['ok' => false, 'reason' => 'Obnovu se nepodařilo bezpečně spustit.'];
    }
    if (!is_resource($process)) {
        return ['ok' => false, 'reason' => 'Obnovu se nepodařilo bezpečně spustit.'];
    }

    fclose($pipes[0]);
    stream_set_blocking($pipes[1], false);
    stream_set_blocking($pipes[2], false);
    $deadline = microtime(true) + $timeoutSeconds;
    $exitCode = -1;
    $timedOut = false;
    do {
        // The seed prints demo credentials as JSON. Drain and intentionally discard
        // both streams so no credential can reach the browser or session flash.
        stream_get_contents($pipes[1]);
        stream_get_contents($pipes[2]);
        $status = proc_get_status($process);
        if (!$status['running']) {
            $exitCode = (int)$status['exitcode'];
            break;
        }
        if (microtime(true) >= $deadline) {
            $timedOut = true;
            proc_terminate($process);
            break;
        }
        usleep(50000);
    } while (true);

    stream_get_contents($pipes[1]);
    stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $closeCode = proc_close($process);
    if ($exitCode < 0 && $closeCode >= 0) {
        $exitCode = $closeCode;
    }

    if ($timedOut) {
        return ['ok' => false, 'reason' => 'Obnova překročila časový limit a byla ukončena.'];
    }
    if ($exitCode !== 0) {
        return ['ok' => false, 'reason' => 'Lokální demo se nepodařilo obnovit. Zkontrolujte serverový log.'];
    }

    return ['ok' => true, 'reason' => 'Lokální demo bylo bezpečně obnoveno.'];
}

/**
 * @return list<array{
 *   id:string, role:string, area:string, status:string, steps:list<string>,
 *   expected:string, note:string, links:list<array{label:string,path:string,scope:string}>
 * }>
 */
function localhostAcceptanceScenarios(string $root): array
{
    $definitions = [
        [
            'id' => 'A01', 'role' => 'Rodič', 'area' => 'Zákaznická část', 'declared_status' => 'ready',
            'steps' => ['Přihlaste se zákaznickým účtem rodiče.', 'Otevřete Moje osoby a následně Sportovní přehled.', 'Zkontrolujte obě spravované děti.'],
            'expected' => 'Rodič vidí právě dvě vlastní děti a žádnou cizí osobu.',
            'note' => 'Použijte přihlašovací údaje z lokálního seedu; rozcestník je z bezpečnostních důvodů nezobrazuje.',
            'links' => [
                ['label' => 'Přihlášení zákazníka', 'path' => 'booking/prihlaseni.php', 'scope' => 'customer'],
                ['label' => 'Moje osoby', 'path' => 'booking/moje_osoby.php', 'scope' => 'customer'],
                ['label' => 'Sportovní přehled', 'path' => 'booking/sportovni_prehled.php', 'scope' => 'customer'],
            ],
        ],
        [
            'id' => 'A02', 'role' => 'Dítě', 'area' => 'Sportovní část', 'declared_status' => 'ready',
            'steps' => ['Přihlaste se samostatným účtem sportovce.', 'Otevřete Můj sport.', 'Ověřte, že nejsou dostupné údaje sourozence ani rodiče.'],
            'expected' => 'Dítě vidí jen vlastní tréninky, platby, události a soupisky.',
            'note' => 'Seed vytváří jeden omezený účet; jeho login neobsahuje rodinné ani administrační mutace.',
            'links' => [
                ['label' => 'Přihlášení sportovce', 'path' => 'booking/sportovec_prihlaseni.php', 'scope' => 'customer'],
                ['label' => 'Můj sport', 'path' => 'booking/muj_sport.php', 'scope' => 'customer'],
            ],
        ],
        [
            'id' => 'A03', 'role' => 'Rodič', 'area' => 'Zákaznická část', 'declared_status' => 'ready',
            'steps' => ['V e-shopu vložte testovací kroužek do košíku.', 'Jako příjemce vyberte první dítě.', 'Dokončete objednávku převodem a otevřete její detail.'],
            'expected' => 'Objednávka patří rodiči, služba prvnímu dítěti a zobrazí se testovací QR označené NEPLATIT.',
            'note' => 'Nevybírejte druhé dítě a testovací QR nikdy skutečně neplaťte.',
            'links' => [
                ['label' => 'E-shop', 'path' => 'booking/eshop.php', 'scope' => 'customer'],
                ['label' => 'Moje objednávky', 'path' => 'booking/moje_objednavky.php', 'scope' => 'customer'],
            ],
        ],
        [
            'id' => 'A04', 'role' => 'Administrátor', 'area' => 'Administrace', 'declared_status' => 'ready',
            'steps' => ['V objednávkách najděte právě vytvořenou testovací objednávku.', 'Ručně potvrďte úhradu s jasným testovacím důvodem.', 'Zkontrolujte program a soupisku příjemce.'],
            'expected' => 'Vznikne právě jedna aktivní účast a jedno členství ve správné školní soupisce.',
            'note' => 'Přepnutí role proveďte v samostatném anonymním okně, aby se zákaznická a administrátorská relace nemíchaly.',
            'links' => [
                ['label' => 'Objednávky', 'path' => 'eshop_orders_admin.php', 'scope' => 'admin'],
                ['label' => 'Programy', 'path' => 'club_programs_admin.php', 'scope' => 'admin'],
                ['label' => 'Soupisky', 'path' => 'kis_rosters_admin.php', 'scope' => 'admin'],
            ],
        ],
        [
            'id' => 'A05', 'role' => 'Administrátor', 'area' => 'Administrace', 'declared_status' => 'ready',
            'steps' => ['V průvodci vyberte existujícího sportovce z kroužku a cílovou závodní soupisku.', 'Zkontrolujte náhled bez zápisu.', 'Proveďte potvrzený přechod s důvodem pouze nad demo daty.'],
            'expected' => 'Sportovec zůstane stejnou osobou a získá závodní členství a věkovou soupisku.',
            'note' => 'Průvodce zachovává identitu osoby; ukončení kroužkové soupisky je volitelné a všechny změny jsou auditované.',
            'links' => [
                ['label' => 'Průvodce přechodem', 'path' => 'kis_transition_admin.php', 'scope' => 'admin'],
                ['label' => 'Soupisky a rollover', 'path' => 'kis_rosters_admin.php', 'scope' => 'admin'],
            ],
        ],
        [
            'id' => 'A06', 'role' => 'Administrátor', 'area' => 'Administrace', 'declared_status' => 'ready',
            'steps' => ['Vyberte demo věkovou soupisku a cílovou kalendářní sezonu.', 'Nejprve zkontrolujte náhled a fingerprint.', 'Teprve potom potvrďte provedení s důvodem.'],
            'expected' => 'Věkový člen se přesune, disciplína se přenese, výjimka zůstane a opakování nic nezdvojí.',
            'note' => 'Proveďte pouze nad soupiskami LOCALHOST; změna je auditovaná a není to pouhá simulace.',
            'links' => [
                ['label' => 'Průvodce A06', 'path' => 'kis_rollover_a06_admin.php', 'scope' => 'admin'],
                ['label' => 'Soupisky a rollover', 'path' => 'kis_rosters_admin.php', 'scope' => 'admin'],
            ],
        ],
        [
            'id' => 'A07', 'role' => 'Trenér', 'area' => 'Administrace', 'declared_status' => 'ready',
            'steps' => ['Otevřete testovací plánovaný trénink.', 'Zkontrolujte cílové soupisky a očekávané účastníky.', 'Zapište skutečnou docházku a ověřte ji ve sportovním přehledu.'],
            'expected' => 'Očekávaní účastníci pocházejí ze soupisek a skutečná docházka zůstává v Evidenci.',
            'note' => 'Používejte jen položky označené LOCALHOST TEST.',
            'links' => [
                ['label' => 'Průvodce A07', 'path' => 'kis_training_a07_admin.php', 'scope' => 'admin'],
                ['label' => 'Plánovač tréninků', 'path' => 'planovac.php', 'scope' => 'admin'],
                ['label' => 'Sportovní přehled', 'path' => 'booking/sportovni_prehled.php', 'scope' => 'customer'],
            ],
        ],
        [
            'id' => 'A08', 'role' => 'Rodič', 'area' => 'Zákaznická část', 'declared_status' => 'ready',
            'steps' => ['Otevřete cílené klubové události.', 'Vyberte oprávněné dítě s členstvím ve dvou cílových soupiskách.', 'Přihlášku odešlete jen jednou.'],
            'expected' => 'Vznikne právě jedna přihláška oprávněného dítěte, i když podmínku splňuje přes dvě soupisky.',
            'note' => 'Neoprávněné dítě musí být odmítnuto a poslední místo může přejít na čekací listinu.',
            'links' => [
                ['label' => 'Klubové události', 'path' => 'booking/krouzky.php', 'scope' => 'customer'],
            ],
        ],
        [
            'id' => 'A09', 'role' => 'Veřejnost', 'area' => 'Zákaznická část', 'declared_status' => 'ready',
            'steps' => ['Zaregistrujte nový testovací účet s datem narození.', 'Zkontrolujte veřejný profil.', 'Rezervujte bezplatný nebo placený demo slot velodromu.'],
            'expected' => 'Vznikne jeden profil sportovce a jedna kapacitně chráněná rezervace stejné osoby.',
            'note' => 'Bezplatný slot se potvrzuje přímo; placený slot pokračuje přes standardní shop objednávku a testovací QR.',
            'links' => [
                ['label' => 'Registrace', 'path' => 'booking/registrace.php', 'scope' => 'customer'],
                ['label' => 'Veřejný profil', 'path' => 'booking/verejny_profil.php', 'scope' => 'customer'],
                ['label' => 'Velodrom', 'path' => 'booking/velodrom.php', 'scope' => 'customer'],
            ],
        ],
        [
            'id' => 'A10', 'role' => 'Administrátor', 'area' => 'Administrace', 'declared_status' => 'ready',
            'steps' => ['Vyhledejte jednu demo osobu v auditní časové ose.', 'Porovnejte události účtu, objednávky, programu a soupisky.', 'U rezervace zkontrolujte historii ručního potvrzení nebo storna.'],
            'expected' => 'U každé významné změny lze dohledat kdo, kdy, co a proč provedl.',
            'note' => 'Časová osa je pouze ke čtení a skládá existující audity; u zdrojů bez důvodu žádný důvod nedoplňuje.',
            'links' => [
                ['label' => 'Auditní osa osoby', 'path' => 'person_audit_admin.php', 'scope' => 'admin'],
                ['label' => 'Účty a osoby', 'path' => 'eshop_identity_admin.php', 'scope' => 'admin'],
                ['label' => 'Objednávky', 'path' => 'eshop_orders_admin.php', 'scope' => 'admin'],
                ['label' => 'Soupisky', 'path' => 'kis_rosters_admin.php', 'scope' => 'admin'],
                ['label' => 'Velodrom', 'path' => 'verejny_velodrom_admin.php', 'scope' => 'admin'],
            ],
        ],
    ];

    foreach ($definitions as &$scenario) {
        $missing = [];
        foreach ($scenario['links'] as $link) {
            if (!is_file($root . '/' . $link['path'])) {
                $missing[] = $link['label'];
            }
        }
        $scenario['status'] = $missing === [] ? $scenario['declared_status'] : 'unavailable';
        if ($missing !== []) {
            $scenario['note'] .= ' Chybí cesta: ' . implode(', ', $missing) . '.';
        }
        unset($scenario['declared_status']);
    }
    unset($scenario);

    return $definitions;
}
/**
 * Sada B: kompletní funkční akceptace celého systému nad rámec integračních
 * scénářů A01–A10. Výsledky se ukládají stejným mechanismem; sada B nevstupuje
 * do závěrečné brány M2 (ta zůstává vyhrazena A01–A10).
 *
 * @return list<array<string,mixed>>
 */
function localhostAcceptanceScenariosB(string $root): array
{
    $definitions = [
        [
            'id' => 'B01', 'role' => 'Trenér', 'area' => 'Tréninky', 'declared_status' => 'ready',
            'steps' => ['Založte nový trénink s kategorií, délkou a docházkou.', 'Přidejte měření kolo/běh s výslovnou jednotkou (km i m) a striktním časem MM:SS.', 'Přidejte posilovací měření s číselným RPE.', 'Zkuste uložit neplatný čas („cca 2 min“) a RPE mimo 1–10.'],
            'expected' => 'Platný zápis se uloží s jednotkou viditelnou v přehledu; neplatný čas/RPE je odmítnut před uložením.',
            'note' => 'Ověřuje sports-measurement-v1 kontrakt v ostrém zadávání.',
            'links' => [
                ['label' => 'Nový trénink', 'path' => 'formular.php', 'scope' => 'trainer'],
                ['label' => 'Moje tréninky', 'path' => 'moje_treninky.php', 'scope' => 'trainer'],
            ],
        ],
        [
            'id' => 'B02', 'role' => 'Trenér', 'area' => 'Tréninky', 'declared_status' => 'ready',
            'steps' => ['Otevřete existující trénink v editaci.', 'Změňte náplň, docházku a upravte jedno měření.', 'Uložte a zkontrolujte, že se změny propsaly a žádné měření se neztratilo.'],
            'expected' => 'Editace zachová všechna měření i vazby; upravené hodnoty jsou vidět v detailu.',
            'note' => '',
            'links' => [
                ['label' => 'Správa tréninků', 'path' => 'sprava_vsech_treninku.php', 'scope' => 'trainer'],
                ['label' => 'Editace tréninku', 'path' => 'edit_trenink.php', 'scope' => 'trainer'],
            ],
        ],
        [
            'id' => 'B03', 'role' => 'Trenér', 'area' => 'Tréninky', 'declared_status' => 'ready',
            'steps' => ['Projděte výkaz činností za aktuální měsíc.', 'Stáhněte měsíční XLSX a CSV export.', 'Zkontrolujte přehled trenéra a kalendář skupiny.'],
            'expected' => 'Součty ve výkazu odpovídají zadaným tréninkům; exporty se stáhnou a jdou otevřít.',
            'note' => '',
            'links' => [
                ['label' => 'Výkaz činností', 'path' => 'vypis_vykazu.php', 'scope' => 'trainer'],
                ['label' => 'Export XLSX', 'path' => 'export_xls.php', 'scope' => 'trainer'],
                ['label' => 'Export CSV', 'path' => 'export_csv.php', 'scope' => 'trainer'],
                ['label' => 'Přehled trenéra', 'path' => 'prehled_trenera.php', 'scope' => 'trainer'],
            ],
        ],
        [
            'id' => 'B04', 'role' => 'Trenér', 'area' => 'Závody', 'declared_status' => 'ready',
            'steps' => ['Založte závod s kategorií a URL výsledků.', 'Přidejte klubového i externího závodníka s pořadím a časem.', 'Přidejte měření k závodu.', 'Otevřete detail a poté závod editujte.'],
            'expected' => 'Detail ukazuje interní i externí výsledky, měření a soubory; editace nezničí historii.',
            'note' => '',
            'links' => [
                ['label' => 'Nový závod', 'path' => 'formular_zavod.php', 'scope' => 'trainer'],
                ['label' => 'Detail závodu', 'path' => 'zavod_detail.php', 'scope' => 'trainer'],
                ['label' => 'Editace závodu', 'path' => 'edit_zavod_form.php', 'scope' => 'trainer'],
                ['label' => 'Přehled závodů', 'path' => 'prehled_zavodu.php', 'scope' => 'trainer'],
            ],
        ],
        [
            'id' => 'B05', 'role' => 'Trenér', 'area' => 'Plánování a rezervace', 'declared_status' => 'ready',
            'steps' => ['Vytvořte plánovaný trénink s více podskupinami a opakováním.', 'V plánovači přesuňte plán drag&drop a přejmenujte ho dvojklikem.', 'Zkopírujte týden a poté sérii zrušte.'],
            'expected' => 'Plány se chovají podle filtru Moje/Vše, kopie týdne nekopíruje rezervace a série jde zrušit celá.',
            'note' => '',
            'links' => [
                ['label' => 'Nový plán', 'path' => 'planovany_trenink_form.php', 'scope' => 'trainer'],
                ['label' => 'Plánovač', 'path' => 'planovac.php', 'scope' => 'trainer'],
            ],
        ],
        [
            'id' => 'B06', 'role' => 'Trenér', 'area' => 'Plánování a rezervace', 'declared_status' => 'ready',
            'steps' => ['Vytvořte interní rezervaci sportoviště se sidebarem denního rozvrhu.', 'Vypište individuální lekci (zelenou i žlutou) s kapacitou.', 'Ve správě lekcí jednu potvrďte a jednu zamítněte.'],
            'expected' => 'Kalendář sportovišť ukazuje kapacitu X/5, lekce mají správné stavy a zamítnutí nabídne slot čekací listině.',
            'note' => '',
            'links' => [
                ['label' => 'Kalendář sportovišť', 'path' => 'kalendar_sportovist.php', 'scope' => 'trainer'],
                ['label' => 'Nová rezervace', 'path' => 'rezervovat_sportoviste.php', 'scope' => 'trainer'],
                ['label' => 'Nová lekce', 'path' => 'individualni_lekce_form.php', 'scope' => 'trainer'],
                ['label' => 'Správa lekcí', 'path' => 'individualni_lekce_sprava.php', 'scope' => 'trainer'],
            ],
        ],
        [
            'id' => 'B07', 'role' => 'Zákazník', 'area' => 'Plánování a rezervace', 'declared_status' => 'ready',
            'steps' => ['Ve veřejném kalendáři rezervujte volný slot zelené lekce.', 'U plné lekce se zapište na čekací listinu.', 'Stornujte aktivní rezervaci a sledujte posun z listiny.'],
            'expected' => 'Zelená lekce je potvrzená ihned, čekací listina ukazuje pořadí a po stornu se první čekající posune.',
            'note' => '',
            'links' => [
                ['label' => 'Veřejný kalendář', 'path' => 'booking/kalendar.php', 'scope' => 'customer'],
                ['label' => 'Moje rezervace', 'path' => 'booking/moje_rezervace.php', 'scope' => 'customer'],
            ],
        ],
        [
            'id' => 'B08', 'role' => 'Administrátor', 'area' => 'E-shop', 'declared_status' => 'ready',
            'steps' => ['Naimportujte Shoptet export (dry-run → staging).', 'V administraci vyřiďte ruční kontroly a potvrďte převod běhu.', 'V Aktivaci katalogu zveřejněte vybrané produkty.'],
            'expected' => 'Produkty projdou stavem kandidát → draft → aktivní a objeví se ve veřejném katalogu.',
            'note' => 'Na produkci se import spouští nástrojem kis-shoptet-import.ps1.',
            'links' => [
                ['label' => 'Kontrola importu', 'path' => 'eshop_admin.php', 'scope' => 'trainer'],
                ['label' => 'Aktivace katalogu', 'path' => 'eshop_catalog_publication_admin.php', 'scope' => 'trainer'],
                ['label' => 'Veřejný katalog', 'path' => 'booking/eshop.php', 'scope' => 'customer'],
            ],
        ],
        [
            'id' => 'B09', 'role' => 'Zákazník + administrátor', 'area' => 'E-shop', 'declared_status' => 'ready',
            'steps' => ['Vložte zboží do košíku a dokončete objednávku.', 'Zkontrolujte QR/SPD platební předpis a stav skladu.', 'Jako administrátor ručně potvrďte platbu.', 'Přepněte objednávku na připraveno a osobně vydáno.'],
            'expected' => 'Sklad se sníží při objednávce, platba změní stav právě jednou a výdej uzavře objednávku.',
            'note' => '',
            'links' => [
                ['label' => 'Katalog a košík', 'path' => 'booking/eshop.php', 'scope' => 'customer'],
                ['label' => 'Objednávka', 'path' => 'booking/objednavka.php', 'scope' => 'customer'],
                ['label' => 'Objednávky K4', 'path' => 'eshop_orders_admin.php', 'scope' => 'trainer'],
            ],
        ],
        [
            'id' => 'B10', 'role' => 'Zákazník + administrátor', 'area' => 'E-shop', 'declared_status' => 'ready',
            'steps' => ['Stornujte nezaplacenou objednávku a ověřte vrácení skladu.', 'U zaplacené objednávky proveďte storno se stavem refund_required.', 'Potvrďte bankovní vratku s referencí.'],
            'expected' => 'Sklad se vrátí právě jednou, vratka jde potvrdit jen jednou a zákazník vidí stav refunded.',
            'note' => '',
            'links' => [
                ['label' => 'Moje objednávky', 'path' => 'booking/moje_objednavky.php', 'scope' => 'customer'],
                ['label' => 'Objednávky K4', 'path' => 'eshop_orders_admin.php', 'scope' => 'trainer'],
            ],
        ],
        [
            'id' => 'B11', 'role' => 'Zákazník + administrátor', 'area' => 'E-shop', 'declared_status' => 'ready',
            'steps' => ['Založte kupón s omezením jen na zboží a minimem košíku.', 'Zkuste ho uplatnit na samotný kroužek (má selhat).', 'Uplatněte ho na zboží a zkontrolujte snapshot slevy v objednávce.'],
            'expected' => 'Rozsah kupónu se vynucuje na serveru; sleva se počítá ze způsobilého mezisoučtu.',
            'note' => '',
            'links' => [
                ['label' => 'Správa kupónů', 'path' => 'eshop_coupons_admin.php', 'scope' => 'trainer'],
                ['label' => 'Katalog a košík', 'path' => 'booking/eshop.php', 'scope' => 'customer'],
            ],
        ],
        [
            'id' => 'B12', 'role' => 'Rodič', 'area' => 'E-shop', 'declared_status' => 'ready',
            'steps' => ['Jako nepřihlášený ověřte veřejnou cenu produktu.', 'Přihlaste se rodičem se členem na soupisce s klubovou cenou.', 'Zkontrolujte zvýhodněnou cenu v katalogu, košíku i objednávce.'],
            'expected' => 'Klubová cena se odvozuje ze soupisek rodiny a checkout ukládá neměnný snapshot.',
            'note' => '',
            'links' => [
                ['label' => 'Klubové ceny', 'path' => 'eshop_member_prices_admin.php', 'scope' => 'trainer'],
                ['label' => 'Veřejný katalog', 'path' => 'booking/eshop.php', 'scope' => 'customer'],
            ],
        ],
        [
            'id' => 'B13', 'role' => 'Zákazník', 'area' => 'E-shop', 'declared_status' => 'ready',
            'steps' => ['Otevřete detail produktu s více variantami.', 'Přepněte varianty a zkontrolujte cenu, sklad a obrázky.', 'Vložte konkrétní variantu do košíku.'],
            'expected' => 'Detail ukazuje jen aktivní publikované varianty, obrázky se načítají přes HTTPS a bez referreru.',
            'note' => '',
            'links' => [
                ['label' => 'Detail produktu', 'path' => 'booking/produkt.php', 'scope' => 'customer'],
            ],
        ],
        [
            'id' => 'B14', 'role' => 'Rodič', 'area' => 'Kroužky a programy', 'declared_status' => 'ready',
            'steps' => ['Přihlaste schválené dítě na bezplatný kroužek.', 'Zkuste totéž dítě přihlásit podruhé (má selhat).', 'Přihlášku stornujte a ověřte uvolnění kapacity.'],
            'expected' => 'Kapacita se drží transakčně, duplicitní aktivní přihláška nevznikne a storno kapacitu vrátí.',
            'note' => '',
            'links' => [
                ['label' => 'Kroužky a události', 'path' => 'booking/krouzky.php', 'scope' => 'customer'],
                ['label' => 'Moje kroužky', 'path' => 'booking/moje_programy.php', 'scope' => 'customer'],
            ],
        ],
        [
            'id' => 'B15', 'role' => 'Rodič + administrátor', 'area' => 'Kroužky a programy', 'declared_status' => 'ready',
            'steps' => ['Kupte placený program pro dítě přes košík.', 'Jako administrátor potvrďte platbu a ověřte automatickou aktivaci účasti.', 'Proveďte storno s vratkou a ověřte deaktivaci.'],
            'expected' => 'Účast v programu se aktivuje po úhradě právě jednou a storno/refund ji auditovaně ukončí.',
            'note' => '',
            'links' => [
                ['label' => 'Správa programů', 'path' => 'club_programs_admin.php', 'scope' => 'trainer'],
                ['label' => 'Moje kroužky', 'path' => 'booking/moje_programy.php', 'scope' => 'customer'],
            ],
        ],
        [
            'id' => 'B16', 'role' => 'Administrátor', 'area' => 'Kroužky a programy', 'declared_status' => 'ready',
            'steps' => ['Vytvořte placenou událost cílenou na více soupisek s kapacitou.', 'Ověřte, že se nabízí jen oprávněným dětem.', 'Stáhněte CSV export účastníků.'],
            'expected' => 'Cílení na soupisky funguje, kapacita drží i pro košík s více dětmi a export má auditovaný kontrakt v1.',
            'note' => '',
            'links' => [
                ['label' => 'Klubové akce a soupisky', 'path' => 'eshop_events_admin.php', 'scope' => 'trainer'],
                ['label' => 'Export účastníků', 'path' => 'club_event_participants_export.php', 'scope' => 'trainer'],
            ],
        ],
        [
            'id' => 'B17', 'role' => 'Veřejnost + administrátor', 'area' => 'Kroužky a programy', 'declared_status' => 'ready',
            'steps' => ['Zkontrolujte veřejné hodiny velodromu a proveďte bezplatnou rezervaci.', 'Projděte placenou rezervaci přes objednávku a QR.', 'Ve správě velodromu upravte kapacitu hodiny.'],
            'expected' => 'Kapacita se drží od objednávky, platba potvrdí rezervaci a správa hodin je auditovaná.',
            'note' => '',
            'links' => [
                ['label' => 'Velodrom', 'path' => 'booking/velodrom.php', 'scope' => 'customer'],
                ['label' => 'Správa velodromu', 'path' => 'verejny_velodrom_admin.php', 'scope' => 'trainer'],
            ],
        ],
        [
            'id' => 'B18', 'role' => 'Veřejnost', 'area' => 'Rodina a účty', 'declared_status' => 'ready',
            'steps' => ['Zaregistrujte nový zákaznický účet a ověřte e-mail.', 'Požádejte o propojení s existujícím sportovcem.', 'Jako administrátor žádost schvalte a ověřte, že se osoba objevila v Moje osoby.'],
            'expected' => 'Registrace nevyzrazuje existující účty, claim nevyzrazuje osoby a schválení je atomické.',
            'note' => '',
            'links' => [
                ['label' => 'Registrace', 'path' => 'booking/registrace.php', 'scope' => 'customer'],
                ['label' => 'Moje osoby', 'path' => 'booking/moje_osoby.php', 'scope' => 'customer'],
            ],
        ],
        [
            'id' => 'B19', 'role' => 'Zákazník + dítě', 'area' => 'Rodina a účty', 'declared_status' => 'ready',
            'steps' => ['Vyžádejte obnovu hesla rodičovského účtu a dokončete ji.', 'Vyžádejte obnovu hesla sportovního účtu dítěte.', 'Ověřte, že staré přihlášené relace byly odhlášeny.'],
            'expected' => 'Token platí jednou a 60 minut, sportovcův odkaz dostane jen ověřený rodič/self a session se zneplatní.',
            'note' => '',
            'links' => [
                ['label' => 'Zapomenuté heslo', 'path' => 'booking/zapomenute_heslo.php', 'scope' => 'customer'],
                ['label' => 'Nové heslo', 'path' => 'booking/nove_heslo.php', 'scope' => 'customer'],
            ],
        ],
        [
            'id' => 'B20', 'role' => 'Rodič', 'area' => 'Rodina a účty', 'declared_status' => 'ready',
            'steps' => ['Vytvořte soukromý rodinný ICS odkaz a přidejte ho do kalendáře.', 'Odkaz zrotujte a ověřte, že starý přestal platit.', 'Zkontrolujte 30denní rodinný program v přehledu.'],
            'expected' => 'ICS obsahuje jen oprávněné položky rodiny, rotace zneplatní starý odkaz a program ukazuje správné děti.',
            'note' => '',
            'links' => [
                ['label' => 'Rodinný kalendář', 'path' => 'booking/rodinny_kalendar.php', 'scope' => 'customer'],
                ['label' => 'Sportovní přehled', 'path' => 'booking/sportovni_prehled.php', 'scope' => 'customer'],
            ],
        ],
        [
            'id' => 'B21', 'role' => 'Rodič', 'area' => 'Rodina a účty', 'declared_status' => 'ready',
            'steps' => ['Zapněte týdenní souhrn, prohlédněte náhled a zase ho vypněte.', 'Projděte roční přehled skutečně uhrazených plateb.', 'Ověřte, že čekající a stornované platby v přehledu nejsou.'],
            'expected' => 'Opt-in/opt-out funguje jedním krokem a roční přehled odděluje zdroje i měny bez součtů dohromady.',
            'note' => '',
            'links' => [
                ['label' => 'Sportovní přehled', 'path' => 'booking/sportovni_prehled.php', 'scope' => 'customer'],
            ],
        ],
        [
            'id' => 'B22', 'role' => 'Veřejnost', 'area' => 'Rodina a účty', 'declared_status' => 'ready',
            'steps' => ['Otevřete veřejný rozvrh tréninků a veřejný kalendář.', 'Stáhněte veřejný ICS feed a přidejte ho do kalendáře.'],
            'expected' => 'Veřejné výstupy neobsahují osoby, docházku ani interní poznámky.',
            'note' => '',
            'links' => [
                ['label' => 'Veřejné tréninky', 'path' => 'booking/treninky.php', 'scope' => 'customer'],
                ['label' => 'Veřejný kalendář', 'path' => 'booking/verejny_kalendar.php', 'scope' => 'customer'],
            ],
        ],
        [
            'id' => 'B23', 'role' => 'Administrátor', 'area' => 'KIS a členství', 'declared_status' => 'ready',
            'steps' => ['Projděte 4krokový průvodce synchronizace: nahrání tří XLSX, mapování soupisek, náhled, provedení.', 'Zkontrolujte, že osoby mimo import nebyly archivovány.'],
            'expected' => 'Preview ukazuje nové/aktualizované/beze změny a provedení běží v transakci s mapovanými vazbami.',
            'note' => '',
            'links' => [
                ['label' => 'Synchronizace evidence', 'path' => 'sync_evidence.php', 'scope' => 'trainer'],
            ],
        ],
        [
            'id' => 'B24', 'role' => 'Administrátor', 'area' => 'KIS a členství', 'declared_status' => 'ready',
            'steps' => ['V KIS centru zkontrolujte uložený preview běh a jeho fingerprint.', 'Proveďte sandbox promote a poté rollback.', 'Ověřte, že kanonická data se nezměnila.'],
            'expected' => 'Promote je transakční a idempotentní, rollback funguje i při driftu a vše je auditované.',
            'note' => '',
            'links' => [
                ['label' => 'KIS centrum', 'path' => 'kis_sync_center.php', 'scope' => 'trainer'],
            ],
        ],
        [
            'id' => 'B25', 'role' => 'Administrátor + rodič', 'area' => 'KIS a členství', 'declared_status' => 'ready',
            'steps' => ['Zkontrolujte členské předpisy v administraci.', 'Ověřte tentýž předpis v pohledu rodiče a omezeného sportovce.', 'Zkontrolujte auditní historii předpisu.'],
            'expected' => 'Předpisy jsou read-only pro rodiče/sportovce podle vazeb a administrace nemá skrytou mutaci.',
            'note' => '',
            'links' => [
                ['label' => 'Členské předpisy', 'path' => 'member_charges_admin.php', 'scope' => 'trainer'],
                ['label' => 'Sportovní přehled', 'path' => 'booking/sportovni_prehled.php', 'scope' => 'customer'],
            ],
        ],
        [
            'id' => 'B26', 'role' => 'Administrátor', 'area' => 'KIS a členství', 'declared_status' => 'ready',
            'steps' => ['Projděte správu soupisek: přidání a historické odebrání člena.', 'Zkontrolujte kartu sportovce s auditní historií.', 'Použijte hromadné akce s preview.'],
            'expected' => 'Odebrání nemaže historii, karta ukazuje časovou osu a hromadné akce běží v transakci.',
            'note' => '',
            'links' => [
                ['label' => 'Soupisky', 'path' => 'kis_rosters_admin.php', 'scope' => 'trainer'],
                ['label' => 'Karta sportovce', 'path' => 'sportovec_karta.php', 'scope' => 'trainer'],
                ['label' => 'Hromadné akce', 'path' => 'sportovci_hromadne.php', 'scope' => 'trainer'],
            ],
        ],
        [
            'id' => 'B27', 'role' => 'Administrátor + rodič', 'area' => 'KIS a členství', 'declared_status' => 'ready',
            'steps' => ['Jako rodič zapněte připomínky splatnosti (3/7/14 dní).', 'V administraci zkontrolujte frontu a náhled přesného textu.', 'Na localhostu proveďte testovací doručení do souborového outboxu.'],
            'expected' => 'Fronta respektuje opt-out a stav předpisu, náhled je no-store a testovací transport nikdy neposílá skutečný e-mail.',
            'note' => '',
            'links' => [
                ['label' => 'Připomínky plateb', 'path' => 'member_charge_reminders_admin.php', 'scope' => 'trainer'],
                ['label' => 'Sportovní přehled', 'path' => 'booking/sportovni_prehled.php', 'scope' => 'customer'],
            ],
        ],
        [
            'id' => 'B28', 'role' => 'Administrátor', 'area' => 'Administrace a bezpečnost', 'declared_status' => 'ready',
            'steps' => ['Založte testovacího trenéra a přihlaste ho ve druhém okně.', 'Odeberte mu oprávnění a ověřte okamžitou ztrátu přístupu bez nového přihlášení.', 'Vraťte nastavení zpět.'],
            'expected' => 'Oprávnění se vyhodnocují při každém požadavku; revokace platí okamžitě a fail-closed.',
            'note' => '',
            'links' => [
                ['label' => 'Správa trenérů', 'path' => 'sprava_treneru.php', 'scope' => 'trainer'],
                ['label' => 'Nastavení oprávnění', 'path' => 'nastaveni_opravneni.php', 'scope' => 'trainer'],
            ],
        ],
        [
            'id' => 'B29', 'role' => 'Administrátor', 'area' => 'Administrace a bezpečnost', 'declared_status' => 'ready',
            'steps' => ['Projděte provozní přehled a admin dashboard.', 'Zkontrolujte kvalitu sportovních dat a přípravu importu.', 'Otevřete audit log a vyfiltrujte akce jednoho trenéra.'],
            'expected' => 'Read-only přehledy odkazují do auditovaných obrazovek a audit log ukazuje skutečné akce se správnými sloupci.',
            'note' => '',
            'links' => [
                ['label' => 'Provozní přehled', 'path' => 'provozni_prehled_admin.php', 'scope' => 'trainer'],
                ['label' => 'Admin dashboard', 'path' => 'admin_dashboard.php', 'scope' => 'trainer'],
                ['label' => 'Kvalita sportovních dat', 'path' => 'sports_data_quality_admin.php', 'scope' => 'trainer'],
                ['label' => 'Příprava importu', 'path' => 'sports_import_review_admin.php', 'scope' => 'trainer'],
                ['label' => 'Audit log', 'path' => 'auditlog/seznam.php', 'scope' => 'trainer'],
            ],
        ],
        [
            'id' => 'B30', 'role' => 'Všechny role', 'area' => 'Administrace a bezpečnost', 'declared_status' => 'ready',
            'steps' => ['Jako běžný trenér ověřte, že nevidíte menu Klub/Administrace, ale vidíte své nástroje.', 'Jako admin projděte obě nová menu a odkaz Veřejný portál.', 'Jako zákazník otevřete menu Můj účet na stránce bez Bootstrap JS.', 'Vyzkoušejte tmavý režim a klávesové zkratky.'],
            'expected' => 'Navigace odpovídá roli, žádná stránka neztratila vstup a menu fungují i bez JavaScriptu.',
            'note' => 'Akceptace informační architektury po přestavbě navigace.',
            'links' => [
                ['label' => 'Nástěnka', 'path' => 'index.php', 'scope' => 'trainer'],
                ['label' => 'Veřejný portál', 'path' => 'booking/eshop.php', 'scope' => 'customer'],
            ],
        ],
    ];

    foreach ($definitions as &$scenario) {
        $missing = [];
        foreach ($scenario['links'] as $link) {
            if (!is_file($root . '/' . $link['path'])) {
                $missing[] = $link['label'];
            }
        }
        $scenario['status'] = $missing === [] ? $scenario['declared_status'] : 'unavailable';
        if ($missing !== []) {
            $scenario['note'] .= ' Chybí cesta: ' . implode(', ', $missing) . '.';
        }
        unset($scenario['declared_status']);
    }
    unset($scenario);

    return $definitions;
}
