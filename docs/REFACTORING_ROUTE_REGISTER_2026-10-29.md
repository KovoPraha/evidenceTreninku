# K0.1 — registr cest a měření celé aplikace

Zdroj: `3cc60fb6d7fad4bc6430724529295ef025d75178`, ověřeno 29. 10. 2026.
Toto je mapa vybraných kritických toků, nikoli úplný seznam URL, bezpečnostní
certifikace ani měření všech agend. Guard znamená současnou kontrolu v kódu;
nenahrazuje zápisovou autorizaci po čekání na zámek. Výčty tabulek jsou hlavní
doménové zdroje, nikoli kompletní tranzitivní seznam SQL všech helperů/bootstrapu.

## Cesty, hranice dat a ověření

| ID / vstup | Identita a rozsah | Služba → hlavní tabulky | Vedlejší účinky / citlivost | Existující ověření; další měření |
| --- | --- | --- | --- | --- |
| R01 `booking/eshop.php`, `booking/produkt.php` | Veřejné; pouze zveřejněné nabídky. Členská cena má samostatné podmínky. | `shop_catalog_cards`, `shop_storefront_read`, `club_program` → `shop_products`, `shop_variants`, `shop_product_publications`, `club_program_offers`, `club_program_enrollments`, `shop_order_items` | Projekce; nezveřejnit interní nebo cizí osobní údaje. Kapacita je informativní do zamčeného checkoutu. | `ProgramOfferBatchReadTest`, `ProgramStorefrontReadCompatibilityTest`; vlny 1–5 a nynější profil kapacity. Změřit migrované schéma a různé počty překrývajících se nabídek. |
| R02 `booking/sportovni_prehled.php` | `verejny_uzivatel_id`; `familyPortalAuthorizedPeople`, aktuální schválená vazba self/guardian. | `family_portal`, `family_tasks`, `family_annual_paid_overview` → `account_person_roles`, `sportovci`, `club_roster_members`, `club_program_enrollments` a finanční zdroje | GET citlivý přehled; POST má CSRF a samostatné doménové akce. Zdravotní/sportovní a finanční údaje. | `FamilyPortalTest`, `FamilyAnnualPaidOverviewTest`; rodiny 1/5/20 osob, včetně odňaté vazby. HTTP/SQL profil dosud nezměřen. |
| R03 `booking/moje_osoby.php` | Zákaznická session; žádost o vazbu není schválená vazba. | `account_person_claim`, `athlete_registration` → `account_person_claim_requests`, `account_person_roles`, `sportovci` | Osobní údaje, auditovaná žádost; CSRF. Žádné automatické schválení podle shody jmen. | `AccountPersonClaimTest`, `AccountPersonRoleTest`; pending/rejected/cancelled a počet žádostí. Profil dosud nezměřen. |
| R04 `sprava_sportovcu.php`, `sportovec_karta.php` | Session trenéra, route owner/aktivní pozice, `canAccess('sprava_sportovcu')`; zápisy přes editor a membership služby. | `sportovec_editor`, `person_state`, `athlete_training_timeline` → `sportovci`, `club_roster_members`, `sportovec_interni_poznamka`, historie | Změna členství, poznámky, soupisky; důvody/revize/CSRF. Identifikační údaje a interní poznámky. | `SportovecEditorTest`, `SportovecEditorDetailsTest`, `SportovecInternalNoteTest`, MariaDB editor smoke; 100/1 000/10 000 osob, filtr i prázdný výsledek. Profil dosud nezměřen. |
| R05 `planovac.php` | Trenérská session, `canAccess('planovac')`, platné trenérské rozsahy v dotčených službách. | `training_planner_view`, `training_roster_bridge`, `training_rsvp` → `planovane_treninky`, `planovane_treninky_podskupiny`, `treneri`, soupisky | Čtení plánů a POST změny; plán není skutečná účast. Osoby a svěřené týmy. | `TrainingPlannerViewTest`, `PlannerJavascriptSmoke.cjs`; 1/12/52 týdnů a cizí týmy. Opakované SQL je kandidát, čas a řádky dosud nezměřeny. |
| R06 `training_closure_admin.php` | Doménová autorizace uzavření a trenérský rozsah, opět uvnitř transakce. | `training_closure`, `sports_activity` → `training_closures`, `treninky`, `trenink_sportovec`, související historie | Uzavření a opravy skutečné účasti, audit. Žádné tiché přepsání uzavřené historie. | `TrainingClosureTest`, `TrainingClosureMariaDbRace.php`, `TrainingClosureAuthorizationRace.php`; syntetické skupiny 10/50/200. Profil dosud nezměřen. |
| R07 `club_calendar.php` | Trenérská session, route owner; doménové kontroly konkrétní akce a rozsahu. | `club_calendar` → `club_events`, `club_event_planned_participants`, `club_event_charges`, `ucto_vozidla` | Akce, účast, poplatky a audit; CSRF. Plátce/účastník nejsou zaměnitelní. | `ClubCalendarTest`; 1/12/52 týdnů, různé typy událostí. Profil dosud nezměřen. |
| R08 `booking/rezervovat.php` | Zákaznická session, dostupný termín a oprávněná osoba; one-time token + CSRF. | Stránka, `individual_lesson_context`, `booking_notification`, `club_finance` → `individualni_lekce`, `verejne_rezervace`, fronta oznámení | Rezervace pod `GET_LOCK`, čekací listina a fronta; stránka má také větev push. Transport musí být při měření izolován. | `TransactionalNotificationWorkerTest` ověřuje frontu, nikoli celý rezervační souběh. Před W8 doplnit konkrétní HTTP/race měření posledního místa a storna. |
| R09 `booking/objednavka.php` | Zákazník, vlastní košík/osoby, serverové ceny, CSRF; zámky a opakovaná práva v checkoutu. | `shopCheckoutPlace` → `shop_carts`, `shop_cart_items`, `shop_orders`, `shop_order_items`, `payments`, kapacity a sklad | Objednávka, VS, rezervace, outbox; finanční a osobní údaje. Bez volání skutečné brány. | `ShopCheckoutMariaDbSmoke.php` (idempotence/poslední místo), `ShopAuthorizationMariaDbRace.php`; 1/10/50 položek. Nový výkonový profil dosud nezměřen. |
| R10 `eshop_inventory_admin.php` | Aktivní catalog_manager/finance_manager; rozdílné úkony podle role, `shopInventoryActor`/`shopInventoryAssertActorRoles`. | `shop_inventory` → `shop_stock_balances`, `shop_stock_movements`, `shop_stock_lots`, rezervace | Příjem, blokace, inventura, cena; potvrzení/důvod/idempotence/CSRF. Cena nemá přejít na skladovou roli. | `ShopInventoryTest`, `ShopInventoryMariaDbRace.php`; 100/1 000/10 000 variant. Filtr po načtení všech variant je ověřený tvar kódu, výkonový dopad dosud nezměřen. |
| R11 `accounting_ledger_admin.php` | finance_manager + `accountingLedgerActor`; důvod/potvrzení/CSRF a kontrola v transakci. | `accounting_ledger` → `accounting_movements`, alokace, účty a audit | Skutečný pohyb peněz odlišný od předpisu. Citlivé částky, příjemci a účetní období. | `AccountingLedgerTest`, `AccountingLedgerMariaDbRace.php`; 1 000/10 000/100 000 řádků, uzavřená období. Profil dosud nezměřen. |
| R12 `accounting_documents_admin.php`, `accounting_exports_admin.php`, příslušné downloady | finance_manager + doménový actor; soukromý download s aktuálním oprávněním. | `accounting_documents`, `accounting_export` → `accounting_documents`, `accounting_export_batches`, `accounting_export_events`, ledger/sklad/klubové zdroje | Neměnný snapshot, PDF/XLSX/ZIP, soukromé soubory, explicitní předání; download není platba/převzetí. | `AccountingDocumentsTest`, `AccountingExportTest`, `AccountingStockExportTest`; měřit snapshot a render odděleně pro 1 000/10 000/100 000 řádků. Profil dosud nezměřen. |
| R13 `medical_clearance_admin.php`, `medical_activity_admin.php` | Doménový actor a konkrétní svěřený rozsah; soukromé podklady. | `medical_clearance`, `medical_activity` → `medical_clearance_records`, `medical_clearance_watches` | Zdravotní údaje, platnost a historie. Do profilů jen ID syntetických osob a agregované počty. | `MedicalClearanceTest`, `MedicalClearanceMariaDbRace.php`; časové hranice a cizí osoby. Profil dosud nezměřen. |
| R14 `auth_mail_admin.php`, worker transakčních zpráv, `bin/roster-message-worker.php` | Admin route + role; CLI/scheduler má vlastní konfiguraci. Nezaměňovat worker s veřejnou stránkou. | `auth_mail_outbox`, `transactional_notification_worker`, `roster_message` → `auth_mail_outbox`, doménové fronty | Claim/retry/send; šifrované přístupové odkazy. K měření použít testovací sender; nevypsat payload/token. | `AuthMailOutboxTest`, `TransactionalNotificationWorkerTest`, `OperationalDeliveryMariaDbRace.php`; 0/100/10 000 čekajících, souběh/expirace. Profil dosud nezměřen. |
| R15 `sync_evidence.php`, `kis_person_import_admin.php` | Trenérská session, route owner, `canAccess('sync_evidence')` u synchronizace; oddělený import actor. | `kis_sync_lib`, `kis_import_run_lib`, person import → `kis_import_runs`, `kis_import_rows`, `kis_import_matches`, osoby/soupisky | Preview není apply; fingerprint, mapy, karanténa, transakce, privátní podklady. Pouze syntetické importy. | `KisImportPreviewIntegrityTest`, `KisPersonImportMariaDbSmoke.php`; 100/1 000/10 000 vstupů s konflikty. Profil dosud nezměřen. |
| R16 `bin/db-backup.php` a obnova v testu | CLI-only, explicitní app root/cíl, databázové přihlašovací údaje mimo výstup. | Backup ownership + migrační katalog → vlastněné tabulky, views/triggery a privátní soubory podle typu zálohy | Soubor s citlivými daty, checksum, retenční mazání pouze v určeném cíli; obnova výhradně izolovaná. | `DatabaseBackupMariaDbSmoke.php`, `DatabaseBackupOwnershipContractTest`; měřit export, kompresi a obnovu odděleně. Nový výkonový profil dosud nezměřen. |

