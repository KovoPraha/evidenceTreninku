# BAR: provozní přejímka a návrh nabídky, 6. 11. 2026

Tento protokol zachovává stav před zveřejněním. Po novém výslovném souhlasu vlastníka následovala [skutečná publikace nabídky 1 / vydání 1](../bar-menu-publication-20261106/RESULTS.md); další provozní kroky jsou v [session handoffu](../../SESSION_HANDOFF_BAR_2026-11-06.md).

## Výsledek

Technická přejímka skutečným přihlášeným prohlížečem nad oddělenými syntetickými daty PASS. V produkci je uložen jediný neveřejný návrh **Bar KOVO – základní nabídka**, ID **1**, zobrazení **Fotografie produktů**, **45/45** aktivních produktů nabízených formulářem. Aktuální katalogové ceny ani zásoby se při uložení nemění. Veřejná stránka po uložení stále zobrazovala **Nabídka se připravuje.** Nabídka nebyla zveřejněna.

Produkční zápis provedl vlastník v obnovené relaci `marek-mixa` přes běžné rozhraní Administrátor baru. Nakonec byl obnoven původní kontext **Správce systému**. Nevznikl produkční testovací prodej, kasa, skladový příjem, vratka, vlastní spotřeba ani účetní pohyb. Tato etapa nevyžaduje nové nasazení aplikačního kódu.

Návrh: <https://kis.kovopraha.cz/bar/offers.php?id=1> (v kontextu Administrátor baru). Veřejná obrazovka: <https://kis.kovopraha.cz/bar/menu.php>.

## Oddělená provozní zkouška

Výchozí strom `89e73d5a570631a42a113f563011c49a37809a68`, vlastní worktree a nová databáze `evidence_bar_test_browser_027ead70ef`, MariaDB na loopback:3327, PHP na loopback:8895. Všechny účty, produkty, potvrzení plateb a částky v následující tabulce jsou **syntetické**. Žádná komunikace s platebním poskytovatelem nebo poštou. Nový režim `BAR_BROWSER_OPERATIONAL_DEMO=1` ponechává obsluhu bez vlastní spotřeby a uděluje ji pouze testovacímu správci 7003. Původní checkout demo režim zůstává zachovaný.

| Scénář v prohlížeči | Uložený výsledek | Stav |
| --- | --- | --- |
| Přihlášení testovací Lucky a oddělení rolí | Obsluha baru; katalog správy odmítnut; vlastní spotřeba bez grantu odmítnuta | PASS |
| Vlastní kasa obsluhy | Kasa 1, vlastník 7004, počátek 1 000 Kč | PASS |
| Hotovostní prodej 1 | 2 čaje po 35 Kč + 2 kelímky po 20 Kč = 110 Kč; zásoba 20 → 18 | PASS |
| Vrácení jednoho kelímku a následné storno prodeje 1 | Vráceno 20 Kč, následně jen zbývajících 90 Kč; zásoba zpět 20; kasa zpět 1 000 Kč | PASS |
| Hotovostní prodej 2 bez storna | 35 Kč, zásoba 19; kasa 1 035 Kč | PASS |
| Čekající QR prodej 3 | Rezervace zásoby; pokus o uzávěrku odmítnut, kasa zůstala otevřená | PASS |
| Storno nevydaného QR prodeje 3 | Platba cancelled, vratka 0 Kč, zásoba obnovená na 19 | PASS |
| Kartový prodej 4 a ruční potvrzení s výslovně testovacím podkladem | Stav confirmed/manual, bez změny hotovosti | PASS |
| Storno kartového prodeje 4 s testovacím podkladem vratky | 35 Kč kartou; zásoba obnovená na 19, hotovost nezměněná | PASS |
| Uzávěrka s úmyslným schodkem | Očekáváno 1 035 Kč, spočteno 1 030 Kč, rozdíl −5 Kč s důvodem; kasa uzavřená | PASS |
| Vlastní spotřeba správce s explicitním grantem, doklad 5 | 1 čaj, 0 Kč, bez kasy a bez platebního záznamu; zásoba 19 → 18 | PASS |
| Storno vlastní spotřeby 5 | Vratka 0 Kč, zásoba zpět 19; uzavřená kasa beze změny | PASS |
| Oddělení od hlavního účetnictví | `accounting_movements` = 0 | PASS |

