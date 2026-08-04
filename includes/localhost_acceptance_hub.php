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
