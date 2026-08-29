# Datové toky aplikace EvidencePavel – technická mapa

Datum auditu: 24. 8. 2026
Auditovaný stav: Git `30a357551b1e9f483055bf190260deb491d36823`
Rozsah: pouze zdrojový kód v `C:\xampp\htdocs\evidencePavel`; bez přístupu k databázi, bez spuštění aplikace, bez externích volání a bez produkčních změn.

## Implementační aktualizace po mapování

Po vytvoření této mapy byly 29. 8. 2026 v pracovním stromu nad `main` `11f7b1f` změněny kritické větve: legacy závodní writery vracejí 410, KIS wizard je preview-only, přílohy tréninků/závodů používají kompenzační file transaction, UCI šablona leží v soukromém `uci-temp`, odkazy používají `appUrl()` a webový `db.php` již nespouští DDL. Detail původních toků zůstává auditním snapshotem; zdejší popisy opravených větví a stavový přehled v `NALEZY_A_RIZIKA.md` zachycují aktuální implementaci.

## 1. Jak tuto mapu číst

Tento dokument popisuje, kudy data procházejí od vstupu až k uložení nebo výstupu. Grafická verze je v `DATOVE_TOKY_APLIKACE.xlsx`. Každý tok používá tuto posloupnost:

`vstup → autentizace/oprávnění → validace a normalizace → aplikační funkce → transakce/úložiště → vedlejší efekty → odpověď`

Kódová reference `soubor.php:123` znamená řádek ve stavu uvedeném výše. Statická inventura v Excelu je automatický záchyt; ručně popsané toky v tomto dokumentu jsou autoritativnější tam, kde jednoduchý regulární výraz neumí rozlišit SQL v komentáři, dynamický název tabulky nebo nepřímé volání.

## 2. Rozsah vstupní plochy

Statická inventura první strany (bez `vendor/`, `tests/`, `migrations/`, `docs/` a `var/`) našla:

- 334 PHP souborů první strany,
- 229 potenciálních vstupních/obslužných souborů mimo `includes/`,
- 207 souborů s formulářem nebo externím vstupním kanálem,
- 277 HTML formulářů,
- 115 souborů čtoucích POST, 112 čtoucích GET a 15 čtoucích uploady,
- 4 JSON body endpointy, 12 CLI vstupů a 29 souborů čtoucích prostředí,
- 69 vstupních souborů s přímo zachyceným SQL zápisem,
- 17 download/export cest, 38 souborů se souborovým vedlejším efektem a 8 vstupních souborů s přímým `mail()`.

Čísla jsou metrika zdrojového stromu, ne důkaz dostupnosti konkrétní URL v nasazeném prostředí.

## 3. Hlavní datové identity a hranice oprávnění

| Identita / hranice | Zdroj dat | Vznik a ověření | Kam se propaguje | Kód |
|---|---|---|---|---|
| Trenér | `treneri` | Přihlášení jménem/e-mailem, rate limit, právě jedna heslová shoda | `$_SESSION['trener_id']`, role, oprávnění, pracovní pozice | `login.php:29-89`, `includes/auth_rate_limit.php:218`, `includes/auth_session.php:80` |
| Veřejný účet | `verejni_uzivatele` | E-mail + heslo, ověřený e-mail, sjednocení s trenérem | `$_SESSION['verejny_uzivatel_id']`, rodina, košík, rezervace | `booking/prihlaseni.php:20-69`, `includes/unified_account.php:64` |
| Sportovní účet dítěte | `child_access_accounts` | Oddělené přihlášení, revokační verze | Omezený sportovní přehled jedné osoby | `booking/sportovec_prihlaseni.php`, `includes/child_access.php` |
| Veřejná karta sportovce | `sportovci.hash` | 256bitový bearer token v URL | Čtení tréninků a zápis vlastní poznámky | `includes/public_profile_token.php:4`, `sportovec_treninky.php:69-76`, `ajax_sportovec_poznamka.php:13-64` |
| Zaměstnanecká pozice | `staff_user_positions`, `staff_superadmins` | Aktivní pozice v session + serverové ověření | Jemnější oprávnění pro finance, registraci, trenéry a správu | `includes/staff_workspaces.php:532`, `prepnout_pracovni_pozici.php` |
| CLI/CRON | PHP SAPI + prostředí | CLI guard, env konfigurace, někde potvrzovací parametr | Importy, migrace, workery, expirace, zálohy | `bin/*.php`, `cron_upominky.php:14-17` |
| Webhook | Podepsané HTTP tělo | Stripe podpis + event ID + serverový snapshot | Kanonický platební přechod | `booking/stripe_webhook.php`, `includes/stripe_gateway.php:148-190` |