Konečný stav [synthetic-final-state.tsv](synthetic-final-state.tsv) byl načten přímo z izolované DB po posledním stornu: stánek 19, předsklad 0, hotovostní pohyby 103 500 haléřů, uzávěrka −500 haléřů, čtyři storna, jeden nestornovaný hotovostní prodej. Jediný grant vlastní spotřeby patří syntetickému správci 7003. Test neprokazuje zaplacení skutečnou kartou/QR ani vrácení peněz poskytovatelem.

## Důkazy

- [Uložený produkční návrh, všech 45 voleb a dosud prázdná publikace](bar-offer-draft-production-20261106.png).
- [Storno po částečném vrácení kelímků](bar-op-cup-void-20261106.png).
- [Odmítnutí uzávěrky s čekající platbou](bar-op-pending-close-blocked-20261106.png).
- [Uzavřená kasa s vysvětleným schodkem](bar-op-closed-difference-20261106.png).
- [Storno vlastní spotřeby bez platby](bar-op-consumption-void-20261106.png).

## Před zveřejněním a skutečným prodejem

1. Vlastník potvrdí výběr v již existujícím návrhu 1, případně jej upraví; nezakládat duplicitní návrh. Výběr vychází ze stávajících prodejních příznaků katalogu, zahrnuje také knihy. Publikace přepne současně veřejnou obrazovku i výběr v pokladně a přebírá aktuální katalogové ceny/popisy. Žádný pevný cenový snapshot nevzniká.
2. Původní přihlášení skutečné Lucky: **NOT RUN**, původní heslo se neměnilo ani nečetlo. Syntetický login výše není důkazem produkčního přihlášení Lucky.
3. Fyzická inventura a příjem skutečných zásob, skutečný počáteční vklad do vlastní kasy a první prodej obsluhou: **NOT RUN**. Převod B7 záměrně začínal bez zásob a historie; množství nelze odhadnout z katalogu. Produkční testovací doklady se nevytvářely.
4. Skutečné přijetí/vrácení bezhotovostní platby a lidská finanční přejímka: **NOT RUN**. Ruční testovací potvrzení nejsou automatická platební integrace.
5. Automatické účtování BAR: **NOT RUN / neaktivované**, nadále podle samostatného účetního kontraktu. Technický test jen ověřil, že nic nepřipsal do hlavního účetnictví.

Počáteční import ani striktní nulový cutover status neopakovat za účelem odstranění legitimního návrhu či budoucí historie. Produkční obnova podle B7 zůstává samostatnou existující evidencí. Provozní plán: [BAR_OPERATIONAL_ACCEPTANCE_2026-11-06.md](../../BAR_OPERATIONAL_ACCEPTANCE_2026-11-06.md).

## Kontroly a úklid

- `composer validate --no-check-publish` a syntaxe změněného PHP PASS.
- `composer test`: **2265 testů / 41972 assertions / 3 skipped**, bez selhání. Lokální Windows běh nenahrazuje linuxové kontroly přeskočených případů v CI.
- `composer style:check`: 1 změněný PHP soubor, 0 chyb; `composer analyse`: 898 souborů, 0 chyb.
- `php tools/check-current-docs.php`: po zachování souběžného handoffu #331 celkem 80 řádků CURRENT_STATE, 770 místních odkazů, 103 archivovaných bloků, 0 chyb; `git diff --check` PASS.
- Původní ignorovaný `config.php` vlastního worktree obnoven se shodným SHA-256. Vypnut pouze vlastní PHP server 8895 a vlastní MariaDB 3327 po ověření identity procesů; oba porty již neposlouchají. Syntetická DB i testovací konfigurace zůstávají v soukromém runtime pro případné zopakování. Původní uživatelský checkout a jeho rozpracovaný soubor nebyly měněny.
