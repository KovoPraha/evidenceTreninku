# Produkční vydání B04/B23 — 25.–26. 10. 2026

**Nasazeno a čtecí kontroly PASS.** Živý release je `5df4b0278129a3d66b62a19657911567d7196dee`, [chráněné nasazení 40242443325](https://github.com/KovoPraha/evidenceTreninku/actions/runs/40242443325). Obsahuje B04/B23 z [PR #205](https://github.com/KovoPraha/evidenceTreninku/pull/205), předchozí sloučené opravy přejímky a aktualizovanou čtecí diagnostiku [PR #206](https://github.com/KovoPraha/evidenceTreninku/pull/206).

Workflow dokončilo aktivaci a HTTP smoke 25. 10. před půlnocí SEČ; následná obnova zálohy a čtecí kontroly skončily 26. 10. 2026. Metadata `deployed_at=2026-10-25T22:58:51+00:00` označují přípravu release, nikoliv přesný okamžik aktivace.

## Před nasazením

- Ověřený předchozí živý kód `a23e393f64eeffafd75df834e6e5ba8c78213b72`: 44/44 otisků změněných cest i jejich archivního release, včetně očekávané absence nových souborů. [Předletový odečet](preflight.json).
- Připravená a otestovaná návratová větev zachovává nové migrace, tabulky, zálohování a provozní data. Finální CI návratu: **1719 testů / 34754 assertions**, MariaDB 10.3 i 11.4 PASS. [Postup návratu](ROLLBACK.md), [CI](rollback-ci.json).
- Produkční ochrana správně odmítla diagnostiku spuštěnou z nechráněné pracovní větve; neproběhl žádný serverový krok. Diagnostika poté prošla standardním PR, celou CI a spustila se z chráněného main. Ochrany prostředí se neměnily.
- Test vzhledu původně zaměňoval řetězec `hlavicka.php` v manifestu za skutečné include. Opravené rozpoznání PHP tokenů má cílenou regresi; finální CI **1740 testů / 35100 assertions**, CodeQL a obě MariaDB PASS. Produkční workflow testy zopakovalo.

## Záloha a ověřená obnova

Před migracemi vznikla `evidence_2026-10-25_225734_011e5e09.sql.gz`, **330 tabulek, 2 triggery**, 6 062 527 komprimovaných bajtů. SHA-256:

`1c886cba16f289cd1355a82a8f32e0195f3b224ec0fd46ea37207971a8ac5e64`

Záloha a manifest jsou uložené mimo webroot v soukromém `.kis-backups`. [Doklad vytvoření](backup-created.json).

[Obnova 40242825853](https://github.com/KovoPraha/evidenceTreninku/actions/runs/40242825853) stáhla tuto přesnou generaci do dočasného izolovaného runneru a obnovila ji v odděleném kontejneru MariaDB. Souhlasí checksum, manifest, počty všech 330 tabulek i oba triggery. Do produkční databáze se nic neobnovovalo; dočasný kontejner i dump byly uklizené. [Výsledek](restore.json), [konkrétní důkaz](restore-evidence.txt).

## Nasazení a následné čtecí kontroly

| Kontrola | Výsledek |
|---|---|
| Přesná identita živého release a run ID | `5df4b027…` / `40242443325`, PASS |
| Otisky všech 44 změněných aplikačních cest | 44/44 shodných s ověřeným zdrojem |
| Další účetní/skladové knihovny | 8/8 otisků shodných s Git bloby nasazeného SHA |
| Migrace | Dvě nové migrace, `current=true`, `pending=[]`; katalog 140, historický ledger 141 záznamů |
| Původní variabilní symboly | Před/po beze změny |
| Konfigurace aplikace | SHA-256 config.php před/po shodný |
| Fio import/auto-confirm, SumUp, Stripe, Velocota | Vypnuté před i po; Packeta konstanta nadále nedefinovaná |
| Produkční kanonický zápis KIS | Vypnutý (`JE_LOKALNE=false`) |
| Nové tabulky B04/B23 | 0 výsledků, účastí, plánů a událostí; pouze 1 technický řádek mutexu z migrace |
| Účetní vazby | 14/14 PASS |
| Skladové vazby | 20/20 PASS |
| Objednávky, osoby, rodinné přístupy, tréninky a lekce | 16/16 databázových invariantů PASS, bez porušení |
| Anonymní HTTP | 7/7 očekávaných odpovědí |

HTTP ověření: index a e-shop 200; nové administrační stránky výsledků a KIS 403; KIS centrum přesměruje na login; soukromý archivní klíč 403; místní přejímací rozcestník na produkci 404. Žádné autentizované testovací zápisy ani objednávky nevznikly.

Důkazy: [nasazení](deploy.json), [živý odečet](postflight.json), [migrace](migrations.json), [VS](variable-symbols-preserved.json), [účetní a skladové vazby](accounting-stock-audit.json), [databázové invarianty](invariants.json), [HTTP](http-checks.json), [úložiště](private-storage.json).

## Hranice ověření a otevřené body

- Skutečná data se neimportovala, kroužkový import byl explicitně `false`, nové integrace ani plánovače se nezapínaly. Produkční UAT schválení zůstalo `false`.
- Jde o nasazení a čtecí kontrolu. Účetní a řízené skladové tabulky jsou nyní prázdné; jejich konzistence proto není důkazem skutečného průchodu prodeje, výdeje, vratky nebo účetního exportu. 855 katalogových variant dosud nemá řízený skladový zůstatek; počáteční inventura se neodhadovala ani neimportovala.
- Před i po zůstávají dvě historicky chybějící přílohy. Soukromá migrace měla `candidates=0`, `missing=2`, `errors=0`; žádný dokument se při tomto vydání nepřesouval. Obnova chybějících originálů vyžaduje dohledání podkladů.
- Rozhodnutí o zdroji pravdy, skutečných exportech, termínu přechodu a potvrzení finanční parity účetní zůstávají otevřená. B23 se nasazením kódu neaktivovalo pro ostrý převod osob ani financí.
- Uživatelovy rozpracované soubory v původním checkoutu zůstaly beze změny. Finální dokumentace je záznamem tohoto nasazení, nikoliv další změnou běžícího kódu.
