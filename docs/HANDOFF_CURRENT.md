# Aktuální předání vývoje na další stanici

**24. 10. 2026 — PR #187 nasazen, produkční skladový/účetní audit PASS.**
[Protokol a souhrnné důkazy](verification/stock-production-20261024/RESULTS.md).
Živý release `065ed2a6fb797e21928d0eb5d1d5b84af0c04d93`, deploy40085679569 SUCCESS.
Záloha330 tabulek, pending=[], VS zachovány, HTTP200; ověřeny hashe8 souborů.
Drill40085999249: 16 kontrol PASS; audit40086062724: 20 skladových + 14 účetních PASS.
Produkce: 8 objednávek, 0 skladových/účetních záznamů a souborů, 855 katalogových
variant zboží bez řízeného skladového stavu. Bez skutečné inventury nelze doplnit
počátky; nulové tabulky nejsou dokladem reálného průchodu. Testovací zápisy jen izolovaně.
Opakovatelná diagnostika PR #188 (main283c46db) není další aplikační deploy:
1677 testů / 33411 assertions, MariaDB10.3/11.4, CodeQL a negativní kontroly PASS.
Použít `production-drills.yml`, `overit-ucetni-vazby`, `PROVEST`; žádný aplikační
bootstrap, konzistentní READ ONLY, dočasné skripty mimo webroot se odstraní.

**24. 10. 2026 — skladové návaznosti ověřeny, PR #187 zatím bez nasazení.**
[Protokol a souběžné kontroly](verification/stock-followup-20261024/RESULTS.md).
Výdej respektuje úplné účetní vratky z historie přídělů; při sdíleném příjmu
má přednost výslovný původní doklad. Obě chyby reprodukovány před opravou.
1676 testů / 33403 assertions, MariaDB10.3/11.4, CodeQL PASS; fyzická vratka
trička prošla až do skladového PDF v účetním exportu a nezměnila kurzy dětí.
Produkce nedotčena; testovací data jen izolovaná. Další nasazení nebylo provedeno.

**23. 10. 2026 — účetní opravy PR #184 nasazeny; další kontroly bez prohlížeče hotové.**
[Protokol a důkazy](verification/accounting-production-20261023/RESULTS.md).
Produkční release `ebc3081cbee3a1b38f75bcc2989e4bf6800b1cb9`, deploy40025423618 SUCCESS.
Záloha330 tabulek, pending=[], VS zachovány; 16 čtecích databázových kontrol PASS.
Účetní audit40026086361 PASS, živé hashe souborů odpovídají commitu.
**8 objednávek, účetní tabulky zatím prázdné**: nevydávat testovací doklady/exporty
v produkci; produkční PDF/ZIP ani lidské převzetí zatím nelze označit za prokázané.
Diagnostika PR #185 (main59725050) není další aplikační deploy. Běží přes
`production-drills.yml`, operace `overit-ucetni-vazby`, potvrzení `PROVEST`, READ ONLY;
dočasné skripty mimo webroot se uklidí. 1674 testů / 33371 assertions,
MariaDB10.3/11.4 + CodeQL PASS, detekce poškození ověřena izolovaně.
Žádné nové produkční účetní/doménové zápisy ani aktivace integrací.

**23. 10. 2026 — účetní návaznosti objednávek opravené, zatím nenasazeno.**
[Protokol](verification/accounting-followup-20261023/RESULTS.md).
XLSX nově zachová účastníka, období, referenci případu, VS a splatnost z dokladu.
Opraveny kontroly dvojí vratky přes dílčí příjmy a dokončení úplné vratky z více příjmů.
73 cílených testů / 456 assertions PASS; nativní průchod PDF → příjmy → XLSX/ZIP
→ opravný doklad a vratka → neměnná revize exportu. Ukázka pouze syntetická.
Bez migrace a bez produkční změny. Účetní převzetí i browser UAT zůstávají otevřené.

**23. 10. 2026 — rozšířená technická přejímka rodinného nákupu, bez nasazení.**
[Protokol](verification/family-followup-20261023/RESULTS.md), PR #183.
38 cílených testů / 960 assertions a nativní MariaDB smoke PASS: dva sourozenci,
zboží, různé rozsahy kupónu, přesné finanční podíly, rollback posledního místa,
účetní řádky, dílčí příjmy, přeplatek a idempotentní výdej. Pouze testy a dokumentace.
Prohlížeč se odpojil; mobilní přejímka, doplnění B15 přes UI a nový export odloženy.
B15 zůstává PARTIAL, lidské převzetí i účetní otevřené. Produkce se v tomto kole nemění.
**23. 10. 2026 21:12 — stejný kurz pro více dětí v jedné objednávce, nasazeno.**
[Protokol a důkazy](verification/multi-child-course-20261023/RESULTS.md).
Samostatné položky a souhlasy, přesná kapacita, neměnné finanční podíly,
jména v detailu objednávky. Browser B1 objednávka33/varianta55: Anna91 + Bára93,
300 Kč, účasti12/13 aktivní, podíly150+150Kč. Nativní MariaDB smoke včetně
postupné úhrady podílů PASS. Migrace137 zahrnuje zálohování finančních podílů.
Předchozí omezení na samostatné objednávky sourozenců je nahrazeno touto změnou.
PR #181, release `43a825e01235e66baa1601c3bc957365df011009`, chráněný deploy
**40009024140 SUCCESS**, záloha/migrace/zachování VS/HTTP smoke PASS.
Linux **1659 testů / 33138 assertions**, MariaDB10.3/11.4 a CodeQL PASS.
Živý browser potvrdil release, úložiště PASS a nový postup na produktu247.
B15 nadále PARTIAL; částečné storno uhrazené objednávky nepřidáno.
Produkční doménová data pouze čtena, žádné zkušební objednávky ani aktivace integrací.

**23. 10. 2026 20:28 — rodinné účty a nákup kurzů, PR #179 nasazen.**
[Nový protokol](verification/family-course-20261023/RESULTS.md) obsahuje tři opravy,
browser B1 objednávku32 / osobu91 / vazbu86, čtecí produkční kontrolu a odložený
sportovní login. Cílené testy 229/2596 + 8/78 PASS; plná Windows sada má pouze
známé selhání POSIX scanneru. Linux **1656/33008**, MariaDB 10.3/11.4 a CodeQL PASS.
Produkční release `c6e3328be0567a1ea4b242f18eb6d66c4e1e9202`, chráněný deploy
**40002714298 SUCCESS**, dokončen 20:28:10 SELČ. Browser SHA a private storage PASS;
celková UAT brána zůstává BLOKOVÁNO. Moje kroužky načteny bez cizích údajů.
Další browser krok vyžaduje existující izolovaný sportovní účet; nevytvářet heslo za uživatele.
Nenahrazovat tím lidské přijetí, nevytvářet syntetické zápisy v produkci.

