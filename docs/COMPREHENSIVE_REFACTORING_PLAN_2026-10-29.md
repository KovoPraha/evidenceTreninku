# Komplexní plán refaktoringu a optimalizace Evidence / KIS

**Stav k 29. 10. 2026: K0/W6.1 sloučeno, W6.2 a W6.3 ověřeno a nasazeno.**
Zadání vlastníka z 29. 10. 2026: připravit komplexní pojetí celé aplikace,
v dalších krocích autonomně implementovat. Navazující pokyn vlastníka aktivoval
[první realizační balík](REFACTORING_K0_W6_2026-10-29.md). Původní příprava
plánu měnila pouze dokumentaci; realizace navazuje na dokončené vlny 1–5.

Výchozí zdroj `49daae3a192d8168ed427c7adb4f203c916081d9`, ověřený fetch 29. 10.
Pracovní větev `codex/comprehensive-refactoring-plan-20261029`, izolované worktree
`C:/Users/Marek/Documents/Codex/evidencePavel-comprehensive-refactoring-plan-20261029`.
Původní checkout s rozpracovaným `kis_rosters_admin.php` zůstává nedotčený.
Aktuální release a provozní hranice patří do [CURRENT_STATE](CURRENT_STATE.md),
pravidla převzetí do [HANDOFF_CURRENT](HANDOFF_CURRENT.md).

## 1. Cíl a měřítko úspěchu

Zrychlit důležité uživatelské cesty, zmenšit cenu bezpečných změn a zpřehlednit
odpovědnost kódu při zachování současných doménových pravidel. Každá změna má
měřitelný výkonový přínos, doložené odstranění vady, nebo konkrétní zlepšení
udržovatelnosti ověřené stejným chováním. Počet přepsaných řádků není výsledek.

Zachovat PHP/PDO modulární monolit, existující URL, kanonické writery, aktéry,
transakce a vlastnictví dat. Nový framework, mikroservisy, globální ORM,
persistentní cache oprávnění, plošný redesign ani upgrade platformy nejsou
výchozí prostředek tohoto plánu. Případný takový návrh musí nejprve doložit
problém, alternativy, provozní náklady a kompatibilitu.

Technické dokončení znamená, že všechny níže uvedené oblasti byly prověřeny,
každý přijatý balík má důkazy a nezavádí regresi a každý vyřazený kandidát má
zdůvodnění. Neznamená účetní převzetí, vyřešení skutečných osob ani aktivaci služeb.

## 2. Ověřený základ a nejistoty

[Inventura zdrojů](verification/comprehensive-refactoring-plan-20261029/source-inventory.json)
nad uvedeným SHA: 2 102 trackovaných souborů; 610 PHP souborů aplikace/CLI
(106 892 řádků, včetně legacy souborů), 160 unit a 159 integračních testovacích
souborů, 43 MariaDB podpůrných skriptů, 28 HTTP acceptance skriptů a 140 PHP migrací.
Definici výběru obsahuje [reprodukční skript](verification/comprehensive-refactoring-plan-20261029/inventory-source.py).
Nejde o počet aktivních endpointů, výkonnostní profil ani procento testového pokrytí.

Načtená metadata a nadpisy všech 238 trackovaných Markdown dokumentů jsou v
[inventáři dokumentace](verification/comprehensive-refactoring-plan-20261029/documentation-inventory.json).
Podrobně byly posouzeny aktuální stav, pravidla práce, poslední refaktoringové
protokoly, mapa datových toků, nálezy, kvalita PHP, předání implementace R01–R44
a provozní rozhodnutí. Nejde o novou ruční revizi každého řádku aplikace ani
o nový audit obsahu PDF/XLSX/DOCX příloh.

