# 06 – Program board

Aktualizováno: 4. 8. 2026 (technické dokončení M1)
Aktuální produkční brána: **F0 – červená**
Aktuální lokální produktový milník: **M2 – provozní pilot na localhostu**
Povolená práce: localhost přírůstky podle
[10 – Milník M2](10-milnik-m2-provozni-pilot.md), opravy z A01–A10, testy a dokumentace
Zakázaný start: produkční aktivace nových funkcí, Stripe, automatické Fio,
wallet, exporty a ostrý KIS/Shoptet cutover

## Ověřený výchozí stav

| Položka | Stav |
|---|---|
| produkční commit | `58ec8ec985d447dfe901481ac8bb24b944b03d08` |
| poslední ověřený deploy | GitHub run `30668559417`, úspěšný |
| produkční schema/PHP | `2.20.2` / `8.2.32` |
| vzdálený `main` | PR #1 až #6 sloučeny po vrstvách; `7f48b50b128b65f7340442ba33bfb9c66c27703a`, finální run `30743017895` úspěšný |
| lokální práce | technická část M1 dokončena; poslední brána je vlastníkova prohlídka A01–A10; bez produkčních změn |
| bezpečnostní snapshot | `d2b3c56` na `codex/pre-reconcile-20260801`, pouze lokálně |
| odchylka lokálního main | odstraněna fast-forwardem; unikátní práce je zachována ve snapshot větvi |
| syntax | všech 339 first-party PHP souborů prošlo lintem |
| dependency audit | 0 advisories lokálně; produkční stav nebyl v tomto přírůstku měněn |
| automatické testy | 299 testů / 2547 assertions lokálně; poslední vzdálený důkaz zůstává GitHub CI run `30743017895` |
| migrace | 32 číslovaných migrací; `20260804230000_club_event_shop` byla aplikována pouze na localhost a katalog je 32/32 current |
| deploy/backup | fail-closed záloha, preflight pepperu a pořadí release → migrace → aktivace PHP jsou v `main`; chybí GitHub Secret `SSH_KNOWN_HOSTS` a produkční pepper nebyl ověřen |
| restore drill | post-M1 záloha `evidence_2026-08-03_222958_4e76dbc4.sql.gz` má 123 tabulek / 2 triggery a SHA-256 `e4d43a20b008d7188af5c5b47a905893c44698c659dc78509af498bfa3d38d6b`; ownership kontrakt je `.6` |
| KIS matcher | dále zpřísněn: jméno-only ani e-mail-only se automaticky nepřijmou, rozdílné datum narození je konflikt; ostrý import zůstává blokovaný |
| Shoptet katalog | reálných 241/807 bylo po auditované kontrole transakčně převedeno do izolovaného draft katalogu; opakování bez duplicity, veřejně aktivních produktů 0 |
| Aktivace katalogu | `5500927`: jednotlivé `goods` lze ručně aktivovat s plain-text veřejným snapshotem a auditem; K3 typy jsou fail-closed; veřejný storefront stále neexistuje |
| K3 akce | `4ef5690`, `88f5b97`, `e5fcaa0`, `a949c38`: navíc FIFO čekací listina a atomické povýšení nejstarší oprávněné osoby po stornu; bez objednávky, plateb, soupisky a KIS zápisu |
| K2 identita | `8c374a4`, `d32fc08`: účet je oddělen od sportovce; veřejný claim neenumeruje osoby, vazby `self`/`guardian` schvaluje pouze admin s důvodem a auditem; neověřený účet ani zrušená vazba účastníka nezpřístupní |
| lokální data | localhost demo obsahuje rodiče, dva běžné profily a samostatného věkově vhodného sportovce `LOCALHOST Přechod U17` pro opakovatelný A05 náhled |

## První paralelní přírůstek M1

| Proud | Commit | Přijatý důkaz |
|---|---|---|
| M1.1 rodinný sportovní přehled | `d42a30c` | guardian/self rozsah, IDOR testy, soupisky, události a docházka |
| M1.1 příjemce objednávkové položky | `82b42a3` | autorizovaný beneficiary v košíku, neměnný snapshot v objednávce, běžné zboží smí zůstat bez příjemce |
| M1.2 série a sezony | `18b81a3` | school/calendar sezony, čtyři politiky a read-only preview s `mutation_count=0` |
| integrační brána | tento commit | migration check 22/22, 213/1583, audit 0 advisories a browser průchod rodiče i KIS admina |