## 4. Autentizace, registrace a osoby

### A01 – Přihlášení trenéra

`login.php` přijme `jmeno` a `heslo`, ověří CSRF, rezervuje rate-limit pokus a načte všechny přesné kandidáty jména/e-mailu. `trainer_password_unique_match()` přijme pouze jednu heslovou shodu. Po úspěchu může být hash hesla přepočítán, vznikne nebo se dohledá spojený veřejný účet a rotuje se session i CSRF token. Do session se zapíše trenérská i veřejná identita a obnoví se oprávnění/pracovní pozice (`login.php:29-89`).

Větve: neplatný CSRF → 400; rate limit → obecná chyba; žádná nebo víceznačná shoda → odmítnutí; úspěch → `index.php`.

### A02 – Přihlášení veřejného účtu

`booking/prihlaseni.php` používá `unifiedAccountAuthenticate()`. Nejprve ověřuje veřejný účet, případně trenéra stejného e-mailu. Trenér může získat současně zákaznickou i trenérskou session. Návratový cíl je omezen na interní relativní PHP cestu (`booking/prihlaseni.php:20-69`, `includes/unified_account.php:64-94`).

### A03 – Registrace a ověření e-mailu

Formulář kontroluje rate limit, jméno, příjmení, e-mail, datum narození a heslovou politiku. Stejná veřejná odpověď pro existující a nový e-mail omezuje enumeraci. Nový účet a `publicProfileSave()` vznikají v jedné transakci; jednorázový token se ukládá pouze jako hash. Po commitu se přímo volá `mail()` (`booking/registrace.php:35-120`, `includes/one_time_token.php:9`). Ověření tokenu pokračuje v `booking/overeni.php` a naváže session.

### A04 – Obnova hesla

`booking/zapomenute_heslo.php` předá identifikátor do `passwordResetRequest()`. Funkce invaliduje staré tokeny, vloží nový hash a commitne. Doručení proběhne až potom callbackem; návratová hodnota `false` z `mail()` se nevyhodnocuje (`includes/password_reset.php:15-60`). Spotřeba tokenu mění heslo a revokační stav v `booking/nove_heslo.php`.

### A05 – Propojení osob a registrace sportovce

Veřejný účet může požádat o propojení existující osoby (`booking/moje_osoby.php` → `accountPersonClaimSubmit()`) nebo o registraci sportovce s verzovaným souhlasem a soukromou fotografií (`booking/registrace_sportovce.php` → `athleteRegistrationSubmit()`). Administrace v `eshop_identity_admin.php` větví žádost na schválení existující osoby, založení osoby, zamítnutí, přiřazení do soupisky a samostatné vystavení členského předpisu (`includes/athlete_registration.php:52`, `includes/athlete_registration_admin.php:277`). Citlivá data a fotografie používají oddělené tabulky a soukromé úložiště.

### A06 – Pracovní pozice a oprávnění

Klasické `canAccess()` čte matici oprávnění načtenou do session. Novější oblasti navíc vyžadují aktivní pracovní pozici nebo superadministrátora. Přepnutí pozice je POST+CSRF, validuje povolený cíl a zapisuje událost (`prepnout_pracovni_pozici.php`, `includes/staff_workspaces.php:532`).

## 5. Tréninky, měření a závody

### S01 – Nový trénink

`formular.php` sbírá datum, obsah, kategorii, trenéry, skupiny/podskupiny, účastníky, tagy, obrázky, rezervaci sportoviště a dynamická měření. JavaScript serializuje měření do `mereni_json`. `ulozit_trenink.php` ověří session/CSRF, parsuje měření přes `sportsMeasurementRowsFromPost()`, vyžádá sportovce u každého řádku a zahájí transakci (`includes/sports_measurement_input.php:130`, `ulozit_trenink.php:91-101`).