| Doložené zjištění | Dopad na plán |
| --- | --- |
| Vlny 1–5 jsou dokončené; poslední technický release `a14d1026` je doložen 28. 10. | Zachovat dosažené rozpočty: cílený detail a navigace, programové karty 120 SQL při 50 programech. Neimplementovat hotové balíky znovu. |
| Přesný main má úspěšné Testy, PHP quality, CodeQL a Current documentation; při kontrole nebyl otevřený PR. | Novou etapu vždy znovu založit z aktuálního main, zkontrolovat mezitím vzniklé PR/WIP. |
| Poslední release prošel 1 782 testy / 35 866 assertions, oběma MariaDB a 22/22 HTTP kontrolami před/po. | Výchozí důkaz, nikoli automatický PASS budoucí změny nebo úplné UAT. |
| PHPStan level 5 má 519 položek baseline / 2 407 výskytů. | Snižovat podle dotčené domény a skutečného nálezu; nepřidávat automaticky nové výjimky. |
| `shop_checkout.php` má 2 182 řádků, `club_program.php` 1 825; velké jsou i formuláře, synchronizace, plánovač a hlavička. | Kandidáti pro rozdělení odpovědností. Délka sama není důkaz pomalosti nebo vady. Minifikované soubory naopak mohou délku podhodnocovat. |
| Starší F01/F02/F10 uváděly přímé e-maily v requestech; současná registrace/reset/rezervace používají fronty. Textové hledání nachází čtyři `mail()` volání v transportních funkcích. | Neplánovat plošnou náhradu hotových front. Prověřit skutečné volající, retry a provozní důkazy; samotný výskyt `mail()` není chyba. |
| Starší dokumentace tvrdí, že Fio nemá schedule; současný workflow má plán pod přepínačem. | Kód a aktuální flags mají přednost. Opravovat aktivní návody cíleně, historické důkazy zachovat. |

V tomto plánovacím kroku nebyla spuštěna nová produkční diagnostika ani měněna
produkce. Provozní údaje jsou poslední doložený stav z 28. 10.; před vydáním
je povinné nové čtení. Dosud neznáme výkonové profily všech přihlášených agend,
produkční distribuci velikostí dat ani provozní doručení všech druhů zpráv.

## 3. Cílové uspořádání uvnitř monolitu

```mermaid
flowchart TD
  R[HTTP nebo CLI vstup] --> G[Identita, aktuální oprávnění, validace]
  G --> Q[Čtecí projekce konkrétní agendy]
  G --> C[Kanonická zápisová operace]
  Q --> V[Data pro šablonu nebo export]
  V --> O[HTML, JSON, soubor]
  C --> T[Transakce: aktuální práva, zámky, invarianty]
  T --> D[Doménová data, audit, existující outbox]
  D --> W[Worker s idempotencí a retry]
  W --> X[Externí transport podle povolené konfigurace]
```

- Vstupní stránka řeší request, guard, CSRF a odpověď; doménové rozhodnutí volá.
- Čtecí projekce vrací přesně potřebná data, umí stránkování/dávku a nevytváří DDL.
  Kontext čtení žije v jediné operaci a na jediném PDO. Není autoritou pro zápis.
- Zápis zůstává u kanonické služby. Opakuje aktuální oprávnění a podmínky uvnitř
  transakce, zachovává pořadí zámků, idempotenci a audit. Refaktoring nesmí
  přesunout bezpečnostní kontrolu před čekání na zámek a tím ji oslabit.
- Prezentace nesmí spouštět skryté SQL nebo transport. Formátovací helper může
  sdílet zobrazení; nesmí sjednotit odlišné účetní nebo autorizační významy.
- Základní typy (ID, peníze, časové hranice, stavy) zpřesňovat nejprve PHPDoc
  a validací vstupů; malé datové objekty zavádět jen tam, kde omezí konkrétní chyby.
- Existující fasády a názvy funkcí ponechat jako kompatibilní vstupy při postupné
  extrakci. Odstranit je až po inventuře všech volajících a behaviorálním důkazu.

## 4. Rozsah celé aplikace a invarianta