Hodnoty lokální DB nejsou produkční statistika. Slouží pouze k posouzení, zda
současné vývojové prostředí dokáže ověřit navrhované scénáře.

## Druhý paralelní přírůstek M1

| Proud | Commit v `main` | Přijatý důkaz |
|---|---|---|
| M1.3 tréninkový most | `9f7e531` | více soupisek na plán, snapshot očekávaných osob, deduplikace a žádný automatický zápis docházky |
| M1.4 kroužkové programy | `e6fad8e` | nabídka/období, beneficiary, kapacita, oddělená účast, audit a aktivace potvrzené položky |
| M1.6 cílení událostí | `218bfd3` | více soupisek, transakční kontrola oprávnění, jeden zápis při překryvu a snapshot důvodu |
| integrační brána | následující commit | katalog 25/25, 236/1779, audit 0 advisories, 296 lintů, 109 tabulek a browser smoke všech tří cest |

## Třetí paralelní přírůstek M1

| Proud | Commit v `main` | Přijatý důkaz |
|---|---|---|
| M1.4 automatický lifecycle | `15cd57b`, `589d79b` | potvrzení platby atomicky aktivuje účast; storno ji auditovaně ukončí; refund je fail-closed; pořadí zámků je sjednocené |
| M1.5 provedení rolloveru | `8cf6774`, `94ab4a2` | fingerprint náhledu, individuální výjimky, auditovaný přesun a idempotentní souběh chráněný DB/advisory zámkem |
| M1.7 veřejný velodrom | `9d8cee5` | kanonický self profil, datum narození, sdílené/výhradní sloty, kapacita, storno/rebook a ruční potvrzení placeného slotu |
| integrační brána | následující commit | katalog 28/28, 258/2029, audit 0 advisories, 310 lintů, backup 117/2 a autentizovaný browser smoke profilu, velodromu a rollover preview |

## M1.8 integrovaný akceptační přírůstek

| Proud | Commit v `main` | Přijatý důkaz |
|---|---|---|
| A01–A10 rozcestník | `bf3caa2` | localhost-only, admin+CSRF reset seedu, žádná hesla v UI, pravdivé ready/partial stavy |
| omezený sportovní přístup | `91b105f` | vlastní session a revokace, DB-scoped read-only přehled, žádní sourozenci ani rodičovské mutace |
| placený velodrom přes shop | `664f855` | standardní order/payment/QR, snapshot slotu, paid aktivace, storno/refund a uvolnění kapacity |
| integrační brána | následující commit | katalog 30/30, opakovaný seed, plná sada, backup `.5` a browser A02/A09 včetně lifecycle |

## M1.9 provozní a akceptační přírůstek

| Proud | Commit v `main` | Přijatý důkaz |
|---|---|---|
| A05 přechod kroužek → závodní tým | `d3e7e96` | preview-first, stejná osoba, kontrola věku, stale fingerprint, volitelné ukončení kroužku, idempotence a MariaDB smoke |
| A10 auditní osa osoby | `e363d86` | admin-only read-only agregace devíti zdrojů, pravdivě chybějící aktér/důvod, omezené stránkování a MariaDB/browser smoke |
| expirace pending objednávek | `c50e572` | defaultní dry-run, explicitní potvrzení, payment-first lock order, kontrola nulové programové účasti a právě-jednou uvolnění skladu i velodromu |
| integrační brána | tento commit | 31/31, 298/2435, 26 změněných PHP lintů, audit 0, opakovaný seed, reálná idempotentní expirace a backup 121/2 |

## Technické dokončení M1

