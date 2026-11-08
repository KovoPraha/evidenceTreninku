# Předání P11-R13-F — hledání a datum zdravotního přehledu

8. 11. 2026, Europe/Prague. ROZPRACOVÁNO / PR [#385](https://github.com/KovoPraha/evidenceTreninku/pull/385).
[Plán](MEDICAL_FILTER_READ_PERFORMANCE_2026-11-07.md),
[parita a výkon](verification/medical-filter-read-performance-20261107/RESULTS.md),
[provozní důkazy](verification/medical-filter-release-20261108/RESULTS.md).
Vlastník povolil commit, merge a standardní deploy po shodě výsledků, přínosu a zelených kontrolách.

## Izolace a současný stav

Větev `codex/medical-filter-read-performance-20261107`, worktree
`C:/Users/Marek/Documents/Codex/evidencePavel-medical-state-performance-20261107`.
Reference `35fdad4483ec4326e5da9b6ffbb908f77a4df85f`; produkce stále `938e9c50` / #381.
Původní checkout i staré výstupy jsou zachované. WIP `kis_rosters_admin.php` SHA256:
`7a7afd75bfa34f736f158096ab46a671b7fee6c6faedc3701f989c0cf64733e5`, opakovaně shodný.

V1–V4 měly přesnou paritu, ale některé datumové/pending profily regredovaly.
Zamítnuté varianty a bezpečné profily jsou ve výsledkovém protokolu; nejsou schválené k nasazení.
V5 přidává neunikátní krycí index `(watch_id,id,state,issued_on,valid_until,replaces_id)`.
Změna původního omezení bez DDL je odůvodněná v [D15](DECISION_LOG.md#d2026-11-07-15).
Nezměněné zůstávají kanonické zdravotní stavy, práva, writer, dokumenty a přímá osoba/transakce.

## Ověření V5

Lokálně medical + deploy kontrakt: **44 testů / 1328 assertions PASS**; migrační kontrakt navíc **1/91 PASS**.
Nativní parita **12/495 na každé DB PASS**; nativní přínos a celé HTTP/Chrome **PASS** (run42458893492).
Na100k celé GET: široké hledání1182,86→572,95ms; datum od882,55→508,70ms, do882,43→325,78ms.
2040kladných HTTP vzorků,48záporných GET,364Chrome pozorování; přesné HTML/checksumy, úklid PASS.
CI na 7c083f0a našlo jediný chybějící nový identifikátor v očekávaném seznamu migrací
(celá migrace a idempotence prošly) a příliš dlouhý popis benchmarku.
Opravy v `727e1d53`: úplná sada2389/48273 a PHPStan/styl PASS.
Dokumentační #387 převzatý do `fa902ba6`, Q01 i původní ID zachované; nyní nové CI celé větve.
Duplicitní starší integrační běhy zastavené, nejsou vydávané za PASS. Dokončené důkazy archivované.
Aplikační/migrační zdroje všech měření mají SHA256 shodné s aktuálním HEAD.
Anonymní inventář lokálně: 36 veřejných / 2 CLI / 260 chráněných, 0 chyb.

Nativní metoda: dvě data-shodná syntetická schémata, query cache OFF, buffer pool 512 MB,
16 případů × 4 objemy (100/1k/10k/100k) × 17 rotujících trojic a 4 zahřátí.
Trojice oddělují původní kód/staré indexy, původní kód/nový index a nový kód/nový index.
HTTP: stejný fixture 10k/100k, 17 kladných a 12 záporných případů, 7 scénářů Chrome.
Index se mění pouze v syntetické DB při vypnutém PHP serveru, nikdy během GET.

## Další krok

Bezpečné nativní JSON/XML i úplné HTTP/Chrome důkazy připravené v iteration5, zatím lokální.
Dokončit zelené CI fa902ba6; finální důkazy sdílet přes dokumentační PR po release.
Při regresi ponechat lepší změřenou cestu, jinak fresh main, ověřit shodu testovaného stromu a merge.
Deploy nejprve čte pouze produkční indexová metadata v READ ONLY transakci, poté ověřuje zálohu,
aplikuje idempotentní online ADD INDEX a znovu ověří index před aktivací.
Po úspěchu ověřit pět veřejných GET a anonymní odmítnutí zdravotních filtrů, WIP a důkazy do main.
Merge/deploy V5 zatím **NOT RUN**. Přihlášené produkční zdravotní UAT **NOT RUN**;
starší blokované UAT výběru osob a ostatní lidské přejímky zůstávají samostatné.

Fa902ba6: společný historický SyntheticReadPages benchmark odmítl neshodný počet migrací mezi zmrazeným předchůdcem a HEAD. Oprava pouze testovacího prostředí: do všech tří vlastních historických worktrees kopírovat a skutečně aplikovat stejnou novou indexovou migraci i helper jako HEAD; kontroly ledgeru/parity se neoslabují, zmrazený aplikační kód se nemění. Přínos samotného indexu již odděleně měří V5 trojice. Nové CI nutné před merge.

551b46d3 všech15CI PASS včetně opakovaného celého HTTP/Chrome a opravených historických srovnání. Důkazy v final/. Před merge přijatý čistě dokumentační #388 s opraveným stavem karet/čtecího UAT převzatý beze ztráty; přijaté E01–03 zůstávají, zdravotní E04–08. Aplikační/testové/workflow soubory jsou přesně stejné jako plně ověřený551b46d3. Nové CI dokumentačního merge před release.

Main následně přijal #389/a3cca282: třířádkové odstranění druhé klasifikace v rychlém nákupu a Q01 dokumentaci. Převzaté beze ztráty; zdravotní/migrační zdroje zůstávají přesně shodné s měřením, kombinace s novým main se musí integračně prověřit. Přijaté E01–07 zachované, zdravotní události nyní E08–12. Všechny naše změny a předchozí důkazy zachované, standardní deploy stále NOT RUN.