| Oblast a hlavní zdroje | Kritické toky a co musí zůstat pravda |
| --- | --- |
| Bootstrap, session, role: `includes/init.php`, `auth_session.php`, `session_security.php`, `staff_workspaces.php`, `coach_scope.php` | Přihlášení → aktivní pracovní pozice → konkrétní rozsah → akce. Revokace je účinná při dalším chráněném rozhodnutí, namespace trenéra/zákazníka/sportovce se nemísí; negativní průchody 401/403/redirect bez těla. |
| Osoby, rodiny, členství: `family_portal.php`, `account_person_role.php`, `account_person_claim.php`, `sportovec_karta.php` | Účet ≠ osoba ≠ plátce ≠ členství. Platnost vazeb a souhlasů, karanténa sporných identit, verzování a audit; žádné odhadované párování skutečných osob. |
| Programy a katalog: `club_program.php`, `club_program_read_context.php`, `shop_storefront_read.php`, `shop_catalog_cards.php` | Zveřejnění → prodejnost → příjemce → kapacita → podmínky. Sdílené fyzické týmy, termíny, placené čekání na identitu, expirace a překrytí období musí mít stejné výsledky. |
| Sport, soupisky, kalendáře: `kis_roster.php`, `training_closure.php`, `sports_activity.php`, `race_results.php`, `club_calendar.php`, `planovac.php` | Plán → RSVP → skutečná účast → uzavření → historie/náhrada. Plán není skutečnost, uzavřená historie se nepřepisuje; trenér vidí jen svěřený rozsah. |
| Zdravotní způsobilost a osobní dokumenty: `medical_clearance.php`, `medical_activity.php`, soukromé downloady | Platnost posudku a omezení činnosti, oprávněný příjemce, verze podkladu a dohled. Citlivé údaje se nesmějí objevit v obecné cache, exportu cizí role nebo diagnostickém logu. |
| Rezervace a veřejné akce: `club_event_registration.php`, `public_velodrome.php`, `venue_operations.php`, stránky individuálních lekcí | Kapacita, souběh, čekací listina, deadline storna, přihlášení a platba. Žádný oversell ani dvojí povýšení čekajícího. |
| Obchod a sklad: `shop_checkout.php`, `shop_inventory.php`, `shop_fulfillment.php`, `shop_returns.php` | Serverový přepočet → rezervace → objednávka → úhrada → výdej → vratka. Neověřená zásoba není neomezená; ceny a slevy nejsou autoritou klienta; idempotence a platná skladová bilance. |
| Finance a účetnictví: `accounting_ledger.php`, `accounting_documents.php`, `accounting_export.php`, `member_charge_read.php`, `club_finance.php`, `financial_decisions.php` | Předpis, pohyb peněz, alokace, doklad, zákaznický stav a expedice jsou rozdílné skutečnosti. Zachovat částky, VS, řady, historické snapshoty a uzavřené období; opravy jsou auditované, ne tichý přepis. |
| Komunikace a integrace: `auth_mail_outbox.php`, `club_event_notification.php`, `roster_message.php`, `family_weekly_delivery.php`, worker CLI a poskytovatelé | Doménová změna + outbox atomicky; claim/retry/expirace, ochrana tokenů, stav zařazeno/odesláno/doručeno. Refaktoring nesmí zvýšit počet reálných odeslání ani zapnout provider. |
| Importy, soubory a exporty: `sync_evidence.php`, KIS review/import helpery, `private_storage.php`, `bin/db-backup.php` | Náhled → explicitní mapy → fingerprint → promote → dohled. Idempotence, soukromé soubory, staging/finalize/kompenzace, kontrolní součty a obnova; bez automatického převodu skutečných dat. |
| UI, navigace, API a provoz: `hlavicka.php`, `ui_shell.php`, `assets/app-ui.*`, admin/booking vstupy, workflow | Zachovat URL, role, návratový kontext, české popisky, escaping, přístupnost a mobilní chování. Testy a CLI nesmějí být veřejným vstupem. |

Názvy bez adresáře v tabulce označují helpery v `includes/`, pokud není uvedeno jinak.
První [registr 16 hlavních cest](REFACTORING_ROUTE_REGISTER_2026-10-29.md) doplnilo K0.1;
detailní audit vlastníků a endpointů pokračuje po doménách. Tato tabulka není úplný
seznam cest ani nový návrh vlastnictví historických tabulek.

## 5. Etapy a pořadí