Nové přílohy nejprve vstupují do stagingu `fileMutationTransactionStageUpload()`. Uvnitř DB transakce vzniká `treninky` a vazby `trenink_trener`, `trenink_skupina`, `trenink_podskupina`, `trenink_sportovec`, `tagy`/`trenink_tag`, `mereni_zaznamy`/`trenink_mereni`. Je-li vstupem `plan_id`, plán se zamkne, zkopíruje se očekávaná soupiska přes `trainingRosterBridgeCopyPlanToTraining()` a plán přejde na `evidovany`. Po DB commitu se soubory dokončí přes `fileMutationTransactionFinalize()`; chyba uklidí staging a kompenzuje již provedené přesuny.

### S02 – Editace tréninku

`edit_trenink.php?id=` předvyplní formulář. `update_trenink.php` znovu používá společný parser měření, přepíše vazby a měření v transakci a zachová společný normalizovaný kontrakt. Nové obrázky se stageují, odebrané soubory se evidují k retire a filesystem se mění až v řízeném finalize kroku po DB commitu. Chybová větev používá rollback/kompenzaci společného `file_mutation_transaction.php`.

### S03 – Smazání tréninku

`smazat_trenink.php` vyžaduje POST+CSRF a oprávnění. V transakci odpojí plán/rezervaci a smaže záznam. Soubory jsou měněny na úrovni filesystemu, takže nejde o jednu atomickou jednotku s DB.

### S04 – Veřejná karta a poznámka sportovce

Bearer `sportovci.hash` vybere osobu a zpřístupní její tréninky, měření a veřejné obrázky. Stránka vytvoří CSRF token pro anonymní session. `ajax_sportovec_poznamka.php` ověří hash, CSRF a skutečnou účast/měření sportovce v tréninku a pak provede upsert do `sportovec_poznamka` (`sportovec_treninky.php:69-76,988-1048`, `ajax_sportovec_poznamka.php:17-64`).

### S05 – Zátěžový test

`zatezovy_test_form.php` → `ulozit_zatezovy_test.php`. Základní záznam a metadata souborů vznikají v transakci. Soubory pro veřejný obrázek, interní obrázek a ostatní přílohy jdou do `privateStorageStore()`; při rollbacku se nově uložené klíče soft-delete uklidí (`ulozit_zatezovy_test.php:47-129`, `includes/private_storage.php:74`). Veřejný obrázek vydává `private_download.php?kind=stress&id=&hash=`, interní část vyžaduje trenéra.

### S06 – Kanonické vytvoření závodu

`formular_zavod.php:161` odesílá do `ulozit_zavod.php`. Společný parser zpracuje měření; soubory se nejprve stageují a transakce zapíše `zavody`, trenéry, skupiny, podskupiny, účastníky, fotografie, importní soubory a normalizovaná měření. Po commitu se souborový plán finalizuje, při chybě rollbackuje/kompenzuje.

### S07 – Kanonická editace závodu

`edit_zavod_form.php:309` odesílá do `update_zavod.php`. Handler aktualizuje závod, sesouhlasí vazby účastníků bez ztráty jejich výsledků a znovu vloží měření přes společný kontrakt. Nové a odebírané soubory vede ve společném file-transaction plánu; finalize následuje až po DB commitu a chybová větev provede kompenzaci.

### S08 – Starší závodní větve

Obě historické větve jsou fail-closed. `edit_zavod.php` při GET zachová kompatibilní přesměrování, ale zápisový request vrátí HTTP 410. `import_vysledku_zavodu.php` vrací HTTP 410 bez načtení uploadu nebo provedení SQL. Jediné podporované závodní writery jsou `ulozit_zavod.php` a `update_zavod.php`.

### S09 – Read-only kontrola kvality sportovních dat

`sports_data_quality_admin.php` a `sports_import_review_admin.php` pouze agregují/klasifikují historická a normalizovaná data. Nevytvářejí import ani backfill; jsou kontrolním výstupem.

