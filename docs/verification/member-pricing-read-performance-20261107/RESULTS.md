# P11 — dávkové klubové ceny: výsledek a měření 7. 11. 2026

**Implementace, shoda cen a párová měření hotové. Bez nasazení.**
Pro 500 produktů se společnými kategoriemi kleslo čtení cen **2000 → 4 SQL**,
tedy o **99,8 %**, a načtené řádky **2500 → 504**. Medián této cenové části
na MariaDB 10.3/11.4 klesl **557,00 → 3,21 ms / 377,49 → 2,88 ms**.
Všechna pole variant včetně typů a zdrojových popisků jsou shodná.
To není zrychlení celé stránky ani produkční SLA.

[Plán a přejímka](../../MEMBER_PRICING_READ_PERFORMANCE_2026-11-07.md),
[session handoff](../../SESSION_HANDOFF_MEMBER_PRICING_READ_PERFORMANCE_2026-11-07.md),
[PR #358](https://github.com/KovoPraha/evidenceTreninku/pull/358).
Výchozí main `feb8fe8c`, vlastní větev `codex/member-pricing-read-performance-20261107`,
kopie `C:/Users/Marek/Documents/Codex/evidencePavel-member-pricing-performance-20261107`.
Plán uložený v commitu `911de8e1`, měřená implementace `0890e496`.
Pozdější změny přidávají důkazy a regresi nad skutečnými katalogovými kartami;
nemění měřený aplikační reader. Původní WIP zachované.

## Co se změnilo

- [Reader](../../../includes/shop_member_pricing_read.php) pro jednu operaci
  načte způsobilé soupisky jednou, produktové ceny a kategorie dávkově po
  500 produktech. Produkty se stejnou uspořádanou sadou kategorií sdílejí
  jedno načtení pravidel. Pravidlové dávky mají nejvýše 80 větví / běžně 800
  parametrů; jediný větší původní vstup se zachová bez tichého oříznutí.
- Porovnání měn produktových cen zůstává v SQL. Pravidlové UNION větve
  zachovávají původní predikát a pořadí vstupů; nevzniká PHP náhrada
  databázové kolace nebo join různě porovnávaných sloupců.
- [Katalog](../../../booking/eshop.php) a [detail](../../../booking/produkt.php)
  používají nový reader. Filtry/řazení/stránka se stále počítají až ze stejných
  úplných cen, měsíční tarify se stejně jako dříve nepřepočítávají.
- Každé volání čte aktuální účet, vazby a pravidla. Žádná statická datová cache
  nebo sdílená cache oprávnění. V transakci wrapper použije původní cestu.
- Kanonický [cenový helper](../../../includes/shop_member_pricing.php)
  a [checkout](../../../includes/shop_checkout.php) jsou beze změny.
  Jejich nové ověření oprávnění a cen se nenahrazuje dřívějším čtecím snapshotem.

První studená kontrola dostupnosti schématu přidává jeden SQL. Níže jsou
zahřáté počty, měřené shodně před/po. Rozpočet pro 500 variant/produktů
se stejnými kategoriemi je tedy 4 SQL, případně 5 se studenými metadaty.
Rozdílné kategorie a větší katalog přidávají omezené dávky; neslibujeme čtyři
dotazy pro libovolný počet kategorií či produktů. Hranice 500/501 produktů
a 80/85 různých kategorických požadavků jsou v regresích.

## Stejná data před / po na podporovaných MariaDB

CI [42358282429](https://github.com/KovoPraha/evidenceTreninku/actions/runs/42358282429),
PHP **8.2.35**, MariaDB **10.3.39 / 11.4.13**, nativní prepared statements.
Každá verze měří obě implementace na stejném serveru, stejných datech a PDO.
Pět zahřátí na fázi, potom 30 vzorků na fázi se střídáním pořadí před/po.
Celkem **9 případů × 2 fáze × 30 = 540 časových vzorků na verzi**, tedy
**1080 CI vzorků**. Medián je průměr prostředních dvou, p95 nearest-rank
(29. z 30). Zvlášť se měří SQL, vrácené řádky a reprezentativní EXPLAIN.
JSON/otisky se počítají mimo měřený interval; časová PDO nejsou instrumentovaná.

Fixture používá minimální syntetické závislosti a skutečnou migraci cen
`20260805010000_shop_member_pricing`. Dvě způsobilé soupisky, jedna společná
kategorie, procentní a pevná sleva, rozdílné ceny variant. Případy měří
12/100/500 variant jednoho produktu i stejné počty různých produktů.
Další tři případy mají účet bez vazby, neověřený účet a anonymní ID.
V MariaDB běží měřicí PDO v `TRANSACTION READ ONLY`; vlastní náhodná schémata
se po každém případu odstraní. Produkční config, dump a osobní data nepoužity.

| MariaDB | Produktů | SQL před → po | Načtené řádky | Medián ms před → po | p95 ms před → po |
| --- | --- | --- | --- | --- | --- |
| 10.3 | 12 | 48 → 4 | 60 → 16 | 13,41 → 1,24 | 13,66 → 1,27 |
| 10.3 | 100 | 400 → 4 | 500 → 104 | 111,73 → 1,60 | 117,96 → 1,83 |
| 10.3 | 500 | 2000 → 4 | 2500 → 504 | 557,00 → 3,21 | 572,98 → 4,33 |
| 11.4 | 12 | 48 → 4 | 60 → 16 | 9,10 → 0,84 | 9,46 → 0,89 |
| 11.4 | 100 | 400 → 4 | 500 → 104 | 75,86 → 1,29 | 77,01 → 1,41 |
| 11.4 | 500 | 2000 → 4 | 2500 → 504 | 377,49 → 2,88 | 389,76 → 3,21 |

U 500 variant **jednoho** produktu: stejné 2000 → 4 SQL, řádky **2500 → 5**,
medián 10.3 **556,48 → 2,06 ms**, 11.4 **377,60 → 1,32 ms**.
Přírůstek špičky PHP paměti při 500 různých produktech **902568 → 776968 B**,
u 500 variant jednoho produktu **575160 → 437496 B**, shodně na obou verzích.
Nejde o celkovou paměť procesu ani počet řádků prohledaných databází.

Účet bez způsobilých soupisek / neověřený účet: **500 → 1 SQL**, všechna
veřejná data a ceny shodné. Anonymní pomocný případ má před/po nula SQL
a malou CPU režii navíc; skutečná anonymní stránka nový reader vůbec nevolá.
Malé CLI časy tohoto případu se nevydávají za přínos pro anonymní stránku.

Časy jsou cenová operace v jednom zahřátém CLI procesu se syntetickými daty,
nikoli celé HTTP, bootstrap, navigace, vykreslení v browseru nebo produkční
latence. Nativní prepared režim měřiče je uveden výslovně; produkční PDO
režim ani jeho konkrétní čas tímto nebyl změřen. Zrychlení 131–174× u 500
produktů patří pouze k této měřené cenové části a fixture, ne celé aplikaci.

## Parita a regrese

[MariaDB 10.3 parita](ci-10.3/member-pricing-parity.json) a
[11.4 parita](ci-11.4/member-pricing-parity.json): **39 kontrol na každé verzi PASS**.
Shodné celé varianty, typy, pořadí i popisky při:

- neověřeném/neaktivním účtu, chybějící nebo odvolané vazbě, začátku a konci
  platnosti, více osobách/soupiskách a jejich deduplikaci, neaktivním týmu;
- produktové výjimce, více kategoriích, neaktivním pravidle, rozdílných měnách,
  různých veřejných cenách, remízách stejného týmu a při změnách mezi voláními;
- skutečné kolaci `utf8mb4_unicode_ci` a kategoriích v `utf8mb4_bin`:
  `ÓBLÉČENÍ ` / `Oblečení`, rozdílné case, diakritice a koncových mezerách;
  měna produktové ceny `cZk` a vstupy `CZK`, `CZK `, `czk`;
- transakci: původní a nová cesta mají **identické SQL včetně pořadí**,
  dříve zobrazená sleva se po změně pravidla nerecykluje.

[Cílený JUnit](targeted-tests.xml): **20 testů / 301 assertions PASS**.
Vedle výše uvedeného ověřuje nulové/záporné vstupní ceny, zaokrouhlení,
ořez slevy na nulu, tarify, schéma bez cen, dávkové hranice, aktuální kanonický
helper proti zmrazenému původu a skutečné karty `shopPublicCatalogCards()`.
Do citlivých controllerů nevznikl nový POST ani nový webový endpoint.

[Úplné Linux PHPUnit nad měřenou implementací](linux-phpunit.txt):
**2321 testů / 42987 assertions PASS**, dále unixové hranice soukromého
úložiště **3 / 45 PASS**. Následná doplňující regrese skutečných karet má
lokální cílený PASS; nejnovější plný běh je dohledatelný u PR.
[Všech 11 kontrol měřeného commitu](ci-checks-runtime.json) PASS: obě MariaDB
smoke i širší profily, PHPUnit, CodeQL, styl, PHPStan a dokumentace.
PHPStan baseline snížen o jednu neplatnou výjimku (2146 → 2145 nálezů),
žádná nová potlačení. Místní PHPStan i kontrakt, styl osmi PHP souborů,
Composer validate a diff check PASS. Závěrečné odkazy a zdrojové otisky
ověřuje [kontrolní souhrn](verification.json).

## Důkazy a reprodukce

- [Srovnání 10.3](ci-10.3/member-pricing-comparison.json),
  [srovnání 11.4](ci-11.4/member-pricing-comparison.json): všechny časové vzorky,
  paměť, počty a skupiny SQL, EXPLAIN, otisky dat i zdrojů a úklid devíti schémat.
- [SQLite srovnání](sqlite-comparison.json): dalších 540 místních vzorků,
  stejné SQL rozpočty a shodné výstupy. Nejde o MariaDB časový ekvivalent.
- [Zmrazený původ](../../../tests/Support/MemberPricingReadBaseline.php),
  [fixture](../../../tests/Support/MemberPricingReadFixture.php),
  [měřič](../../../tests/Support/MemberPricingReadProfile.php),
  [MariaDB regrese](../../../tests/Support/MemberPricingReadMariaDbSmoke.php).

```powershell
php vendor/bin/phpunit --filter 'MemberPricingCatalogReadTest|ShopMemberPricingTest'
php tests/Support/MemberPricingReadProfile.php
# MariaDB pouze na vlastní izolované instanci; ověřuje skutečný port a datadir:
$env:EVIDENCE_PERFORMANCE_CONFIRM='SYNTHETIC-ONLY'
$env:EVIDENCE_MARIADB_SMOKE_PORT='3306'
$env:EVIDENCE_PERFORMANCE_DATADIR='/var/lib/mysql'
php tests/Support/MemberPricingReadProfile.php --mariadb
```

Pro MariaDB 10.3/11.4 použít přidané kroky testovacího workflow; původní
a nová cesta se měří společně, nenavazuje se na staré časy jiného stroje.

## Lokální kalibrace, úklid a zbývající práce

[Místní prostředí a neuzavřené běhy](local-environment.json), [úklid](cleanup.json).
První vlastní MariaDB 10.4.32 uvázla při DDL v InnoDB native AIO latch,
ještě před měřením. Vlastní náhradní native-off instance byla při potvrzených
CI výsledcích zastavena spolu s nedokončenou místní paritou. Místní úplný
Windows suite byl přerušen po více než 1098 testech s platformními skipy.
Tyto místní běhy nejsou PASS a nepoužívají se jako výkonový důkaz; úplné
Linux CI a podporované MariaDB jsou doložené výše. Nový testovaný reader
nevznikal v produkční databázi ani v databázi jiné aplikace.

Obě vlastní lokální služby zastavené, na portu 33447 nezůstal žádný vlastní
testovací server. Automatická kontrola odmítla rekurzivní úklid dvou vlastních
datadirů s důvodem **blocked by policy**. Úklid se neobcházel, adresáře
zastavených syntetických instancí zůstaly; přesné cesty jsou v cleanup.json.
Původní WIP má shodný SHA256 jako při zahájení.

Další samostatné kandidáty vede plán: katalogový výřez před tvorbou karet,
přihlášené celé HTTP/bootstrap/nav, souběh session a browser metriky.
Celé K0.2 není tímto uzavřené. **Žádný deploy, migrace, aktivace integrace,
provozní zápis, platba ani pošta.** Aktuální produkci nadále určuje pouze
[CURRENT_STATE](../../CURRENT_STATE.md); PR/CI samo nic nenasazuje.
