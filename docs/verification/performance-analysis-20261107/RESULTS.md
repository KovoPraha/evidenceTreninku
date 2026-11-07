# P11 — analýza rychlosti a příležitostí k refaktoringu 7. 11. 2026

Stav: ANALÝZA A SOUBOROVÉ PŘEDÁNÍ HOTOVÉ, kontroly PASS.
Zadání vlastníka: analyzovat rychlost aplikace a najít refaktoringy
s potenciálem zlepšení. Rozsah: současné zdroje, dosavadní měření,
nová lokální syntetická diagnostika a lehké anonymní HTTP měření produkce.

Výchozí čerstvý `origin/main`: `89edfdbee5fbddbe05e0134d94f79ba0cb4143d9`.
Větev `codex/performance-analysis-20261107`, pracovní kopie
`C:/Users/Marek/Documents/Codex/evidencePavel-performance-analysis-20261107`.
Původní WIP v `C:/xampp/htdocs/evidencePavel` zůstává zachovaný.

Navazuje na [P11 a další práci](../../WORK_PLAN_CURRENT.md),
[komplexní plán](../../COMPREHENSIVE_REFACTORING_PLAN_2026-10-29.md),
[syntetické měření](../synthetic-performance-20261106/RESULTS.md) a již
začleněné optimalizace [R05](../planner-read-performance-20261106/RESULTS.md)
a [R13](../medical-read-performance-20261106/RESULTS.md).

## Výsledek a pořadí

Nejlepší další samostatný zásah je **dávkové čtení klubových cen v e-shopu**.
Následuje omezené načítání katalogu, dávkové ověření účastníků docházky a
stránkování tréninkové historie. Důležitý společný vzor: nejprve určit malý
výřez a společná data načíst jednou; podrobnosti sestavit pouze pro výřez.
Samotný nízký počet SQL nestačí, pokud se stále vracejí tisíce řádků.

| Pořadí | Kandidát | Důkaz / očekávaný přínos | Náročnost a jistota |
| --- | --- | --- | --- |
| 1 | Klubové ceny katalogu | Nově 100 variant = 400 SQL, 500 = 2000 SQL; opakují se stejné vazby a pravidla. Dávkový reader může odstranit téměř všechny opakované dotazy. | Střední; mechanismus změřený, výsledná úspora zatím neměřená. |
| 2 | Katalog: výběr před tvorbou karet | Pro 12 zobrazených produktů vzniká celý katalog; 5000 syntetických karet ≈ 24,06 MiB přírůstku paměti. | Střední až vyšší kvůli pravidlům prodejnosti a řazení členskou cenou. |
| 3 | Docházka R06: dávka účastníků a omezený výběr osob | Nově samotná kontrola 200 osob = 202 SQL; existující celá měřená část R06 = 244 SQL. | Menší až střední; dobře doložené, existuje i samostatná pracovní kopie R06. |
| 4 | Správa a profil tréninků | Hlavní seznamy/historie nejsou omezené stránkou; podrobnosti a měření se čtou pro celý výsledek. | Střední; doložené zdrojem, vlastní objemový profil ještě chybí. |
| 5 | Datové filtry a globální hledání | YEAR/MONTH/DATE nad datem a LIKE s úvodním `%`; ověřit plány a počet prohledaných řádků. | Menší u období, vyšší u zachování podřetězcového hledání; dosud hypotéza o čase. |
| 6 | Společný bootstrap/navigace a session | Každý požadavek obnovuje role, oprávnění a zvoneček; session běžně zůstává otevřená do konce. | Nejprve profil celého požadavku a souběhu, potom cílená změna. |
| 7 | Sdílené assety | Některé stránky načítají Bootstrap z CDN a zároveň lokálně; velký společný inline blok hlavičky. | Menší sjednocení, přínos pro první vykreslení ověřit browserem. |
| 8 | Zbývající stavové/hierarchické filtry R13/R05 | Běžné stránky už opravené; stav zdravotních podkladů a filtr starých plánů stále procházejí kandidáty. | Vyšší sémantické riziko; další práce až podle skutečné četnosti. |

