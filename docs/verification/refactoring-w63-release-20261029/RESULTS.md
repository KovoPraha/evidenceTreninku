# W6.3 — ověřené produkční vydání

29. 10. 2026, [kis.kovopraha.cz](https://kis.kovopraha.cz/).
[PR #223](https://github.com/KovoPraha/evidenceTreninku/pull/223) sloučen jako
`3a168a68050be1d44648086bdb190df7c556617e`, [deploy 40914561899](https://github.com/KovoPraha/evidenceTreninku/actions/runs/40914561899) SUCCESS.
Nová čtecí kontrola potvrdila stejné živé SHA a run ID. Vlastník autorizoval
autonomní implementaci, sloučení a nasazení po úspěšném ověření.

## Co bylo vydáno

Rezervované položky se zpracovávají postupně, bez druhého úplného PHP pole.
Stejná pravidla person/request/item, max(quantity), exclude, legacy fallback
a dostupná místa; stejné SQL, parametry, řádky, zámky i bufferování PDO.
Při 10 000 položkách klesla špička přírůstku PHP paměti o 66,6 %, při jedné
opakované identitě o 88,6 %. Jde o syntetický profil migrovaného schématu,
nikoli zrychlení celé produkce. Bez migrace, indexu nebo změny UI.
[Implementace a měření](../refactoring-w63-20261029/RESULTS.md).
Součástí zdroje je také mapa 16 cest a charakterizace z PR #220.

CI přesného nasazeného main: [Current documentation 40914037135](https://github.com/KovoPraha/evidenceTreninku/actions/runs/40914037135), [Push on main 40914036628](https://github.com/KovoPraha/evidenceTreninku/actions/runs/40914036628), [PHP quality 40914037212](https://github.com/KovoPraha/evidenceTreninku/actions/runs/40914037212), [Testy 40914037413](https://github.com/KovoPraha/evidenceTreninku/actions/runs/40914037413). [Metadata](github-ci-main.json).
Deploy zopakoval 1799 testů / 35956 assertions PASS.
Linux POSIX, MariaDB 10.3/11.4, checkout souběh/idempotence, přímé porovnání
baseline/current, PHPStan, styl, anonymous sweep a JS prošly. Plné lidské UAT
z toho neplyne. Lokální limit PHPStan a RED/GREEN test generátoru jsou zaznamenány v protokolu.

## Provozní důkazy

Workflow: `potvrzeni=NASADIT`, `uat_schvaleno=false`, `importovat_krouzky=false`.
Záloha vytvořena a ověřena: 336 tabulek, 2 triggery,
6063507 komprimovaných bajtů, `evidence_2026-10-29_164652_2e7b4766.sql.gz`.
SHA-256 `1849d6d6a6ee63088e65a30ee9c998ac7c7c77604751b50f0f35e561449c42a8`. Obnova v tomto vydání neopakována.
Migrační kontroly current / pending []; variabilní symboly zachovány.
Dočasná kopie konfigurace odstraněna. [Bezpečné výstupy deploye](deployment.json).

Čtecí kontrola [před 40912230035](https://github.com/KovoPraha/evidenceTreninku/actions/runs/40912230035)
a [po 40915215872](https://github.com/KovoPraha/evidenceTreninku/actions/runs/40915215872)
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

W6.3 je technicky dokončeno. Navazuje K0.2/W7: měření osob, rodin a oprávnění.
Dávkové sdílení kapacit mezi nabídkami zůstává samostatný kandidát podmíněný
měřením konkrétního katalogu; tato změna ho nezavádí.
Lidské/účetní převzetí D12–D14, inventura, rodinné vazby, trenérské rozsahy,
dohody/částky a dvě přílohy zůstávají otevřené. Integrace/importy nebyly aktivovány.