## Měření, rozsah a další pořadí

K0.2 se zavádí po doménách: nejprve kapacita R01, poté rodina/registrář R02–R04,
sport R05–R08, obchod R09–R10, finance R11–R12 a provoz R13–R16 podle plánu.
Navržené velké objemy jsou testovací profily, nikoli zjištěný počet produkčních dat.
Před každým profilem ověřit proveditelnost, seed, izolaci transportů a oprávnění.

Výstup každého profilu: base SHA, fixture + schema, role/negativní případy,
počet SQL, načtené řádky (ne řádky prohledané serverem), execute/fetch čas,
PHP čas, peak memory, velikost výsledku, 5 zahřátí + 30 vzorků, medián,
EXPLAIN na migrovaném schématu před návrhem indexu. HTTP a browser měřit zvlášť.
Testovací `QueryProfilePdo` podporuje execute/query + fetch/fetchColumn/fetchAll;
nezachytává exec, iterátor, chybné SQL ani samotnou přípravu. Nesmí se připojit
k produkci ani používat pro transportní payloady. Aplikační bootstrap se nemění.

| Nález | Důkaz / stav | Priorita a další krok |
| --- | --- | --- |
| Kapacita míchá načtení a deduplikaci | Ověřeno v `clubProgramOfferCapacityStateForTeam`; charakterizační testy | W6.2: malá čistá extrakce po uzavření měřicího základu. Zámky/SQL ponechat. |
| Dávkování obsazenosti může zmenšit počet dotazů, ale zvětšit načtenou historii | Kandidát; profil fan-out v tomto balíku | W6.3 až po migrovaném schématu, reálném EXPLAIN a dávkových limitech. Bez návrhu nového indexu v tomto balíku. |
| Plánovač znovu provádí COUNT v části vykreslování | Ověřený zdroj `planovac.php`, nikoli doložená latence | W8.1: charakterizovat výstup a rozsah, změřit SQL/řádky pro více týdnů. |
| Sklad načte všechny varianty a filtruje text v PHP | Ověřený zdroj `eshop_inventory_admin.php` | W9.3: měření a kontrakt hledání/kolace před přesunem do SQL/stránkování. |
| Účetní snapshot používá fetchAll pro více domén | Ověřený zdroj `includes/accounting_export.php`; paměť neznámá | W10.2: oddělit profil snapshotu/renderu, zachovat konzistentní okamžik. |
| Starý nález přímé pošty v registraci/resetu | Neplatí na současném main; nyní auth outbox | W11 nevytváří druhou frontu; ověří skutečné producenty a retry. |