**23. 10. 2026 19:49 — produkční prohlídka osob a tréninků po obnoveném přihlášení.**
Začít [novým protokolem](verification/people-training-production-20261023/RESULTS.md).
Aktuální produkční kód `313cd61ba5111f0d88e158b5107bb1160cb0d374` (PR #177), chráněný
deploy **39997113354 SUCCESS**, dokončen 19:46:45 SELČ. Opravené hledání osoby nad
různými kolacemi, odkaz na skutečný nejstarší nezapsaný plán a české entity v oznámeních.
Browser na produkci ověřil obě opravy, auditní hledání a soukromé úložiště; bez doménových zápisů.
CI 1653/32963, MariaDB 10.3/11.4 a CodeQL PASS. B1 protokol B05/B26 doplněn, stále PARTIAL.
Přihlášení již není překážkou této kontroly. Plná lidská/účetní přejímka zůstává otevřená;
neprovádět historické opravy vazeb, přidělování přístupů ani zapnutí integrací odhadem.

**23. 10. 2026 — detailní kontrola osob, tréninků a vazeb na e-shop.**
[Nový protokol a přesné meze](verification/people-training-20261023/RESULTS.md).
Opraven souběh editace osob, atomická poznámka/audit, rychlá přihláška kurzu,
aktuální oprávnění rodiny a historické soupisky. Tréninky: opraven JavaScript,
identita měření, zachování spolutrenérů, bezpečnost snímků, opakování formuláře
včetně návratu Zpět, rozepsaná docházka a viditelné odmítnutí změny uzavřeného záznamu.
Browser B1 potvrdil osobu92 a plán15/trénink31 s uzávěrkou v2; zkouška34 po opravě
nevytvořila při návratu Zpět duplicitu. Pouze syntetická místní data.
Cílená sada 232/2491 PASS, Node 8/8 PASS; plná Windows sada má známé POSIX selhání.
PR #175 nasazen jako `ee6cff62`, deploy **39987332095 SUCCESS**. Linux CI **1 650 testů / 32 947 assertions PASS**, MariaDB i CodeQL PASS. Produkční přihlášení při reloadu
vypršelo; přihlášená živá prohlídka zůstává odložená. Lidských 43 scénářů NEPROVEDENO,
účetní NEPŘEVZATO. Žádné aktivace integrací ani opravy historických vazeb odhadem.


**23. 10. 2026 — technická browser přejímka dvou propojených toků.**
Začít [aktuálním protokolem](verification/browser-acceptance-20261023/RESULTS.md),
který obsahuje reprodukované chyby, opravy, identifikátory syntetických záznamů,
ověření skutečně staženého exportu a stav vydání. PR #173 sloučen a nasazen
jako `e78af565`, chráněný deploy **39937236344 SUCCESS**. Linux CI
**1 614 testů / 32 689 assertions PASS**; produkční čtecí ověření prošlo.
Větev oprav `codex/browser-acceptance-fixes-20261023`, dokumentační doplnění
`codex/browser-acceptance-release-proof-20261023`, pracovní kopie
`C:\xampp\htdocs\evidencePavel-finance-b1-20261022`.
Původní pracovní kopie obsahuje cizí rozpracované změny; nepřepisovat.
Technické ověření agenta není lidské převzetí ani schválení účetní metodiky.
A01–A10 ani 43 scénářů lidské přejímky tím nejsou uzavřeny.


**23. 10. 2026 — hostingový přístup k soukromému úložišti obnoven.**
Po výslovném schválení vlastníkem změněno pouze PHP `open_basedir` pro KIS
na režim Posunuto. Nový webový požadavek potvrzuje dostupný soukromý adresář
a oprávnění k zápisu; ostatní nastavení hostingu beze změny.
Nasazený diagnostický doplněk `fb2e00c7`, deploy 39926289201 SUCCESS.
Skutečná webová zkouška vytvoření/0600/zápisu/čtení/odstranění PASS;
1 584 testů / 32 313 assertions a obě MariaDB integrační sady v Linux CI PASS.
[Navazující důkazy a stav skutečné souborové zkoušky](verification/private-storage-20261023/RESULTS.md).
Původní P0 nález níže je uzavřený. Plná provozní přejímka zůstává otevřená.

**23. 10. 2026 — společné vydání R01–R44 nasazené, provozní přejímka má otevřený P0 blok.**
Živé SHA `0b03b564dfa6b20817478848fa8721fea09ca082`; [deploy SUCCESS](https://github.com/KovoPraha/evidenceTreninku/actions/runs/39922198281).
[Produkční důkazy a zbývající práce](verification/production-club-20261023/RESULTS.md):
1 572 testů / 31 989 assertions PASS; 64 čtecích browser pozorování / 59 cest.
Opravený vstup do položkových výdejů, ochrana pracovních pozic a vyprodaná varianta přejaté prohlížečem.
**P0: webové PHP `open_basedir` nepovoluje připravené soukromé úložiště.**
[Konkrétní zásah hostingu a následné ověření](CLUB_STORAGE_HOSTING_BLOCKER.md).
Do vyřešení není vystavování soukromých PDF/archivů provozně převzaté.
Doložit granty 36 trenérů, počáteční inventuru 799 variant a dohodu/částku jednoho plánu příspěvků.
Nic z toho nebylo doplněno odhadem. Shoptet/Evidence běží dál, žádné finanční testovací zápisy ani aktivace integrací.
43 scénářů lidské přejímky nadále NEPROVEDENO, účetní převzetí NEPŘEVZATO.

Následující záznamy popisují historický stav před autorizovaným nasazením:


**23. 10. 2026 — balíček pro skutečnou uživatelskou přejímku je připraven.**
Začít [návodem a mezemi přejímky](acceptance-20261023/README.md),
[inventářem podkladů](acceptance-20261023/fixtures.md)
a [ověřením artefaktů](acceptance-20261023/verification.md).
Větev `codex/club-acceptance-package-20261023`, základ `abbb4be9`.
ZIP je v `output/KOVO-prejimka-a-ucetni-20261023.zip`; není verzovaný v Gitu.
Nezaměnit technické kontroly za výsledky člověka: 43 scénářů je NEPROVEDENO,
účetní ukázka NEPŘEVZATO. Podklady v isolated B1 přibyly jen přes UI (produkty
44/45, varianty 50–52); žádný skladový pohyb ani peníze. Přístupy zůstávají soukromé.
Omezený trenér, rozhodnutí D01–D16 a produkční přechod jsou odložené.
Bez změny aplikačního kódu, push, merge, nasazení a odesílání podkladů.

Následuje předání před přípravou tohoto balíčku:

**23. 10. 2026 — společné navazující opravy a místní přejímka dokončeny.**
Větev `codex/club-followup-review-20261023`, základ `7aff7884`, izolovaná kopie
`C:\xampp\htdocs\evidencePavel-finance-b1-20261022`.
Začít [předáním oprav](CLUB_AUTONOMOUS_FOLLOWUP.md),
[finálními důkazy](verification/autonomous-followup/RESULTS.md)
a [návodem k prohlídce](CLUB_LOCAL_REVIEW.md).
1 545 testů / 31 583 assertions PASS; záloha a nezávislá obnova 326 tabulek,
2 triggerů a 175 soukromých souborů PASS. 136 migrací current.
Nový runtime DB vznikl po izolované obnově jediné nečitelné tabulky;
původní datadir je zachovaný, nejistota její historie je zdokumentovaná.
Nepouštět souběžně starý archivovaný datadir a nemažte jej jako odpad.
Žádný push, merge, deploy ani produkční změna. D01–D16 nadále odložené;
technické ověření není skutečný pilot nebo převzetí účetní.

Následuje historické předání před společnou kontrolou:

**23. 10. 2026 — místní implementace a technická příprava R38–R44 dokončena.**
Větev `codex/migration-cutover-r38-r44-20261023`, pracovní kopie
`C:\xampp\htdocs\evidencePavel-finance-b1-20261022`, základ `67121f0b`.
Nejprve číst [společné předání R01–R44](CLUB_IMPLEMENTATION_HANDOFF.md)
a [výsledky](verification/r38-r44/RESULTS.md). Pro uživatele je připravený
[návod k místní kontrole](CLUB_LOCAL_REVIEW.md) a dva izolované účty.
Finální regrese: 1 526 testů / 31 327 assertions, 132 HTTP kontrol,
326 obnovených tabulek a 157 soukromých souborů. Katalog 136 migrací current;
kontrakt záloh `2026-10-23.3`.
**Žádný push, merge, deploy, reálný import ani produkční zápis.**
Produkční brána zůstává zavřená; skutečný pilot, převzetí účetní a přepnutí
neproběhly. Rozhodnutí D01–D16 jsou odložená, nikoli automaticky schválená.
Původní checkout `C:\xampp\htdocs\evidencePavel` má cizí WIP a neupravoval se.

Následuje historické předání předchozího balíku:

Lokální R31–R37 dokončené v pracovní kopii
`C:\xampp\htdocs\evidencePavel-finance-b1-20261022`, větev
`codex/club-administration-r31-r37-20261023`, základ `4033bf18`.
[Rozsah](CLUB_ADMINISTRATION_R31_R37_IMPLEMENTATION.md),
[výsledky](verification/r31-r37/RESULTS.md).
133 migrací tohoto vydání; zálohovací kontrakt `2026-10-23.2`.
Pouze syntetický localhost, žádný push ani produkční změna.
Uživatel požaduje autonomně pokračovat: navazují R38–R44 technická příprava
migrace a přechodu. Reálná rozhodnutí jsou v
[registru k pozdějšímu potvrzení](CLUB_COMPLETION_DECISIONS.md).
Pilot, účetní převzetí a nasazení nejsou místními testy nahrazeny.

Následuje předchozí předání:

Lokální vývoj 23. 10. 2026: **R25–R30 — členská a sportovní agenda**,
větev `codex/membership-sports-r25-r30-20261023`, pracovní kopie
`C:\xampp\htdocs\evidencePavel-finance-b1-20261022`, základ R18–R24
`12f28b611a8781968ca6b73821c0df3e2e4075dc`.
Nejprve číst [předání R25–R30](MEMBERSHIP_SPORTS_R25_R30_IMPLEMENTATION.md)
a [ověření](verification/r25-r30/RESULTS.md).
**Bez push, merge, nasazení a produkčních změn; pouze izolované syntetické prostředí.**
Migrace 130 položek, zálohovací kontrakt `2026-10-23.1`.
Osobní přijetí podporuje doloženého plátce bez účtu. Sportovní skutečnost je
verzovaná; zdravotní skeny čte jen registrář. Reálné politiky a údaje nebyly
odhadnuty ani importovány, automatické úlohy nebyly aktivovány.
Navazují R31–R34; úplný účetní export R36 a ostrá migrace/pilot později.

Následuje historické předání předchozích lokálních balíků:

Lokální vývoj 22. 10. 2026: **R18–R24 — sklady, prodej a vratky**, větev
`codex/inventory-r18-r24-20261022`, pracovní kopie
`C:\xampp\htdocs\evidencePavel-finance-b1-20261022`, základ R13–R17
`fd402b6d9e12a86d932f376c8eaff76877d9a029`.
Předání: [implementace R18–R24](INVENTORY_R18_R24_IMPLEMENTATION.md),
[důkazy přejímky](verification/r18-r24/RESULTS.md).
**Pouze izolované syntetické prostředí; bez push, merge, nasazení a produkčních změn.**
Fyzické počátky, FIFO a účetní údaje pro skutečný provoz se nepřebíraly.
Úplnost účetního předání dokončí R36; navazuje R25–R30.

Následuje historické předání předchozího lokálního balíku:

Aktuální úkol 22. 10. 2026: **R13–R17**, izolovaná pracovní kopie
`C:\xampp\htdocs\evidencePavel-finance-b1-20261022`, větev
`codex/finance-r13-r17-20261022`, základ `0e23e9e766079ce658bbc7687fed7f19b53779de`.
Implementace a ověření se subagenty autorizovány, produkční změny a nasazení
výslovně zakázány. Žádný push/PR/merge/deploy tohoto balíku.
Nejprve přečíst [předání R13–R17](FINANCE_R13_R17_IMPLEMENTATION.md)
a [výsledky přejímky](verification/r13-r17/RESULTS.md). Dále podle plánu
R18–R24 (sklady/prodej); úplné měsíční předání uzavírá R36.
Syntetické účetní nastavení není schválením skutečných údajů a číselných řad.

Následuje předchozí lokální úkol a dále historický produkční kontext:

Aktuální úkol 22. 10. 2026: balík **R07–R12** v izolované pracovní kopii
`C:\xampp\htdocs\evidencePavel-finance-b1-20261022`, větev
`codex/club-foundation-r07-r12-20261022`, základ B1 `2ccd30e27fa209302db4d1e17d4eb411a4c87cca`.
Vlastník schválil implementaci a ověření se subagenty; výslovně **zakázal změny
produkce a nasazení**. Žádný push/PR/merge/deploy tohoto balíku není proveden.
Před pokračováním přečíst [předání balíku](CLUB_FOUNDATION_R07_R12.md)
a [výsledky přejímky](verification/r07-r12/RESULTS.md). Další realizační balík:
R13–R17. Reálné trenérské rozsahy ani historické identity se nemigrovaly.

Následující odstavce jsou kontext předchozího úkolu; nejsou autorizací k nasazení
R07–R12:

Předchozí autorizace 22. 10. 2026: vlastník výslovně schválil push kontroly
účetního zařazení před publikací, PR, merge po zelených kontrolách a chráněné
produkční nasazení. Ověření blokace pouze čtením; produkty #274, #243, #245
a existující/stálé VS zachovat. Nasazení tohoto řezu se teprve připravuje.

22. 10. 2026: schválené účetní mapování uloženo v produkční administraci
(38 auditovaných změn); produkty #274, #243, #245 a kontrolované původní VS
zachovány. Následná nová kontrola účetního zařazení před zveřejněním produktu
je implementována lokálně v `evidencePavel-publication-accounting-20261022`
na větvi `codex/shop-publication-accounting-guard-20261022`; zatím není nasazená.
Ověření: 1 214 testů / 26 960 kontrol, PHP lint, `git diff --check` a lokální
MariaDB smoke číslování včetně nové publikační kontroly prošly.
Podrobnosti, rozsah ověření a omezení jsou v
[plánu kontroly publikace](SHOP_PUBLICATION_ACCOUNTING_GUARD_2026-10-22.md).

Historický produkční záznam převzatý z origin/main při přípravě vydání:

22. 10. 2026 nasazena kontrola účetního zařazení před zveřejněním produktu:
[PR #164](https://github.com/KovoPraha/evidenceTreninku/pull/164), aplikační SHA
`cc9c9280f137465eb59f20523d827b5502416e20`, úspěšný
[deploy 39779478133](https://github.com/KovoPraha/evidenceTreninku/actions/runs/39779478133).
PR i aplikační main mají zelené PHPUnit (1 214 testů / 26 960 kontrol),
MariaDB 10.3/11.4 a CodeQL. Záloha 246 tabulek / 2 triggerů, migrace aktuální,
`pending=[]`, HTTP 200, `uat_schvaleno=false`, `importovat_krouzky=false`.
Automatické porovnání VS `unchanged=true`: osm původních plateb nezměněno,
obě tabulky stálých VS zůstaly prázdné. Živě pouze čtením ověřena blokace
Nezařazeno u #274 a #243 a shoda katalogových i hlavních údajů, cen a skladů
#274, #243 a #245 před/po. Žádný produkt ani platba se při kontrole neuložily.
Předchozí schválené účetní mapování (38 auditovaných změn) zůstává zachováno.
Neprovádět automatickou opravu ani deaktivaci tří odložených produktů,
nepřepisovat existující VS. [Produkční report](SHOP_PUBLICATION_ACCOUNTING_RELEASE_2026-10-22.md)
a [implementace](SHOP_PUBLICATION_ACCOUNTING_GUARD_2026-10-22.md) popisují
důkazy i omezení. Dokumentační dokončení je na větvi
`codex/shop-publication-release-proof-20261022`, bez dalšího deploye.
Následující datované záznamy popisují starší řezy.

## Autorizované vydání Zásilkovny — 22. 10. 2026

Vlastník schválil commit, push a nasazení připravených úprav. Dopravu i
simulaci ponechat vypnutou, neodesílat e-maily ani zásilky, fakturace odložená.
[Aktuální release checklist](PACKETA_RELEASE_2026-10-22.md) má přednost před
staršími zákazy vzdálených kroků a historickými průběžnými výsledky níže.
PR #162 sloučen na `b6b4251548fc6a99a4c654dc00a70589cc8d2386`;
CI 39776280900 (PHPUnit, MariaDB 10.3/11.4) a CodeQL 39776275878 zelené.
Deploy 39776493246 úspěšně nasadil tento SHA, oba volitelné příznaky false.
Lokálně 1 198/26 816 PASS, záloha/obnova 198 tabulek PASS, 112 migrací.
Záloha 246 tabulek / 2 triggerů, 112 aktuálních migrací, původní VS
nezměněny (osm plateb), HTTP 200. Produkčně přes UI ověřeny cena **150 Kč**,
**doprava vypnutá, simulace vypnutá, klíč prázdný**. Produkt #296 zůstává
Stažený a má nový checkbox Packety; PPL je samostatné. Objednávky i stavy
se načítají. Fronta před/po shodná: **3 pending / 0 pokusů / 0 sent**,
shodný otisk celé tabulky. Žádné objednání, podání, opakování zprávy ani
worker; původní pozice Finance obnovena. Veřejný klíč zůstal jen lokálně.
Packeta objednávky na produkci nejsou, expediční akce tedy ověřeny testy,
nikoli skutečným podáním. Aktuální produkční protokol je v release checklistu.
Dokumentační větev nyní
`codex/packeta-release-proof-20261022`, žádná změna aplikace v tomto řezu.
Začleněn main `886c07b2` včetně číselných řad a ochrany původních VS;
společný zálohovací kontrakt `2026-10-22.1` zachovává obě nové datové smlouvy.

## Veřejný klíč a skutečná mapa — lokální PASS 22. 10. 2026

Po přihlášení vlastníka převzat jen veřejný klíč widgetu do lokálního KIS,
uložen přes UI (audit #3); API heslo se nepřenášelo. In-app browser načetl
skutečnou mapu CZ Z-BOXů a callback vybral **22312, Nad Kapličkou 3324/15
(TJ Kovo Praha)** do guest checkoutu 5/5; součet 125 + 150 = 275 Kč.
Serverová aktuální validace stejného boxu PASS, pobočka 39209 a syntetické
ID 999000001 správně odmítnuty. Po zkoušce UI dopravu vypnulo (audit #4).
Konečné lokální nastavení **enabled=0, cena=15000, délka klíče=16,
SHOP_PACKETA_LOCAL_DEMO=false**. Fronta nezměněna: 5 pending / 0 pokusů,
2 objednávky, auth/roster fronty a email_log 0. Nic objednáno, odesláno,
commitnuto, pushnuto ani nasazeno. První mapa se načítala několik minut;
starý nepoužívaný celý v5 feed timeout 12 s, bodová validace prošla.
[Výsledek a důkazy](PACKETA_MODULE_AUDIT_2026-10-21.md#propojení-veřejného-klíče-a-skutečná-mapa--dokončeno-22-10-2026).
Zbývá ověřit obchodní připravenost účtu, skutečný štítek/podání/doručení
a budoucí schválené nasazení/aktivaci. Fakturace nadále odložena.

## Živý účet Packeta — nastavení po přihlášení vlastníka

Účet již existuje. Uložen kontakt Marek Mixa (+420724116963,
`mixa@kovopraha.cz`), firemní adresa IČO 00541753 / Nad Kapličkou 3324/15,
100 00 Praha 10 (#718887) a po výslovném potvrzení VOP odesílatel Kovo Praha
(`#577459`, označení `kovopraha`). Povoleny jen CZ Z-BOXy, ostatní země,
pobočky a externí místa vypnuté. Veřejný klíč nyní pouze v lokální DB;
API heslo nebylo kopírováno.
**Vratkové místo dokončeno:** podle výslovné volby vlastníka uložena
Praha 10, Strašnice, Počernická 3492/1a **#39209** místo automatického depa
Olomouc #9936. Úspěšné uložení i přetrvání po novém otevření ověřeny;
důkaz `var/local-status-mail-review/packeta-account-return-39209.png`.
Veřejný klíč a mapa již lokálně ověřeny podle aktuálního výsledku výše.
Žádná zásilka nebyla vytvořena/podána a KIS nebyl nasazen ani zapnut.
[Detaily a důkazy v plánu](PACKETA_MODULE_AUDIT_2026-10-21.md).

## Lokálně připraven jednoduchý ruční provoz Zásilkovny

Rozhodnutí vlastníka a výsledky jsou v
[plánu modulu](PACKETA_MODULE_AUDIT_2026-10-21.md#schválené-zjednodušení-od-vlastníka--21-10-2026).
CZ Z-BOXy, podání pouze přes box, vše vyřizuje Kovo Praha; účet nastaven výše.
Výchozí dopravné 150 Kč měnitelné v administraci; nový samostatný checkbox
u fyzického produktu, kroužky blokované. Lokálně dále opraven widget a
aktuální validace, expediční přehled, oprava/duplicita štítku a potvrzení
storna v Klientu. Žádný API konektor pro založení nebo tracking.
PHPUnit 1 183 / 26 590 PASS; MariaDB backup/restore 190 tabulek PASS,
lokálních 111 migrací aktuálních. UI cena/checkbox uloženy a ověřeny.
Lokálně doprava vypnutá, cena 150 Kč, veřejný klíč uložen, simulace false,
fronta 5 pending / 0 pokusů. Žádný worker/skutečný e-mail či balík.
Změny jsou necommitnuté na `codex/packeta-module-audit-20261021` ve stejném
worktree. Neprovádět nové nasazení/aktivaci; fakturace odložená. Zbývá ověřit
obchodní připravenost účtu, skutečný štítek/podání a poštovní provoz před
pozdější schválenou aktivací.

## Historie auditu a předchozího nasazení

Navazující analýza Zásilkovny 21. 10. 2026 dokončena: plán a výsledky jsou v
[auditu modulu](PACKETA_MODULE_AUDIT_2026-10-21.md), větev
`codex/packeta-module-audit-20261021`, stejný vývojový worktree. Pouze analýza
a dokumentace; neaktivovat dopravu ani skutečné zásilky/e-maily. Výchozí
nasazený kód a doklady o vydání zůstávají níže. Zdrojově zjištěn nesoulad
widgetu `carrierId:czzbox` proti současnému `group:zbox`, chybějící aktuální
validace, expediční přehled Packety, limity balení a náprava štítku/storna.
Audit obsahuje úkoly mimo KIS, oddělení cílového boxu od místa podání,
bezpečný postup pro sdílenou poštovní frontu, API etapu a šest rozhodnutí
vlastníka. Ostrý účet/mapa, CRON ani doručení nebyly v analýze ověřeny.

Nasazeno 21. 10. 2026: číselné řady a účetní kategorie z [PR #160](https://github.com/KovoPraha/evidenceTreninku/pull/160).
Aplikační SHA `1441683ae1ae2bb9da16d8be3af62ce40f35385c`,
[deploy 39609254832](https://github.com/KovoPraha/evidenceTreninku/actions/runs/39609254832)
prošel zálohou 238 tabulek / 2 triggerů, 111 aktuálními migracemi, aktivací a HTTP 200.
Kontrola VS před/po vrátila `unchanged=true`: osm plateb nezměněno; obě
tabulky stálých příspěvkových VS jsou prázdné a zůstaly prázdné. CI výsledného
main i CodeQL zelené; 1 193 testů / 26 739 kontrol a MariaDB 10.3/11.4.
Živě ověřeno finanční menu, sedm řad, náhledy, mapování, audit, ochrana formulářů
a odmítnutí jiné aktivní pozice. Původní VS objednávky `0000000013` zachován,
role Objednávky obnovena. Žádné nastavení, objednávka ani úhrada se při browser
kontrole nezapsaly; mapování z localhostu se nepřenášelo. `uat_schvaleno=false`,
`importovat_krouzky=false`. [Produkční report](SHOP_NUMBER_SERIES_RELEASE_2026-10-21.md)
a [návod](SHOP_NUMBER_SERIES_2026-10-21.md) jsou aktuální. Tento navazující
dokumentační řez nepřidává aplikační změny; následující lokální stavy jsou historie.

Aktuální autorizace 21. 10. 2026 pro číselné řady: vlastník výslovně schválil
push, PR, merge po zelených kontrolách a chráněné produkční nasazení. Starší
lokální omezení tohoto řezu níže je historické. Zachovat všechny existující
VS a příspěvkové rezervace; produkční ověření administrace pouze čtením.
Podrobný průběh vydání je v [plánu číselných řad](SHOP_NUMBER_SERIES_2026-10-21.md).

Lokální práce 21. 10. 2026: číselné řady e-shopu RR N KK XXXXX a účetní
kategorie na větvi `codex/shop-number-series-20261021`, worktree
`C:\xampp\htdocs\evidencePavel-number-series-20261021`.
[Plán a stav](SHOP_NUMBER_SERIES_2026-10-21.md) je autoritou pro tento řez.
Vlastník schválil implementaci bez produkčního nasazení; staré VS a stálé VS
příspěvků zachovat. Následující autorizace jiných řezů se na tuto práci nevztahují.
Lokálně dokončeno: administrace v agendě Hospodář a platby, účetní číselník,
mapování s děděním, smíšená řada 9/00, nezařazené 8/00, všechny tři nákupní
cesty a neměnné zařazení položek. Závěrem 1 190 testů / 26 730 kontrol, nový
MariaDB souběh a původní checkout smoke prošly; záloha/obnova 198 tabulek.
Browser ověřil oprávnění, uložení čítače a mapování i audit bez konzolových chyb.
Bez push, merge a nasazení. Lokální náhled je `http://localhost:8921/`;
podrobnosti, limity a historické migrační varování jsou v odkazovaném plánu.

Aktuální autorizace 21. 10. 2026: vlastník schválil commit, push a produkční
nasazení stavů/Zásilkovny/systémových e-mailů. Starší zákaz nasazení níže je
historický. Platí zákaz skutečné pošty při ověřování a odložení fakturace.
Simulace v lokálním configu vypnuta. Začleněno `61ed7385`, kontrakt `.4`,
nové testy 1 178/26 530 a MariaDB záloha/obnova 190 tabulek prošly.
[Aktuální kroky vydání](ORDER_STATUS_EMAIL_PLAN_2026-10-21.md#aktuální-autorizace-vydání-21-10-2026)
jsou nyní autoritou pro tento řez; pokračovat push/PR/CI a chráněným deployem.
Nasazeno 21. 10. 2026: stavy, oprava akcí Zásilkovny a verzovaný editor šesti
systémových e-mailů z [PR #156](https://github.com/KovoPraha/evidenceTreninku/pull/156).
Aplikační SHA `574328421fff748b48f7d744a3ce454b9b50dd1e`,
[deploy 39603396933](https://github.com/KovoPraha/evidenceTreninku/actions/runs/39603396933)
je zelený: záloha 236 tabulek / 2 triggerů, 110 aktuálních migrací a HTTP 200.
PHPUnit 1 178/26 530, CI MariaDB 10.3/11.4 a CodeQL prošly. Živě ověřeny
systémové stavy, editor a syntetický náhled bez uložení a odeslání, seznam
objednávek a fronta. Starší zpráva zachovala obsah; konečná fronta 3 pending,
0 pokusů a 0 sent. Dvě další zprávy jsou z nezávislého testu před aktivací.
Simulace Zásilkovny vypnuta; ostrá doprava také zůstává neaktivní, protože
chybí cena a veřejný klíč. Žádné objednávky ani produkční šablony jsme při
této kontrole neměnili, worker nespouštěli. Fakturace je odložená.
[Plán](ORDER_STATUS_EMAIL_PLAN_2026-10-21.md) a
[produkční report](ORDER_STATUS_EMAIL_RELEASE_2026-10-21.md) jsou aktuální
autoritou; starší zákazy push/deploy a průběžné počty níže jsou historie.
Dokumentační větev `codex/order-status-release-proof-20261021` nepřidává
aplikační změny a nevyžaduje nové nasazení; vývojový worktree zůstává stejný.

Navazující TEST ověření 21. 10. 2026: produkční cyklus změny převodu na
hotovost, příjmu, storna a samostatné vratky byl dokončen na nové objednávce
`KP2610218B7C69C006` za 1 Kč. Konečný stav Stornováno/Vráceno, způsob Hotově,
TEST reference a audit zachovány. Nový TEST produkt #296 / varianta #950
zůstává stažený, sklad 1 → 0 → 1. Skutečné objednávky ani peníze se neměnily;
původní pracovní pozice byla obnovena. [Přesný protokol](ORDER_CASH_REFUND_PRODUCTION_2026-10-21.md)
rozlišuje tuto simulaci od plného UAT a ověření skutečného převodu.

Platební scénář je uzavřený; nečeká na další potvrzení ani vratku. Protokol
byl začleněn v [PR #157](https://github.com/KovoPraha/evidenceTreninku/pull/157),
commit `a0e2bac12cf6818120f2dd5515499bfe5d7dc601`; PHPUnit, MariaDB 10.3/11.4
a CodeQL prošly. [Provozní návod](ORDER_PAYMENTS_2026-10-21.md) obsahuje příjem
i samostatnou vratku. TEST záznamy ponechat pro audit. Tato dokumentační
aktualizace nevyžaduje nový produkční deploy; plné UAT zůstává samostatné.


Lokální výsledek 21. 10. 2026: na větvi
`codex/order-status-system-mail-20261021` je připravena oprava Zásilkovny,
oddělené zobrazení systémového a zákaznického stavu a editor šesti existujících
systémových e-mailů s náhledem, historií a verzí uloženou ve frontě.
[Plán a výsledky](ORDER_STATUS_EMAIL_PLAN_2026-10-21.md#úplný-lokální-browser-průchod-21-10-2026)
obsahují dokončený browser nákup/výdej osobního odběru i Z-BOXu, lokální B09
PASS a přesnou kontrolu pěti zpráv ve frontě (5 pending, 0 pokusů, 0 odesláno).
Sklad 10→9→7, oba koncové stavy, odstranění podacího kódu a zachování starší
zprávy po editaci šablony ověřeny. Nová úplná PHPUnit sada: 1 169/26 302, OK;
předchozí MariaDB souběh editorů, záloha/obnova 188 tabulek a 109 migrací platí.
Výběr boxu, štítek, platby a fyzické předání byly syntetické lokální simulace;
reálný dopravce ani doručení pošty tím nejsou ověřeny. Režim Z-BOX demo vyžaduje
výslovný příznak v ignorovaném configu a striktní loopback všech adres.
Pracovní složka: `C:\xampp\htdocs\evidencePavel-order-status-system-mail-20261021`.
Žádný push, merge, deploy ani skutečná rozesílka. Worker nespouštěn; čekající
testovací zprávy nepředávat skutečnému mail transportu. Fakturace je odložená.
Implementační commity jsou `55f43e68` a `690fefd8`. Plán eviduje i navazující
dokumentační PR #154 na `origin/main`, který zatím není do pracovní větve převzat.

## Pravidlo pro větší změny a pokračování po kompaktaci

Na výslovný pokyn vlastníka z 21. 10. 2026 ukládej před větší implementací
konkrétní plán do samostatného souboru v `docs/` a odkazuj na něj z tohoto
handoffu. Průběžně zapisuj rozsah autorizace, pracovní větev/složku,
dokončené kroky, výsledky testů, zbývající práci a přesný další krok.
Před kompaktací nebo předáním stav obnov; chat nesmí být jediným úložištěm
plánu. Po pokračování nejdřív přečti plán a aktuální stav souborů.

Lokální práce 21. 10. 2026: oprava Zásilkovny, přehled stavů a editor
systémových e-mailů se připravují na větvi
`codex/order-status-system-mail-20261021` v odděleném worktree
`C:\xampp\htdocs\evidencePavel-order-status-system-mail-20261021`.
Podrobný rozsah, průběžné úkoly a odložená rozhodnutí jsou v
[plánu stavů a e-mailů](ORDER_STATUS_EMAIL_PLAN_2026-10-21.md).
Vlastník schválil pouze lokální přípravu a testování; žádný push, merge,
deploy ani skutečná rozesílka. Fakturace je výslovně odložená.


Nejnovější předání: 21. 10. 2026, názvy registračních textů z
[PR #152](https://github.com/KovoPraha/evidenceTreninku/pull/152). Nasazený aplikační
SHA je `f5d6276838239ace6426aa8c326c01a213730a36`,
[deploy 39593524611](https://github.com/KovoPraha/evidenceTreninku/actions/runs/39593524611)
prošel zálohou 234 tabulek / dvou triggerů, 108 aktuálními migracemi a HTTP smoke.
Registrář může upravit **Název souhlasu / informace** a zveřejnit i změnu samotného
názvu. Název se verzovaně ukládá s textem; nové nadpisy používají přihlášky
i historie. Původní potvrzení zachovávají vazbu na původní dokument a název.
Živě bylo ověřeno nové pole na `registration_texts_admin.php?purpose=member_data_notice`;
produkční názvy a texty se při kontrole neměnily. Podrobnosti a návod jsou v
[reportu názvů souhlasů](REGISTRATION_TEXT_TITLES_2026-10-21.md). Plné UAT zůstává otevřené.

Nejnovější předání: 21. 10. 2026, hotovostní platby objednávek z [PR #150](https://github.com/KovoPraha/evidenceTreninku/pull/150).
Nasazený aplikační SHA je `f813264b16ffd49224bd41a908adc33d9687af88`;
[deploy 39590481900](https://github.com/KovoPraha/evidenceTreninku/actions/runs/39590481900)
prošel ověřenou zálohou, 107 aktuálními migracemi, aktivací a HTTP 200.
Hospodář může současně změnit převod na hotovost a přijmout celou platbu;
objednávka má audit, navazující aktivace a podporuje ruční vratku.
CI: 1 155 testů / 26 040 kontrol, MariaDB 10.3/11.4 a CodeQL.
Postup a hranice ověření jsou v [předání plateb](ORDER_PAYMENTS_2026-10-21.md).
Přihlášená kontrola formuláře byla 21. 10. dokončena: role, přechod na správnou
platbu, volba hotovosti, povinná pole, CSRF a čistá konzole. Platební formulář
nebyl odeslán; následná kontrola potvrdila nezměněný řádek objednávky.
Podrobné ověření proběhlo na navazujícím release `f5d6276`; původní pracovní
pozice byla obnovena. Skutečné ani testovací platby nebyly potvrzovány.
`uat_schvaleno=false`, `importovat_krouzky=false`. Následující předání jsou historická.

Nejnovější předání: 21. 10. 2026, správa registračních textů a souhlasů z PR
[#148](https://github.com/KovoPraha/evidenceTreninku/pull/148). Nasazený aplikační
SHA je `3c64dc530fa8025854f2cc8be07e74bdc1a51aab`,
[deploy 39588673226](https://github.com/KovoPraha/evidenceTreninku/actions/runs/39588673226)
prošel zálohou, 107 aktuálními migracemi a HTTP smoke. Registrář má v běžném
menu **Členové → Registrační texty a souhlasy**: neměnné koncepty, samostatné
zveřejnění, historie a audit autora/času/důvodu. Dosavadní přihláškové snapshoty
i schválené veřejné texty zůstaly zachované. Živý prohlížeč načetl všech pět
typů textů; publikační zápisy se ověřily v odděleném localhostu. Podrobný
[report a návod](REGISTRATION_TEXTS_ADMIN_2026-10-21.md) obsahuje CI, produkční
důkazy a hranice ověření. Plné UAT aplikace zůstává otevřené.

Nejnovější předání: 21. 10. 2026, oprava rychlé přihlášky z PR
[#146](https://github.com/KovoPraha/evidenceTreninku/pull/146). Nasazený aplikační
SHA je `e935950ed5be7181687dadba4b1fc2daf8dd5095`,
[deploy 39585582515](https://github.com/KovoPraha/evidenceTreninku/actions/runs/39585582515)
prošel. Formulář `booking/rychla_prihlaska.php` volal
`shopBankSettingsEffective()` bez načtení její definice a po odeslání zobrazil
obecnou chybu ukládání. Explicitní načtení `includes/shop_bank_settings.php`
je doplněno do rychlé přihlášky i rychlého nákupu bez účtu.
Oba nové testy samostatného načtení před opravou selhaly; po opravě prošlo
1 140 testů / 25 706 kontrol, MariaDB 10.3/11.4 a CodeQL.
[CI výsledného main](https://github.com/KovoPraha/evidenceTreninku/actions/runs/39585403375)
je zelené. Deploy ověřil zálohu 233 tabulek, 106 migrací bez čekajících kroků,
aktivaci a HTTP smoke. Po aktivaci byla přihláška Aligátoři
`product_id=247&variant_id=813` načtena v prohlížeči; přihláška, detail produktu,
e-shop a homepage vracejí HTTP 200 bez PHP fatální chyby. Produkční přihláška
ani objednávka nebyla v rámci ověření vytvořena. `uat_schvaleno=false`,
`importovat_krouzky=false`; další plné UAT zůstává samostatné.
Následující předání jsou historické body.

Nejnovější předání: 20. 10. 2026, zbývající administrační formuláře z PR [#144](https://github.com/KovoPraha/evidenceTreninku/pull/144). Nasazený aplikační SHA je `246dee2a4df5dc3152c1a769a9af128b87a91092`, [deploy 39496342888](https://github.com/KovoPraha/evidenceTreninku/actions/runs/39496342888) i živé ověření prošly. Navazujte podle [reportu formulářů](REMAINING_ADMIN_FORMS_2026-10-20.md): společné styly starších editorů, zelené ukládání, funkční popisky, kořenové cesty hlavičky a zachování rozepsaného tréninku při chybějící soupisce. Lokálně 1 138 testů / 25 708 kontrol; 105 adres v osmi rolích, syntetické zápisy, mobil a tmavý režim. Produkčně osm stránek a přepnutí pracovní pozice z vnořeného formuláře. B30 zůstává PARTIAL, plné UAT není schválené. Následující předání jsou historické body.

Nejnovější doplnění: 20. 10. 2026, sjednocená administrace a správa e-shopu z PR #140–142. Release `175f92c7a59294acff7e15fe09cbed258707fa2e` má úspěšný [deploy 39483597831](https://github.com/KovoPraha/evidenceTreninku/actions/runs/39483597831) a produkční browser ověření variant, cen, skladu, archivace konceptu a společného vzhledu. Navazujte podle [produkčního reportu](UNIFIED_ADMIN_SHOP_PRODUCTION_2026-10-20.md), který uvádí také ponechanou skrytou testovací variantu se skladem 0 a archivovaný testovací koncept. Plné UAT zůstává samostatně otevřené. Níže uvedené starší SHA jsou historické body svých částí.

Produkční doplnění: 20. 10. 2026, Europe/Prague. Hierarchie skupin a soupisek,
časové členství, sjednocené cílení tréninků a akcí, opakované nabídky kroužků
a rozdělení rodinných bankovních plateb jsou nasazené z PR
[#138](https://github.com/KovoPraha/evidenceTreninku/pull/138). Přesný aplikační
SHA je `4991c91f966eb65852876fd0084dd6ad041e3b69`;
[deploy 39472709660](https://github.com/KovoPraha/evidenceTreninku/actions/runs/39472709660)
prošel zálohou, 106 migracemi bez čekajících kroků, aktivací, ochranou
soukromého klíče a HTTP smoke testem. [CI 39472026706](https://github.com/KovoPraha/evidenceTreninku/actions/runs/39472026706)
ověřilo PHPUnit i MariaDB 10.3/11.4. Živá stránka správy hierarchie se načetla
v roli Registrář. První pokus o deploy skončil po aktivaci na síťovém timeoutu
runneru; druhý běh stejného SHA dokončil celý protokol.

**Další krok je datové zařazení, nikoli opakovaný import osob:** 43 živých
soupisek zatím nemá skupinu a read-only mapování starých skupin/podskupin
ukazuje 0 přesných shod a 46 položek bez vazby. V administraci je třeba
založit cílové skupiny, ověřit každé zařazení a použít náhled dopadu;
historické tréninky a přihlášky se nepřepisují. Generátor nových nabídek,
automatické bankovní párování a plné UAT zůstávají vypnuté.
Podrobný postup je v
[předání hierarchie](ROSTER_HIERARCHY_IMPLEMENTATION_HANDOFF_2026-10-20.md).

Následující doplnění je historický stav před PR #138.

Produkční doplnění: 20. 10. 2026, Europe/Prague

Aktuální důkazy nasazení a prohlížečového ověření shrnuje
[PARENT_JOURNEY_PRODUCTION_2026-10-20.md](PARENT_JOURNEY_PRODUCTION_2026-10-20.md).
Rodičovská cesta z PR #91 prošla nasazením a ověřením 94 migrací. Poslední
aplikační release včetně opravy příslušenství z PR #92 je
`fd6ccc1584beb3a3266e8147497263cb8a38044f`:
[deploy 39402251446](https://github.com/KovoPraha/evidenceTreninku/actions/runs/39402251446)
a [CI 39402250654](https://github.com/KovoPraha/evidenceTreninku/actions/runs/39402250654)
prošly. Lokální implementační a akceptační podrobnosti
zůstávají v [původním předání](PARENT_JOURNEY_2026-10-20.md).

Produkční prohlížeč potvrdil přihlášení testovacího rodiče, existující rodinu
a stránku Moje kroužky. **Úplná cesta nové registrace až do soupisky zatím
ověřena není.** Novou žádost o dítě blokuje chybějící platná informace
o zpracování osobních údajů. Všech 52 zkontrolovaných účastnických variant
je veřejně viditelných, ale čeká na propojení; přesné produktové identifikátory
jsou v novém reportu. Existujících 26 programů a 74 nabídek se soupiskami
není třeba plošně zakládat znovu: všech 74 nabídek hlásí chybějící platný
souhlas se storno podmínkami
a všude je nyní neomezená kapacita. Je třeba rozhodnout skutečné vazby
a nastavení, včetně rozdílných cen Pumptracku a termínů Točňáků / Kunratic.

Fio, Stripe a SumUp se nezapínaly. Skutečné produktové vazby nebyly měněny.
Lokální sada po opravě příslušenství v PR #92 prošla:
**1 019 testů / 23 532 kontrol**. Doručení e-mailu ani skutečná platba
se tím nepotvrzují. Po posledním deployi jsou Trička a Uskladnění správně
bez blokace na program; Aligátoři zůstávají blokováni kvůli chybějícím vazbám.
Syntetický testovací program má připravenou nabídku, ale veřejná prezentace
TEST programu je koncept; rodičovský katalog jej nenabízí. Pozitivní produkční nákup proto
nebyl dokončen.

Níže je **historický bod předání z 11. 10. 2026**. Jeho SHA, počty testů,
produkční UAT a stav nasazení nejsou aktuálním důkazem současného provozu.

Tento dokument je stabilní vstupní bod pro další počítač nebo vývojáře.
Autoritativní zdroj je vždy vzdálená větev `origin/main` repozitáře
<https://github.com/KovoPraha/evidenceTreninku>. SHA uvedené níže je kontrolní
snapshot z data aktualizace, nikoli náhrada za nový `git fetch`.

## Ověřený bod předání

| Položka | Hodnota |
|---|---|
| Větev | `main`; přesné aktuální SHA zjistí nový clone pomocí `git rev-parse origin/main` |
| `origin/main` při kontrole | `16b7d4d60883f3f4f655c097339339c68c1be07d` |
| Nasazený aplikační commit | `16b7d4d60883f3f4f655c097339339c68c1be07d` |
| Otevřené pull requesty | žádné při kontrole 11. 10. 2026 |
| CI aktuálního `main` | [běh 37886667244](https://github.com/KovoPraha/evidenceTreninku/actions/runs/37886667244), úspěch |
| CI nasazeného aplikačního commitu | [běh 37886667244](https://github.com/KovoPraha/evidenceTreninku/actions/runs/37886667244), úspěch |
| CodeQL nasazeného commitu | [běh 37886667406](https://github.com/KovoPraha/evidenceTreninku/actions/runs/37886667406), Actions a JavaScript/TypeScript úspěch |
| Produkční deploy | [běh 37886677875](https://github.com/KovoPraha/evidenceTreninku/actions/runs/37886677875), úspěch, `uat_schvaleno=true` |
| Obnovení UAT účtů a dat | [běh 38093332413](https://github.com/KovoPraha/evidenceTreninku/actions/runs/38093332413), úspěch, okno do 12. 10. 2026 20:00 |
| UAT připravenost | [běh 38093377833](https://github.com/KovoPraha/evidenceTreninku/actions/runs/38093377833), `ready=true`, 10/10 oblastí |
| Produkční invarianty | [běh 38093408270](https://github.com/KovoPraha/evidenceTreninku/actions/runs/38093408270), 16/16 bez porušení |
| Obnova zálohy | [běh 37701691548](https://github.com/KovoPraha/evidenceTreninku/actions/runs/37701691548), 195 tabulek a 2 triggery |
| Produkční fronta zpráv | [běh 37606107788](https://github.com/KovoPraha/evidenceTreninku/actions/runs/37606107788), úspěch |
| CodeQL default setup | [běh 37764312992](https://github.com/KovoPraha/evidenceTreninku/actions/runs/37764312992), Actions a JavaScript/TypeScript bez otevřeného nálezu |
| Produkce | <https://kis.kovopraha.cz/> |

UX navigace, kalendářů, e-shopu a pracovních přehledů je popsána v
[`UX-NAVIGACE-A-KALENDARE-2026-10-08.md`](UX-NAVIGACE-A-KALENDARE-2026-10-08.md).
Nová stanice musí před další změnou ověřit aktuální SHA a poslední produkční
běh; stavové hodnoty v tomto dokumentu se po každém release aktualizují
samostatným dokumentačním commitem.

CI nad aplikačním commitem ověřilo 877 testů / 11 995 kontrol a MariaDB 10.3 i
11.4. Produkce běží na tomto commitu; dokumentace může být na `main` novější.
Po deployi prošla z prohlížeče domovská stránka, e-shop, veřejný rozvrh,
klubový kalendář, velodrom a přihlášení bez fatálních chyb a bez chyb či
varování v konzoli. Databázové invarianty jsou zelené. Poslední deploy má
`uat_schvaleno=true`; nové časově omezené UAT okno bylo schváleno a ověřeno do
12. 10. 2026 20:00 Europe/Prague. Po jeho skončení je nutné testovací účty a
data deaktivovat auditovaným workflow.

Automatický Fio import je dočasně vypnutý. Workflow zůstává jen ručně
spustitelné a produkční `FIO_IMPORT_ENABLED` má zůstat `false`, dokud vlastník
znovu neschválí token, účet a provozní dohled.

## Nová stanice: čisté převzetí

```powershell
cd C:\xampp\htdocs
git clone https://github.com/KovoPraha/evidenceTreninku.git evidencePavel
cd evidencePavel
git fetch --prune origin
git switch main
git pull --ff-only origin main
git rev-parse HEAD
git rev-parse origin/main
```

Poslední dva příkazy musí vrátit stejné SHA. Potom spusťte
`PRIPRAVIT_LOCALHOST_TESTOVANI.cmd`. Skript připraví ignorovaný `config.php`,
oddělenou lokální MariaDB na portu 3308, migrace a syntetická demo data. Pro
další starty slouží `START_LOCALHOST_TESTOVANI.cmd`.

## Existující clone: bezpečná synchronizace

Nejdřív zjistěte, zda stanice neobsahuje rozpracovanou práci:

```powershell
git status --short --branch
git fetch --prune origin
git branch --show-current
git rev-list --left-right --count HEAD...origin/main
```

Pokud je pracovní strom čistý a lokální `main` nemá vlastní commity:

```powershell
git switch main
git pull --ff-only origin main
```

Pokud jsou přítomné lokální změny nebo vlastní commity, nepoužívejte force,
reset ani přepis souborů. Nejdřív práci uložte do samostatné větve a teprve
potom synchronizujte `main`. Cizí rozpracované změny se nesmí přidat do nového
commitu jen proto, že leží ve stejném adresáři.

## Zahájení nové práce

```powershell
git fetch --prune origin
git switch -c codex/kratky-popis origin/main
```

Před commitem zkontrolujte změněné soubory a spusťte přiměřené testy. Úplná
základní brána je:

```powershell
composer install
composer validate --strict
composer test
$env:APP_HOST='localhost'
php bin/migrate.php --check --json
git diff --check
```

Nová migrace musí být nový neměnný soubor v `migrations/`. Již přijatá migrace
se neupravuje. Pokud přidává tabulku vlastněnou aplikací, musí se současně
aktualizovat zálohovací ownership kontrakt v `bin/db-backup.php` a jeho testy.

## GitHub pracovní postup

`main` má vynucené aktuální tři CI kontroly, strict aktualizaci větve,
zamítnutí zastaralých review a pravidla platí i pro administrátora. Povinný
počet schválení zůstává dočasně `0`, protože repozitář i organizace mají pouze
jediného člověka s přístupem (`mmmixamarek-source`); hodnota `1` by znemožnila
schválit vlastní pull request. Jakmile bude přidán druhý důvěryhodný reviewer,
zvyšte počet na `1` a zapněte stejného reviewera také pro prostředí
`production`.

1. Pushnout pouze vlastní pracovní větev.
2. Otevřít pull request do `main`.
3. Počkat na PHPUnit a MariaDB 10.3/11.4 kontroly.
4. Před merge znovu ověřit, zda se `origin/main` mezitím neposunul.
5. Sloučit až po zelených kontrolách.
6. Po merge provést `git fetch --prune origin` a ověřit výsledný SHA.

Push ani merge není produkční nasazení. Produkční workflow se spouští ručně a
jen po výslovném rozhodnutí podle [`NASAZENI.md`](NASAZENI.md).

## Co není v GitHubu

- `config.php` a jakákoli tajemství;
- lokální nebo produkční databáze a obnovovací dumpy;
- skutečné osobní údaje, importy a přílohy;
- lokální outboxy, session a cache;
- `vendor/`, který obnoví Composer podle `composer.lock`.

Tyto výjimky jsou bezpečnostní hranice, nikoli chybějící část synchronizace.

## Kde hledat aktuální informace

- stav GitHubu, produkce a posledních oprav: [`CURRENT_STATE.md`](CURRENT_STATE.md);
- dokumentační rozcestník: [`README.md`](README.md);
- lokální instalace: [`localhost-testovani.md`](localhost-testovani.md);
- produkční nasazení: [`NASAZENI.md`](NASAZENI.md);
- produkční UAT: [`PRODUKCNI-UZIVATELSKE-TESTOVANI.md`](PRODUKCNI-UZIVATELSKE-TESTOVANI.md);
- pravidla migrací: [`../migrations/README.md`](../migrations/README.md).

Datované dokumenty jako `HANDOFF_2026-08-29.md`, auditní zprávy a implementační
prompty jsou historické záznamy. Při rozporu nemají přednost před aktuálním
kódem, `origin/main`, CI ani tímto dokumentem.