Pořadí zohledňuje sílu důkazu, rozsah cest a velikost změny; není založené
na nedostupné návštěvnosti. R06 lze dokončit souběžně s e-shopem v jeho
existující pracovní kopii. Tento požadavek implementaci optimalizací nespouští.

## Co skutečně odpovídá na produkci

[Surové HTTP vzorky](public-http.json), [vybrané bezpečné hlavičky](public-headers.json).
7. 11. 2026 přibližně 08:29 CET, pět prokládaných kol, celkem 25 **sériových**
anonymních GET přes HTTPS, každé nové curl spojení; komprese zapnutá, cookies
se nepřenášejí mezi požadavky, těla se neukládají, bez následování redirectů.
Všech 25 odpovědí HTTP 200. Nešlo o zátěžový test.

| Cesta | Vzorků | Medián TTFB | Nejvyšší TTFB |
| --- | --- | --- | --- |
| `/` | 5 | 107,1 ms | 338,9 ms |
| `/booking/eshop.php` | 5 | 400,6 ms | 2091,0 ms |
| `/booking/treninky.php` | 5 | 109,3 ms | 119,5 ms |
| `/booking/kalendar.php` | 5 | 101,8 ms | 104,4 ms |
| `/booking/prihlaseni.php` | 5 | 108,5 ms | 158,1 ms |

E-shop v tomto malém vzorku vychází pomalejší. TTFB zahrnuje DNS, připojení,
TLS, síť a server; **není to čistý čas PHP ani dokončené vykreslení stránky**.
Pět vzorků nestačí pro spolehlivý produkční p95. Špička 2,09 s sama nedokazuje
svou příčinu. Anonymní měření neobsahuje náklady členských cen přihlášeného
zákazníka. HTTP výsledek patří aktuálnímu živému webu; shoda jeho release
s analyzovaným main nebyla ověřována a nelze ji odvozovat z úspěšného GET.

CSS na produkci již má gzip, ETag a týdenní `max-age=604800`; nasazovat
kompresi nebo statickou cache jako údajně chybějící optimalizaci není podložené.
HTML e-shopu má `no-store` a anonymní session cookie. Sdílená HTML cache
vyžaduje nejprve oddělit osobní/session obsah; pouhé odstranění `no-store`
není navržené řešení.

## Nová syntetická diagnostika

[Měřicí zdroj](probe.php.txt), [všechny vzorky, SQL a otisky zdrojů](probe-results.json).
Reprodukce z kořene této pracovní kopie:

```powershell
php docs/verification/performance-analysis-20261107/probe.php.txt
```

PHP 8.2.12, SQLite 3.39.2, Windows; pouze `sqlite::memory:`, žádný config,
produkční dump ani síťová DB. Tři zahřátí a dvacet neinstrumentovaných
vzorků na případ ve stejném procesu. Medián ze dvou prostředních hodnot,
p95 nearest-rank (19. z 20); přírůstek používané špičky paměti, nikoli celý
proces. SQL a vrácené řádky se měří samostatným zahřátým `QueryProfilePdo`.
Jde o steady-state; první kontrola dostupnosti členského schématu přidává
ještě jeden SQL. Všech devět případů má striktně shodný výstup mezi běžným
a instrumentovaným PDO. Není to parita před/po implementaci, runtime se nemění.

| Část | Velikost | SQL | Načtené řádky | Medián / p95 | Paměť navíc |
| --- | --- | --- | --- | --- | --- |
| Ověření účastníků | 10 osob | 12 | 12 | 0,02 / 0,02 ms | < 0,01 MiB |
| Ověření účastníků | 50 osob | 52 | 52 | 0,05 / 0,05 ms | 0,01 MiB |
| Ověření účastníků | 200 osob | 202 | 202 | 0,15 / 0,15 ms | 0,04 MiB |
| Členské ceny | 12 variant | 48 | 36 | 0,85 / 1,03 ms | 0,02 MiB |
| Členské ceny | 100 variant | 400 | 300 | 7,23 / 9,16 ms | 0,09 MiB |
| Členské ceny | 500 variant | 2000 | 1500 | 42,07 / 71,76 ms | 0,43 MiB |
| Veřejné karty katalogu | 12 produktů | 9 | 29 | 0,85 / 0,89 ms | 0,06 MiB |
| Veřejné karty katalogu | 1000 produktů | 9 | 2005 | 27,71 / 46,67 ms | 4,76 MiB |
| Veřejné karty katalogu | 5000 produktů | 17 | 10005 | 183,69 / 293,14 ms | 24,06 MiB |

