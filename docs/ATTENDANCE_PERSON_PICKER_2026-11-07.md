# P11 R06 — výběr osob v docházce, 7. 11. 2026

ID P11-PICKER. Vlastník Marek, realizace Codex. HOTOVO v rozsahu implementace,
parity, společného HTTP/Chrome měření, merge a schváleného nasazení.
[Produkční protokol](verification/attendance-picker-release-20261107/RESULTS.md),
[integrované výsledky](verification/attendance-person-picker-20261107/integrated/RESULTS.md),
[session handoff](SESSION_HANDOFF_ATTENDANCE_PERSON_PICKER_2026-11-07.md),
[registr P11](WORK_PLAN_CURRENT.md).

## Zadání a oprávnění

Původní plán před kódem `c0883ff2` navazoval na HTTP analýzu 10221 voleb osob,
1,37 MB HTML a pomalé načítání. Původní zákaz deploye patří k historické etapě.
Nové výslovné zadání vlastníka: dokončit #366, ověřit shodu a změřit před/po;
pokud kontroly projdou, commitnout, sloučit a nasadit. Zachovat WIP a vést plán/handoff v repu.
Žádné aktivace integrací, produkční role/osoby ani skutečná docházka se kvůli testu nemění.
[Rozhodnutí D09 a D10](DECISION_LOG.md#d2026-11-07-10), události v [deníku](EVENT_LOG.md).

## Volba a zachované kontrakty

50 výsledků hledání plus všechny již vybrané osoby; hosté mimo preferovanou soupisku
nadále dostupní. Počet účastníků není omezen na 50. Pouze id/jméno/příjmení,
čerstvá autorizace bez cache a nativní POST bez nového JS/AJAX endpointu.
Pouhé skrytí dlouhého seznamu by nesnížilo přenos; omezení na soupisku by změnilo chování.

Review první verze odhalilo ztrátu sousedního draftu u dvou nezávislých formulářů.
Finální jeden společný formulář a prefixy omluvy zachovávají obě rozepsané části
při hledání i paging v obou směrech, včetně prázdného výběru, původu a verze.
Původní API omluvy zůstává podporované. formnovalidate u zápisových tlačítek
neblokuje sousední část, kanonická serverová validace/CSRF/transakce/zámky platí dál.

## Dokončené etapy a důkazy

1. Původní [Windows místní pár a JS/noJS UAT](verification/attendance-person-picker-20261107/final/RESULTS.md)
   dokončené, včetně 390 px, obou draftů, hosta, verze/původu a skutečných syntetických tlačítek.
   Tyto starší časy nejsou zpětně přejmenované na měření po #367.
2. Nový společný pár: main `2ef20aed` před pickerem versus shodné aktuální #367 s pickerem.
   Linux CI 42379723334 SUCCESS, MariaDB 11.4.13 / PHP 8.2.35 / Chrome 156,
   jediný syntetický seed, 540 HTTP časů a 78 Chrome pozorování tří rolí.
   Osm měřených zdrojů shodných se sloučeným i nasazeným runtime.
3. Všech 10221 osob / 205 stran pro obě role PASS; stejné výběry, čas, zdroj,
   historie a verze. 13 nezměněných checksumů, 0 DML/DDL při čtení; instrumentované
   HTML striktně stejné s prostým HTTP každé varianty. 44 HTTP kontrol PASS.
   Teprve pak samostatná syntetická revize 53/201/paper, stale 422, legacy API a revokace PASS.
4. Všech 12 kontrol hlavy `50a0c811` PASS: Linux 2362 testů / 46386 assertions,
   obě MariaDB 10.3/11.4, CodeQL, PHPStan/styl, dokumentace a nový browser benchmark.
5. #366 mezitím sloučila souběžná relace (`faae75e8`) po svém zeleném CI;
   změna převzatá beze změny aplikačního kódu. #370 přidalo jen dokumentaci.
   Schválený standardní deploy a produkční čtecí ověření dokončené; [přesný protokol](verification/attendance-picker-release-20261107/RESULTS.md).

Trenér / velká docházka: HTML **1368679 → 91620 B**, HTTP medián **48,40 → 27,87 ms**,
Chrome warm medián **445,65 → 82,60 ms**; SQL **56 → 72**. Přínos je HTML/DOM,
nikoli plošně nižší SQL. Native profile se nevydává za produkční SLA, rows examined ani fyzický mobil.
Přihlášené produkční UAT NOT RUN; pět katalogových GETů 5/5 shodných a soukromá trasa anonymně odmítnutá.

## Pracovní kopie, provozní předání a zbývající P11

Původní implementace `codex/attendance-person-picker-20261107` zachovaná.
Dokončení `codex/attendance-picker-completion-20261107`, worktree
`C:/Users/Marek/Documents/Codex/evidencePavel-attendance-picker-completion-20261107`,
[výsledkové předání #372](https://github.com/KovoPraha/evidenceTreninku/pull/372).
Původní WIP v htdocs má shodný hash; Windows Aria selhání a první CI bridge kalibrace
zůstávají doložené, nepočítají se jako PASS. Vlastní poškozený datadir je zastavený,
adresáře se plošně nemažou; Linux fixture byl uklizený.

Přijaté AUTO01/#370, ostatní plány a automatické bloky zachované. Pracovní pokračování
pickeru po převzetí E30/31 AUTO01 nese E32–34; historická čísla v commitech nepřepisovat.
Nasazení neopakovat z názvu staré větve; skutečný release/flags vlastní CURRENT_STATE.
Další P11: R13/R05 objemy/kandidáti, rows examined, fyzický mobil/síť/CPU/souběh
a další R01–R16. Celé K0.2 ani lidská klubová přejímka nejsou tímto uzavřené.

## P11-R06-HTTP-COMBINED — rozšířené celé Windows měření, 7. 11. 2026

HOTOVO v místním rozsahu nového zadání vlastníka; žádná změna aplikace/produkce.
Plán před měřením `3ca7e028`, větev `codex/attendance-integrated-http-20261107`,
worktree `C:/Users/Marek/Documents/Codex/evidencePavel-attendance-http-20261107`.
[Finální výsledky a meze](verification/attendance-integrated-http-20261107/RESULTS.md),
[předání](SESSION_HANDOFF_ATTENDANCE_HTTP_2026-11-07.md).

Nový jediný seed 350 tabulek/161 migrací, 10221 osob, 728 plánů/52 týdnů,
200 účastníků/52 revizí, 10k zdravotních sledování/30k záznamů, bez reseedu.
Pár d958fd6e před oběma změnami → faae75e8 s #366/#367. Zdrojové otisky
přesně ověřené; novější přijaté #370/#372 nemění aplikační soubory tohoto páru.

- 21 HTTP případů na variantu, 540 pozitivních časů; tentokrát Chrome ve všech
  9 kladných scénářích / 3 rolích, 234 pozorování celkem. 18 DOM kontrol PASS.
- Velká docházka: HTML1,37 MB →92,5kB, Chrome warm6,40 →0,35s, HTTP945 →113ms.
  Široké lekce Chrome6,39 →0,45s, SQL203 →72; seznam SQL36 →23. Čerstvé kontroly
  u běžného detailu SQL59 →72 zachované, přínos není plošné snížení počtu SQL.
- Oddělená diagnostika celé HTML parity, 0 aplikačních DML/DDL a 20 stejných
  doménových checksumů. JS/noJS obou současných draftů, Enter/paging/host/prázdný
  výběr/390px, 44 HTTP, skutečné syntetické zápisy i revokace PASS.
- Zápisové UAT až po časování: revize53/201/paper, stale422, browser omluva1001/paper
  a původní API1002/phone ověřené v DB. Žádné skutečné osoby, platby nebo role.
- Vlastní schema/účty/oba configy/credentials/cookies odstraněné, služby/browsers
  zastavené, TCP18105/33469 odmítá spojení, původní WIP stále shodný.

Tento Windows pár není totéž co přijaté Linux měření #372: tam2ef20aed →faae75e8
se stejnými už optimalizovanými lekcemi a 78 browser pozorování. Zde se srovnávají
obě úpravy dohromady a 234 pozorování včetně filtrů. Oba reporty mají po změně
72SQL pro široké lekce; předchozích203 vs56 plyne z jiné kontrolní verze.
Absolutní časy ani p95 z odlišných strojů/verzí nesčítat a nevydávat za SLA.

Provozní důkaz #372/release56f5ce08 přebíráme, deploy neopakovat. Tato relace jej
nespouštěla a nemá nové pověření měnit produkci. Předání/CI této výsledkové větve
ověřit u jejího PR; rozsah místního měření je dokončený.

Zbývá: R13/R05 objemy/kandidáti, skutečné rows examined, fyzický mobil/řízená
síť/CPU/souběh a další R01–R16. Přihlášené produkční UAT vede provozní plán;
toto není jeho provedení. Celé K0.2 otevřené. Volba [D11](DECISION_LOG.md#d2026-11-07-11).
Pracovní D10/E30 v 3ca7e028 před sdílením kolidovaly s přijatým #370/#372;
přijatá ID zachovaná, vlastní volba nyní D11 a události E36/E37.
