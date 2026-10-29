# W6.2 — ověřené produkční vydání

29. 10. 2026, [kis.kovopraha.cz](https://kis.kovopraha.cz/).
[PR #221](https://github.com/KovoPraha/evidenceTreninku/pull/221) sloučen jako
`330f8bde7a02984e21dc59309a7d8e6fb85bac62`, [deploy 40877039442](https://github.com/KovoPraha/evidenceTreninku/actions/runs/40877039442) SUCCESS.
Nová čtecí kontrola potvrdila stejné živé SHA a run ID. Vlastník autorizoval
autonomní implementaci, sloučení a nasazení po úspěšném ověření.

## Co bylo vydáno

Čistý výpočet obsazenosti je oddělený od PDO/SQL a zámků. Stejná pravidla
person/request/item, max(quantity), exclude, legacy fallback a dostupná místa.
SQL, parametry a načtené řádky jsou v přímém benchmarku shodné. Bez migrace,
nového indexu, změny UI nebo deklarace zrychlení; přínosem je testovatelná hranice.
[Implementace a měření](../refactoring-wave6-20261029/RESULTS.md).
Součástí zdroje je také mapa 16 cest a charakterizace z PR #220.

CI přesného nasazeného main: [PHP quality 40876631658](https://github.com/KovoPraha/evidenceTreninku/actions/runs/40876631658), [Push on main 40876631075](https://github.com/KovoPraha/evidenceTreninku/actions/runs/40876631075), [Testy 40876631205](https://github.com/KovoPraha/evidenceTreninku/actions/runs/40876631205), [Current documentation 40876631269](https://github.com/KovoPraha/evidenceTreninku/actions/runs/40876631269). [Metadata](github-ci-main.json).
Deploy zopakoval 1798 testů / 35952 assertions PASS.
Linux POSIX, MariaDB 10.3/11.4, checkout souběh/idempotence, přímé porovnání
baseline/current, PHPStan, styl, anonymous sweep a JS prošly. Plné lidské UAT
z toho neplyne. Lokální timeout a první opravený test jsou zaznamenány v protokolu.

## Provozní důkazy

Workflow: `potvrzeni=NASADIT`, `uat_schvaleno=false`, `importovat_krouzky=false`.
Záloha vytvořena a ověřena: 336 tabulek, 2 triggery,
6063493 komprimovaných bajtů, `evidence_2026-10-29_125555_f44c8c5f.sql.gz`.
SHA-256 `20f5797ce3d98de2d7296eee04cbe561b8d8acddd33262d97f369fc220b3df0c`. Obnova v tomto vydání neopakována.
Migrační kontroly current / pending []; variabilní symboly zachovány.
Dočasná kopie konfigurace odstraněna. [Bezpečné výstupy deploye](deployment.json).
Přímé spojení GitHub runneru mělo TCP timeout; existující workflow dokončilo
HTTP 200 přes serverový fallback. Nezávislý externí smoke níže také prošel.

Čtecí kontrola [před 40874539428](https://github.com/KovoPraha/evidenceTreninku/actions/runs/40874539428)
a [po 40877996673](https://github.com/KovoPraha/evidenceTreninku/actions/runs/40877996673)
PASS, obě v read-only session bez doménových mutací.
[Před](pre-release.json), [po](post-release.json), [porovnání](release-comparison.json).
Konfigurace, flags, ledger 141, KIS canonical writes a stav příloh shodné.
Fio import/auto-confirm, SumUp, Stripe, Velocota vypnuté; Packeta nedefinovaná.
KIS canonical writes false. Privátní podklady: 0 přesunutelných, 2 historicky
chybějící, 0 chyb. Identitu dokládají deployment metadata, ne nový soupis hashů
všech runtime souborů; stará pole diagnostiky nejsou novou runtime inventurou.

[HTTP před](pre-http.json) a [po](post-http.json): 22/22 PASS, stejné produkty.
[Skript](http-smoke.py) používá anonymní GET bez cookies a POST, kontroluje
homepage, katalog/detail, přihlášení, chráněné a neveřejné cesty. Tři opakování
katalogu/detailů jsou orientační HTTP pozorování, nikoli důkaz zrychlení nebo
produkční zátěžový test. Žádná skutečná objednávka, registrace nebo platba.

## Další práce a otevřené hranice

W6.2 je technicky dokončeno. W6.3 potřebuje profil migrovaného schématu a
paměťově omezené dávky; K0.2 pokračuje ostatními doménami podle registru.
Lidské/účetní převzetí D12–D14, inventura, rodinné vazby, trenérské rozsahy,
dohody/částky a dvě přílohy zůstávají otevřené. Integrace/importy nebyly aktivovány.