## 6. Plánování, sportoviště a rezervace

### P01 – Plánovaný trénink

`planovany_trenink_form.php` zapisuje jeden nebo více plánů, opakování a M:N podskupiny. `trainingRosterBridgeReplacePlanTeams()` ukládá cílové soupisky. `planovac.php` může kopírovat týden nebo rušit plán/sérii. JSON endpoint `ajax_update_plan.php` umožní rename/move pouze vlastníkovi nebo správci a kontroluje CSRF.

### P02 – Rezervace sportoviště

`rezervovat_sportoviste.php` validuje čas, kapacitu, kolize a volitelně zároveň vytvoří plán. Zápisy do `rezervace_sportovist`, `planovane_treninky` a jejich vazeb probíhají transakčně a zapisují `venue_operation_events`. Zrušení používá `venueReservationCancel()` (`includes/venue_operations.php:24`).

### P03 – Individuální lekce

`individualni_lekce_form.php` vytváří nebo upravuje lekci, případně opakování. `individualni_lekce_sprava.php` mění stav lekce/rezervace, ruší rezervaci sportoviště a spouští čekací listinu. Zákaznické e-maily se zde stále posílají přímo v requestu.

### P04 – Veřejná rezervace lekce

`booking/rezervovat.php` přijme lekci, slot, poznámku a waitlist. Po CSRF použije MySQL advisory lock pro poslední kapacitu, znovu kontroluje duplicitu a vloží `verejne_rezervace` (`booking/rezervovat.php:98-154`). Větve: zelená → `potvrzena`; žlutá → `ceka` + jednorázový schvalovací token; plno → `cekaci_listina`. Po DB zápisu se best-effort posílá push a přímý e-mail (`booking/rezervovat.php:163-208`).

### P05 – Čekací listina a potvrzení

Storno zákazníka nebo zamítnutí trenérem volá `notifyWaitingList()`. Funkce vezme prvního čekajícího, změní stav a pošle přímý e-mail. `booking/potvrdit.php` používá hashovaný jednorázový token a potvrzovací POST fázi.

### P06 – Veřejný velodrom

`booking/velodrom.php` může rezervovat bezplatný termín přímo přes `publicVelodromeReserve()` nebo vložit placený termín do košíku. Placená rezervace se aktivuje až v kanonickém platebním přechodu (`includes/public_velodrome.php:295`, `includes/public_velodrome_shop.php`).

### P07 – Kalendářové výstupy

`booking/verejny_kalendar.php` skládá anonymní ICS z publikovaných tréninků, událostí a velodromu. `booking/rodinny_kalendar.php` nejprve vyřeší revokovatelný hashovaný feed token a pak skládá rodinné položky. Oba výstupy končí `publicCalendarRender()` (`includes/public_calendar_feed.php:93`).

## 7. E-shop, katalog a platby

### E01 – Shoptet katalog

CLI `bin/shoptet-products-dry-run.php` přijme lokální CSV/XML, parser kontroluje velikost, formát a kontrakt, ale nic nezapisuje. `bin/shoptet-products-stage.php --apply` zapisuje kandidáty přes `shopCatalogStage()`. Admin `eshop_admin.php` provede review a explicitní `shopCatalogPromote()`, čímž vzniknou kanonické draft produkty/varianty. Další samostatný krok publikace řídí `shopCatalogPublicationActivate()` (`includes/shop_catalog_stage.php:13`, `includes/shop_catalog_promotion.php:38`).

### E02 – Ruční katalog a metadata

`eshop_produkt_admin.php` vytváří/archivuje produkt, varianty, skladové pohyby a bezpečně překódované obrázky. Kategorie, atributy, kupóny a členské ceny mají samostatné admin formuláře a auditní tabulky. Tyto toky mění kanonický katalog, ale samy nevytvářejí objednávku.

### E03 – Košík

`booking/eshop.php` větví POST podle `action`: přidání/množství, příjemce programu, kupón, odebrání události nebo velodromu a checkout (`booking/eshop.php:19-60`). Košík slučuje zboží/programy, placené události a placený velodrom.

### E04 – Checkout