Tato malá SQLite schémata ukazují růst práce a skutečné opakování příkazů,
**ne latenci produkční MariaDB**. Časy jednotlivých částí nelze přičítat
k veřejnému HTTP měření. Stanice není vyhrazená pro benchmark. Fixture cen
má různé varianty stejného produktu, jednu způsobilou soupisku a kategorii
s 10% pravidlem. Je to záměrně jednoduchý případ opakovaných stejných dat,
nikoli tvrzení o velikosti či struktuře živého katalogu. Katalogové fixture
obsahuje prodejné zboží, netestuje programy ani členské tarify.

## Konkrétní zásahy a jejich přejímka

### 1. Dávkové klubové ceny, explicitní čtecí kontext

[booking/eshop.php](../../../booking/eshop.php), řádky 173–242:
`shopMemberPriceApplyToItem()` běží pro každou variantu celého katalogu
ještě před filtry, řazením a `array_slice(..., 12)`.
[shop_member_pricing.php](../../../includes/shop_member_pricing.php), řádky 21–92:
každá cena znovu načte způsobilé soupisky, produktové ceny, kategorie
a kategorická pravidla. Při způsobilém účtu/kategorii jsou to čtyři SQL na
variantu. Účet bez způsobilých soupisek stále opakuje dotaz na způsobilost.
Existující WeakMap cachuje pouze dostupnost schématu.

Navržený řez: oddělit čtení pravidel od čistého výpočtu ceny. Pro konkrétní
PDO, účet a datum načíst soupisky jednou, ceny a kategorie pro dávku produktů,
potom pravidla pro unikátní kategorie. Rozdílné veřejné ceny jednotlivých
variant vyhodnotit samostatně nad mapami. Nezavádět globální cache práv.
Přejímací cíl pro jednoduchý fixture do 500 variant: nejvýše pět čtecích
SQL včetně metadat; je to návrh rozpočtu, ne již naměřený výsledek opravy.

Povinná parita: produktová výjimka proti kategorii, nejnižší cena mezi
soupiskami, shodná rozhodnutí při remíze, měna, zaokrouhlení, ukončené
členství, neověřený účet a odvolaná vazba. Při checkoutu zůstává nové čtení
aktuálního oprávnění a přepočet v transakci. Nejdříve dávkovat celý dosavadní
výsledek, teprve zvlášť měnit stránkování, aby se zachovalo řazení podle ceny.

### 2. Výběr katalogu před drahou prezentací

[shop_storefront_read.php](../../../includes/shop_storefront_read.php), řádky 24–74,
čte všechny varianty; [shop_catalog_cards.php](../../../includes/shop_catalog_cards.php)
skládá všechny karty. E-shop až potom filtruje a vybírá 12 produktů.
Samostatné menu už má lehčí projekci a dávkové obrázky/kategorie už existují;
nenavrhovat znovu jejich vyřešené N+1.

Navržený řez: nejprve lehká projekce identit, prodejnosti, kategorie a cen
pro přesné počty/filtry/řazení, poté plné karty, obrázky a atributy jen pro
vybraných 12 ID. Později lze přesunout i kandidátní výběr do SQL. Prosté
`LIMIT 12` před vyhodnocením prodejnosti by mohlo skrýt oprávněné nabídky
a zkazit počty; zvlášť ohlídat programy, plné kapacity, zájmové nabídky,
tarify, více variant a pořadí podle skutečné členské ceny.

Přejímka: 12/1000/5000 produktů, různé kategorie a role, stejná viditelnost,
počet a pořadí produktů/variant. Detailová materializace má růst s velikostí
stránky, nikoli s celým katalogem; agregace počtů může dál projít kandidáty.

### 3. Docházka: dávkové ID a omezení formulářových voleb