Etapa je několik malých PR, ne jeden rozsáhlý přepis. Základní pořadí:
**K0 → W6 → W7 → W8 → W9 → W10 → W11 → W12 → W13 → W14 → W15**.
Průřezové testy, dokumentace a zúžení baseline se provádějí v každém balíku;
W14 je závěrečná systematická konsolidace, ne odklad testování.
Reprodukovaná chyba ztráty dat, překročení práv nebo nesprávné finanční operace
má před výkonovou frontou přednost a dostane vlastní opravný PR.

| Etapa / stav | Konkrétní balíky v pořadí | Vstup / závislost | Hotovo, když |
| --- | --- | --- | --- |
| **K0 — průběžná realizace**: měření a kontrakty celé aplikace | K0.1 aktuální mapa route → guard → služba → tabulky → side effect; K0.2 syntetické profily a měřicí prostředí; K0.3 registr zjištění a řazení backlogu | Aktuální main a tento plán | Každá oblast má vlastníka, alespoň hlavní pozitivní/negativní scénář a výchozí měření nebo výslovně popsanou mezeru. |
| **W6 — W6.2 a W6.3 nasazeno**: obsazenost programů | W6.1 charakterizační testy a profil; W6.2 oddělit načtení podkladů od čistého výpočtu; W6.3 případně dávkové veřejné čtení podle měření | K0 pro katalog, dosavadní W1–5 | Kanonický a nový výstup shodný ve všech scénářích, čerstvé zámky zachované, prokazatelný přínos bez zhoršení DB práce/paměti. |
| **W7 — W7.1a ověřování**: osoby, rodiny a oprávnění | W7.1 seznamy osob a rodinné přehledy; W7.2 oddělit vstupy/čtení/prezentaci; W7.3 odstranit konkrétní duplicity autorizačních predikátů | K0; bezpečnostní kontrakty před extrakcí | Stabilní stránkování a omezené načítání; stejné výsledky pro self/guardian/trenéra/registráře; okamžité odnětí vazby a souběžná revokace projdou negativními testy. |
| **W8 — kandidáti k měření**: sport a rezervace | W8.1 plánovač, kalendáře a timeline; W8.2 formuláře tréninku/závodu a uzavření účasti; W8.3 rezervace, náhrady, čekací listina | K0 + dotčené kontrakty W7 | SQL neprobíhá po jednom záznamu bez důvodu; shoda časových hranic, účasti a historie; browser klávesnice/mobil a souběžné poslední místo PASS. |
| **W9 — připravit kontrakty**: obchod a sklad | W9.1 rozdělit čtecí/view část checkoutu; W9.2 izolovat výpočet ceny a validaci bez přesunu transakčních kontrol; W9.3 skladové výpisy, výdej a vratky | K0 + W6/W7 při společných závislostech | Stejné minor-unit částky, stock a VS; idempotentní opakování, souběžná objednávka/vratka a rollback bez vedlejších zápisů. |
| **W10 — připravit kontrakty**: finance a účetní výstupy | W10.1 přehledy předpisů/sald/ledgeru; W10.2 oddělit čtení od renderování dokladů a exportů; W10.3 cílené extrakce finančních služeb | K0 + stabilní kontrakty W9 | Součty a význam každého řádku shodné, uzavřené snapshoty neměnné; export roste v přijatelných paměťových mezích, zachová konzistentní okamžik čtení a oprávnění. |
| **W11 — prověřit současný stav**: fronty a adaptéry | W11.1 inventura skutečných producentů/workerů/transportů; W11.2 sdílet pouze prokazatelně shodný mechanismus claim/retry/diagnostiky; W11.3 sjednotit klasifikaci chyb a testovací adaptéry | K0; aktuální outbox a provider kontrakty | Žádná ztracená nebo duplicitní zpráva při retry, rollbacku a pádu workeru; tajemství mimo logy; současné vypnuté služby zůstanou vypnuté. |
| **W12 — kandidáti k měření**: importy, soubory, zálohy | W12.1 streamování/dávky parserů a exportů; W12.2 rozdělit synchronizační orchestrace; W12.3 staging, cleanup a obnova | K0 + identity W7, finance W10 podle typu dat | Stejné preview/fingerprint/mapy/karanténa, opakování bez duplicit, selhání uprostřed dávky obnovitelné; DB i privátní soubory ověřeny v izolované obnově. |
| **W13 — průběžně, poté konsolidace**: UI a vstupní stránky | W13.1 formuláře a datové modely pro šablony; W13.2 sdílená hlavička/menu/tabulky; W13.3 případné odstranění nepoužitých assetů/aliasů | Stabilní služby dotčených etap + inventura volajících | Stejná URL a role, návraty, filtry, prázdné/chybové stavy, escaping a přístupnost; doložené nepotřebné soubory odstraněny přes přesný seznam. |
| **W14 — průběžně, poté konsolidace**: typy a testy | W14.1 baseline podle domén, validní PHPDoc a skutečné chyby; W14.2 křehké zdrojové aserce nahradit chováním; W14.3 udržitelné fixtures a cílené zrychlení CI | Změněné domény a měření CI | Baseline neroste; odstraněný nález má vysvětlení/test. Zachována negativní detekce bezpečnostních testů; rychlejší CI nesmí vynechat povinné scénáře. |
| **W15 — závěrečná technická přejímka**: celé toky a provoz | W15.1 rodina → program → objednávka → platba → účast → export; W15.2 revokace, rollback, fronty, import/obnova; W15.3 release evidence a aktualizace aktivních návodů | Přijaté předchozí balíky | Souvislé syntetické scénáře PASS, opakovatelné měření a obnova, žádné nevysvětlené regrese; skutečné UAT evidované odděleně. |