Checkout vyžaduje ověřený účet, CSRF, jednorázový klíč a fingerprint košíku. `shopCheckoutPlace()` získá advisory lock a DB transakci, zamkne košík, varianty, události, termíny a programové nabídky, znovu vypočte ceny/slevy a porovná fingerprint. Poté vytvoří `shop_orders`, snapshoty položek, skladové pohyby, `payments` a kapacitní holdy; idempotency hash vrací stejnou objednávku při replay (`booking/eshop.php:50-56`, `includes/shop_checkout.php:196-330`).

### E05 – Bankovní platba

`eshop_payments_admin.php` po oprávnění, důvodu a potvrzení volá `shopOrderAdminConfirmBankPayment()`. Uvnitř transakce se používá `shopOrderConfirmPaymentInTransaction()`, která zamkne platbu a objednávku, přepne `pending → paid`, vytvoří auditní událost, aktivuje program, klubovou událost a velodrom a vloží idempotentní notifikaci do společné fronty (`includes/shop_checkout.php:703-736`).

### E06 – Stripe

`booking/objednavka.php` může při aktivním fail-closed nastavení vytvořit Stripe Checkout Session z uloženého serverového snapshotu. `booking/stripe_webhook.php` přijme raw JSON a podpis. `stripeHandleWebhook()` ověří podpis, idempotentně vloží event ID, zamkne navázanou platbu, porovná metadata/částku/měnu a zavolá stejný kanonický platební přechod jako banka (`includes/stripe_gateway.php:148-190`). Raw payload se neukládá, pouze jeho SHA-256.

### E07 – Storno, expirace, fulfillment a vratka

Administrace objednávek/plateb používá samostatné potvrzené akce s důvodem. Storno obnovuje sklad a ruší navázané programy/události/velodrom. Zaplacené storno vytváří `refund_required`; teprve samostatné potvrzení vratky přepne `refunded`. CLI `bin/expire-shop-orders.php` a webový preview admin expirují pouze čekající objednávky.

### E08 – Oznámení o přijaté platbě

`shopPaymentNotificationEnqueue()` musí běžet uvnitř transakce platby a vloží snapshot e-mailu do existující `club_event_notifications` (`includes/shop_payment_notification.php:134-165`). CLI `bin/club-event-notifications.php` claimne zprávu, odešle ji přes mail transport nebo localhostový souborový outbox a uloží stav/událost (`includes/club_event_notification.php:128`).

## 8. Klubové programy, události a soupisky

### K01 – Program a nabídka

Admin formuláře zakládají stabilní program, sezonní nabídku, věkové hranice, kapacitu, vazbu na produktovou variantu a verzované podmínky. Wizard `clubProgramWizardCreate()` může v jedné koordinované transakci vytvořit program, nabídku, katalogové vazby a obrázek (`includes/club_program_wizard.php:29`).

### K02 – Přihlášení na klubovou událost

`booking/krouzky.php` vybere oprávněnou osobu a ověří cílové soupisky, kapacitu a verzi souhlasu. Bezplatná událost vytvoří registraci přímo přes `clubEventRegisterParticipant()`. Placená vloží snapshot do košíku a aktivuje se až po platbě (`includes/club_event_registration.php:306`, `includes/club_event_shop.php`).

### K03 – Aktivace programu platbou

Po potvrzení platby `clubProgramActivatePaidOrderInTransaction()` vytvoří/obnoví `club_program_enrollments` a naváže člena na cílovou soupisku. Ruční stránka `booking/moje_programy.php` umí idempotentně dokončit oprávněnou položku stejného účtu (`includes/club_program.php:517`).

### K04 – Soupisky, rollover a přechody

`kis_roster_settings_admin.php` spravuje série, sezony a týmy. `kis_rosters_admin.php` přidává/odebírá členy, výjimky a provádí potvrzený rollover podle fingerprintu. `kis_transition_admin.php` provádí náhled a potvrzený přesun hobby člena. `kis_training_a07_admin.php` porovnává očekávanou a skutečnou docházku bez zápisu.

## 9. Importy KIS a Fio

### I01 – Upload tří KIS XLSX