[coach_training_access.php](../../../includes/coach_training_access.php), řádky 48–57,
provádí `SELECT id FROM sportovci WHERE id=?` jednou pro každé unikátní ID.
Čerstvý profil potvrzuje N + 2 dotazy. Volá jej
[training_closure.php](../../../includes/training_closure.php), řádky 46–71.
Dosavadní [R06 profil na MariaDB 10.3](../planner-read-performance-20261106/ci-10.3/after/summary.json)
potvrzuje 54/94/244 SQL při 10/50/200 účastnících v měřené části stránky.

Návrh: jednou ověřit aktivního aktéra a pracovní pozici, dávkově načíst
unikátní existující ID, porovnat s požadovanou množinou. Ponechat odmítnutí
neplatného ID, čerstvost při každém volání a autorizační/zámkový kontrakt
v transakci. Nový cíl samotné kontroly: 2 SQL pro aktéra + počet dávek ID,
nikoli N + 2. Zrychlení celého GET je nutné teprve změřit.

[training_closure_admin.php](../../../training_closure_admin.php), řádky 143–145,
navíc načítá všechny osoby a trenéry; seznam osob se opakuje ve dvou
selectech. `trainingDayChoices()` v [training_day.php](../../../includes/training_day.php)
provádí po výběru až 50 lekcí další kontroly po jedné. Tyto náklady nevydávat
za již odstraněné pouhou změnou kontroly účastníků.
Navazující výběr osob omezit vyhledáním/stránkou se zachováním všech
vybraných osob a obnovení formuláře po chybě.

Při inventuře existuje `C:/Users/Marek/Documents/Codex/evidencePavel-attendance-performance-20261107`
na stejném výchozím main, se zatím neversionovaným session handoffem.
To není důkaz hotové optimalizace nebo začlenění; před implementací ověřit
její aktuální stav a nevytvářet kolizní druhou opravu.

### 4. Historie tréninků a profil: nejprve stránka ID

[sprava_treninku.php](../../../sprava_treninku.php), řádky 54–100: hlavní
agregovaný seznam nemá limit ani stránku, pak čte účastníky všech tréninků.
[ajax_treninky.php](../../../ajax_treninky.php), řádky 29–81: filtry období
existují, ale výsledek není stránkovaný; uživatel může období vypnout.
[ajax_sportovec_treninky.php](../../../ajax_sportovec_treninky.php), řádky 73–138:
bez roku čte celou historii, všechny poznámky osoby a všechna měření pro
vrácené tréninky. [sportovec_treninky.php](../../../sportovec_treninky.php),
řádky 125–148 a 206–224, načítá kompletní měření a testy již v počátečním GET.

Návrh: samostatná projekce seznamu, stránka ID se stabilním pořadím
`datum DESC, id DESC`, podrobnosti/poznámky/měření jen k těmto ID, souhrny
odděleně agregovat. Detailní záložky načítat na požádání. Všechny výsledky
a historie musejí být dál dosažitelné stránkováním; nepoužít tiché oříznutí.
Hierarchický filtr vyhodnocovaný ke dni tréninku musí proběhnout před
výběrem výsledné stránky. Přesné počty, náklady a parita zatím NOT RUN.

### 5. Databázové filtry: nejdříve EXPLAIN, potom index

[ajax_treninky.php](../../../ajax_treninky.php), řádky 42–49: `YEAR(t.datum)`
a `MONTH(t.datum)`; [ajax_sportovec_treninky.php](../../../ajax_sportovec_treninky.php),
řádek 82: `YEAR`; [sportovec_treninky.php](../../../sportovec_treninky.php),
řádek 317: `DATE(t.datum) BETWEEN`.
Pro skutečný interval rok/měsíc navrhnout predikát nad původním sloupcem
`datum >= začátek AND datum < následující_začátek`; samotný měsíc bez roku
má jinou sémantiku a nelze jej automaticky nahradit jedním intervalem.
MariaDB verze se v optimalizaci funkcí nad datem liší, proto měřit obě
podporované verze a neoznačovat každý takový dotaz bez EXPLAIN za full scan.