Nezjištěná latence není nula ani PASS. Tato mapa uzavírá první inventuru K0.1;
K0.2 celé aplikace a doménové detailní audity zůstávají průběžné.

## Doplnění po W6.3, 29. 10. 2026

Zdroj `3a168a68050be1d44648086bdb190df7c556617e`, PR #223.
R01 má nyní [profil migrovaného schématu](verification/refactoring-w63-20261029/RESULTS.md)
se skutečnými indexy, FK a obnovenými statistikami. W6.2 oddělilo čistý výpočet;
W6.3 odstranilo materializaci rezervovaných řádků v PHP. Shoda stavů a SQL,
limit přírůstku paměti ≤ 4 MiB / ≤ 60 % baseline při 10 000 položkách PASS
na MariaDB 10.3/11.4. Profil používá 3 warmupy + 15 střídavých vzorků; měření
času/paměti běží na běžném PDO odděleně od instrumentace SQL.

Dávkové sdílení mezi nabídkami zůstává kandidát, nikoli hotová implementace.
Výše uvedené první nálezy jsou historický vstup; příští hlavní krok K0.2/W7
je měření R02–R04. Ostatní dosud nezměřené profily se tímto neuzavírají.

## Doplnění W7.1a, 29. 10. 2026

Zdroj `81544542bb40cb96e7141791ea22e4b382531d5e`, PR #225.
[Profil rodinného přístupu](verification/refactoring-w71-20261029/RESULTS.md)
ověřuje konkrétní službu `familyPortalCanViewPerson` sdílenou rodinnými agendami:
rodiny 0/1/5/20 osob, vlastní/cizí profil, všech 140 migrací s doplněnými legacy
membership sloupci, FK a EXPLAIN. U 20 osob SQL 24 → 4, datové řádky 40 → 0/1,
stejná pravidla přístupu; žádná cache. CI MariaDB 10.3/11.4 a negativní testy PASS.
Nejde o kompletní HTTP profil R02–R04; ty zůstávají otevřené.

Původní kandidát revokace během čekání writeru byl ověřen a opraven v W7.1b.

## Doplnění W7.1b, 29. 10. 2026

PR #227, zdroj `66521929f7e9ce55f7a03d669cb7821f49382f6c`.
[Souběhový protokol](verification/refactoring-w71b-20261029/RESULTS.md):
15 nepovolených zápisů před opravou, následně 32/32 PASS na MariaDB 10.3/11.4.
RSVP, rezervace a storno náhrady kontrolují aktuální oprávnění po doménových
zámcích před první mutací a drží je do konce transakce. Čtecí helpery beze změny.
Tím se neuzavírá audit všech writerů ani plné profily R02–R04; navazuje W7.2
úplným rodinným přehledem R02 a teprve podle měření další optimalizací.
