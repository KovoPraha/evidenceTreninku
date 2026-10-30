# W7.3 — ověřené produkční vydání

30. 10. 2026, [kis.kovopraha.cz](https://kis.kovopraha.cz/).
[PR #231](https://github.com/KovoPraha/evidenceTreninku/pull/231) sloučen jako
`4628845c9e0a7b61e2f2e24b4a6362c27fa70e55`, [deploy 41029345885](https://github.com/KovoPraha/evidenceTreninku/actions/runs/41029345885) SUCCESS.
Nová čtecí kontrola potvrdila stejné živé SHA a run ID. Vlastník autorizoval
autonomní implementaci, sloučení a nasazení po úspěšném ověření.

## Co bylo vydáno

Rodinný GET sdílí výsledky existence tabulek/sloupců v explicitním rozsahu
vázaném na PDO; `finally` jej po renderu uzavírá. Žádná cache oprávnění nebo
osobních/finančních dat, POST zůstává bez scope. Bez změny writerů W7.1b.
Syntetických 20 osob: celá stránka SQL 2244 → 752 (−66,5 %), metadata
1590 → 98 (−93,8 %). Shodná data/HTML i nemetadata SQL, parametry, pořadí
a řádky. Rozšířená historie +1000 vazeb i POST kontrola PASS.
Formátovací změny jsou pokryty AST a celým renderem; PDO guard odstranil
26 původních baseline výjimek. Bez migrace, aktivace integrací či změny UI.
[Implementace, měření a jejich meze](../refactoring-w73-20261030/RESULTS.md).

CI přesného nasazeného main: [Current documentation 41028951282](https://github.com/KovoPraha/evidenceTreninku/actions/runs/41028951282), [Push on main 41028951254](https://github.com/KovoPraha/evidenceTreninku/actions/runs/41028951254), [PHP quality 41028951333](https://github.com/KovoPraha/evidenceTreninku/actions/runs/41028951333), [Testy 41028951249](https://github.com/KovoPraha/evidenceTreninku/actions/runs/41028951249). [Metadata](github-ci-main.json).
Deploy zopakoval 1821 testů / 36135 assertions PASS.
Linux POSIX, MariaDB 10.3/11.4, checkout souběh/idempotence, porovnání
rodinné stránky a souběžné revokace, PHPStan, styl, anonymous sweep a JS prošly. Plné lidské UAT
z toho neplyne. RED/GREEN rozpočet a charakterizace oprávnění jsou zaznamenány v protokolu.

## Provozní důkazy

Workflow: `potvrzeni=NASADIT`, `uat_schvaleno=false`, `importovat_krouzky=false`.
Záloha vytvořena a ověřena: 336 tabulek, 2 triggery,
6063556 komprimovaných bajtů, `evidence_2026-10-30_085431_cbf28f75.sql.gz`.
SHA-256 `768c72e2d679370e33c71f8d64a9ddff32b5795ae1214e397d3860c982598046`. Obnova v tomto vydání neopakována.
Migrační kontroly current / pending []; variabilní symboly zachovány.
Dočasná kopie konfigurace odstraněna. [Bezpečné výstupy deploye](deployment.json).
HTTP kontrola workflow: Přímý test z GitHub runneru HTTP 200; nezávislý anonymní GET smoke 26/26.

Čtecí kontrola [před 41025783698](https://github.com/KovoPraha/evidenceTreninku/actions/runs/41025783698)
a [po 41029921064](https://github.com/KovoPraha/evidenceTreninku/actions/runs/41029921064)
PASS, obě v read-only session bez doménových mutací.
[Před](pre-release.json), [po](post-release.json), [porovnání](release-comparison.json).
Konfigurace, flags, ledger 141, KIS canonical writes a stav příloh shodné.
Fio import/auto-confirm, SumUp, Stripe, Velocota vypnuté; Packeta nedefinovaná.
KIS canonical writes false. Privátní podklady: 0 přesunutelných, 2 historicky
chybějící, 0 chyb. Identitu dokládají deployment metadata, ne nový soupis hashů
všech runtime souborů; stará pole diagnostiky nejsou novou runtime inventurou.

[HTTP před](pre-http.json) a [po](post-http.json): 26/26 PASS, stejné produkty.
[Skript](http-smoke.py) používá anonymní GET bez cookies a POST, kontroluje
homepage, katalog/detail, přihlášení, čtyři rodinné stránky a neveřejné cesty. Tři opakování
katalogu/detailů jsou orientační HTTP pozorování, nikoli důkaz zrychlení nebo
produkční zátěžový test. Žádná skutečná objednávka, registrace nebo platba.

## Další práce a otevřené hranice

W7.3 je technicky dokončeno. Další kandidát W7.4 je 160 opakovaných čtení
členských polí při zachování čerstvé autorizace; R03 žádosti a R04 seznamy registráře zůstávají samostatné.
Tento balík odstraňuje opakované čtení metadat; netvrdí dokončení celého
W7 ani stabilní produkční latenci podle syntetického profilu.
Lidské/účetní převzetí D12–D14, inventura, rodinné vazby, trenérské rozsahy,
dohody/částky a dvě přílohy zůstávají otevřené. Integrace/importy nebyly aktivovány.
