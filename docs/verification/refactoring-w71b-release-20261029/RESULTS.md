# W7.1b — ověřené produkční vydání

29. 10. 2026, [kis.kovopraha.cz](https://kis.kovopraha.cz/).
[PR #227](https://github.com/KovoPraha/evidenceTreninku/pull/227) sloučen jako
`66521929f7e9ce55f7a03d669cb7821f49382f6c`, [deploy 40939479761](https://github.com/KovoPraha/evidenceTreninku/actions/runs/40939479761) SUCCESS.
Nová čtecí kontrola potvrdila stejné živé SHA a run ID. Vlastník autorizoval
autonomní implementaci, sloučení a nasazení po úspěšném ověření.

## Co bylo vydáno

RSVP, rezervace a storno náhrad znovu ověřují rodinné nebo sportovní
oprávnění po doménových zámcích, před první mutací. Aktuální zamykající čtení
vidí revokaci potvrzenou během čekání i přes starší snapshot a drží oprávnění
do commit/rollback. Před opravou 15 nepovolených zápisů ve 24 race scénářích;
po opravě 32/32 PASS lokálně i na MariaDB 10.3/11.4 v CI. Veřejné čtení,
pravidla přístupu, UI a schéma beze změny.
[Implementace a důkazy](../refactoring-w71b-20261029/RESULTS.md).

CI přesného nasazeného main: [Current documentation 40939142743](https://github.com/KovoPraha/evidenceTreninku/actions/runs/40939142743), [Push on main 40939144235](https://github.com/KovoPraha/evidenceTreninku/actions/runs/40939144235), [PHP quality 40939142856](https://github.com/KovoPraha/evidenceTreninku/actions/runs/40939142856), [Testy 40939142879](https://github.com/KovoPraha/evidenceTreninku/actions/runs/40939142879). [Metadata](github-ci-main.json).
Deploy zopakoval 1808 testů / 36095 assertions PASS.
Linux POSIX, MariaDB 10.3/11.4, checkout souběh/idempotence, souběžné
revokace, PHPStan, styl, anonymous sweep a JS prošly. Plné lidské UAT
z toho neplyne. RED/GREEN souběhová charakterizace oprávnění je zaznamenána v protokolu.

## Provozní důkazy

Workflow: `potvrzeni=NASADIT`, `uat_schvaleno=false`, `importovat_krouzky=false`.
Záloha vytvořena a ověřena: 336 tabulek, 2 triggery,
6063508 komprimovaných bajtů, `evidence_2026-10-29_192403_2a725d00.sql.gz`.
SHA-256 `4671007486e4ba98228f4b8e13c6185eb2159ec010bda5f5b0d44a896ca0fa17`. Obnova v tomto vydání neopakována.
Migrační kontroly current / pending []; variabilní symboly zachovány.
Runner měl TCP timeout; stávající serverový HTTP fallback přes SSH vrátil 200.
Nezávislý GET smoke zároveň prošel 26/26. Dočasná kopie konfigurace odstraněna. [Bezpečné výstupy deploye](deployment.json).

Čtecí kontrola [před 40936709856](https://github.com/KovoPraha/evidenceTreninku/actions/runs/40936709856)
a [po 40940284666](https://github.com/KovoPraha/evidenceTreninku/actions/runs/40940284666)
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

W7.1b je technicky dokončeno. Navazují úplné profily rodinného přehledu R02,
žádostí R03 a seznamů registráře R04. Tento balík uzavírá pouze tři uvedené
zápisové služby, nikoli audit všech writerů či celé W7.
Lidské/účetní převzetí D12–D14, inventura, rodinné vazby, trenérské rozsahy,
dohody/částky a dvě přílohy zůstávají otevřené. Integrace/importy nebyly aktivovány.
