# Produkční přejímka scénářů A01–B30 — 25. 10. 2026

> Aktualizace po navazujícím izolovaném průchodu: viz závěrečná sekce „Dokončení oprav a příprava sloučení“. Produkční tabulka níže zachycuje původní průchod, nikoli výsledky pozdějšího localhostového ověření.

## Rozsah a stav

Uživatel schválil čtecí přejímku produkce a zápisy na vyhrazených testovacích osobách a trénincích. Finance, importy a změny přístupů mají zůstat v izolaci. V tomto průchodu nebyl nasazen žádný nový kód. Výchozí produkční verze: `a23e393f64eeffafd75df834e6e5ba8c78213b72`; lokální větev `codex/hub-full-acceptance-20261025` vznikla z dokumentačního následníka `3b164e91`.

**Celých 40 scénářů zatím nemá dokončenou uživatelskou přejímku.** Technické testy, otevření stránky a provedení celé uživatelské cesty jsou různé úrovně důkazu. Původní čtyři PASS v localhostovém rozcestníku nejsou výsledkem tohoto produkčního průchodu a nebyly plošně přepsány.

## Co bylo skutečně zapsáno na produkci a uklizeno

1. Existující syntetická osoba **#11782, TEST-PR137 Ověření nasazení**: pole Oddíl bylo změněno z prázdné hodnoty na `TEST UAT 20261025 – dočasné ověření`. Po novém načtení se hodnota zobrazila. Potom bylo pole vyprázdněno, uloženo a znovu načteno. Osoba zůstává archivovaná jako před testem. Nevznikla nová osoba ani rodičovské oprávnění. Audit úpravy a jejího vrácení se zachovává.
2. Nový syntetický trénink **#1603**, označený `TEST-UAT-20261025`, datum 25. 10. 2026, existující soupiska `TEST - UAT děti`, výhradně osoba #11782. Vytvoření v existujícím kontextu vedoucího sportu; délka 1,25 h, měření 1,5 km a 02:13.450.
3. Editace na 1,5 h a nový testovací popis. Nové načtení formuláře prokázalo zachování osoby, soupisky, jednotky km i času. Po uložení se však zobrazila chyba pracovního kontextu — viz oprava níže.
4. Aplikace nejprve správně odmítla přímé smazání tréninku s měřením. Syntetické měření bylo odebráno standardní editací a potom byl odstraněn pouze trénink #1603. UI potvrdilo **„Trénink byl úspěšně smazán.“** V seznamu zůstal původní trénink #1602. Nebyla vytvořena uzávěrka docházky, odměna, platba ani rezervace.

První neúspěšné pokusy o zápis v trenérském kontextu nevytvořily trénink: účet neměl aktuální rozsah soupisek. Prázdné hledání osob v tomto kontextu **není důkazem vylučování archivovaných osob**; příčinou byl chybějící rozsah. Existující kontext vedoucího sportu test umožnil bez přidělení nových práv. Tento průchod se superadministrátorem neprokazuje izolaci běžných rodičovských a trenérských účtů.

## Nalezené chyby a lokální opravy

### Návrat po editaci tréninku

`update_trenink.php` vždy přesměrovával na `moje_treninky.php`. Vedoucí sportu tam nemá přístup, přestože editace v databázi uspěje. Stejný nesprávný cíl měly dva návratové odkazy v editoru.

Oprava: vedoucí sportu se vrací na `sprava_vsech_treninku.php`; trenér dál na `moje_treninky.php`. Regresní HTTP test kontroluje skutečné uložení, oba odkazy, hlavičku Location a úspěšné načtení cílové stránky. **Oprava je součástí PR #201; není nasazená.**

### Příprava demonstračních dat

Seed publikoval program ještě před vytvořením jeho nabídky, vybíral zboží i mimo zvolený import a pro nově zvolené produkty chybělo zařazení účetní řady. Dále jeho reset A05 kolidoval s časovou historií členství a SQL bez `FROM DUAL` neprošlo na MariaDB 10.3.

