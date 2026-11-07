# P11 — celé HTTP a prohlížeč, předání 7. 11. 2026

## Zadání a hranice

Autonomně změřit celé načtení včetně bootstrapu/navigace, dalších rolí a filtrů.
**Produkci neměnit, zachovat chování a původní WIP, výsledky ukládat do souborů.**
Base `feb8fe8c` / #354, větev `codex/full-http-performance-20261107`, kopie
`C:/Users/Marek/Documents/Codex/evidencePavel-full-http-performance-20261107`.
Původní `C:/xampp/htdocs/evidencePavel` se nepřepíná; WIP `kis_rosters_admin.php`
má při převzetí SHA256 `7a7afd75bfa34f736f158096ab46a671b7fee6c6faedc3701f989c0cf64733e5`.
Aplikační stránky ani produkční workflow se touto relací neupravují.

## Dokončený výsledek

[Výsledky, metoda, úplná matice a důkazy](verification/full-http-performance-20261107/RESULTS.md).
Měřená aplikace `feb8fe8c`, žádné app změny. Lokální PHP 8.2.12 / MariaDB 10.4.32 /
Chrome 157.0.8103.38, nové schéma 348 tabulek / 160 migrací, 52 týdnů plánů,
200 účastníků / 52 revizí a 10k zdravotních sledování / 30k záznamů.

- **65 HTTP případů**: 31 pozitivních × 5 zahřátí / 30 vzorků = **930 vzorků**,
  34 negativních; 11 aktuálních pozic + druhý trenér + anonymní přístup.
- 12 standardních HTTP loginů, čtyři skutečné UI loginy. Celé nezměněné
  vstupní stránky, bootstrap, živá session/role a navigace, žádné eval tělo nebo
  ručně podstrčená session. Filtry a propojené lekce ověřené.
- **31 DOM kontrol PASS**: plány, vybraných 200 osob, papírový původ,
  50/0 lekcí, zdravotní stránkování a bounded výběr osoby.
- Oddělená diagnostika všech 65 případů: celé HTML stejné jako prosté HTTP,
  **0 zápisových SQL**, stejné checksums 13 doménových tabulek. PHP plný request,
  SQL general log a dodatečný EXPLAIN. Fetched rows/SQL duration se tímto neměří.
- **403 browser pozorování**: všech 31 pozitivních případů × 3 cold / 10 warm
  po prime. Skutečný Chrome, native okno 1036×703 CSS px / DPR 1,25.
  Všechny statusy 200, navigace přítomná, Resource Timing v surových podkladech.
- Dvě čerstvé revokace: odstraněná role 403 / neaktivní účet 303 na expired
  login, původní celé řádky obnovené. Finální doménové checksums po browseru
  a obnovení role shodné.
- Samostatná CPU stopa a tři nativní CDP pozorování na dva případy oddělené
  od běžných časů. Přesný podíl režie automatizace nevyčíslený.
- Místní relevantní regrese **42 testů / 2852 assertions PASS**, styl a baseline
  budget PASS (497 / 2146 beze změny). Finální CI a merge jsou samostatné kroky;
  lokální nový HTTP/browser profil není vydávaný za Linux 10.3/11.4 běh.

## Praktické nálezy a další priority

1. **R06 velký výběr osob:** 10221 dostupných osob, cca 1,37 MB HTML.
   Trenérská docházka HTTP medián 0,81 s, browser load 6,18 s; širší hledání
   load 6,87 s / 203 SQL. Navrhnout omezený výběr s hledáním při zachování
   všech dostupných účastníků, aktuálních voleb, původu a původního formuláře.
   Stejná data a browser/native kontrola před/po; žádné oprávnění necachovat.
2. **R06 lekce:** posoudit 50 jednotlivých čerstvých kontrol a 203 SQL.
   Vyšší SQL count sám nedokládá časovou kauzalitu; writer a zámky zachovat.
3. **R13/R05:** stavový filtr stále vyhodnocuje kandidáty pro úplný počet;
   historický hierarchický filtr načítá ID/data. Další objemy a skutečné rows
   examined posuzovat samostatně, EXPLAIN je odhad.
4. Browser měl 93 měřených 404 pouze na localhost favicon; žádná jiná chybová
   URL/failed request. Plánovač/login mají vedle lokální sady externí Bootstrap
   CSS. Jsou to malé oddělené kandidáty, nyní neopravované; favicon se netvrdí
   jako produkční závada.
5. Fyzický mobil, CPU/síťové omezení, souběh a další R01–R16 jsou samostatné
   úkoly; celé K0.2 neuzavřené. Lokální vývojový server bez TLS/gzip a produkční
   OPcache není produkční SLA. První obsah může přijít dříve než load.

## Úklid a předání

[Úklid](verification/full-http-performance-20261107/cleanup.json): vlastní
`p11_read_38a870931ab87cc3`, DB účet, vytvořený hashově ověřený config a lokální
credentials/cookie jars odstraněné. Browser zavřený, vlastní HTTP/MariaDB
zastavené, porty 18094/33449 neposlouchají. Runtime
`C:/xampp/tmp/kis-httpperf-20261107-edbb462e` ponechaný zastavený; žádné rekurzivní
mazání starších adresářů. Původní WIP při závěrečné kontrole stejný SHA256.

Bez deploye, produkčních dat/konfigurace, aktivací nebo transportu. P05/P07,
export P06, heslo/T07 a ranní audit jiné relace se neopakovaly. Produkční stav
nadále výhradně [CURRENT_STATE](CURRENT_STATE.md), který se touto relací nemění.
[Reprodukce](verification/full-http-performance-20261107/REPRODUCE.md),
[kalibrace](verification/full-http-performance-20261107/CALIBRATION.md),
[průběžný plán](WORK_PLAN_CURRENT.md), [předchozí R06](SESSION_HANDOFF_ATTENDANCE_READ_PERFORMANCE_2026-11-07.md).
