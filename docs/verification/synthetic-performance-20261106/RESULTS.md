# P11 / K0.2 — syntetické měření výkonu 6. 11. 2026

## Praktický výsledek

- Největší načítání navíc má správní přehled lékařských podkladů R13: při
  10 000 syntetických sledováních vrací stránku 25 položek, ale načte **50 224
  řádků**. Filtr na jedinou osobu stále načítá celý seznam osob pro formulář.
- Plánovač R05 provádí **112 / 203 SQL** pro trenéra / vedoucího i při výřezu
  sedmi / čtrnácti plánů. Opakované kontroly metadat a načítání RSVP po plánech
  jsou doložený kandidát další práce; delší historie zvyšuje načtené řádky.
- Čtení uzavřené docházky R06 roste **54 → 244 SQL** při 10 → 200 účastnících,
  především kvůli ověření jednotlivých osob. Historie oprav má menší vliv.

**Místní měření hotové: 21 případů, 630 časových vzorků a 21 oddělených SQL
měření.** Výsledková data i HTML po normalizaci jediné procesní CSP nonce
jsou mezi běžným a instrumentovaným PDO shodné, v každém běhu čtecí session.
Čtyři negativní autorizační případy správně odmítnuté. Nejde o produkční SLA,
celé HTTP/browser měření ani dokončení K0.2 celé aplikace. Žádná optimalizace
aplikačního kódu, migrace, produkční operace nebo aktivace nevznikla.

## Zadání a pracovní kopie

Vlastník výslovně zadal autonomní měření podle plánu na syntetických datech,
bez změn produkce, se zachováním WIP a průběžným souborovým předáním.
Výchozí aplikační strom `1d0e974798cb199344cbfb595f4805509582a788`, vlastní větev
`codex/synthetic-performance-20261106`, kopie
`C:/Users/Marek/Documents/Codex/evidencePavel-performance-20261106`.
Původní `C:/xampp/htdocs/evidencePavel` se nepřepínala ani nepřepisovala;
SHA256 `kis_rosters_admin.php` před i po měření:
`7a7afd75bfa34f736f158096ab46a671b7fee6c6faedc3701f989c0cf64733e5`.
P05/P07, export P06, heslo/T07 i ranní audit jiné relace zůstávají beze změny.

## Metoda a skutečně měřené hranice

[Shrnutí a všechny vzorky](summary.json), [tabulka CSV](measurements.csv),
[prostředí/úklid](local-environment.json), [manifest metody](method-manifest.json).
Vlastní MariaDB **10.4.32**, PHP **8.2.12**, Windows, InnoDB buffer pool
128 MiB, limit PHP 512M. Náhodné schéma na vlastním loopback portu 33446,
**347 syntetických tabulek / 160 záznamů migračního ledgeru**: celý aktuální
migrační katalog nad minimálním legacy bootstrapem, nikoli kopie 387 produkčních
tabulek. Doplněné pouze popsané minimální legacy sloupce/tabulky pro tyto GET.
Žádný produkční config, dump, osobní příloha nebo připojení k poskytovateli.

Skutečné čtecí tělo a šablona `planovac.php`, `training_closure_admin.php`,
`medical_clearance_admin.php`; u R06 i skutečný `training_day_view.php`.
Bez původního databázového/session bootstrapu, společné navigace a vkládání
sdílených assetů. Syntetické přihlášení, skutečné doménové kontroly role
(R05 navíc výslovný čerstvý coachTrainingRequireActor guard). Žádný POST.
Měřicí PDO session má `SET SESSION TRANSACTION READ ONLY`; každý zachycený
SQL příkaz je SELECT/SHOW. V měřeném těle zůstávají jeho doménové guardy; původní security bootstrap se
nezahrnuje. Mimo měřené tělo se ustaví řízený syntetický kontext.

Čas/paměť: obyčejné PDO, **5 zahřátí + 30 vzorků v samostatném procesu pro
každý případ**; medián a nearest-rank p95 (29. z 30 seřazených hodnot).
Peak je maximální přírůstek používané PHP paměti v měřeném těle; zvlášť je
zaznamenaný procesní alokovaný peak, nejvýše 50 MiB. Kontrolní SHA/JSON se
počítají až po zachycení času a peaku. Samostatný instrumentovaný proces má
pět zahřátí a jedno SQL měření: execute/fetch čas, skutečně načtené řádky a
EXPLAIN pro první reprezentativní parametry každé unikátní SELECT šablony.
Jde o načtené řádky, nikoli serverem prohledané řádky. CLI eval zahrnuje
kompilaci vybraného těla; není to web s produkčním OPcache ani síťovou latencí.
Host nebyl vyhrazený pro benchmark, proto samotné rozdíly časů mezi objemy
neznamenají zrychlení/zpomalení. Rozhodující nálezy stojí na SQL a řádcích.

