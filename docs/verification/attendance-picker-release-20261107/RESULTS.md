# P11 výběr osob — schválené nasazení, 7. 11. 2026

HOTOVO: implementace, shoda výsledků, nové společné měření s #367, merge a schválené nasazení.
[Plán](../../ATTENDANCE_PERSON_PICKER_2026-11-07.md), [předání](../../SESSION_HANDOFF_ATTENDANCE_PERSON_PICKER_2026-11-07.md),
[integrované měření a přesná metoda](../attendance-person-picker-20261107/integrated/RESULTS.md).

## Oprávnění, CI a přesný release

Vlastník výslovně zadal dokončit #366, ověřit shodu a změřit před/po, potom při PASS
commitnout, sloučit a nasadit. Předchozí zákaz deploye byl nahrazen tímto zadáním.
Žádná aktivace integrací, změna rolí/osob ani zápis skutečné docházky nebyly součástí úkolu.

- [#366](https://github.com/KovoPraha/evidenceTreninku/pull/366) mezitím sloučila souběžná relace:
  `faae75e8d1bcf590e7b7d3936c7a60087aba48d4`, identický strom hlavy `d62c3ad2` po 11 CI PASS.
  Sloučení převzaté, bez duplicitní implementace.
- Nové měření [42379723334](https://github.com/KovoPraha/evidenceTreninku/actions/runs/42379723334)
  SUCCESS, head `50a0c811`; osm aplikačních otisků přesně odpovídá #366 i nasazenému release.
- Na stejné hlavě všechny **12 kontrol SUCCESS**: Testy 42379723332, PHP quality 42379723377,
  docs 42379723364, CodeQL 42379720365 a nový HTTP/Chrome benchmark.
  Linux **2362 testů / 46386 assertions**, zvlášť unixové hranice 3/45, obě MariaDB 10.3/11.4 PASS.
- Před dispatch se do main přidalo pouze dokumentační #370. Celý rozdíl proti #366
  zkontrolovaný: žádná změna PHP, JS nebo workflow; osm zdrojových otisků nadále shodných.
- [Produkční deploy 42380611292](https://github.com/KovoPraha/evidenceTreninku/actions/runs/42380611292)
  **SUCCESS**, přesně `56f5ce08e9ffbe1df9acfe7b844234b449171ad8`.
  Vzdálený main ověřený bezprostředně před dispatch, headSha běhu po dispatch shodné.
  [Bezpečný záznam kroků](deployment.json): syntaxe/testy, předletová kontrola,
  vytvoření/ověření zálohy, migrace, zachování VS, aktivace a HTTP smoke PASS.
  `potvrzeni=NASADIT`, `uat_schvaleno=false`, `importovat_krouzky=false`; import přeskočený.

Nezaváděla se migrace, nevytvářely se produkční osoby/role/lekce, nepotvrzovala se účast,
neměnily se platby ani integrační přepínače. Ověření nové DB zálohy není tvrzení její obnovy;
samostatná dřívější izolovaná obnova #370 má vlastní důkazy. Release/flags vlastní CURRENT.

## Shodné výsledky a měřený přínos

Nový jediný syntetický fixture: 10221 osob, 728 plánů, 200 účastníků a 52 revizí,
10k zdravotních sledování / 30k záznamů. Baseline main `2ef20aed` před pickerem,
stejný aktuální reader lekcí #367 na obou stranách, bez reseedu.
MariaDB 11.4.13, PHP 8.2.35, skutečný Chrome 156.0.8078.79, viewport 1036 × 703.

| Trenér / velká docházka | Před | Po |
| --- | --- | --- |
| HTML | 1368679 B | 91620 B (−93,3 %) |
| Celé HTTP, medián 30 časů | 48,40 ms | 27,87 ms |
| Chrome load, warm medián 10 časů | 445,65 ms | 82,60 ms |
| SQL | 56 | 72 |

540 HTTP časů / 78 browser pozorování tří rolí, shodné vybrané osoby/trenéři,
verze/původ/časy/historie. Všech 10221 osob dostupných právě jednou přes 205 stran obou rolí.
13 shodných doménových checksumů, v oddělené diagnostice žádné DML/DDL;
celé instrumentované HTML striktně stejné s prostým HTTP každé varianty.
44 nativních HTTP kontrol PASS; až po měření samostatný syntetický kanonický zápis
revize 53 / 201 osob / paper, stale 422 bez přepsání, původní API a revokace PASS.
Žádné browser console errors nebo failed requests ve všech 78 vzorcích.

Přínos je menší přenos a HTML/DOM; SQL počet s čerstvými kontrolami někde stoupl.
LIMIT neprokazuje rows examined. Čísla jsou syntetické Linux CI výsledky,
nikoli produkční SLA nebo fyzický mobil. Starší Windows a JS/noJS/390 px UAT mají
samostatné [původní důkazy](../attendance-person-picker-20261107/final/RESULTS.md) nad shodnými UI soubory.
Neúspěšné Windows Aria a první CI bridge kalibrace jsou výslovně zachované v novém protokolu.

## Produkční čtecí ověření a omezení

[Před](before.json) a [po](after.json): pět sériových anonymních katalogových GETů,
všude HTTP 200, **5/5 shodný normalizovaný celý main/navigace, ID/pořadí i počet 222 nabídek**.
Výchozí, cena, strana 2, hledání cyklo (prázdné) a záměrně prázdný výsledek.
[Sonda](snapshot.php.txt) ukládá pouze veřejná ID/počty/hash, odstraňuje oba CSP nonce atributy;
žádné HTML/cookies/session. Toto není produkční výkonový nebo zátěžový test.

[Anonymní GET cílové docházky](anonymous-lesson.json): 302 na login.php,
[sonda](anonymous-lesson.php.txt) bez cookies, redirectu nebo POST.
Přihlášené produkční UI UAT pickeru **NOT RUN**; netestovalo se skutečnými osobami a zápisy.
Technický Linux browser a nativní doménová/autorizační parita jsou samostatné důkazy.

## WIP, sdílení a další krok

Původní checkout `C:/xampp/htdocs/evidencePavel`, větev
`codex/fio-auto-reconciliation-recovery`, soubor kis_rosters_admin.php i ostatní výstupy zachované.
SHA256 před/po stále `7a7afd75bfa34f736f158096ab46a671b7fee6c6faedc3701f989c0cf64733e5`.
Původní picker worktree a jeho výstupy se neměnily; dokončení v izolovaném
`C:/Users/Marek/Documents/Codex/evidencePavel-attendance-picker-completion-20261107`.
Linux fixture, oba DB účty, config a cookie jars uklizené; poškozený vlastní Windows
datadir zůstal zastavený pro diagnostiku, bez dalšího pokusu o opravu či rekurzivní mazání.

Výsledkové předání a nový opakovatelný benchmark sdílí [#372](https://github.com/KovoPraha/evidenceTreninku/pull/372).
Jeho následná dokumentační integrace není další runtime deploy. Přijaté #370 výsledky a WIP zachované.
Další P11: R13/R05 objemy, rows examined, fyzický mobil/síť/CPU a souběh; celé K0.2 otevřené.