Podle K0 se pořadí výkonových kandidátů může změnit: nejprve častá a pomalá cesta
s doloženou příčinou a zvládnutelným rizikem. Neodkládat ochranu dat kvůli snadnému
výkonovému výsledku. Přesuny etap zapsat s důvodem; neprohlašovat neprověřený
balík za dokončený. Kalendářní odhady až po K0 a prvním ověřeném řezu domény.

## 6. První proveditelný balík: K0 + W6.1

1. Čerstvý main, vlastní worktree, aktuální CI/PR a protokol základní verze.
2. Založit registr cest alespoň pro: veřejný katalog/detail; rodinný přehled
   a osoby; registrářský seznam/kartu; trenérský plánovač/účast; kalendář a
   rezervaci; objednávku; skladový pohyb; ledger/doklad/export; frontu;
   preview importu a zálohu. Uvést roli, scope, tabulky, citlivé údaje a testy.
3. Pro každou cestu změřit SQL, přenesené řádky, čas PHP/HTTP, špičkovou paměť
   a velikost odpovědi. Zápisy měřit pouze na syntetické DB s blokovanými transporty.
   Navržené velikosti: seznamy osob 100/1 000/10 000, kalendář 1/12/52 týdnů,
   ledger/export 1 000/10 000/100 000 řádků; obsahuje i neaktivní a nepřístupné
   záznamy. K0 ověří proveditelnost na testovacím hostu a rozpočty upraví před
   měřením. Nejde o tvrzení o skutečných produkčních objemech.
4. W6: znovu profilovat `clubProgramOfferCapacityStateForTeam()` a volající.
   Rozlišit čas jednotlivých SQL, počet vrácených řádků a PHP deduplikaci.
5. Uložit charakterizační testy níže. Teprve poté vybrat nejmenší výhodnou
   změnu: sdílené čisté vyhodnocení, omezené přednačtení nebo doložený index.
   Jedna nová obří SQL není automaticky lepší než více malých indexových lookupů.
6. Vydat samostatný PR s měřením a testy; pokud žádná bezpečná optimalizace
   nevyjde, doložit důvod, zachovat kanonickou cestu a pokračovat nejvýše
   hodnoceným kandidátem z K0. Nenutit změnu kvůli číslu vlny.

### Povinné scénáře W6

- Nula/jedna/více nabídek, 1/10/50/400/401 variant; více variant stejného
  programu a několik programů na jednom či více fyzických týmech.
- Překryv období, stejné mezní datum, neplatné/chybějící datum a původní fallback.
  Čas vyhodnocovat v původní zóně Europe/Prague, včetně změny letního času.