Fixture R05: 1/12/52 týdnů historie, 14 plánů týdně dvou trenérů, 20 očekávaných
členů soupisky. Zobrazuje se aktuální týden: trenér 7 plánů, vedoucí 14.
R06: 10/50/200 skutečných účastníků × 1/12/52 revizí papírového uzavření,
pevný základní výběr 221 osob. **Uzavřený trénink bez vazby na konkrétní plán**,
standardní vyhledání lekcí zůstává součástí GET. R13: 100/1000/10000 sledování,
tři stavové metadata záznamy na sledování (žádný soubor), stránka 25 řádků,
celý seznam a filtr konkrétní osoby. Stavové datum je den běhu.

## Výsledky místního běhu

| Cesta a objem | Role / filtr | SQL | Načtené řádky | Medián ms | p95 ms | Přírůstek paměti MiB |
| --- | --- | ---: | ---: | ---: | ---: | ---: |
| R05 / 1 týdnů | trenér | 112 | 414 | 185.84 | 488.27 | 0.37 |
| R05 / 1 týdnů | vedoucí | 203 | 782 | 208.50 | 372.14 | 0.46 |
| R05 / 12 týdnů | trenér | 112 | 491 | 134.78 | 182.44 | 0.39 |
| R05 / 12 týdnů | vedoucí | 203 | 936 | 191.12 | 250.85 | 0.54 |
| R05 / 52 týdnů | trenér | 112 | 771 | 151.09 | 272.48 | 0.52 |
| R05 / 52 týdnů | vedoucí | 203 | 1496 | 188.92 | 366.09 | 0.78 |
| R06 / 10 osob, 1 revizí | trenér | 54 | 290 | 37.91 | 78.28 | 0.27 |
| R06 / 10 osob, 12 revizí | trenér | 54 | 301 | 40.97 | 50.34 | 0.27 |
| R06 / 10 osob, 52 revizí | trenér | 54 | 341 | 45.45 | 58.17 | 0.29 |
| R06 / 50 osob, 1 revizí | trenér | 94 | 410 | 39.67 | 60.93 | 0.27 |
| R06 / 50 osob, 12 revizí | trenér | 94 | 421 | 37.97 | 52.54 | 0.27 |
| R06 / 50 osob, 52 revizí | trenér | 94 | 461 | 41.62 | 58.93 | 0.29 |
| R06 / 200 osob, 1 revizí | trenér | 244 | 860 | 76.36 | 147.71 | 0.28 |
| R06 / 200 osob, 12 revizí | trenér | 244 | 871 | 72.94 | 102.24 | 0.29 |
| R06 / 200 osob, 52 revizí | trenér | 244 | 911 | 105.13 | 150.73 | 0.31 |
| R13 / 100 sledování | registrář | 6 | 724 | 18.42 | 23.46 | 0.60 |
| R13 / 100 sledování | registrář / jedna osoba | 6 | 328 | 13.64 | 23.20 | 0.26 |
| R13 / 1000 sledování | registrář | 6 | 5224 | 121.04 | 205.54 | 4.41 |
| R13 / 1000 sledování | registrář / jedna osoba | 6 | 1228 | 35.45 | 49.59 | 0.76 |
| R13 / 10000 sledování | registrář | 6 | 50224 | 456.70 | 803.10 | 42.79 |
| R13 / 10000 sledování | registrář / jedna osoba | 6 | 10228 | 130.39 | 454.54 | 6.09 |

Každý případ má detail `01-R05.json` až `21-R13.json`: všech 30 vzorků,
SQL seskupení a EXPLAIN, velikost HTML, ověřená čtecí session a otisky.
R13 / 10k / celek: 25 zobrazených položek, asi 682 kB HTML, 42,79 MiB přírůstku.
R13 / 10k / jedna osoba: jeden podklad, asi 679 kB HTML, 6,09 MiB přírůstku;
většinu rozhraní stále tvoří načtený výběr všech osob. Počet dotazů sám zde
není vhodná metrika. U plánovače je téměř polovina SQL kontrolami existence
metadat; profily zároveň ukazují opakované čtení RSVP pro jednotlivé plány.

## Přenositelnost a opakování

[Zmrazená přesně provedená místní metoda](local-harness.php.txt) odpovídá SHA
v místním summary. [Aktivní přenositelný CLI profil](../../../tests/Support/SyntheticReadPagesProfile.php)
se liší pouze povinným deklarováním localhost portu/datadiru a nastavením
časové zóny session před měřením a záznamem skutečné revize checkoutu/otisků
zdrojů mimo měřené tělo. Všechna měřicí, seedovací a úklidová těla
jsou bajtově ověřená jako shodná (manifest obsahuje jejich SHA256).
Odmítnutý chybný datadir a chybějící potvrzení byly ověřeny před tvorbou schématu.

Příklad nad **vlastním nově inicializovaným a spuštěným** lokálním serverem:

```powershell
$env:EVIDENCE_PERFORMANCE_CONFIRM='SYNTHETIC-ONLY'
$env:EVIDENCE_MARIADB_SMOKE_PORT='33446'
$env:EVIDENCE_PERFORMANCE_DATADIR='C:/cesta/vlastni-synteticke-mariadb/data'
New-Item -ItemType Directory -Path output/p11-repeat
php -d memory_limit=512M tests/Support/SyntheticReadPagesProfile.php --run output/p11-repeat
```

