# P11 R06 — výběr osob v docházce, 7. 11. 2026

Stav: implementace, místní srovnání a UAT HOTOVO; bez produkčního nasazení.
Vlastník: vývoj podle přímého zadání vlastníka v tomto chatu.
Base `d958fd6e` / #363, větev `codex/attendance-person-picker-20261107`, worktree
`C:/Users/Marek/Documents/Codex/evidencePavel-attendance-picker-20261107`.
Původní WIP `kis_rosters_admin.php` SHA256
`7a7afd75bfa34f736f158096ab46a671b7fee6c6faedc3701f989c0cf64733e5` zachovat.

## Cíl, volba a hranice

[Předchozí celé měření](verification/full-http-performance-20261107/RESULTS.md)
ukázalo 10221 osob ve dvou seznamech, cca 1,37 MB HTML a několik sekund
načítání docházky. Omezit seznamy na hledanou stránku a vybrané osoby.
Hledání musí zpřístupnit i hosty mimo preferovanou soupisku; oprávnění se
ověřuje čerstvě, preference není omezení přístupu. Jména a ID jsou jediné
osobní údaje nové projekce. Žádné dlouhodobé cache ani změny writeru/zámků.

Zvolený postup: nativní formulář s hledáním a stránkováním, vždy zachovat
aktuální výběr a celý rozepsaný návrh. První verze používá nativní POST hledání,
bez nového AJAX endpointu nebo skriptu.
Dohledání každé osoby i odeslání tak funguje také bez JavaScriptu. Osobní
omluva má samostatný výběr a stav, zachová se papírový původ, verze, validace,
prázdný výběr i konflikty souběhu. Pouhé skrytí 10k option v DOM nepomůže
velikosti HTML; omezení na vlastní soupisku by změnilo chování a není zvolené.

## Postup a akceptace

1. Nový vlastní localhost runtime a stejný kombinovaný seed: 52 týdnů,
   200 účastníků / 52 revizí, 10k zdravotních sledování / 30k záznamů.
   Změřit současný base na novém schématu, potom stejná data po změně;
   staré časy #363 nepoužívat jako přímou kontrolní skupinu.
2. Omezit projekci a HTML obou výběrů, zachovat vybrané osoby nezávisle na
   hledání/stránce. Žádné načtení všech jmen do JSON ani DOM.
3. Regrese hledání/stránek, preferencí a hostů, falešných ID, odebrané role,
   obou formulářů, selhaného POST, klávesnice a režimu bez JavaScriptu.
   Ověřit kanonické uložení, původ a verzi pouze na syntetických datech.
4. Stejné HTTP a Chrome cold/warm případy před/po, HTML bajty/option count,
   oddělená SQL/PHP diagnostika a shoda doménových dat po čtecím profilu.
5. Místní kontroly, doklady v `docs/verification/`, úklid vlastních služeb,
   aktualizace registru/CURRENT/handoff/logů, PR a CI bez deploye.

## Průběh a zbývající práce

Implementace zapojená: 50 výsledků + vybraní, nativní čtecí POST, obě formy.
Před/po 21 HTTP případů (9 pozitivních × 30 = 270 vzorků na variantu),
39 Chrome pozorování na variantu pro coach / sports_lead / other_coach.
9 DOM kontrol na variantu PASS, stejné doménové checksums; větší docházka
1,37 MB → 92 kB. Lokálně cílených 35 testů / 374 assertions PASS.
Oddělená HTTP diagnostika před/po: 0 DML/DDL, shoda s prostým HTTP.
SQL u trenérského formuláře 59 → 75 kvůli čerstvým kontrolám a vybraným
projekcím; zmenšuje se přenos jmen/HTML, netvrdíme pokles počtu SQL všude.
Nativní JS/noJS, 36 HTTP kontrol, revize 53 / 201 osob / paper, stale write,
osobní omluva přes UI a role/account revokace PASS. Všech 10221 osob dosažitelných
právě jednou ve 205 stránkách pro obě role. PHPStan i styl devíti PHP PASS.
Vlastní runtime uklizený/zastavený, WIP shodný. [Výsledky](verification/attendance-person-picker-20261107/RESULTS.md),
[předání](SESSION_HANDOFF_ATTENDANCE_PERSON_PICKER_2026-11-07.md).
Plán před kódem pushnutý v c0883ff2; celé předání a finální CI/merge řídí
[PR #366](https://github.com/KovoPraha/evidenceTreninku/pull/366). Žádný náš deploy.
Hledání lekcí, R13/R05, fyzický mobil, souběžná zátěž a další R01–R16
zůstávají samostatné etapy P11; toto není uzavření celého K0.2.

Volba upřesněna před zapojením: nativní POST hledání stačí k omezení HTML;
AJAX by přidal souběh požadavků a další endpoint bez nutnosti pro měřený cíl.
Změna hledání nesmí spustit writer; CSRF, actor i lekce se kontrolují standardně.
Nový base má 350 tabulek / 161 migrací kvůli #355, doménový seed je stejný
jako #363. Před/po běží na jediném společném novém schématu.

Historická CI parita R06 bude používat shodnou novou stránku a picker v obou
stromech, ale ponechá zmrazený původní validátor. Přidává se samostatná
MariaDB kontrola celé množiny, pořadí, kolací, wildcardů a čerstvé role.
Archivované before-harness soubory se nemění, přísná HTML parita se neruší.
Vybraná ID se při srovnání formulářů chápou jako množina: původní SQL nemělo
určené pořadí při identických jménech, nyní má ID jako stabilní tie-breaker.

Volbu eviduje [D2026-11-07-07](DECISION_LOG.md#d2026-11-07-07); původní pracovní ID 06 bylo před integrací sjednocené s přijatým DOC02.

Při integraci převzat main `22e1b2cb`: DOC02 katalog/pravidla, mobilní správa přístupů a záznam cizího nasazení P07 se zachovávají. Měřené R06 soubory se tím nemění; nový globální main ani produkce nebyly samostatně přeměřené.