[ajax_global_search.php](../../../ajax_global_search.php), řádky 28–45 a 77:
podřetězce přes `%q%`, CONCAT jmen a hledání v popisech. Limit 5/6 zmenšuje
výstup, nemusí zmenšit prohledaný objem. Frontend už má 300ms debounce
a AbortController ([hlavicka.php](../../../hlavicka.php), řádky 1374–1408).
Případný prefix/fulltext mění shody a musí mít schválenou výsledkovou paritu;
prostý B-tree index automaticky neřeší podřetězce.

Vlastní EXPLAIN/ANALYZE a návrh nových indexů těchto cest: NOT RUN.
Změny schématu odložit do konkrétního PR s důkazem selektivity, velikostí
indexu a dopadem na zápisy. Dřívější dočasné tabulky/kapacitní problémy
nepovažovat za vyřešené přidáním dalšího indexu bez měření.

### 6. Bootstrap, navigace a session: společný měřicí bod

[init.php](../../../includes/init.php) startuje session a [db.php](../../../db.php)
otevírá spojení a validuje identity. [auth_session.php](../../../includes/auth_session.php),
řádky 143–165: obnova role, oprávnění a pracovních pozic; navazující
[staff_workspaces.php](../../../includes/staff_workspaces.php), řádky 894–954,
ověřuje tabulky, pozice a superadmina.
[hlavicka.php](../../../hlavicka.php), řádky 42–47, znovu čte jméno trenéra,
a [staff_notification_bell.php](../../../includes/staff_notification_bell.php)
volá inbox. Ten má dva další dotazy s autorizačními podmínkami
([staff_notifications.php](../../../includes/staff_notifications.php), řádek 184).

Nejprve měřit celý GET včetně těchto sekcí; dosavadní R05/R06/R13 profily
bootstrap a navigaci úmyslně vynechávají. Potenciální řez: jeden explicitní
kontext čtení a reuse již načteného jména/metadat během téhož GET.
Neodstraňovat čerstvé revokační a doménové kontroly a neukládat práva
do dlouhodobé cache kvůli úspoře několika dotazů.

[session_security.php](../../../includes/session_security.php), řádky 278–300,
otevírá běžnou session. Globální hledání a většina čtecích AJAX cest ji
časně neuzavírají; výjimky existují např. u platební stránky a TV obrázku.
Při souborovém zamykajícím handleru může dlouhý request zdržet další
požadavky stejné session. **Produkční handler ani skutečné čekání na zámek
nebyly změřené.** Kandidát: uložit nutné session změny a uzavřít session
před dlouhým čistým čtením, nejprve na samostatném čtecím AJAX endpointu.
Prověřit CSRF, flash, rotaci, změny identity a opětovné otevření v hlavičce;
plošný `read_and_close` v bootstrapu není navržený bezpečný zásah.

### 7. Frontend: sjednocení sdílených prostředků

[moje_treninky.php](../../../moje_treninky.php), řádek 43,
[sprava_treninku.php](../../../sprava_treninku.php), řádek 113, a
[sportovec_treninky.php](../../../sportovec_treninky.php), řádky 519–520,
obsahují CDN Bootstrap CSS. `appUiAssets()` ze
[ui_shell.php](../../../includes/ui_shell.php), řádky 18–44, vkládá stejnou
verzi lokálně; hlavička ho rovněž volá. Jeho static guard brání opakování
vlastního helperu, nikoli samostatnému CDN linku. Lokální Bootstrap CSS má
232808 B před kompresí; zjištěný gzip HEAD uvádí 31248 B.

Návrh: jednotný asset helper, odstranit duplicitní CDN odkazy, přesunout
společné inline CSS/JS hlavičky do verzovaných souborů. Bootstrap bundle
je nyní vložený synchronně; `defer` až po kontrole závislých inline skriptů.
Obrázky katalogu už mají `loading=lazy` a `decoding=async` (řádek 564),
nejde o chybějící optimalizaci. Skutečný waterfall, LCP, INP, CLS a chování
na mobilní síti: NOT RUN.

### 8. Doplňky již optimalizovaných R05/R13

[medical_clearance_read.php](../../../includes/medical_clearance_read.php),
řádky 30–84, má úsporný stream, ale pro přesné počty stavového filtru stále
vyhodnocuje všechny kandidátní historie. Dosavadní nový R13 profil uvádí
30056 místo 50224 řádků při 10k a filtru Platné; paměť už je omezená.
Další řešení vyžaduje přesný model nahrazování/odvolání a platnosti ke dni,
nikoli jen poslední záznam nebo neudržovanou cache.

