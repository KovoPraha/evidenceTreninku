# W7.2 — ověřené produkční vydání

29. 10. 2026, [kis.kovopraha.cz](https://kis.kovopraha.cz/).
[PR #229](https://github.com/KovoPraha/evidenceTreninku/pull/229) sloučen jako
`7000ec60007015d0ca0044190de7db68f6e25a46`, [deploy 40951722439](https://github.com/KovoPraha/evidenceTreninku/actions/runs/40951722439) SUCCESS.
Nová čtecí kontrola potvrdila stejné živé SHA a run ID. Vlastník autorizoval
autonomní implementaci, sloučení a nasazení po úspěšném ověření.

## Co bylo vydáno

Celý rodinný přehled načítá při opakované kontrole pouze aktuální osobu,
nikoli znovu všechny příbuzné. Aktuální autorizace každého profilu zůstává;
žádná cache ani změna W7.1b zápisových transakcí. U syntetické rodiny 20 osob
SQL celé stránky 2706 → 2326 (-14,0 %), načtené řádky 2953 → 2193 (-25,7 %).
Data všech sekcí i HTML jsou shodné po normalizaci nonce a času pozorování
salda. Bez změny UI, schématu, historie či finančního významu.
[Implementace, měření a jejich meze](../refactoring-w72-20261029/RESULTS.md).

CI přesného nasazeného main: [Current documentation 40951317765](https://github.com/KovoPraha/evidenceTreninku/actions/runs/40951317765), [Push on main 40951316661](https://github.com/KovoPraha/evidenceTreninku/actions/runs/40951316661), [PHP quality 40951317779](https://github.com/KovoPraha/evidenceTreninku/actions/runs/40951317779), [Testy 40951317762](https://github.com/KovoPraha/evidenceTreninku/actions/runs/40951317762). [Metadata](github-ci-main.json).
Deploy zopakoval 1814 testů / 36118 assertions PASS.
Linux POSIX, MariaDB 10.3/11.4, checkout souběh/idempotence, porovnání
rodinné stránky a souběžné revokace, PHPStan, styl, anonymous sweep a JS prošly. Plné lidské UAT
z toho neplyne. RED/GREEN rozpočet a charakterizace oprávnění jsou zaznamenány v protokolu.

## Provozní důkazy

Workflow: `potvrzeni=NASADIT`, `uat_schvaleno=false`, `importovat_krouzky=false`.
Záloha vytvořena a ověřena: 336 tabulek, 2 triggery,
6063525 komprimovaných bajtů, `evidence_2026-10-29_204624_914a2c5a.sql.gz`.
SHA-256 `8bfeb6545ea0857cbdba484d1dca86ad39cedc599b2dfadeb512d267cbae7f82`. Obnova v tomto vydání neopakována.
Migrační kontroly current / pending []; variabilní symboly zachovány.
Dočasná kopie konfigurace odstraněna. [Bezpečné výstupy deploye](deployment.json).
HTTP kontrola workflow: Runner TCP timeout; existing SSH server-side fallback HTTP 200; independent anonymous GET smoke 26/26.

Čtecí kontrola [před 40947584342](https://github.com/KovoPraha/evidenceTreninku/actions/runs/40947584342)
a [po 40952420283](https://github.com/KovoPraha/evidenceTreninku/actions/runs/40952420283)
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

W7.2 je technicky dokončeno. Další měření R02 má rozdělit náklady na jednotlivé
sekce a metadata; R03 žádosti a R04 seznamy registráře zůstávají samostatné.
Tento balík odstraňuje kvadratické načítání rodiny; netvrdí dokončení celého
W7 ani stabilní produkční latenci podle syntetického profilu.
Lidské/účetní převzetí D12–D14, inventura, rodinné vazby, trenérské rozsahy,
dohody/částky a dvě přílohy zůstávají otevřené. Integrace/importy nebyly aktivovány.