| Proud | Stav | Přijatý důkaz |
|---|---|---|
| placená událost pro soupisky | hotovo lokálně | shop order/payment/QR, cenový a souhlasový snapshot, `payment_pending` drží kapacitu, paid aktivace, storno/expirace/refund a ochrana duplicity |
| deterministická demo data | hotovo lokálně | placená i bezplatná událost, U13 → U15 → U17, dráha, silnice a rollover výjimka; seed prošel dvakrát |
| integrační brána | hotovo lokálně | 32/32, 299/2547, 339 lintů, audit 0, browser paid flow a backup 123/2 `.6` |
| produktová brána | čeká na vlastníka | ruční průchod A01–A10 a zařazení připomínek do M2 |

## Zahájení M2

| Proud | Stav | Přijatý důkaz |
|---|---|---|
| M2.1 export účastníků akce | hotovo lokálně | admin-only POST+CSRF, kontrakt `m2.event-participants.v1`, oddělení akcí, neutralizace tabulkových vzorců a audit počtu/stavů |
| integrační brána M2.1 | hotovo lokálně | 303 testů / 2603 assertions, 345 PHP souborů ověřeno, Composer audit 0 advisories a autentizovaný browser export s viditelným auditem |
| produktové připomínky A01–A10 | čekají na vlastníka | chyby a UX připomínky zařadit před další větší funkcí M2 |
| M2.3a KIS raw archiv | hotovo lokálně | localhost-only dry-run/explicit write, archiv mimo webroot, SHA-256 + velikost, idempotence, preview manifest a žádný promote |
| integrační brána M2.3a | hotovo lokálně | katalog 33/33, 308/2635, 350 PHP lintů, audit 0, dvojí MariaDB archivace stejné syntetické fixture a backup 124/2 `.7` |
| M2.4a detail produktu | hotovo lokálně | aktivní publikace, seskupené varianty, sklad, schválené texty, validní HTTPS obrázky a oddělené zobrazení kroužkové nabídky |
| integrační brána M2.4a | hotovo lokálně | 33/33 migrací, 313/2664, 354 PHP lintů, audit 0 a přihlášený browser průchod zboží i kroužku včetně vyčištění košíku |
| M2.4b rozsah kupónů | hotovo lokálně | neměnná maska zboží/kroužek/událost/velodrom, výchozí pouze zboží, způsobilý mezisoučet a auditovaný redemption snapshot |
| integrační brána M2.4b | hotovo lokálně | 34/34 migrací, 315/2707, 355 PHP lintů, audit 0; browser potvrdil zamítnutí `LOCAL10` na kroužek, slevu na zboží i administrační rozsah |
| M2.4c checkout hardening z externí revize | hotovo lokálně | bezpečný opakovaný nákup stornovaného kroužku, legacy kapacita velodromu a součet více dětí stejné události v jednom košíku |
| integrační brána M2.4c | hotovo lokálně | 35/35 migrací na localhost MariaDB, 318/2774, 356 PHP lintů a audit 0; produkce beze změny |
| bezpečnostní hardening legacy | hotovo lokálně | `65a0433`: náhodné profilové tokeny, rotace odvoditelných URL, hash legacy hesel, CSRF, download oprávnění, redakce auditu a Apache defense-in-depth |
| bezpečnostní integrační brána | hotovo lokálně | 36/36, 323/2903, 361 PHP lintů, Composer audit 0 a localhost HTTP hlavičky/upload 403; produkce beze změny |
| M2.5 registrace bez enumerace | hotovo lokálně | `b8ecdaa`: shodná veřejná odpověď pro nový i existující e-mail; 324/2907 |
| M2.5 obnova účtu a oprávnění | technicky hotovo lokálně | `7c1490e`: hashovaný hodinový single-use reset rodiče i sportovce, revokace relací, živé guardian ověření a request-scoped oprávnění; 37/37, 330/2944, 367 lintů |
| M2.5 localhost reset UX | hotovo lokálně | `edc6c62`: po platné žádosti se jen na localhostu ukáže testovací fragmentový odkaz; produkce zůstává pouze e-mailová; 330/2946 |
| uzavření MEDIUM kompatibility | hotovo lokálně | `29f6029`: explicitní měna varianty/události, fail-closed UTF-8 CSV a oficiální Fio datum s offsetem; 333/2971, 367 lintů, 37/37 |
| M2.6 čerstvý backup | hotovo lokálně | `fdbe30c`: ownership kontrakt `.8` zahrnuje reset tokeny; záloha mimo webroot má 125 tabulek, 2 triggery a ověřený SHA-256 `a7382f999126595fbbabffc99c7f5e926c0a134600fcf8659f167c949a0174a9` |
| M2.6 seed + A01/A03/A04 | hotovo lokálně | `4090bdc`: stabilní interní A05 identita s náhodným veřejným tokenem, seed 2× se stejnými ID, rodič právě se dvěma dětmi a objednávka `KP2608040ECDA87D7D` od QR po auditovanou platbu, účast a soupisku |
| M2.6 e-shop/událost/velodrom lifecycle | hotovo lokálně | browser + MariaDB: ochrana duplicity události, auditované storno/refund události a velodromu, návrat kapacity 3/3 a 1/1, sklad zboží přesně 2→1→2; žádná konzolová chyba |
| M2.2 společná homepage | hotovo lokálně | `25830e1`: veřejný vstup propojuje e-shop, služby, rodinu, sportovce a trenéry; trenérský dashboard má rychlé volby Evidence/KIS/objednávky/veřejný portál; 336/3004, audit 0 |
| M2.2/A02 sportovní přehled | hotovo lokálně | `18deb9c`: vlastní souhrny, české stavy a datumy, návrat na společnou homepage; browser IDOR pokus zachoval jedinou identitu; 337/3014, 367 lintů, 37/37, audit 0 |
| M2.6/A05 přechod do závodního týmu | hotovo lokálně | `8647bce`: jediná kanonická demo identita, preview + věk + auditovaný zápis a pravdivý no-op při novém opakovaném náhledu; reset vrací scénář před přechod; 338/3025, 367 lintů, 37/37, audit 0 |
| M2.2/M2.5 veřejný portál a jednotný účet | hotovo lokálně | `efa1ca8`: veřejný katalog, kroužky, velodrom a bezpečný rozvrh; akce až po loginu; trenér i e-shop používají jednu identitu a reset hesla; 346/3063, 375 lintů, 38/38, audit 0, browser master flow |
| M2.6/A06 roční obnova soupisek | hotovo lokálně | `dde0f3e`: společný preview+fingerprint, U15→U17, přenos dráhy, zachované výjimky, auditované/idempotentní dílčí běhy a opakovatelný reset; browser 3 přesuny/2 výjimky, 349/3081, 379 lintů, 38/38, audit 0 |
| M2.4d klubové ceny podle soupisek | hotovo lokálně | `e67eed8`: veřejná cena + výzva k přihlášení, nejvýhodnější aktivní soupiska rodiny, sleva kategorie nebo přesná cena produktu, audit a checkout snapshot; 353/3120, 383 parse, 39/39, audit 0 |
| M2.6/A07 plán → docházka → sportovec | hotovo lokálně | `03774db`: vlastnická a datová ochrana plánu, neměnná kopie snapshotu ke skutečnému tréninku, přehled očekávaní/skuteční/chybějící/neočekávaní; browser 1/1/0/0 a sportovní přehled, 354/3130, 384 parse, 39/39, audit 0 |
| M2.6/A08 událost pro dvě soupisky | hotovo lokálně | `6ae75c1`: UI nabízí oprávněné dítě právě jednou, databáze potvrzuje jednu přihlášku a dvě vyhovující soupisky; opakovatelný seed používá auditované storno místo mazání, 355/3133, 386 parse, 39/39, audit 0 |
| M2.6/A10 auditní osa osoby | hotovo lokálně | `4ce0f17`: browser spojil objednávku, soupisky, přihlášku a přístup s pravdivými aktéry/důvody; opakovaný seed už nevytváří falešné password-reset události, 355/3135, 386 parse, 39/39, audit 0 |
| nezávislá AI revize + MariaDB CI | přijato s validací | Cowork report `AUDIT-M2-AI-SIMULACE.md` obsahoval zastaralé bridge závěry; živě potvrzený nedostatek opravil `ef5ec21`: samostatný MariaDB job pro child-access a KIS transition smoke, 356/3142, oba smokes OK, 386 parse, 39/39, audit 0 |
| vlastníkův feedback A01–A10 + AI kontext | hotovo technicky | `875c9e3`: localhost-only CSRF/admin formuláře, souhrn, bezpečný JSON se zámkem a Markdown export; `CLAUDE.md` opraven jako samostatný projekt a přidán `CURRENT_STATE.md`; browser save/reload/reset, 358/3156, 388 parse, 39/39, audit 0 |
| M2.3b integrita KIS preview | hotovo lokálně | `26076ba`: archivně podmíněná úplná klasifikace, stabilní non-PII fingerprint, bezpečný JSON report a idempotentní demo seed; browser run #7 2/2 bez blokátoru, 364/3197, 392 parse, 40/40, audit 0 |
| M2.3c sandbox promote/rollback | hotovo lokálně | `5caa850`: admin+CSRF+localhost, fingerprint, transakce, idempotence, audit a rollback dostupný i při driftu; browser 2/2→0/2, 369/3254, 396 parse, 41/41, audit 0 |
| M2.3d stabilní KIS ID | hotovo technicky | `2bcb346`: `kis-import-field-v1`, KIS ID oddělené od UCI, spojení tří exportů, non-PII report a fail-closed zápis; browser run #8 2/2→0/2, starý run blokován, 377/3308, 398 parse, 42/42, audit 0 |
| M2.3e cutover parita | hotovo technicky | `95693a2`: uložený non-PII report osob/členství/soupisek/platebních signálů; run #9 pravdivě 3 blokátory včetně chybějícího cílového kontraktu předpisů, sandbox 2/2→0/2, 379/3332, 401 parse, 43/43, audit 0 |
| M2.3f členské předpisy | hotovo technicky | `d69ee4f`: `member-charge-v1`, cílové a auditní tabulky, stabilní ID+částka, atomický staging a non-PII porovnání; run #12 2 staging/2 čeká, 388/3369, 406 parse, 44/44, audit 0 |
| M2.3g auditovaný promote/rollback předpisů | hotovo lokálně | `7c8b444`: localhost admin+CSRF+fingerprint, transakční a idempotentní přenos, samostatná historická platba, invarianty a bezpečný rollback; browser run #13 2/2 + 1 platba → 0/2 + 0 plateb, 391/3430, 408 parse, 45/45, audit 0 |
| kontrolní audit zálohy M2.3g | opraveno | `281fcd0`: ownership kontrakt `.9` doplnil 12 chybějících KIS/členských cenových tabulek, generický test hlídá všechny trvalé migrační tabulky, MariaDB CI vytvořilo zálohu 90 tabulek; přesný převod platebních signálů bez float, 393/3496, 409 parse, 45/45, audit 0 |
| M2.6 závěrečná brána | hotovo technicky | `9a04c3c`: localhost-only admin panel odděluje automatické kontroly cest, migrační integrity a demo dat od vlastníkovy akceptace; živě 3/3 technika, A01–A10 10/10, migrace 48/48, PASS 0/10, blokátory 0; 429/3833, 433 parse, backup 95, audit 0 |
| M2.7a veřejný ICS kalendář | hotovo technicky | `3aa39f8`: jeden anonymní feed nad již zveřejněnými tréninky, otevřenými klubovými akcemi a veřejnými hodinami velodromu; stabilní UID, UTC, standardní escapování/skládání, žádné osobní ani interní údaje; 403/3617, 416 parse, 45/45, audit 0 |
| M2.7b soukromý rodinný ICS kalendář | hotovo technicky | `004e4a6`: hashovaný 256bitový token, jednorázové zobrazení odkazu, auditovaná rotace/revokace, živé oprávnění profilů a izolace rodin; cílené tréninky, akce, rezervace a splatnosti, HTTP 200→404 po revokaci; 410/3662, 423 parse, 46/46, backup smoke 92 tabulek, audit 0 |
| M2.7c opt-in připomínky splatnosti | hotovo technicky | `29e3d5d`: nastavení 3/7/14 dní, idempotentní fronta, audit, kontrola aktivní vazby a stavu předpisu, jedna zpráva/20 h/účet, ochrana souběhu a pět pokusů; bezpečný login odkaz bez ID, browser zapnutí/vypnutí bez skutečného e-mailu; 418/3728, 428 parse, 47/47, backup 95, audit 0 |
| M2.7d provozní obsluha připomínek | hotovo technicky | `66b4241`: admin přehled pěti stavů, ruční retry pouze přes POST+CSRF+důvod+potvrzení, audit typu/ID aktéra a opětovná kontrola opt-in i stavu předpisu; web zprávu neodesílá; 421/3761, 429 parse, 48/48, backup 95, audit 0 |
| M2.7e náhled a bezpečný testovací transport | hotovo technicky | `68e1199`: no-store admin náhled escapovaného uloženého textu, localhost-only souborový outbox, odmítnutí produkčního hostu a webový zákaz `var/`; skutečný mail se nezapnul; 423/3781, 429 parse, 48/48, backup 95, audit 0 |
| M2.7f obnovitelná localhost ukázka | hotovo technicky | `5843f70`: admin+CSRF+potvrzení připraví auditovaný syntetický předpis, opt-in a právě jednu čekající zprávu, poté otevře náhled; browser 0→1, žádný transport; 425/3799, 431 parse, 48/48, backup 95, audit 0 |
| M2.7g browserové testovací doručení | hotovo technicky | `6d290cc`: localhost admin+CSRF+potvrzení zpracuje jednu čekající zprávu výhradně do chráněného souborového outboxu; claim i sent auditují konkrétního trenéra, browser ověřil Čeká 1→Odesláno 1 a obnovu na Čeká 1; 427/3819, 431 parse, 48/48, backup 95, audit 0 |