- Aktivní přihlášky téhož sportovce přes více nabídek se počítají stejně jako dnes.
- Nezaplacená objednávka před/přesně v/po expiraci; placená rychlá registrace
  čekající na identitu; zamítnutá/zrušená žádost; již aktivovaná položka.
- Deduplicita podle osoby/žádosti/položky a původní pravidlo quantity; nevydávat
  jednoduchý COUNT(*) za ekvivalent existujícího výpočtu.
- Kapacita NULL/0/kladná, více týmů s odlišným obsazením, legacy chybějící sloupce,
  `excludeHeldSportovecId` a nové čtení po změně rezervace či přihlášky.
- Dvě spojení soutěžící o poslední místo, rollback, opakovaný checkout a změna
  oprávnění během čekání. Žádná recyklace veřejného kontextu při FOR UPDATE.

## 7. Měření a pravidla přijetí

| Co | Protokol / brána |
| --- | --- |
| Správnost | Před změnou charakterizovat platné chování; pro opravu vady reprodukovat selhání. Porovnat úplné struktury, typy, pořadí a relevantní chyby. Golden výstupy nesmějí jen kopírovat novou implementaci. |
| SQL | Malý/běžný/zátěžový syntetický dataset, stejné schema a data před/po; počet dotazů i řádků, EXPLAIN na MariaDB 10.3/11.4. Index navrhnout až po důkazu plánu, zhodnotit cenu zápisů a DDL. |
| Latence | Oddělit čas služby, celé HTTP a browser. Minimálně 5 zahřátí + 30 střídavých měření na klidném testovacím prostředí, uložit vzorky/medián; p95 označit za hrubý odhad při malém vzorku. Neprovádět produkční zátěž bez samostatného zadání. |
| Paměť a velikost | Zaznamenat peak memory, načtené řádky a velikost odpovědi/exportu. Dávkové čtení nesmí neomezeně načíst historii celé aplikace. Parametrické limity musí být testované. |
| Nejasný výsledek | Při nečekaném zpomalení nad 10 % nebo růstu paměti nad 20 % zopakovat řízeně, určit příčinu a rozhodnutí doložit. Jde o spouštěč šetření, nikoli toleranci bezpečnostní či účetní regrese. |
| Přínos refaktoringu | Popsat odstraněnou duplicitu, zmenšenou odpovědnost, zpřesněný kontrakt nebo konkrétní odstraněný nález. Neslibovat výkon bez měření; některý bezpečný refaktoring může být výkonově neutrální. |
| Bezpečnost a souběh | Negativní role, cizí ID, CSRF, odnětí vztahu, aktuální scope, reálné DB zámky a idempotence. SQLite je rychlá regrese, ne důkaz MySQL zamykání nebo kolace. |
| UI a dokumenty | Při změně šablon skutečný browser desktop/mobil, klávesnice a chybové stavy. PDF/XLSX změny ověřit obsahem i renderem; binární hash může měnit metadata, porovnává se i doménový význam. |

Pro K0 se nepředepisuje plošný SLA ani nereálný cílový počet SQL pro všechny
agendy. Konkrétní rozpočet se uloží před implementací z naměřené příčiny.
U W6 je výchozí 120 SQL při 50 programech; přesný cíl určí W6.1 podle řádků,
indexů, fan-out týmů a paměti. Správnost a zachování zámků mají absolutní přednost.

## 8. Testy a existující nástroje

Znovu použít existující testy, ne zavádět paralelní druhou testovací infrastrukturu:

- Programy: `ProgramOfferBatchReadTest`, `ProgramTermsBatchReadTest`,
  `ProgramStorefrontReadCompatibilityTest`, `ClubProgramSharedCapacityTest`,
  `ClubProgramPaidIdentityCapacityTest`, `ClubProgramPaymentLifecycleTest`.
- Osoby/role: `AccountPersonRoleTest`, `AccountPersonClaimTest`, `FamilyPortalTest`,
  `AuthSessionValidationTest`; MariaDB autorizační race skripty pro dotčenou doménu.