Lokální opravy omezují výběr na konkrétní import, vytvářejí nabídku před publikací a doplňují explicitní klasifikaci jen konkrétním demo produktům. A05 obnovuje pouze původní intervaly vyhrazené syntetické osoby a neukončuje znovu její výchozí U13 členství. Produkční ochrany historie ani publikace nebyly oslabeny. Seed prošel dvakrát po sobě; soubor s testovacími přístupy zůstává v privátním runtime, nikoli v tomto reportu.

### Místní MariaDB a NTFS komprese

Původní instance 10.4.32 měla nečitelnou `club_event_people` a opakovaně padala. Původní data zůstala zachovaná. Z kopie v recovery režimu vznikl **neúplný** logický dump: jedna poškozená tabulka se nedala vyexportovat. Dump byl obnoven do nové syntetické instance 10.3.39; chybějící tabulka byla vytvořena podle migrace. Její původní testovací řádky nejsou prohlášeny za zachráněné.

I nová instance při restartu selhala na zápisu redo logu. Zjištěný atribut souboru `ib_logfile0` byl 2080, tedy Archive + Compressed; po dekompresi je 32. Komprese byla vypnuta pouze v přesně ověřeném datovém adresáři této izolace. Následně prošla kontrola všech 327 vlastněných aplikačních tabulek bez recovery režimu. To podporuje kompresi jako příčinu opakovaného problému, neprokazuje však stav disku obecně.

Spouštěč nyní odmítá komprimovaný adresář **i komprimované soubory uvnitř něj**. Regresní test používá skutečný dočasný NTFS soubor: nekomprimovaný přijme, komprimovaný odmítne a po dekompresi opět přijme. Ostatní místní databáze nebyly měněny. MariaDB recovery instance na portu 33437 byla ukončena; aplikace používá běžnou instanci na 33436 s `innodb_force_recovery=0`.