## Aktivní rozhodnutí

Zdroj pravdy je tabulka D-001 až D-020 v [02 – Zadání a rozhodnutí](02-zadani-a-rozhodnuti.md).

| Rozhodnutí | Stav | Vlastník |
|---|---|---|
| D-001 až D-004: tvar aplikace, shop, Shoptet a KIS přechod | doporučeno | vlastník produktu |
| D-005 až D-006: účet a rodina | čeká na potvrzení | produkt + bezpečnost |
| D-007: případné budoucí sdílení uživatelů | potvrzeno: pouze identita, mimo MVP | vlastník produktu |
| D-008: nová doména klubových akcí | doporučeno | vlastník produktu |
| D-009: reward vs cash kredit | blokující | ekonom + právní/účetní konzultace |
| D-010 až D-013: platby, částky, checkout a doprava | čeká na potvrzení | produkt + ekonom |
| D-014: hranice vůči Velocotě | potvrzeno: žádná širší integrace | vlastník produktu |
| D-015: číslované migrace | technicky přijato ve W0-E; produkční ověření čeká | technický vlastník |

## Backlog F0

| ID | Úkol | Závisí na | Smí běžet paralelně | Hotovo znamená |
|---|---|---|---|---|
| W0-A | Repo a deploy reconciliation | nic | dokončeno | lokální práce zachována, main sjednocen, workflow pravdivě zdokumentovaný |
| W0-B | KIS matcher safety | dokončeno `7106930` | přijato | pořadově nezávislé testy a neměnný cache snapshot |
| W0-C | Dependencies a auth hardening | částečně v `main`, včetně tokenového přírůstku PR #5 | ano, jediný vlastník auth | session lifecycle, DB revokace, limiter, expirované single-use tokeny a POST+CSRF logout hotové; zbývá permission cache, reset hesla a produkční odstranění legacy hesel |
| W0-D | Test harness a CI | dokončeno `0d50584`, remote run `30718098799` zelený | přijato | PHPUnit + GitHub workflow + test gate před deploy SSH |
| W0-E | Migrace a deploy hardening | PR #6 v `main`, produkční ověření čeká | produkční ověření čeká | runner/check, fail-closed backup, lokální restore drill, preflight pepperu a migrace před aktivací PHP jsou doloženy; zbývá Secret/config a autorizovaný první deploy |
| W0-F | ADR identity/KIS/wallet | produktová odpověď | ano, bez kódu | D-004 až D-011 mají schválený stav a důvod |
| W0-G | Realistické anonymizované fixtures | částečně: Shoptet `f0370a3`/`3845eab`/`b77f8c3`, K2 `8c374a4`/`d32fc08`, aktivace `5500927`, K3 `4ef5690`/`88f5b97`/`e5fcaa0`/`a949c38`; matice `168d132`, `8f0cbe8` | ano | Shoptet, K2, goods aktivace a bezplatný K3 průchod včetně čekání pokryty; zbývá reálný KIS formát a platby |