- Sport/obchod: `SportsActivityTest`, `RaceResultsTest`, `ShopInventoryTest`,
  `ShopReturnsTest`; existující HTTP a MariaDB race skripty pro uzavření, výdej a vratky.
- Finance: `AccountingLedgerTest`, `AccountingDocumentsTest`, `AccountingExportTest`,
  `AccountingStockExportTest`; konzistentní snímky exportů a kontrakty ochrany VS.
- Komunikace: `AuthMailOutboxTest`, `TransactionalNotificationWorkerTest`,
  `OperationalDeliveryMariaDbRace.php`; transport pouze lokální/syntetický.

Před sloučením aplikační změny: Composer validate/audit, cílené regrese,
plný PHPUnit, MariaDB 10.3/11.4, PHPStan level 5 a jeho negativní kontrakt,
styl změněných PHP, anonymous sweep, JS kontroly, aktuální dokumentace a diff check.
POSIX/symlink bránu dokládá Linux bez skipů; Windows skip není úplný PASS.
Před deployem zkontrolovat také přesný sloučený main včetně CodeQL.
Čistě dokumentační plán se ověřuje odkazy/archivními kontrakty a jeho CI,
ne novým produkčním obchodním scénářem.

## 9. Autonomní realizační cyklus a změny schématu

1. Navazující implementační pokyn aktivuje K0 + W6.1. Před dalšími balíky číst
   tento plán, CURRENT_STATE, poslední protokol, aktuální main a otevřené PR.
   Platná oprávnění z konverzace se nepoptávají znovu; kontroluje se jejich rozsah.
2. Vlastní větev `codex/…`, jeden srozumitelný balík a explicitní seznam cest.
   Nezahrnovat cizí WIP, konfiguraci, klíče, dumpy ani skutečné osobní údaje.
3. Uložit plán řezu: problém, důkaz, vstupní rozpočet, invarianty, dotčené role,
   tabulky, testy, rollback a definici hotovo. Potom test → změna → ověření.
4. Před merge zkontrolovat celý diff a finální head CI, nikoli starší zelený SHA.
   Sloučení a nasazení provést v rozsahu platného zadání; příprava tohoto plánu
   sama neprovádí žádné nové aplikační vydání.
5. Produkční deploy jen existujícím chráněným workflow: ověřená záloha před
   aktivací, migrační check, zachování VS, HTTP a nový čtecí protokol před/po.
   Kódový release má `importovat_krouzky=false`, `uat_schvaleno=false`, dokud
   není skutečně zvlášť doložené plné UAT daného commitu.
6. Nová migrace pouze při prokázané potřebě, jako nový neměnný soubor. Odhadnout
   DDL zámky/dobu, použít aditivní kompatibilitu, otestovat upgrade i opakování.
   Nová vlastněná tabulka doplní backup ownership kontrakt. Žádné DDL v requestu.
7. Návrat rozlišuje kód a data: návrat předchozího kódu jen při kompatibilním
   schématu. Obnova zálohy není automatická reakce na chybu, mohla by ztratit
   nové transakce; připravit konkrétní dopřednou opravu nebo řízený návrat.
   Hosting stále používá rsync bez delete, nikoli doložený atomický symlink swap.
8. Po vydání uložit PR/SHA/CI/deploy, vzorky a výsledky; aktualizovat CURRENT_STATE,
   celý starý přehled archivovat s hashem. Samostatně sledovat implementaci,
   testy, merge, nasazení a lidskou přejímku. Ukončit jednu dohodnutou vlnu,
   nezačínat bez hranice neomezený řetězec dalších změn.

Při zahájení a dokončení etapy aktualizovat také stav tohoto plánu a stručný
odkaz v HANDOFF_CURRENT; nenechat po implementaci tvrzení „ještě nezačala“.

Když se prokáže regrese, neobcházet kontrolu ani nezvětšovat baseline. Opravit
reprodukovaný problém nebo z balíku změnu vyřadit. Když nelze pokračovat bez
reálné identity, účetní metodiky, provozního okna či souhlasu s novou aktivací,
zapsat přesný otevřený bod a pokračovat jiným nezávislým povoleným balíkem.