Nikdy neukazovat na existující produkční či vývojovou databázi, nepoužívat
produkční přihlašovací údaje. Profil čte pouze root účet bez hesla přes
127.0.0.1 na výslovně zadaném vlastním testovacím serveru, vytváří náhodné
schéma `p11_read_<nonce>` a v finally vrací transakci před jeho odstraněním.

Testovací workflow `tests.yml` doplňuje stejný profil do existující izolované
matice MariaDB **10.3 / 11.4** (žádné produkční workflow). Každá verze musí
vrátit complete=true, všech 21 případů, čtyři negativní kontroly a úklid.
Nezavádí se časový SLA práh závislý na runneru. CI ukládá JSON/EXPLAIN jako
`synthetic-read-pages-mariadb-<verze>`; skutečný výsledek určuje odkazované PR/CI,
nikoli tato příprava. Přesná lokální čísla se za Linux CI čísla nevydávají.

## Zbývající úkoly podle priority

| Priorita | Další konkrétní práce | Co zachovat / mez dokončení |
| --- | --- | --- |
| 1 | R13: navrhnout skutečné omezení načítání stavů pro stránku a nahradit kompletní výběr osob cíleným vyhledáváním. | Shodné datum/stavy včetně nahrazení a odvolání, úplné filtry a počty, oprávnění, pořadí a vybraná osoba. Samotný index neodstraní načítání všech vyžádaných řádků. Implementace zatím není součástí této relace. |
| 2 | R05: oddělit metadata dotazy a zkusit request-scope metadata / dávkové načtení RSVP pro aktuální plány. | Nikdy necachovat aktuální oprávnění, osoby nebo POST validaci; srovnat vlastní/cizí plány, statistiky, zrušení a kopírování. Současná historiová výstraha je neomezené čtení ID/dat i při týdenním výřezu. |
| 3 | R06: charakterizovat dávkové ověření existence účastníků namísto jednoho SELECT na osobu. | Zachovat aktuální roli a autorizační pořadí, cizí trénink odmítnout, papírový původ/verze/historie shodné. Neodstraňovat transakční guardy writeru kvůli GET profilu. |
| 4 | Rozšířit profil o plánem propojenou docházku, další role/filtry a samostatné plné HTTP/bootstrap/nav/browser měření. | Tato čísla nejsou celý request ani úplný audit všech R01–R16. Předchozí doménové důkazy platí ve svém datovaném rozsahu. |
| 5 | Po změně opakovat tutéž syntetickou matici a porovnat s původem na stejném stroji/verzi. | Teprve změřený přínos se shodným výsledkem opravňuje návrh další změny. Nasazení či produkční UAT je samostatný úkon a nyní se neprovádí. |

P02/P04/P08/P09/P12 a odložené P05/P07/P06 si zachovávají své vlastní závislosti;
výkonový profil nenahrazuje skutečné podklady ani lidskou/účetní přejímku.

## Úklid a zachování rozpracované práce

Měřicí schéma v [cleanup.json](cleanup.json) odstraněné. Dodatečná vlastní
kalibrační schémata rovněž odstraněná; žádná `p11_read_*` nezůstala. Vlastní
server byl řádně zastavený a jeho exec session skončila návratovým kódem 0.
Původní WIP má shodný hash, aplikační soubory se neměnily.

Automatické schvalování odmítlo rekurzivní smazání adresáře
`C:/xampp/tmp/kis-perf-20261106-f39c6d1a` pouze s důvodem **blocked by policy**.
Mazání se neobcházelo. Adresář zastaveného syntetického serveru je ponechaný;
jeho odstranění zůstává nepovinným místním úklidem, ne blokací měření.
[Historie kalibrace](CALIBRATION.md) jasně odlišuje chyby přípravy měřidla od
aplikační reality; neúspěšná kalibrace se nevydává za platné finální výsledky.


## Souborová kontrola a předání

Existující QueryProfilePdo, plánovač, trenérský přístup, zdravotní a docházkové
regrese plus dokumentační kontrakt: **27 testů / 155 assertions PASS**,
[JUnit](targeted-tests.xml). Syntaxe a styl nového CLI profilu PASS; aplikační
soubory a produkční workflow jsou beze změny. Composer dle existujícího locku.
Kontrola CURRENT_STATE: 80 řádků / 934 místních odkazů / 106 zachovaných
archivních bloků / 0 chyb. Finální místní odkazy a diff check ověřené před
předáním; aktuální povinné CI a stav merge budou doložené u souvisejícího PR.


Souborové předání: [PR #349](https://github.com/KovoPraha/evidenceTreninku/pull/349).
CI ověřuje přenositelnou variantu v izolovaných MariaDB 10.3/11.4; skutečné
výsledky a artifacty jsou přímo u tohoto PR. Žádný produkční deploy se nevytváří.


Při předání zachován novější main `acb511cc` / #346 a jeho serverové předání
ranního auditu. Sloučeny obě testovací návaznosti a oba záznamy ve společném
plánu/handoffu. Měřené stránky, jejich služby a migrační katalog se příchozí
změnou nemění. Nová produkční operace jiné relace se zde nespouští ani neopakuje.