## Bezpečný merge order

1. W0-A – vytvoří jediný důvěryhodný base.
2. W0-B a W0-C – bezpečnost dat a přístupu.
3. W0-D – rozšíří CI nad sjednoceným základem.
4. W0-E – zavede migrace a release safety.
5. W0-F a W0-G – mohou vznikat průběžně, ale musejí být uzavřené před Bránou F0.
6. Integrační task spustí všechny kontroly a aktualizuje tento board.

## Brána F0 – aktuální checklist

- [x] lokální a vzdálený main bezpečně sjednocen,
- [x] KIS matcher opraven a otestován na foundation,
- [x] dependency audit foundation bez advisories,
- [ ] legacy hesla a session mají schválenou a ověřenou nápravu; tokeny, logout, lifecycle, DB revokace, limiter, localhost password apply, samoobslužný reset a okamžitá oprávnění jsou hotové; zbývá produkční password apply a doručování reset e-mailu,
- [x] unit/integration testy a migrační fixture existují; první GitHub běh `30718098799` je zelený,
- [x] číslované migrace a read-only `--check` existují lokálně,
- [ ] staging/test DB a kompletní realistické fixtures existují; rozšířené syntetické KIS/Shoptet matice a read-only dry-run už jsou doložené,
- [ ] deploy kód selže při chybě zálohy a v `main` ověřuje pepper i pořadí migrace před aktivací PHP; provozně chybí `SSH_KNOWN_HOSTS`, produkční pepper a autorizovaný první release,
- [x] lokální restore drill je doložen; před prvním finančním schématem zopakovat s produkčním backup artefaktem,
- [ ] identity, KIS ownership a wallet pravidla jsou schválena.