`sync_evidence.php` vyžaduje tři XLSX: uživatele, platby a soupisky. `validateKisUpload()` kontroluje upload a `kis_build_import()` sestaví společný kontrakt podle stabilního KIS ID. Každý zdroj se archivuje mimo webroot s hashem/velikostí přes `kisSourceArchive()` a vznikne `kis_import_runs` s řádky, matchi a preview (`sync_evidence.php:188-239`, `includes/kis_sync_lib.php:251`, `includes/kis_import_run_lib.php:17`).

### I02 – Mapování a preview-only wizard

Krok 2 ukládá persistentní `soupiska_mapping`; další kroky sestaví a zobrazí preview. `sync_evidence.php` již neobsahuje přímý writer `executeSync()` a nemění kanonické osoby ani jejich skupinové vazby. Kanonické změny patří do fingerprintovaných promote/rollback funkcí KIS centra.

### I03 – KIS centrum, sandbox a členské předpisy

`kis_sync_center.php` zobrazuje uložený preview/field/parity report. Localhost-only potvrzený `kisImportSandboxPromote()` pracuje jen v izolovaném sandboxu a má rollback. Samostatný `kisMemberChargePromote()` přenáší přesně spárované předpisy a historické platby do auditních cílových tabulek, opět s fingerprintem, důvodem a rollbackem (`includes/kis_import_sandbox_promotion.php:101`, `includes/kis_member_charge_promotion.php:255`).

### I04 – Fio read-only import

`bin/fio-import.php` načte token a období z prostředí, `fioFetchPeriodJson()` volá pouze HTTPS Fio API bez redirectu a `fioImportJson()` ověří období i očekávaný IBAN. Uloží run, deduplikované pohyby a návrhy párování; automatické potvrzení objednávky se zde neprovádí (`includes/fio_readonly_import.php:94,141`).

### I05 – Výsledkové a šablonové importy

Závodní přílohy se ukládají do blokovaného `nahrane_zavody/results` a vydávají přes autorizovaný `download_import.php`. `export_uci.php` přijímá uživatelskou XLSX šablonu do dedikovaného soukromého prostoru `private://uci-temp`, session drží pouze opaque klíč a výdej/úklid probíhá přes private-storage API. Historická webroot cesta `uploads/temp` je navíc blokovaná v Apache.

## 10. Komunikace a automatizace

### N01 – Oznámení

`oznameni.php` a JSON `ajax_nova_oznameni.php` ukládají text a cíle do `oznameni`/`oznameni_targets`. HTML pro zobrazení prochází sanitizací. JSON endpoint vyžaduje session, `canAccess()` a CSRF v těle.

### N02 – Hromadný e-mail

`odeslat_emaily.php` dělá výběr příjemců, preview, potvrzení a přímo volá `mail()` pro každého. Výsledek se zapisuje do `email_log`; nejde přes společnou frontu.

### N03 – Upomínky na nezaevidované tréninky

CLI-only `cron_upominky.php` vybere staré plány bez `upominka_cas`, seskupí je podle trenéra, zavolá `mail()` a při návratu `true` označí všechny plány daného e-mailu časem odeslání. Odkaz do aplikace se skládá přes kanonické `appUrl()`; samotné přímé doručování zůstává otevřeným nálezem M2.

### N04 – Členské připomínky

Preference ve `booking/sportovni_prehled.php` řídí opt-in. `memberChargeReminderGenerate()` idempotentně vytvoří frontu, CLI worker zprávy claimuje a doručuje přes mail nebo localhost outbox. Admin umožní pouze kontrolované retry/demo (`includes/member_charge_reminder.php:107`, `bin/member-charge-reminders.php`).

### N05 – Týdenní rodinný souhrn

Stejný vzor používá `familyWeeklyDeliveryGenerate()` a `bin/family-weekly-summaries.php`: preference → idempotentní snapshot → claim → transport → stav a audit (`includes/family_weekly_delivery.php:119`).

### N06 – Web Push

`push_subscribe.php` přijímá JSON, ověřuje CSRF a tvar endpointu/klíčů a provede upsert/delete v `push_subscriptions`. `sendPushNotification()` načte VAPID konfiguraci, vybere odběratele a volá knihovnu WebPush. Neúspěch push nesmí zrušit rezervaci.

