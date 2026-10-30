# W7.3 — metadata a sekce rodinného přehledu

30. 10. 2026. Základ `c498110adf66ea66cbb40ea081a6021e50108e45`,
[plán před implementací](../../REFACTORING_W73_2026-10-30.md).

## Kontrakt a změna

Sedm helperů v pěti souborech používá `FamilySchemaReadScope::remember`.
Explicitní rozsah se otevírá pouze při sestavení GET stránky a uzavírá
v `finally` po renderu. Je vázaný na konkrétní PDO přes WeakMap/WeakReference;
klíč zahrnuje jméno helperu a jeho argumenty. Uchovává výhradně bool výsledky
úspěšných kontrol existence tabulek/sloupců, včetně výsledku false. Výjimky
se neukládají. Nový rozsah i čtení mimo něj znovu ověřuje metadata.
Uvnitř rozsahu je zakázané DDL a přepínání DB (webové požadavky již nemigrují).
Role, vazby, osobní/finanční data a zamykající writer kontroly se necachují.
POST rozsah neotevírá. Existující GET sync náhrad zůstává zachován.

[RED](red-test.txt): dvě očekávaná selhání ze čtyř testů, 24 assertions:
osm kontrol místo dvou a zbytečné opakování po úspěšném zotavení z chyby.
Po opravě sedm testů a šest dosavadních W7.2 testů: 13 / 63 PASS.
Pokrývají kladná/záporná metadata, oddělení PDO, nové schéma po uzavření,
optional sloupce, vnoření/úklid, opakování po výjimce, změněná data osoby
a revokaci mezi voláními i uprostřed sestavování přehledu.

Povinný styl dotčených legacy souborů vyžádal větší formátovací diff.
U dlouhých šablonových úseků je HTML emitované jako složené řetězce;
porovnává se výsledné HTML, ne jen jeho přibližná struktura.
[AST kontrola](ast-verification.txt) potvrzuje 54 nezměněných těl funkcí
po odstranění sedmi metadata obalů a normalizaci řetězců. SQL ani ostatní
výpočty pomocných funkcí se nezměnily.

## Metoda

`FamilySchemaPageProfile.php` spouští skutečný controller+HTML a zmrazenou
stránku W7.2 `FamilyOverviewPageW72.txt` v oddělených PHP procesech.
Fixture je sdílená s W7.2, náhodná localhost DB s úplným migračním katalogem,
syntetický účet a cizí sentinel, nativní prepares, FK zapnuté.
Pouze DB bootstrap je nahrazen ověřeným syntetickým PDO. Normalizuje se CSP
nonce a čas pozorování salda. Všechny datové sekce i HTML musejí být shodné,
bez skrytých chyb nebo cizích osob. Nová kontrola hlídá úklid rozsahu po renderu.
Původní W7.2 budget nyní používá zmrazenou W7.2 stránku, aby odděleně zachoval
historický kontrakt cílené hydratace od nové cache metadat.

Instrumentovaný běh zachycuje sekci, helper, SQL, parametry a načtené řádky.
Všechny nemetadata SQL, parametry, pořadí a počty načtených řádků musejí být
beze změny. Pro rodiny 1/5/20 musí klesnout metadata alespoň o polovinu.
Čas/paměť je měřen na obyčejném PDO, pět střídajících se vzorků po zahřátí.
Fixture používá aktuální datum pro kalendáře: absolutní rozpočty mezi dny
nejsou totožné (W7.2 dne 29. 10. měla 2326 SQL, zde W7.2 dne 30. 10. 2244).
Relevantní je dvojice baseline/current ve stejném běhu.

## Místní výsledky

[Před](before.json), [po](after.json), [historie](history.json), [POST](post.json).

| Osob | SQL před → po | Řádky před → po | Medián ms před → po |
| ---: | --- | --- | --- |
| 0 | 61 → 43 | 48 → 30 | 79.89 → 70.66 |
| 1 | 249 → 144 | 212 → 108 | 216.04 → 183.26 |
| 5 | 669 → 272 | 612 → 216 | 433.31 → 283.85 |
| 20 | 2244 → 752 | 2112 → 621 | 1292.01 → 690.26 |

Pro 20 osob SQL −66,5 %, metadata 1590 → 98 (−93,8 %).
Rozdělení sekcí v instrumentovaném běhu:

| Sekce | SQL před → po | Metadata před → po |
| --- | --- | --- |
| settings | 10 → 10 | 5 → 5 |
| profiles | 644 → 259 | 423 → 38 |
| finance | 320 → 89 | 240 → 9 |
| orders | 2 → 2 | 1 → 1 |
| makeup | 708 → 68 | 648 → 8 |
| agenda | 53 → 42 | 26 → 15 |
| rsvp | 30 → 25 | 8 → 3 |
| weekly | 53 → 27 | 26 → 0 |
| annual | 35 → 29 | 12 → 6 |
| tasks | 268 → 159 | 121 → 12 |
| render | 121 → 42 | 80 → 1 |

Rozšířená historie obsahuje dalších 50 tréninků × 20 osob = 1000 vazeb,
ve výstupu zůstávají všechny záznamy; stejné SQL 2244 → 752. Nedošlo k omezení
historie ani UI. POST s neplatným CSRF neprovádí akci a potvrzuje stejná
metadata/SQL jako baseline (cache se neaktivuje).

Časy nezahrnují DB/auth bootstrap, HTTP síť ani browser/assets a nejsou
produkční SLA. PHP peak není RSS databáze; načtené řádky nejsou examined rows.
Instrumentace neobsahuje exec, neúspěšné SQL, prepare čas a interní transakce.
Řada optional sekcí je ve fixture prázdná; nejde o kompletní produkční distribuci
nebo lidské UAT. Provozní před/po HTTP jsou anonymní read-only kontroly.

## Stav úplných bran

Styl, PHPStan a dokumentační kontrakt PASS. Kontrola PDO bootstrapu odstranila
26 dosavadních potlačených hlášení; baseline se zmenšil, žádná nová výjimka.
Tři zdrojové kontroly UI textů po formátování opraveny zachováním celých textů
v řetězcích, cílená sada 10 / 57 PASS. Nové plné místní ověření běží.
CI MariaDB 10.3/11.4 opakuje základní profil, širší historii a POST.
Merge/deploy a čtecí produkční porovnání budou zaznamenány po ověření.
Navazující kandidáti: 40/160 čtení členství podle sekce, skutečné aplikační
dotazy a rozsah historie; R03/R04 samostatně. Žádné automatické cachování oprávnění.
