# Aktuální stav projektu

Aktualizováno: 9. 10. 2026, Europe/Prague

Tento soubor je autoritativní stavový rozcestník. Historické audity, předávací
záznamy a implementační prompty zachycují stav v datu svého vzniku a nesmí se
používat jako důkaz aktuální verze. Před každou novou prací má přednost čerstvý
`git fetch`, větev `origin/main`, aktuální GitHub Actions a skutečný kód.

## GitHub a produkce

- Repozitář: <https://github.com/KovoPraha/evidenceTreninku>
- Výchozí větev: `main`
- Stav vzdálené větve `origin/main` při této kontrole:
  `16b7d4d60883f3f4f655c097339339c68c1be07d`
- Otevřené pull requesty při této kontrole: žádné
- Produkční aplikace: <https://kis.kovopraha.cz/>
- Poslední ověřené nasazení: GitHub Actions běh
  [`37886677875`](https://github.com/KovoPraha/evidenceTreninku/actions/runs/37886677875)
  nasadil přesně commit `16b7d4d60883f3f4f655c097339339c68c1be07d`.
- Nasazení vytvořilo a ověřilo databázovou zálohu, připravilo úplný release,
  aplikovalo migrace, aktivovalo kód a dokončilo HTTP smoke test.
- Po nasazení byly z prohlížeče ověřeny domovská stránka, e-shop, veřejný
  rozvrh tréninků, klubový kalendář, velodrom a přihlášení. Žádná z těchto
  cest neobsahovala fatální chybu ani chybu či varování v konzoli.
- Veřejná domovská stránka a bezpečný vstup k veřejnému profilu po nasazení
  odpovídají HTTP 200 a posílají nonce CSP bez povolených inline skriptů.
  Soubor `var/deployment.json` není veřejný a správně odpovídá HTTP 403; důkaz
  release se proto čte z chráněného workflow, nikoli z veřejné URL.

Publikování tohoto dokumentu vytvoří novější dokumentační commit na `main`, ale
nemění nasazený aplikační commit. Přesné aktuální SHA `main` se proto vždy čte
pomocí `git fetch --prune origin` a `git rev-parse origin/main`, nikoli opisem z
dokumentu. Samotný push ani merge do `main` produkci nemění. Produkční nasazení
je vždy samostatný ručně spuštěný workflow podle
[`NASAZENI.md`](NASAZENI.md).

Bezpečnostní nastavení repozitáře bylo 8. 10. 2026 zpřísněno: je zapnuté
secret scanning, push protection, Dependabot alerts i automatické bezpečnostní
aktualizace. GitHub CodeQL default setup běží v rozšířeném režimu pro Actions a
JavaScript/TypeScript; první běh
[`37764312992`](https://github.com/KovoPraha/evidenceTreninku/actions/runs/37764312992)
prošel bez otevřeného nálezu. PHP závislosti navíc kontroluje `composer audit`
v hlavním CI jobu.

## Poslední automatické ověření

GitHub Actions běh
[`37886667244`](https://github.com/KovoPraha/evidenceTreninku/actions/runs/37886667244)
nad aktuálním `origin/main` a současně nasazeným commitem
`16b7d4d60883f3f4f655c097339339c68c1be07d` prošel v tomto rozsahu:

- PHPUnit na PHP 8.2: **877 testů / 11 995 kontrol**;
- integrační smoke na MariaDB 10.3: úspěch;
- integrační smoke na MariaDB 11.4: úspěch;
- kontrola Composer konfigurace: úspěch.

CodeQL běh
[`37886667406`](https://github.com/KovoPraha/evidenceTreninku/actions/runs/37886667406)
nad stejným commitem prošel pro GitHub Actions i JavaScript/TypeScript.

Migrační katalog obsahuje 84 verzovaných PHP migrací včetně bezpečnostních
migrací připravených v pracovní větvi a zmrazený legacy baseline 2.20.2.
Aktuální zálohovací ownership kontrakt zdrojového kódu je `2026-10-08.1`.
Počet souborů není důkazem stavu konkrétní databáze; ten se na každé stanici
ověřuje pomocí `php bin/migrate.php --check --json` s nastaveným `APP_HOST`.

## Funkční stav po změnách z 9. 10. 2026

Pull requesty
[#34](https://github.com/KovoPraha/evidenceTreninku/pull/34),
[#35](https://github.com/KovoPraha/evidenceTreninku/pull/35),
[#36](https://github.com/KovoPraha/evidenceTreninku/pull/36),
[#38](https://github.com/KovoPraha/evidenceTreninku/pull/38) a
[#39](https://github.com/KovoPraha/evidenceTreninku/pull/39),
[#53](https://github.com/KovoPraha/evidenceTreninku/pull/53) a
[#55](https://github.com/KovoPraha/evidenceTreninku/pull/55) jsou sloučené do
`main`. Aktuální release mimo jiné obsahuje:

- srozumitelnější registraci a práci s heslem;
- předletovou kontrolu klubové akce před otevřením přihlašování;
- dvoukrokové ruční založení sportovce;
- bezpečné opakování nebo návrat při nedostupné platební bráně;
- auditované odebírání pracovních pozic a aktivaci/deaktivaci účtů;
- povinnou vazbu plánovaného tréninku na soupisku, nebo výslovné označení
  interního tréninku bez účastníků;
- potvrzení účasti na tréninku rodičem či sportovcem a přehled odpovědí pro
  trenéra;
- zachování vazeb na soupisky při kopírování týdne;
- opravené produkční spouštění fronty zpráv soupiskám přes neveřejný PHP
  bootstrap;
- opravy druhé vlny testerových bodů a pravdivé zobrazení neomezené kapacity
  ve zrychlené přihlášce;
- centralizované a validované ukládání souborů mimo veřejný webroot;
- jednorázové časově omezené odkazy pro veřejný přístup k profilu místo
  přímého přenosu interního identifikátoru;
- limity těla Stripe a SumUp webhooků, fail-closed ověření SumUp odpovědi,
  transakční zápisy a odstranění runtime DDL z obsluhy požadavků;
- nonce CSP pro inline skripty a styly a zúžený přístup k servisním endpointům.
- opravy druhé iterace prohlížečového UAT: bezpečný přechod mezi účtem
  sportovce a rodiče, funkční našeptávač poplatků, QR fallback, lepší mobilní
  správa trenérů, filtrování a stránkování administrativních seznamů a
  idempotentní lokální demonstrační data.

Produkční běh fronty zpráv
[`37606107788`](https://github.com/KovoPraha/evidenceTreninku/actions/runs/37606107788)
nad commitem `623e161` skončil úspěšně. Workflow je naplánovaný každých pět
minut a lze jej spustit také ručně.

Podrobný uživatelský dopad testerových oprav popisuje
[`UAT-OPRAVY-2026-10-07.md`](UAT-OPRAVY-2026-10-07.md).

Navazující UX release sjednocuje veřejné akce a kalendář, přidává skutečný
měsíční rozvrh tréninků, hierarchickou navigaci e-shopu, kratší anonymní
nákupní cestu a prioritní přehledy rodiče a trenéra. Rozsah a ověřovací hranice
jsou popsány v
[`UX-NAVIGACE-A-KALENDARE-2026-10-08.md`](UX-NAVIGACE-A-KALENDARE-2026-10-08.md).

## Důležitá provozní hranice

Poslední deploy má `uat_schvaleno=true`, což označuje schválení přesného
commitu pro řízené produkční UAT, nikoli neomezené povolení zápisů. Read-only
kontrola připravenosti
[`37886988895`](https://github.com/KovoPraha/evidenceTreninku/actions/runs/37886988895)
je aktuálně blokovaná, protože provozní proměnná `KIS_UAT_WINDOW_END` skončila
8. 10. 2026 ve 20:00. Dokud vlastník výslovně neschválí nové časové okno,
nesmí se spouštět zápisové UAT. Reálné e-maily, bankovní pohyby a provozní účty
se nadále používají jen v rozsahu samostatně schváleného scénáře podle
[`PRODUKCNI-UZIVATELSKE-TESTOVANI.md`](PRODUKCNI-UZIVATELSKE-TESTOVANI.md).

Navazující read-only kontrola databázových invariantů
[`37887161804`](https://github.com/KovoPraha/evidenceTreninku/actions/runs/37887161804)
skončila `ok=true`: 16/16 kontrol bez porušení. Deploy před aktivací vytvořil
ověřenou komprimovanou zálohu 196 tabulek a 2 triggerů mimo webroot.

Obnova poslední generace produkční zálohy v izolované MariaDB byla ověřena
během [`37701691548`](https://github.com/KovoPraha/evidenceTreninku/actions/runs/37701691548):
195 tabulek a 2 databázové triggery.

Fio import je od 7. 10. 2026 záměrně provozně pozastavený. Automatický plán byl
odstraněn; kód zůstává připravený pro budoucí ruční obnovení po samostatném
schválení read-only tokenu a provozního postupu.

## Převzetí práce na jiné stanici

Aktuální postup je v [`HANDOFF_CURRENT.md`](HANDOFF_CURRENT.md). Základní pravidla:

1. vždy začít `git fetch --prune origin`;
2. ověřit čistý pracovní strom a shodu lokálního `main` s `origin/main`;
3. novou práci založit z čerstvého `origin/main` na větvi `codex/<téma>`;
4. nemíchat do commitu `config.php`, tajemství, produkční data, lokální importy
   ani cizí rozpracované změny;
5. změnu dostat do `main` přes pull request a zelené kontroly;
6. produkční deploy považovat za samostatné, výslovně schválené rozhodnutí.

## Autorita dokumentace

Pro pokračování ve vývoji platí toto pořadí:

1. skutečný `origin/main`, migrace, testy a workflow;
2. tento stavový soubor a [`HANDOFF_CURRENT.md`](HANDOFF_CURRENT.md);
3. provozní runbooky, zejména [`NASAZENI.md`](NASAZENI.md);
4. tematické dokumenty k jednotlivým funkcím;
5. datované audity, staré handoffy, roadmapy a implementační prompty pouze jako
   historický kontext.

Starší soubory nebyly mazány, protože zachovávají rozhodovací a auditní stopu.
Jejich stará SHA, počty testů a tvrzení typu „plánováno“ nejsou aktuálním
stavem, pokud jsou v rozporu s výše uvedenými autoritami.