## 10. Mimo automatickou implementaci

Refaktoring nezapíná Fio/auto-confirm/SumUp/Stripe/Velocota/Packetu, nemění
frekvenci skutečných rozesílek, nepřevádí živé osoby a salda a nevypíná staré
služby. Stav každého poskytovatele a workeru znovu ověřit; neoznačovat všechny
transporty za vypnuté jen proto, že jsou vypnuté platební integrace.

[D01–D20](CLUB_COMPLETION_DECISIONS.md) a [pilot D12–D14](CLUB_PILOT_D12_D14.md)
nadále drží skutečné inventury/ocenění, rodinné vazby, trenérské rozsahy,
finanční dohody, převzetí účetní a dvě chybějící přílohy. Refaktoring může
zlepšit diagnostiku a zpracování, nesmí tyto skutečnosti doplnit odhadem.

## 11. Evidence a průběžný stav

| Položka | Stav k 29. 10. 2026 | Další výstup |
| --- | --- | --- |
| Vlny 1–5 | Dokončené a doložené | Zachovat regresní rozpočty a chování |
| Komplexní plán | Sloučený v PR #219; realizace autorizována | Průběžná aktualizace podle důkazů |
| K0 + W6.1 | První balík sloučen v PR #220 po úspěšném CI | Migrovaný profil doplnilo W6.3; K0.2 ostatních agend otevřené |
| W6.2 | [Čistá extrakce](REFACTORING_WAVE6_2026-10-29.md) sloučena a nasazena; [důkazy](verification/refactoring-wave6-release-20261029/RESULTS.md) | Hotovo; zachovat nové kontrakty |
| W6.3 | [Paměť kapacit](REFACTORING_W63_2026-10-29.md) sloučena a nasazena; [důkazy](verification/refactoring-w63-release-20261029/RESULTS.md) | Hotovo; dávkové sdílení mezi nabídkami zůstává samostatný kandidát, navazuje K0.2/W7 |
| W7.1a | [Cílený rodinný přístup](REFACTORING_W71_2026-10-29.md), charakterizace a migrovaný profil; ověřování | Rozpočet SQL a autorizační kontrakty; navazuje W7.1b race audit writerů |
| Zbytek W7 a W8–W15 | Naplánované, další měření otevřené | Malé PR podle závislostí a skutečných výsledků K0 |
| Lidské/účetní převzetí, aktivace a ostré převody | Otevřené podle konkrétních rozhodnutí | Samostatné podklady a provozní přejímka |

Každý balík povede protokol `docs/verification/<balik>/RESULTS.md` s: základním
SHA, hypotézou a měřením, změnou, přesnými příkazy/fixtures, výsledkem před/po,
omezeními, souběžnými testy, CI finálního headu, release identitou, případným
rollbackem a dalším krokem. Zjištění má status **ověřeno / kandidát / neplatí
na současném main / čeká na rozhodnutí**, nikoli neurčité „hotovo“.

### Podklady pro příští implementaci

[Protokol přípravy a omezení](verification/comprehensive-refactoring-plan-20261029/RESULTS.md).

[CLAUDE](../CLAUDE.md), [stav](CURRENT_STATE.md), [převzetí](HANDOFF_CURRENT.md),
[dokumentační základ](REFACTORING_DOCUMENTATION_2026-10-28.md),
[pátá vlna](REFACTORING_WAVE5_2026-10-28.md),
[poslední release](verification/refactoring-wave5-release-20261028/RESULTS.md),
[kvalita PHP](PHP_QUALITY.md), [F1–F3](DOZOR_F1_F3_2026-10-26.md),
[předání R01–R44](CLUB_IMPLEMENTATION_HANDOFF.md),
[datové toky](../outputs/data-flow-audit-2026-10-07/DATOVE_TOKY_KOD.md),
[historické nálezy 19. 10.](../outputs/data-flow-audit-2026-10-19/NALEZY_A_RIZIKA.md),
[identitní outbox](identity-email-queue.md), [nasazení](NASAZENI.md),
[migrace](../migrations/README.md).