Podklady: [MariaDB — InnoDB I/O nastavení](https://mariadb.com/docs/server/server-usage/storage-engines/innodb/innodb-system-variables), [Microsoft — stav komprese souborů a dědičnost adresářů](https://learn.microsoft.com/en-us/windows/win32/fileio/compression-state). Samotná změna `innodb_flush_method=normal` před opravou nestačila. Stažení novější MariaDB bylo přerušeno/neúspěšné; nepoužila se neověřená binárka.

## Stav jednotlivých scénářů v produkčním prohlížeči

„Izolace“ v tabulce znamená místo pro zápisové ověření, nikoli automaticky dokončenou celou UI cestu. Jednotlivé automatické sady a jejich výsledky jsou uvedeny samostatně.

| Scénář | Aktuální produkční důkaz / co zbývá |
|---|---|
| A01 | Rodičovský účet se dvěma vlastními dětmi: dokončit v izolaci; v produkci nevytvářet nové vazby. |
| A02 | Omezený sportovní účet: dokončit v izolaci; správcovská session není důkaz izolace. |
| A03 | Nákup programu a QR: zápis pouze v izolaci. |
| A04 | Platba → zápis do programu/soupisky: pouze izolace; správa programů otevřena čtecím průchodem. |
| A05 | Produkční stránka přechodu otevřena. Náhled/potvrzení a historie: izolace. |
| A06 | Pomocná stránka A06 je záměrně dostupná jen na localhostu, na produkci 404. Převod sezon: izolace. |
| A07 | Plánovač otevřen. Pomocná přejímka A07 je localhost-only; plán/skutečnost a rodinný přehled dokončit v izolaci. |
| A08 | Registrace cílené akce a kapacity: izolace, produkční UI nedokončeno. |
| A09 | Veřejný profil a rezervace: izolace, produkční UI nedokončeno. |
| A10 | Časová osa testovací osoby #11782 otevřena. Celá obchodní návaznost: izolace. |
| B01 | Produkční vytvoření tréninku s testovací osobou a měřením ověřeno. Kompletní matice RPE/časů/chyb: technické testy a izolace. |
| B02 | Produkční editace zachovala hodnoty. Chyba návratu opravena a ověřena lokálně; produkční oprava čeká na nasazení. |
| B03 | Výkaz a přehled trenérů otevřeny ve správných kontextech. Stažení a věcný obsah XLSX/CSV v tomto průchodu nedokončeny. |
| B04 | Přehled závodů i formulář otevřeny. Závod a výsledky nevytvářeny na produkci; zápis v izolaci. |
| B05 | Plánovač otevřen; opakování, přetahování a kopie týdne v produkci neprovedeny. |
| B06 | Rezervace sportovišť/lekce: produkční průchod přerušen ztrátou ovládání prohlížeče. |
| B07 | Zákaznická rezervace/čekací listina: izolace, produkční UI nedokončeno. |
| B08 | Import a zveřejnění katalogu: pouze izolace. Syntetický CSV dry-run a stage proběhl lokálně. |
| B09 | Objednávka zboží a výdej: pouze izolace. |
| B10 | Storno a refundace: pouze izolace. |
| B11 | Kupony a neměnnost slev: pouze izolace. |
| B12 | Klubová cena a rodinná oprávnění: pouze izolace. |
| B13 | Varianty produktu: produkční detail v tomto průchodu nedokončen. |
| B14 | Bezplatný program, duplicita a kapacita: izolace. |
| B15 | Placený program a ukončení po vratce: izolace. |
| B16 | Placená cílená akce a export účastníků: izolace. |
| B17 | Veřejný velodrom a kapacity: izolace, produkční UI nedokončeno. |
| B18 | Registrace, ověření e-mailu a rodinná vazba: izolace; stránka účtů se načetla, úplná interakce nebyla dokončena. |
| B19 | Reset hesla, jednorázovost tokenu a zneplatnění session: technické testy v izolaci; produkční hesla neměněna. |
| B20 | Soukromé ICS a rotace odkazu: izolace. |
| B21 | Opt-in týdenního přehledu a roční úhrady: izolace. |
| B22 | Veřejný rozvrh/ICS bez osobních údajů: produkční browser průchod nedokončen. |
| B23 | Synchronizační průvodce otevřen čtecím průchodem. Žádný produkční upload ani import. |
| B24 | KIS synchronizační centrum otevřeno čtecím průchodem. Náhled/apply/rollback pouze v izolaci. |
| B25 | Předpisy a omezený rodinný přehled: izolace. |
| B26 | Seznam osob, testovací karta a vratná editace ověřeny. Historické/hromadné změny soupisek neprováděny v produkci. |
| B27 | Upomínky a odesílací fronta: izolace; žádné skutečné zprávy odeslány. |
| B28 | Změny a revokace oprávnění: výhradně izolace. |
| B29 | Kvalita sportovních dat a příprava sportovního importu otevřeny. Zbytek dashboardů a filtrů dokončit. |
| B30 | Přepínání existujících pracovních kontextů a omezení tras ověřeno částečně. Klávesnice, motiv a režim bez JS nedokončeny. |

## UX poznatky

- Po úspěšném uložení se musí otevřít povolený kontext, jinak uživatel neví, zda změna proběhla. Konkrétní oprava je hotová lokálně.
- Trenér bez rozsahu vidí obecné „nejsou dostupné skupiny ani soupisky“. Vhodnější je odlišit chybějící přidělení rozsahu od prázdného rozvrhu; nedoplňovat oprávnění automaticky.
- Tlačítka s ikonami pro rozbalení a smazání v přehledu tréninků nemají v přístupnostním stromu srozumitelný název. Doporučení: konkrétní `aria-label` s datem/názvem.
- Výběr skutečně přítomných na stránce uzavření zobrazoval v roli vedoucího celou evidenci 1504 osob v dlouhém selectu. Doporučení: přednostně nabídnout cílenou soupisku a hledání hosta; zachovat možnost oprávněné výjimky.
- Výpadky spojení s prohlížečem nejsou samy o sobě důkazem chyby webu nebo pomalého serveru. Nedokončené stránky se nesmějí prohlásit za PASS ani za potvrzenou aplikační závadu.

## Důkazy

Pracovní artefakty: `output/hub-full-acceptance-20261025/` v tomto checkoutu. Obsahují katalog všech 40 scénářů, JUnit XML, logy HTTP sad, výsledky kontrol izolace, komprese a restartu a screenshoty produkčního úklidu. Privátní dump a přihlašovací data jsou mimo webroot v vlastněném runtime; nejsou součástí Git commitu.

- `production-person-restored.png`: testovací profil po obnově.
- `production-training-edited.png`: načtení upraveného testovacího tréninku.
- `production-training-cleaned.png`: potvrzení úspěšného smazání testovacího tréninku.
- `production-read-walk.json`: část automaticky uloženého čtecího průchodu; dřívější sportovní stránky jsou popsány výše, nejsou zpětně vydávány za strojově zachycené položky tohoto souboru.
- `integration-tests.log`, `integration-junit.xml`: 1051 testů / 10282 assertions, PASS v tomto průchodu před lokální opravou přesměrování.
- `targeted-tests.log`: 43 testů / 214 assertions, PASS po opravách.
- `daily-coach-regression.log`: 35 HTTP kontrol včetně skutečného uložení a přesměrování, PASS.
- `compression-guard.json`, `process-guards.json`: 3 + 7 kontrol, PASS.

- `http-summary.json`: **26 izolovaných HTTP sad / 800 kontrol, konečný stav PASS**. První běh dokončil 25 z 26 sad; `ClubCutover` překročil 15sekundový limit a totéž se stalo při prvním opakování. Diagnostické opakování pouze doplnilo názvy požadavků do logu, ponechalo stejný limit i aplikaci a prošlo všech 26 kontrol této sady. Původní neúspěšné logy zůstávají zachované; příčina přechodných timeoutů není potvrzena. Souhrn není tvrzením, že první běh byl celý zelený.
- `training-edit-javascript.log`, `autocomplete-keyboard.log`: **3 + 5 JavaScript testů PASS**, bez přeskočení. Jde o technické smoke testy, nikoli dokončenou přejímku přes prohlížeč.
- `verified-backup.json`: úplný logický dump obnovené syntetické instance, všechny tři databáze včetně routines a triggers, úspěšný návratový kód a SHA-256. Dump je uložen soukromě mimo webroot. Nedokazuje obnovu dříve ztracených řádků poškozené tabulky.
- `final-restart.log`, `final-readiness.json`: po testech proběhlo řádné ukončení a nový start; **PASS dne 25. 10. 2026 v 15:56:42 CET**. Všech 327 požadovaných tabulek čitelných, žádné chybějící povinné tabulky, vnější transporty vypnuté a soukromá data mimo webroot.

Další uživatelské průchody blokovalo spojení automatizace s Chrome (opakované chyby CDP včetně `Emulation.setFocusEmulationEnabled` a načítání stromu přístupnosti). Obnova připojení ani nová karta nepřinesly trvalou nápravu. Zbývá skutečný průchod rodičovského a omezeného sportovního účtu, rezervací a dalších cest označených jako nedokončené v tabulce; technické sady je nenahrazují. Poslední pracovní kontext uživatelské session zůstal koordinátor programů, přidělená oprávnění se neměnila.

Lokální runtime není důkaz přejímky všech produkčních uživatelských rolí. Zůstává zákaz produkčních finančních/importních/přístupových zápisů podle posledního pokynu uživatele. Při uzavření prvního průchodu opravy ještě nebyly odeslány. Navazující stav je uveden níže; produkční nasazení se nadále neprovádí.


## Dokončení oprav a příprava sloučení

Navazující průchod 25. 10. 2026: opravy jsou na větvi `codex/hub-full-acceptance-20261025`, PR [#201](https://github.com/KovoPraha/evidenceTreninku/pull/201). Tento krok zahrnuje sloučení do main, nikoli produkční nasazení. Workflow nasazení je ruční; nebylo spuštěno. Výchozí main byl ověřen jako `3b164e91`.

### Nový nález: opakovatelnost A06

Skutečný UI průchod odhalil nesprávný ročník demo sportovce A: narození v roce 2013 nevytvářelo očekávaný přechod U15 2026 do U17 2027. Syntetický profil má nyní rok 2012. Seed následně ověřuje skutečné náhledy A06, ne pouze existenci záznamů. Po provedení převodu navíc reset narážel na překryv historie. Reset nyní ruší jen přesně vymezené demonstrační cílové intervaly a obnovuje jen původní zdrojové intervaly známých testovacích osob. Nesouvisející osoby ve stejné cílové soupisce se nemění. Produkční kontrola překryvů zůstává zachována.

Nový regresní skript `tests/Support/LocalDemoRolloverAcceptance.php` kontroluje vlastníka runtime, loopback, datadir, syntetický režim a vypnuté integrace před zápisem. Dvakrát provede seed → převod → opakované potvrzení → reset a porovná cizí syntetické členství beze změny. **7 kontrol PASS**, finální seed zůstává připravený pro další průchod.

### Čerstvé technické ověření

- 1051 integračních testů / 10282 assertions PASS na opravě návratu trenéra, před navazující opravou A06 seedu.
- 26 cílených testů / 184 assertions PASS po finální opravě seedu.
- 7 kontrol reálného cyklu MariaDB popsaných výše PASS; PHP syntaxe a `git diff --check` PASS.
- GitHub Actions [Testy #40216216412](https://github.com/KovoPraha/evidenceTreninku/actions/runs/40216216412) PASS pro přesný aplikační commit `eb5a86c62acafb3be30a9455942a2c6d0cc4b1ca`. Zahrnuje Linux a databázové smoke úlohy. Dokumentační doplnění se kontroluje znovu před sloučením.
- Předchozí 26 HTTP sad / 800 kontrol zůstává samostatným technickým důkazem s popsaným přechodným timeoutem. Nejde o 800 nových UI kliknutí.

### Izolovaný prohlížeč: skutečné výsledky

Chrome se nepodařilo stabilně připojit; pokračovalo se funkčním vestavěným prohlížečem na `127.0.0.1:18096`. Přihlašování používalo výhradně syntetické místní účty. Žádná produkční session nebyla kopírována. Původní blokace Chrome tedy již neznamená, že se neprovedly žádné další UI kontroly.

[Podrobná evidence cest](isolated-browser-flows.json) obsahuje **12 PASS a 6 PARTIAL**. PASS se vztahuje k popsanému průchodu, nikoli ke všem kombinacím a souběhům. [Čtecí průchod](isolated-browser-routes.json) obsahuje **36 otevření stránek**, včetně očekávaného odmítnutí A07 v trenérském kontextu a úspěchu po přepnutí registráře. Načtení stránky samo o sobě není přejímka zápisové funkce.

| Stav | Scénáře a konkrétní důkaz |
|---|---|
| PASS | A01, A02: rodič vidí dvě vlastní děti a vlastní profil; sportovní účet jen vlastní údaje, bez sourozence a rodiče. |
| PASS | A03, A04: kurz 1290 Kč pro konkrétní dítě, testovací QR, ruční potvrzení v izolaci, jediná účast a aktivní školní soupiska viditelná sportovci. |
| PASS | A05, A06: individuální přechod se zachováním identity; hromadný přechod 2 přesuny a 2 ponechání, následná opakovatelnost technicky ověřena. |
| PASS | A08, B14: jediná přihláška dítěte přes dvě cílové soupisky, druhé dítě není způsobilé, duplicita v UI zakázána, storno obnovilo 2/2 místa. |
| PASS | B02: u nového izolovaného tréninku #20 editace délky a km, zachování času 02:13.450 a osoby; návrat na povolený přehled vedoucího sportu. |
| PASS | B15: storno uhrazeného kurzu vytvořilo samostatnou vratku; hospodář potvrdil testovací referenci; program má 0 aktivních a 12/12 volných míst. |
| PASS | B27: uložení intervalu upozornění, vypnutí, konkrétní náhled zprávy a 1 doručení do lokálního souborového outboxu; žádný skutečný e-mail. |
| PASS | B29: provozní přehled, dashboard, kvalita dat, kontrola importu a skutečně použitý filtr trenéra v audit logu. |
| PARTIAL | A10: časová osa dítěte obsahuje objednávkové/programové události a aktéra; samostatná rezervační historie rodiče neověřena. |
| PARTIAL | B01: vytvoření tréninku s měřením a odmítnutí neplatného času; zbývající jednotky a RPE kryjí technické testy. |
| PARTIAL | B10: storno nezaplacené rezervace a vratka uhrazeného kurzu; fyzické zboží v tomto UI průchodu nevydáváno. |
| PARTIAL | B17: bezplatná rezervace/storno 3→2→3, placená objednávka s QR a její storno, admin kapacita 3→4→3; potvrzení platby placeného slotu nebylo provedeno v UI. |
| PARTIAL | B20: vytvoření, rotace a revokace ICS, starý odkaz odmítnut; aktivní ICS nebylo přidáno do externího kalendáře. |
| PARTIAL | B21: opt-in/out, přesný náhled týdenního souhrnu a prázdný roční přehled; filtr uhrazených položek kryjí technické testy. |

U zbývajících 22 scénářů není tímto navazujícím průchodem prohlášena úplná UI přejímka. Jejich původní produkční a technické důkazy jsou uvedené výše. Otevřené zůstávají zejména přetahování v plánovači (dostupné ovládání prohlížeče tuto interakci nepodporuje), externí kalendář a doručení skutečných zpráv (v izolaci záměrně vypnuté), kompletní UI výdej fyzického zboží, závody, importní/revokační cesty a kontrola stažených exportů. To neblokuje sloučení regresně ověřených oprav, ale nesmí být vykázáno jako dokončená přejímka celého systému.

Syntetické objednávky tohoto průchodu byly stornovány, u uhrazeného kurzu byla vratka potvrzena pouze v izolaci. Auditní historie, místní trénink #20 a ochranná testovací osoba regresního skriptu zůstávají v izolaci. V navazujícím průchodu nevznikl žádný produkční zápis. Aktuální stav lze osobně testovat na localhostu; lokální stav není tvrzením o nasazení změn.

Pracovní důkazy navíc: `merge-integration.log`, `merge-integration-junit.xml`, `demo-rollover-regression.json`, `merge-training-edited.jpg`, `merge-browser-order.jpg` v `output/hub-full-acceptance-20261025/`. Privátní přístupy, dumpy ani živé kalendářové tokeny nejsou přiloženy.

### Doplňující kontrola rozcestníku po sloučení PR #201

PR #201 byl sloučen do main jako `ba2c9eef96d6bcb4e5a7f1dd2686362c6b63bd0a`; jeho strom se shoduje s ověřeným headem. Poslední předslučovací [CI #40217477153](https://github.com/KovoPraha/evidenceTreninku/actions/runs/40217477153) prošlo. Nasazení nebylo spuštěno.

Při obnově lokálního dema se našlo další falešné hlášení neúplnosti: M2 kontrola hledala pevné přihlašovací jméno `localhost-sportovec`, přestože seed legitimně zachovává již existující dětský účet. Kontrola nyní vyžaduje aktivní dětský účet navázaný na schválené dítě konkrétního demo rodiče. Cizí, neaktivní ani odvolaný přístup nestačí. Cílená sada M2: **3 testy / 16 assertions PASS**; obsahuje přejmenovaný login i negativní vazby. Prohlížeč po opravě potvrzuje **technickou připravenost 3/3** a **138/138 migrací**.

Doložených 18 výsledků bylo jednotlivě uloženo přes formuláře localhostového rozcestníku. Celkový přehled je **14 PASS, 6 PARTIAL, 20 NETESTOVÁNO**: zahrnuje 12 nových PASS a dva dříve uložené PASS A07/B26, které nebyly přepsány. Závěrečná brána zůstává správně otevřená (A01–A10: 8/10 PASS). Screenshot: `output/hub-full-acceptance-20261025/merge-final-hub.jpg`. Oprava připravenosti není schválení přejímky ani nasazení.