[training_planner_read.php](../../../includes/training_planner_read.php),
řádky 15–38 a 42–70, má u zvolené soupisky stále výběr historických
kandidátních ID/dat a následný hierarchický filtr. Bez filtru už existuje
COUNT/MIN a nové dávkové RSVP. Profil více datovaných hran hierarchie
a dlouhé historie s filtrem: NOT RUN v této session.

## Co neopakovat a jak měřit další PR

R05 #353 a R13 #352 jsou v analyzovaném main již začleněné. Historické
výsledky 112/203 SQL plánovače a 50224 řádků běžné zdravotní stránky
nejsou jeho dnešní neopravené nálezy. Jejich úspěšné párové MariaDB
profily dokládají přínos omezeného čtení, nedokládají produkční SLA.

Pro další implementaci zachovat jednotlivé malé PR: (1) dávkové ceny,
(2) katalogový výřez, (3) návaznost na existující R06, (4) historie.
Každý porovnat na stejném runneru a stejných datech před/po:

- MariaDB 10.3 a 11.4, běžná i hraniční data; čas neinstrumentovaně,
  SQL/vrácené řádky a EXPLAIN odděleně, paměť i velikost HTML.
- Shodné ceny, ID, pořadí, počty, viditelnost a chybové chování; zvlášť
  neověřený/odvolaný účet, zrušená soupiska a cizí osoba.
- Celé HTTP se skutečným bootstrapem/navigací pro anonymního zákazníka,
  rodiče, trenéra a správce. Samostatně stejná session vs různé session.
- Až poté browser na desktopu/mobilu s prvním i opakovaným načtením;
  obchodní/platební POST a transakční zámky ověřit samostatně.

## Ověření, předání a meze

HOTOVO: čerstvý main, zdrojový audit uvedených cest, 9 nových syntetických
případů / 180 časových vzorků + samostatné SQL profily a striktní parita,
25 veřejných GET a 3 HEAD. Jde o vybrané významné cesty, nikoli úplný profil
všech agend BAR/účetnictví/exportů a všech rolí. Nové MariaDB měření, přihlášený
HTTP/browser průchod, zátěžový test, produkční slow log a OPcache stav NOT RUN.

Stávající kontroly nad soubory této pracovní kopie, PHPUnit 11.5.56 / PHP
8.2.12, s dostupným Composer autoloadem původní kopie (bez aplikačního bootstrapu):

| Kontrola | Výsledek | Důkaz |
| --- | --- | --- |
| QueryProfilePdo | 1 test / 22 assertions PASS | [JUnit](profile-tests.xml) |
| Členské ceny | 4 / 15 PASS | [JUnit](pricing-tests.xml) |
| Čtení katalogu/menu | 3 / 72 PASS | [JUnit](catalog-tests.xml) |
| Trenérský přístup | 4 / 14 PASS | [JUnit](coach-tests.xml) |
| Syntaxe měřiče `php -l` | PASS | `probe.php.txt` |
| `php tools/check-current-docs.php` | 80 řádků CURRENT_STATE, 987 odkazů, 107 archivních bloků, 0 chyb | Dokumentační kontrakt |
| Místní odkazy všech dotčených dokumentů | 343 ověřených, 0 chyb; shoda všech 9 otisků měřených zdrojů | [Kontrola](link-check.json) |
| `git diff --check` | PASS | Vlastní dokumentační změny |

Celkem **12 testů / 123 assertions PASS**. Celý PHPUnit suite a nové CI se
pro tuto analýzu bez runtime změn nespouštěly. Závěrečný fetch znovu potvrdil
`origin/main` shodný s výchozím `89edfdbe`.

Zdroj měřiče je uložený jako `.txt`, není novým webovým endpointem.
Aplikační kód a schéma se nemění. Žádné nasazení,
aktivace integrací, rozesílání ani změny skutečných dat. Aktuální release
a provozní flags nadále pouze v [CURRENT_STATE](../../CURRENT_STATE.md).
