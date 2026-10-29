# W7.1a — ověřené produkční vydání

29. 10. 2026, [kis.kovopraha.cz](https://kis.kovopraha.cz/).
[PR #225](https://github.com/KovoPraha/evidenceTreninku/pull/225) sloučen jako
`81544542bb40cb96e7141791ea22e4b382531d5e`, [deploy 40930536923](https://github.com/KovoPraha/evidenceTreninku/actions/runs/40930536923) SUCCESS.
Nová čtecí kontrola potvrdila stejné živé SHA a run ID. Vlastník autorizoval
autonomní implementaci, sloučení a nasazení po úspěšném ověření.

## Co bylo vydáno

Kontrola oprávnění k jedné osobě čte cílenou existenci approved self/guardian
vazby aktivního ověřeného účtu se stejnými časovými hranicemi a JOIN.
Společný SQL fragment zachovává podmínky seznamu; žádná cache oprávnění.
Na migrovaném syntetickém schématu s 20 členy rodiny SQL kleslo 24 → 4,
načtené datové řádky 40 → nejvýše 1. Jde o profil služby, ne celé stránky.
Bez změny schématu, zápisových transakcí nebo UI.
[Implementace a měření](../refactoring-w71-20261029/RESULTS.md).
Součástí zdroje je také mapa 16 cest a charakterizace z PR #220.

CI přesného nasazeného main: [PHP quality 40930090645](https://github.com/KovoPraha/evidenceTreninku/actions/runs/40930090645), [Current documentation 40930090635](https://github.com/KovoPraha/evidenceTreninku/actions/runs/40930090635), [Testy 40930090693](https://github.com/KovoPraha/evidenceTreninku/actions/runs/40930090693), [Push on main 40930089391](https://github.com/KovoPraha/evidenceTreninku/actions/runs/40930089391). [Metadata](github-ci-main.json).
Deploy zopakoval 1804 testů / 36058 assertions PASS.
Linux POSIX, MariaDB 10.3/11.4, checkout souběh/idempotence, přímé porovnání
baseline/current, PHPStan, styl, anonymous sweep a JS prošly. Plné lidské UAT
z toho neplyne. RED/GREEN rozpočet SQL a charakterizace oprávnění jsou zaznamenány v protokolu.

## Provozní důkazy

Workflow: `potvrzeni=NASADIT`, `uat_schvaleno=false`, `importovat_krouzky=false`.
Záloha vytvořena a ověřena: 336 tabulek, 2 triggery,
6063508 komprimovaných bajtů, `evidence_2026-10-29_182649_20be62ad.sql.gz`.
SHA-256 `06a103e555f36663a1372832cc138ca8c09417113893020359ae37aa362f4158`. Obnova v tomto vydání neopakována.
Migrační kontroly current / pending []; variabilní symboly zachovány.
Dočasná kopie konfigurace odstraněna. [Bezpečné výstupy deploye](deployment.json).

Čtecí kontrola [před 40928634154](https://github.com/KovoPraha/evidenceTreninku/actions/runs/40928634154)
a [po 40931427289](https://github.com/KovoPraha/evidenceTreninku/actions/runs/40931427289)
PASS, obě v read-only session bez doménových mutací.
[Před](pre-release.json), [po](post-release.json), [porovnání](release-comparison.json).
Konfigurace, flags, ledger 141, KIS canonical writes a stav příloh shodné.
Fio import/auto-confirm, SumUp, Stripe, Velocota vypnuté; Packeta nedefinovaná.
KIS canonical writes false. Privátní podklady: 0 přesunutelných, 2 historicky
chybějící, 0 chyb. Identitu dokládají deployment metadata, ne nový soupis hashů
všech runtime souborů; stará pole diagnostiky nejsou novou runtime inventurou.

GitHub runner měl TCP timeout; existující fallback ověřil HTTP 200 ze serveru.
Nezávislé následné GET kontroly níže prošly bez této síťové cesty.

[HTTP před](pre-http.json) a [po](post-http.json): 26/26 PASS, stejné produkty.
[Skript](http-smoke.py) používá anonymní GET bez cookies a POST, kontroluje
homepage, katalog/detail, přihlášení, čtyři rodinné stránky a neveřejné cesty. Tři opakování
katalogu/detailů jsou orientační HTTP pozorování, nikoli důkaz zrychlení nebo
produkční zátěžový test. Žádná skutečná objednávka, registrace nebo platba.

## Další práce a otevřené hranice

W7.1a je technicky dokončeno. Navazuje W7.1b: race charakterizace revokace
oprávnění během čekání writerů RSVP/náhrad na zámek a případná oprava.
Tento balík prokazuje čerstvou kontrolu mezi voláními, nikoli bezpečnost již
běžícího writeru při souběžné revokaci. R02–R04 dále potřebují úplné profily.
Lidské/účetní převzetí D12–D14, inventura, rodinné vazby, trenérské rozsahy,
dohody/částky a dvě přílohy zůstávají otevřené. Integrace/importy nebyly aktivovány.
