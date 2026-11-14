# P11-PCAP — dávkové kapacity správy programů

14. 11. 2026, Europe/Prague. NASAZENO #479/c7b77fa528558ac18529112260941d926f58f401, testovaný/sloučený strom d1ed26133c5828e886a63ae4e6e9eb011538c891,15 CI PASS.
[Plán](../../club-programs.md#p11-pcap--dávkové-kapacity-správy-programů-14-11-2026),
[PR #479](https://github.com/KovoPraha/evidenceTreninku/pull/479).
Testovaný head6e8bf9068e9ab371cdc69353b4f39fb0bde136f6, baseline PADMIN/b0d2368d08f861444883a99a11e1f4205a2d0263;
[párový běh 43632235271](https://github.com/KovoPraha/evidenceTreninku/actions/runs/43632235271),[provenience](paired-run.json).

## Rozsah a parita

Nový ClubProgramCapacityRead dávkuje pouze kapacity správního seznamu po80
cílech, nejvýše480parametrů; nikdy nenačítá celé tabulky. Aktivní osoby
deduplikované, rezervace řazené interním numerickým ordinalem a konzumované
generátorem společným clubProgramCapacityFromRows. Překryvy, platby,
identity/quantity, vyřazené žádosti a přijaté položky mají stejné predikáty.
Pořadí fyzických soupisek a první stav při stejné obsazenosti jsou shodné.

Stránka, includes/club_program.php, pure výpočet a read context byte-shodné
s baseline. Public/scalar/checkout/locking API a práva beze změny, žádné
schéma/indexy nebo persistentní cache. Dávkový GET zachycuje jeden okamžik
pro expiraci, stejný predikát >= a Europe/Prague; explicitní hraniční test PASS.
Nové volání čte čerstvá data. Duplicitu read-only výběrových predikátů hlídají
testy přesných canonical SQL i celé výsledky pro legacy varianty schématu.

Native baseline je přesně zmrazený již optimalizovaný PADMIN reader z b0d2368d
(pouze přejmenovaná testová funkce, kontrola zdroje). HTTP před používá původní
soubor tohoto readeru. Neporovnáváme zde znovu původní pomalý scalar API.
Ten navíc slouží jako nezávislá strict referenční shoda. Historické výsledky
PADMIN/ASSET12 se nemění; jejich nynější CI je kumulativní regrese.

## Nativní před/po

Vlastněný syntetický plně migrovaný FullHttpReadFixture, MariaDB 10.3/11.4,
0/2/12/100/401, query cache off,4 warmup a17střídavých párů. Obyčejné PDO
pro čas/peak, SQL/řádky samostatně instrumentované. Strict celá pole/typy/
pořadí v každém páru; všech355 checksumů a přesná obnova fixture PASS.
Syntetická měření nejsou produkční latence ani SLA.

| MariaDB | Nabídek | Medián ms před → po | SQL před → po | Načtené řádky před → po | Peak přírůstek B před → po |
| --- | ---: | ---: | ---: | ---: | ---: |
| 10.3 | 0 | 0.282 → 0.290 | 1 → 1 | 0 → 0 | 39896 → 39896 |
| 10.3 | 2 | 5.888 → 5.418 | 14 → 12 | 130 → 130 | 104416 → 116632 |
| 10.3 | 12 | 16.939 → 9.640 | 40 → 12 | 196 → 196 | 197600 → 278376 |
| 10.3 | 100 | 114.362 → 58.460 | 262 → 16 | 758 → 758 | 1153960 → 1380448 |
| 10.3 | 401 | 481.785 → 248.697 | 1023 → 35 | 2685 → 2685 | 3810408 → 4053240 |
| 11.4 | 0 | 0.278 → 0.282 | 1 → 1 | 0 → 0 | 39896 → 39896 |
| 11.4 | 2 | 5.859 → 5.451 | 14 → 12 | 130 → 130 | 104416 → 116632 |
| 11.4 | 12 | 17.145 → 9.815 | 40 → 12 | 196 → 196 | 197600 → 278376 |
| 11.4 | 100 | 115.821 → 51.230 | 262 → 16 | 758 → 758 | 1153960 → 1380448 |
| 11.4 | 401 | 451.206 → 191.825 | 1023 → 35 | 2685 → 2685 | 3810408 → 4053240 |

Peak je přírůstek při volání, nikoliv celý RSS; úplné hodnoty jsou v tabulce.
Vyšší paměť dávky je tradeoff za méně SQL roundtripů, nikoli nová úspora RAM.
[Native10.3](mariadb-10.3/native.json.gz),[11.4](mariadb-11.4/native.json.gz).

## HTTP a Chrome

Na každé DB16 scénářů+prázdný přehled: správce 200,11 jiných rolí 403,
anonymní 302, editory a chybějící program. Kladné4 warmup/17 párů,
záporné 1 pár. Přesné HTML a bezpečnostní hlavičky kromě request CSP nonce,
CSRF a veškeré atributy/skripty zachované. Fixture při čtení100/0, po obnově100;
všech355 checksumů shodných, vlastněné schéma/účty/config/porty uklizené.
[HTTP10.3](mariadb-10.3/http.json.gz),[11.4](mariadb-11.4/http.json.gz),
[prázdný10.3](mariadb-10.3/empty-http.json.gz),[11.4](mariadb-11.4/empty-http.json.gz).

| MariaDB | Scénář | Medián HTTP ms před → po |
| --- | --- | ---: |
| 10.3 | program_coordinator | 93.923 → 56.275 |
| 10.3 | program-terms | 96.490 → 57.348 |
| 10.3 | offer-terms | 96.100 → 58.915 |
| 10.3 | missing-program | 95.116 → 57.154 |
| 10.3 | empty-overview | 4.861 → 4.854 |
| 11.4 | program_coordinator | 92.705 → 51.945 |
| 11.4 | program-terms | 94.884 → 53.107 |
| 11.4 | offer-terms | 96.889 → 54.205 |
| 11.4 | missing-program | 94.470 → 52.134 |
| 11.4 | empty-overview | 5.642 → 5.749 |

Skutečný Apache/Chrome přes Playwright CLI,176měřených+48 warmup navigací,
desktop1036×703light a mobile390×844dark, přehled/editor, cold/warm.
Čtyři full-page pixelové páry shodné; formuláře, geometrie a computed styles
stejné, Bootstrap JS dostupný, bez page/console/network chyb. CSS cold
31248→31248 B, warm 0→0 B,decoded 232803 obě strany; hash/SRI PASS.
Tento řez nedokládá novou CSS úsporu.

| Cesta/téma | Cache | FCP ms před → po | Ready ms před → po |
| --- | --- | ---: | ---: |
| overview / light | cold | 176 → 140 | 198.3 → 156.9 |
| overview / light | warm | 172 → 124 | 190.5 → 135.7 |
| overview / dark | cold | 184 → 136 | 202.2 → 156.9 |
| overview / dark | warm | 172 → 132 | 188.7 → 152.5 |
| terms / light | cold | 204 → 144 | 219.3 → 167.7 |
| terms / light | warm | 184 → 140 | 200.9 → 159.1 |
| terms / dark | cold | 200 → 160 | 219.1 → 180.8 |
| terms / dark | warm | 180 → 136 | 197.7 → 157.8 |

[Browser souhrn/snímky](mariadb-11.4/browser-summary.json),[HTTP mediány](http-medians.json).

## Kontroly a vydání

Lokálně7 testů/210 assertions, rozšířená sada34/406 PASS. První diferenciální
test odhalil chybějící fallback prázdné fyzické soupisky, opraven před přijetím.
79/80/81/160/161/401,2500 rezervací, překryvy/expirace/quantity/identity/tie,
legacy sloupce/tabulky, čerstvé GET a jiná PDO připojení zahrnuté.
Původní public/locking testy PASS; cílený PHPStan a PSR12/200/braces,
PHP/Python syntaxe, docs/diff PASS. [SQLite výběrová diagnostika](sqlite-selection.json)
100:212→16,401:823→33; není zdrojem nativní časové hodnoty.

[Chráněný deploy 43633315305](https://github.com/KovoPraha/evidenceTreninku/actions/runs/43633315305) SUCCESS,
2479 testů / 56904 assertions, ověřená záloha 394/2.
Konfigurace zachovaná, bez doplnění configu; pending migrace[], VS a zdravotní
index stejné, aktivace a úklid kopie configu PASS. Import přeskočený, UAT/import false.
Přímý runner HTTP 200, podporovaný smoke HTTP 200 PASS.
[Receipt](release.json). Nezávislé anonymní živé GET/CSS před/po přesně shodné
([před](production-before.json),[po](production-after.json)); přihlášené produkční
UAT, waterfall a produkční časový benchmark NOT RUN. Skutečné objednávky,
účasti, podmínky, osoby, role ani maily nebyly při ověřování měněné.
Výsledkové doklady samostatným PR, kvůli dokumentaci znovu nenasazovat.

Původní fio/d1221f35 a kis_rosters_admin.php SHA256
7a7afd75bfa34f736f158096ab46a671b7fee6c6faedc3701f989c0cf64733e5 zachované;
ostatní rozpracované PR/prototypy a blokátor náhradních lekcí se tímto neslučují.
Další kandidáti: velikost správního seznamu při doloženém objemu, styly
kalendáře/katalogové administrace; nejprve samostatný profil a přejímka.