Pokud chybí jediná položka, F0 zůstává červená. „Deploy proběhl“ není náhradou
za splnění této brány.

Auth F0 větve přidávají pouze bezpečnostní schéma a přihlašovací infrastrukturu;
nepřidávají košík, platby ani produkční import. Hashované, expirované a atomicky
jednorázové e-mailové/booking tokeny jsou implementované v `main`.
Shoptet export a bezpečný katalogový staging jsou doložené. Produktově stále
chybí potvrzení konkrétních aliasů KIS identity osoby, ID předpisu, částky a data
úhrady na reprezentativním anonymizovaném exportu a retenční doba preview dat.
Cílový model, staging i auditovaný localhost promote/rollback existují v M2.3g.
Před budoucím auth deployem musí být mimo Git nastaven `AUTH_RATE_LIMIT_PEPPER`.

## Pokyn pro příští řídicí task

```text
Pracuj jako řídicí task programu Evidence e-shop + týmová evidence.
Nejdřív načti CLAUDE.md, docs/CURRENT_STATE.md a SESSION_HANDOFF.md a ověř git
status, HEAD, migrace a localhost. Zachovej všechny cizí změny. Aktuální volba
práce je buď vlastníkův průchod A01–A10, nebo po dodání reprezentativního
anonymizovaného KIS exportu zdrojová akceptace a celý cutover rehearsal v testovací DB.
Znovu neimplementuj hotové M1/M2 řezy bez potvrzené chyby. Každý task musí vrátit
base/commit SHA, jmenované soubory, migrace, testy, rizika a browser/DB důkaz.
Produkční deploy, ostrý KIS import a automatické finanční operace spouští ručně
vlastník až po samostatné integrační kontrole; pracovní task je nespouští.
```

## Podmínka pro změnu boardu

Stav lze posunout pouze na základě důkazu: commit/diff, výsledek testu, DB/schema
post-check, restore záznam nebo výslovně potvrzené produktové rozhodnutí. Pouhý
odhad nebo zelený syntax lint nestačí.
