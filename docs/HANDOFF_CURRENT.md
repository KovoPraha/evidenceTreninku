# Převzetí vývoje na další stanici

Stav nasazení, integrací, CI a otevřené provozní body jsou výhradně v
[CURRENT_STATE.md](CURRENT_STATE.md). Dřívější snapshoty zachovává
[CHANGELOG-PROVOZ.md](CHANGELOG-PROVOZ.md). Před změnou čti také [CLAUDE.md](../CLAUDE.md).

[Klubové tarify #316](verification/membership-catalog-release-20261105/RESULTS.md) jsou nasazené.
Produkční koncept Závodní tým 2200 Kč/měsíc (varianta 951) a skupina 86 → MTB 87,
Silnice 88, Dráha 89 jsou založené. Výběr více disciplín pouze zařazuje na soupisky,
nemění cenu. Vlastník doplní fakturační údaje přes `accounting_settings_admin.php`;
vystavovatel, účetní pravidlo a tarif ještě nejsou schválené. Nezakládat koncept
ručně znovu, použít existující. Prodej čeká na schválení/publikaci.
[Pravidelné zpracování je zapnuté](verification/membership-automation-20261105/RESULTS.md):
automatizace, denní plán/zápis i [pošta jsou true](MEMBERSHIP_MAIL_ACTIVATION_2026-11-05.md).
První apply --send i následný heartbeat PASS (0 členství a zpráv), cron 05:23 UTC. Skutečné doručení ještě neověřené.
Podporované roční tarify nemají zadanou skutečnou cenu a žádný roční produkt nevznikl.

[Měsíční členství a zastupování trenérů #314](verification/monthly-coach-release-20261105/RESULTS.md) jsou sloučené a nasazené.
Trenérské přiřazení nově určuje pořadí výběru, ne možnost vypsat/zapsat trénink za kolegu.
Měsíční automatizace a plán následně zapnuté v #318, pošta zapnutá v #321; před ostrým sjednáním je třeba schválit konkrétní tarif podle [runbooku](MONTHLY_MEMBERSHIP_RUNBOOK.md).
Produkční apply i uložený heartbeat PASS (0 členství), skutečná měsíční platba/PDF/doručení ještě neověřené.

Ostré platby mají [protokol aktivace Fio a SumUp](verification/live-payments-20261105/ACTIVATION.md).
Vlastník schválil stávající SumUp klíč a kartu pro všech 223 aktivních produktů
pro nové objednávky. Historické platby zachovávají uložený režim. Fio historii
neimportovat znovu od začátku; nejednoznačné pohyby čekají na účetní posouzení.
[Hostingový Fio plán a 45 pohybů](verification/fio-followup-20261105/RESULTS.md) jsou nově ověřené.
Všech 45 zůstává bez odpovídajícího VS; tři shody částky nejsou dostatečný důkaz.
Status číst přes fio-operations-production.yml; skutečná placená úhrada a přihlášené
platební UAT nejsou doložené. Redundantní GitHub plán je vypnutý. Heslo/T07 dál vynechat.

Pro následující práci na členství platí [pracovní plán evidence členů bez sezon](MEMBER_EVIDENCE_WORK_PLAN_2026-11-02.md)
z 2. 11. 2026. Vlastník potvrdil trvalé skupiny/soupisky a časové členství;
historie před přechodem zůstane v původním KIS. Plán obsahuje inventář stránek,
etapy E0–E8, rozhodnutí D1–D8 a akceptaci A01–A20. Aplikační implementaci E1–E5 a nástroj E6 popisuje
[implementační protokol](MEMBER_EVIDENCE_IMPLEMENTATION_2026-11-02.md). Produkční
inventura a technický nácvik skutečné aplikační zálohy prošly. [Podklady převodu](MEMBER_EVIDENCE_PRODUCTION_TRANSITION_2026-11-02.md)
obsahují potvrzené rozhodnutí převzít 435 aktivních zařazení od 2. 11. 2026 a odstranit
560 historických. Jedna poslední synchronizace je očekávána; datum začátku neposouvá.
Nový nástroj přijímá explicitní rozdíl nad konkrétním exportem a odmítá opakovanou dávku.
Rozšířený nácvik 435/560 na skutečné izolované záloze prošel v běhu 41512931635,
včetně odstranění 560 importních událostí, odpojení převodních vazeb a úplné obnovy.
Vlastník následně schválil [konkrétní mapu D2](MEMBER_EVIDENCE_APPROVED_MAP_2026-11-02.md):
sedm provozních skupin / 41 soupisek a samostatná skupina pro tři testy.
Rexonix je závodní, KDC a Bikros dvě samostatné placené skupiny `other`.
Zdrojová ID, kódy a názvy jsou v soukromém provozním podkladu mimo Git.
Implementace mapy je sloučená v PR #285. Nácvik přesné mapy 41532405191 prošel:
8 skupin, 44 hran, 435 počátečních zařazení, 560 odstraněných historických intervalů,
parita skupin, pilotu, opakování i obnova DB PASS. Všech 11 kontrol PASS (1953 testů / 38293 assertions).
Schválený produkční převod byl potvrzen v běhu 41542954307: 435 počátečních zařazení od 2. 11.,
560 historických intervalů a importních událostí odstraněno, 8 skupin / 44 hran. Technická
identita je po převodu neaktivní bez oprávnění; provoz obnoven. Červený výsledek workflow
způsobil až následný HTTP timeout runneru; nezávislé HTTP 40/40 a čtecí inventura PASS.
Přihlášené UAT, poslední synchronizace a E8 zůstávají otevřené. Živé vydání a zálohy
určuje CURRENT_STATE a [protokol převodu](verification/member-evidence-cutover-20261102/RESULTS.md). Převod neopakovat.

Navazuje [trenérská obnova hesla](TRAINER_READINESS_2026-10-31.md) a její
[produkční přejímka](verification/trainer-recovery-20261031/RESULTS.md).
Odesílání trenérských odkazů zajišťuje [ověřený minutový cron hostingu](verification/trainer-recovery-20261031/scheduler/RESULTS.md).
Vlastník potvrdil doručení a zvolil [samostatný syntetický pilot](TRAINER_PILOT_2026-10-31.md).
Pilot je [založen a technicky ověřen](verification/trainer-pilot-20261101/RESULTS.md):
trenér 56, soupiska 77, pouze dvě syntetické osoby. Grant končí 15. 11. 2026
v 18:17:31 databázového času; nedochází k automatickému prodloužení ani smazání.
Vlastník si nastaví heslo sám přes trenérskou obnovu na schválené samostatné
adrese. Adresa zůstává v soukromém provozním dokladu, není součástí zdrojů.
První dvě adresy patřily veřejným účtům bez pracovní vazby; nepovyšovaly se.
[Dohledání](TRAINER_OWNER_LOOKUP_2026-11-01.md) a
[provozní varianta](TRAINER_PILOT_OPERATIONAL_2026-11-01.md) mají samostatné doklady.
Ke kontrole použít `trainer-pilot-production.yml` na main s `action=status`
a `mode=operational`. Opakované `create` neobnovuje heslo ani neprodlužuje grant.
Technická auditní identita zůstává neaktivní, bez pozic a superadmina.
Pro interní test jsou připravené [scénáře T01–T08](TRAINER_UAT_POSTUP_2026-11-01.md),
[checklist k přeposlání](TRAINER_UAT_CHECKLIST_2026-11-01.md) a
[browser zadání pro jiné vlákno](TRAINER_UAT_BROWSER_2026-11-01.md).
[Částečné browser UAT](verification/trainer-browser-uat-20261101-1955-a7c2/RESULTS.md) proběhlo:
vznikl a jednou se upravil syntetický trénink 1604, záznam zůstává pro diagnostiku.
Nález zobrazení formuláře pro použitý odkaz řeší nasazený PR #269;
[protokol vydání](verification/trainer-reset-release-20261101/RESULTS.md) odděluje technické
ověření neplatných odkazů od skutečného použitého odkazu a T07, které zůstávají odložené.
Nové heslo nadále zadává vlastník; nečíst hesla ani správce hesel.
Výslovně povolené otevření odkazu z cílené testovací zprávy je doloženo níže;
hodnotu odkazu neexportovat ani neukládat do důkazů.
Stav deploye, SHA a flags určují výhradně CURRENT_STATE a jeho doklady.
[Autonomní rozšíření](verification/trainer-recovery-autonomous-20261101-2341/RESULTS.md)
ověřilo skutečné nové doručení, platnou předběžnou kontrolu a oba režimy bez změny hesla.
Nový izolovaný test se dvěma PHP relacemi ověřuje backend revokace, nepředstírá produkční T07.
[Funkční rozšíření 2. 11.](verification/functional-uat-20261102/RESULTS.md) odkládá změnu hesla/T07 dle vlastníka;
opravuje vrstvy účtového menu, přístupné názvy a skrytou poznámku měření.
[PR #272 je sloučen a nasazen](verification/functional-release-20261102/RESULTS.md):
HTTP 26/26, skutečné CSS a veřejné filtry PASS; přihlášené produkční UI
se kvůli odhlášené relaci neopakovalo. Změnu hesla a T07 nadále vynechat.
Dokumentační PR nemění živé SHA a nevyžaduje další deploy.
[Druhý místní funkční průchod](verification/functional-uat-round2-20261102/RESULTS.md)
ověřil akce, velodrom, katalog, kupóny, storno a simulovanou úhradu/vratku.
Oprava odděluje zboží od programu v localhost seedu a zachovává historii staršího dema.
[PR #274 je sloučen a nasazen](verification/functional-round2-release-20261102/RESULTS.md);
produkční dostupnost a konfigurace ověřeny. Samotný seed běží výhradně v lokálním testu;
do produkce se demo data nepřenášela. Heslo a T07 nadále vynechat.
[Třetí autonomní průchod 4. 11.](verification/functional-uat-round3-20261104/RESULTS.md)
rozšířil přihlášené produkční kontroly, místní browser trenéra/registráře/rodiče
a 119 kontrol pracovních přístupů. [PR #293 je sloučen a nasazen](verification/functional-round3-release-20261104/RESULTS.md):
1971 testů, všech 11 kontrol, HTTP 30/30 i izolovaná obnova čerstvé zálohy PASS.
Produkční náhled členství ověřen bez potvrzení; rodinná stránka funguje, ale účet nemá
propojené profily pro ověření štítků. Úplné UAT všech rolí, poslední synchronizace a E8 zůstávají otevřené.
[Čtvrtý funkční průchod](verification/functional-uat-round4-20261104/RESULTS.md) ověřil zdravotní podklady,
docházku a náhrady: 70 HTTP scénářů a místní browser. Oprava zachování papírového původu
při opravě docházky je [sloučená a nasazená v PR #295](verification/functional-round4-release-20261104/RESULTS.md). Heslo a T07 se nadále vynechávají.
[Pátý funkční průchod](verification/functional-uat-round5-20261104/RESULTS.md) přidal 73 HTTP kontrol
souhlasů, papírových podkladů a vyřízení osoby, cílené 35/288 a MariaDB souběžnost PASS.
Nový runtime nález se nepotvrdil; ovládání prohlížeče neodpovídalo, browser této vlny NOT RUN.
[Šestý průchod v Chrome](verification/functional-uat-round6-20261104/RESULTS.md) doplnil skutečné browser scénáře registráře:
produkční mobilní UI bez zápisů, místní souhlasy, historický papírový podklad s opravou a pracovní úkol PASS;
16 kontrol persistence PASS, bez nové chyby runtime. PR #295 je následně sloučený a nasazený; heslo/T07 vynechané.
[Sedmý funkční průchod](verification/functional-uat-round7-20261104/RESULTS.md) opravil ztrátu osob za první tisícovkou
ve filtru úrovní zapojení; úplné počty a stránkování, Chrome a 39 HTTP scénářů PASS.
Oprava je [sloučená a nasazená s bezpečnostním odblokováním](verification/functional-round7-release-20261104/RESULTS.md); sportovní podklady a původní export ověřené. Heslo/T07 vynechané.
[Osmý funkční průchod](verification/functional-uat-round8-20261104/RESULTS.md) ověřil přípravu období a věkové návrhy:
25 kontrol, skutečný místní browser a 21/190 cílených testů PASS; bez nové chyby runtime.
Přihlášené produkční UAT tohoto vydání bez relace NOT RUN; technické ověření a izolovaná obnova PASS.
Chroot nemá `sha256sum` a zakazuje PHP `-r`; kontrola hashů používá soukromý
PHP soubor spouštěný bez přepínačů, stejně jako již ověřený worker.
Před použitím reálných skupin vyřešit blokované e-maily/vazby a potvrdit jejich rozsahy. Rozsahy nepřiřazovat odhadem.

W9.2 / W10.1 jsou [nasazené a ověřené](verification/refactoring-w92-release-20261030/RESULTS.md).
W10.2 je [nasazená](verification/refactoring-w102-release-20261030/RESULTS.md).
W10.3–W15 jsou zahrnuty v [závěrečné technické přejímce](REFACTORING_CLOSURE_2026-10-30.md).
Implementace, CI i finální chráněné nasazení jsou dokončené a technicky ověřené.
[Původní neúspěšný pokus](verification/refactoring-final-release-20261030/RESULTS.md)
a [následná obnova DB kapacity a úspěšný release](verification/db-temp-recovery-20261030/RESULTS.md)
mají oddělené protokoly. Globální Search Velocoty a jeho cron zůstávají
vypnuté; bez trvalé kapacitní nápravy nebo ověřeného úspornějšího indexu je nezapínat.
Stav nasazení ověřte v CURRENT_STATE; samotný lokální PASS není release.
Skutečné účetní UAT zůstává oddělené. Po nové migraci nevracet starý katalog;
opravné vydání zachová migrační soubor a ledger, bez mazání dat nebo záloh.

[Devátý autonomní průchod](verification/functional-uat-round9-20261104/RESULTS.md) odhalil souběh generování s odhlášením či revokací vazby. [PR #300 je sloučený a nasazený](verification/functional-round9-release-20261105/RESULTS.md); přesné vydání a výsledky určuje CURRENT_STATE. Heslo/T07 dál vynechat; produkční přihlášené UAT této vlny bez relace NOT RUN.

[Desátý průchod](verification/functional-uat-round10-20261105/RESULTS.md) opravil kontext objednávky a neúplný seznam vratek. [PR #305 je sloučený a nasazený](verification/functional-round10-release-20261105/RESULTS.md): skutečný přihlášený čtecí Chrome, HTTP a izolovaná obnova PASS; rozsah a omezení jsou v protokolu. Přesné živé vydání určuje CURRENT_STATE. Heslo a T07 dál vynechat.

## Nová stanice: čisté převzetí

```powershell
cd C:\Users\Marek\Documents\Codex
git clone https://github.com/KovoPraha/evidenceTreninku.git evidencePavel
cd evidencePavel
git fetch --prune origin
git switch main
git pull --ff-only origin main
git rev-parse HEAD
git rev-parse origin/main
```

Poslední dva příkazy musí vrátit stejné SHA. Potom spusťte
`PRIPRAVIT_LOCALHOST_TESTOVANI.cmd`. Skript připraví ignorovaný `config.php`,
oddělenou lokální MariaDB na portu 3308, migrace a syntetická demo data. Pro
další starty slouží `START_LOCALHOST_TESTOVANI.cmd`.

## Existující clone: bezpečná synchronizace

Nejdřív zjistěte, zda stanice neobsahuje rozpracovanou práci:

```powershell
git status --short --branch
git fetch --prune origin
git branch --show-current
git rev-list --left-right --count HEAD...origin/main
```

Pokud je pracovní strom čistý a lokální `main` nemá vlastní commity:

```powershell
git switch main
git pull --ff-only origin main
```

Pokud jsou přítomné lokální změny nebo vlastní commity, nepoužívejte force,
reset ani přepis souborů. Nejdřív práci uložte do samostatné větve a teprve
potom synchronizujte `main`. Cizí rozpracované změny se nesmí přidat do nového
commitu jen proto, že leží ve stejném adresáři.

## Zahájení nové práce

```powershell
git fetch --prune origin
git switch -c codex/kratky-popis origin/main
```

Před commitem zkontrolujte změněné soubory a spusťte přiměřené testy. Úplná
základní brána je:

```powershell
composer install
composer validate --strict
composer test
$env:APP_HOST='localhost'
php bin/migrate.php --check --json
git diff --check
```

Nová migrace musí být nový neměnný soubor v `migrations/`. Již přijatá migrace
se neupravuje. Pokud přidává tabulku vlastněnou aplikací, musí se současně
aktualizovat zálohovací ownership kontrakt v `bin/db-backup.php` a jeho testy.

## Pull request a předání

Vlastní větev odešli, otevři PR a ověř zelené kontroly pro přesný head commit.
Před případným sloučením znovu fetchni main a ověř aktuální pravidla repozitáře.
Merge a produkční deploy jsou samostatná rozhodnutí vlastníka podle
[NASAZENI.md](NASAZENI.md); samotné předání, push ani zelené CI je nepovolují.

## Průběžný plán

Začlenění baru řídí [plán z 5. 11. 2026](BAR_INTEGRATION_PLAN_2026-11-05.md).
Práce probíhá v izolovaném worktree; vlastník následně výslovně povolil nasazení
a pokračování převodu. Zachovává se katalog a pouze účet lucka, provozní historie
se nepřenáší. Dvě nové pozice doplňuje samostatné oprávnění vlastní spotřeby.
B0–B6 jsou po výslovném souhlasu vlastníka sloučené a [nasazené do KIS](verification/bar-release-20261105/RESULTS.md).
Úplná skutečná obnova DB i 4341 souborů před vydáním a čtecí přihlášený Chrome PASS;
aktuální živé SHA, CI a migrace určuje výhradně CURRENT_STATE. [Skutečný převod B7](verification/bar-cutover-20261106/RESULTS.md)
je ověřený: 68 produktů / 51 fotografií, pouze lucka jako obsluha s původním heslem,
schválená správa a vlastní spotřeba přidělené pouze existujícímu vlastníkovi. Import
neopakovat ani neobnovovat starou DB přes nové operace. Starý BAR je přesměrovaný:
44 vstupů a samostatný status PASS, úplná obnova 500 původních souborů ověřená.
Pět původních produkčních workflow je pozastavených, zdrojová DB zachovaná.
Zbývá přihlášení Lucky, lidská finanční přejímka, první zásoby/kasa a zveřejnění nové nabídky.
[Účetní kontrakt](BAR_ACCOUNTING_CONTRACT_V1.md) a [návrh adaptéru](BAR_ACCOUNTING_ADAPTER_PLAN_2026-11-05.md)
neaktivují skutečné zaúčtování ani nové bankovní párování.

Navazující [komplexní refaktoring celé aplikace](COMPREHENSIVE_REFACTORING_PLAN_2026-10-29.md)
je sloučený v PR #219 a vlastník zahájil autonomní realizaci. Vlny 1–5 jsou
dokončené; [první K0/W6.1 balík](REFACTORING_K0_W6_2026-10-29.md) je sloučený
v PR #220. [W6.2](REFACTORING_WAVE6_2026-10-29.md), oddělení čistého výpočtu
kapacit, je ověřené a nasazené. [W6.3](REFACTORING_W63_2026-10-29.md) doplnilo
migrovaný profil a postupné čtení s úsporou paměti; je také nasazené.
[W7.1a](REFACTORING_W71_2026-10-29.md) nasadilo cílené ověření rodinného přístupu
s rozpočtem čtyř SQL. [W7.1b](REFACTORING_W71B_2026-10-29.md) opravilo
revokaci během čekání RSVP/náhrad: aktuální zamykající kontrola před zápisem,
32/32 souběhů v obou MariaDB CI. [W7.2](REFACTORING_W72_2026-10-29.md)
nasadilo cílené načítání jednotlivých profilů: celý GET pro 20 osob má
2706 → 2326 SQL a shodná data/HTML; CI medián o 10–12 % nižší.
[W7.3](REFACTORING_W73_2026-10-30.md) je také nasazené: metadata během GET
sdílená v rámci PDO, SQL 2244 → 752 a metadata 1590 → 98 pro 20 osob.
Výstup, historie a aktuální oprávnění zachovány, POST bez cache.
[W7.4](REFACTORING_W74_2026-10-30.md) je nasazena: členské dotazy 160 → 27,
celý GET 752 → 619 SQL, stejné výsledky a čerstvá autorizace.
[W7.5](REFACTORING_W75_2026-10-30.md) je nasazena: stránka 50 z 10 000 osob
načítá 53 místo 10 003 řádků; CI paměť stránky 100 klesla z 36,8 na 0,43 MB.
[Provozní důkazy](verification/refactoring-w75-release-20261030/RESULTS.md).
[W7.6 / W8.1](REFACTORING_W76_W81_2026-10-30.md) je nasazena z PR #237:
roční časová osa 4176 → 16 SQL, shodný výstup;
[release důkazy](verification/refactoring-w81-release-20261030/RESULTS.md).
R03 nemá N+1, finanční historie editoru pokračuje ve W10. Vlastník autorizoval
autonomní pokračování až do technického dokončení W15 včetně sloučení a nasazení
po úspěšném ověření; nový pokyn pro každou vlnu není potřeba.
Plošné měření K0.2 zůstává otevřené;
dávkování kapacit mezi nabídkami je samostatný kandidát.

[W8.2](REFACTORING_W82_2026-10-30.md) je nasazena: společné historické
filtry plánů/tréninků a náhled kopírování, roční filtr 1875 → 5 SQL.
[Společné vydání s W8.3](verification/refactoring-w82-release-20261030/RESULTS.md)
prošlo 1839 testy / 36699 assertions a produkčními čtecími kontrolami.

Před větší implementací ulož konkrétní plán do docs/; zaznamenej rozsah
oprávnění, větev, pracovní kopii, dokončené kroky, testy a další krok.
Před předáním/kompaktací jej obnov a při pokračování znovu přečti.
Aktuální práce dozoru: [pět balíků a jejich hranice](DOZOR_2026-10-25.md).
Navazující opravy a důkazy: [F1–F3 po nezávislé přejímce](DOZOR_F1_F3_2026-10-26.md).

## Co není v GitHubu

- `config.php` a jakákoli tajemství;
- lokální nebo produkční databáze a obnovovací dumpy;
- skutečné osobní údaje, importy a přílohy;
- lokální outboxy, session a cache;
- `vendor/`, který obnoví Composer podle `composer.lock`.

Tyto výjimky jsou bezpečnostní hranice, nikoli chybějící část synchronizace.

## Kde hledat aktuální informace

- stav GitHubu, produkce a posledních oprav: [`CURRENT_STATE.md`](CURRENT_STATE.md);
- dokumentační rozcestník: [`README.md`](README.md);
- lokální instalace: [`localhost-testovani.md`](localhost-testovani.md);
- produkční nasazení: [`NASAZENI.md`](NASAZENI.md);
- produkční UAT: [`PRODUKCNI-UZIVATELSKE-TESTOVANI.md`](PRODUKCNI-UZIVATELSKE-TESTOVANI.md);
- pravidla migrací: [`../migrations/README.md`](../migrations/README.md).

Datované dokumenty jako `HANDOFF_2026-08-29.md`, auditní zprávy a implementační
prompty jsou historické záznamy. Při rozporu nemají přednost před aktuálním
kódem, `origin/main`, CI ani tímto dokumentem.

### Průběžná W8.3

[W8.3](REFACTORING_W83_2026-10-30.md) nasazena společně s W8.2:
rodinný kalendář 4199 → 39 SQL pro 20 osob/rok.
[Release a čtecí důkazy](verification/refactoring-w82-release-20261030/RESULTS.md).
W8.4 klubový kalendář je rovněž
[nasazen z PR #241](verification/refactoring-w84-release-20261030/RESULTS.md):
1842 testů / 36744 assertions, HTTP 26/26, živé SHA a beze změny konfigurace.
W9.1 skladový přehled je [nasazen z PR #244](verification/refactoring-w91-release-20261030/RESULTS.md):
1844 testů / 36778 assertions, HTTP 26/26, živé SHA a flags ověřené.
Součástí jsou i testovací/CI kontrakty W8.5. Navazující W9.2 / W10.1 mají
vlastní [release důkazy](verification/refactoring-w92-release-20261030/RESULTS.md).