## 11. Provozní evidence a soubory

### O01 – Vozidla, jízdy, servis, účtenky a události

Adresáře `vozidla/`, `jizdy/`, `servis/`, `uctenky/` a `udalosti/` používají trenérskou session, `canAccess()`, CSRF a připravené SQL. Zápisy končí v `ucto_*` tabulkách a `zapisAuditLog()`. Audit helper rekurzivně rediguje klíče obsahující heslo, secret, token, cookie nebo CSRF (`includes/funkce.php:53-76`). Servisní dokumenty a účtenky používají soukromé úložiště a autorizovaný výdej.

### O02 – Veřejné a soukromé soubory

Soukromé klíče mají tvar `private://kategorie/náhodný-soubor`, fyzicky leží mimo webroot, mají MIME allowlist a oprávnění 0600/0700 (`includes/private_storage.php:74-109`). `private_download.php` rozhoduje podle druhu záznamu, pozice a případně bearer tokenu. Veřejné tréninkové/závodní fotografie zůstávají v adresářích pod webrootem a jsou určeny k přímému zobrazení.

### O03 – Exporty a reporty

CSV/XLSX exporty čtou filtry z GET/POST a posílají soubor do `php://output`. CSV helpery neutralizují vzorcové prefixy. UCI/dráha používají šablony. `tydenni_report.php` vytváří HTML report a může ho uložit; `generuj_story.php` generuje obrázek přes GD a zapisuje metadata do `story_vygenerovane`.

## 12. Systémové toky

### Y01 – Databázové migrace

Číslované migrace obsluhuje `bin/migrate.php`. Současně každý `db.php` request načítá `includes/auto_migrace.php`; ten má být zmražen na baseline 2.20.2, ale při stavu `missing/pending` stále umí DDL a seed (`db.php:36-37`, `includes/auto_migrace.php:6-48`).

### Y02 – Záloha, preflight a deploy helpery

`bin/db-backup.php`, `bin/deploy-preflight.php`, `bin/production-invariants.php` a další čtou výhradně CLI/prostředí, validují cílové cesty a vydávají strojově čitelné výsledky. Nejsou běžnou webovou vstupní plochou; `.htaccess` blokuje celý `bin/`.

### Y03 – Auditní události

Aplikace používá dvě vrstvy: obecný `ucto_audit_log` a doménové immutable/event tabulky (`*_events`). Novější kritické toky ukládají aktéra, důvod, předchozí/nový stav a snapshot uvnitř stejné transakce. Starší CRUD moduly zpravidla ukládají sanitizovaný POST detail přes `zapisAuditLog()`.

## 13. Potvrzené silné kontrolní body

- POST mutace v inventuře téměř vždy používají CSRF; JSON endpointy nesou token v těle.
- Přihlašovací cesty rotují session, používají revokační verzi a atomický rate limit.
- Kritický checkout používá idempotency key, fingerprint, serverové snapshoty, zámky a transakci.
- Bankovní i Stripe potvrzení končí ve stejné funkci `shopOrderConfirmPaymentInTransaction()`.
- Platba, aktivace programu/události/velodromu a enqueue oznámení jsou jedna DB transakce.
- KIS zdroje mají archiv, hash, velikost, field kontrakt a uložené preview/parity reporty.
- Účtenky, servisní dokumenty, zátěžové testy a registrační fotografie mají autorizovaný výdej ze soukromého úložiště.
- Audit helper rediguje tokeny, CSRF, hesla, cookies a secrets podle názvu klíče.

## 14. Omezení této kontroly

- Nebyla ověřena skutečná DB struktura, aktivní oprávnění, CRON, e-mailový transport ani nasazené URL.
- Nebyly provedeny HTTP požadavky, uploady, webhooky ani platby.
- „Bez nalezené UI reference“ neznamená nedostupný endpoint; přímou URL může stále obsloužit webserver.
- Dynamické SQL a volání přes callback mohou uniknout jednoduché statické inventuře; ručně byly trasovány hlavní peněžní, importní, identitní a souborové cesty.
