# Evidence osob, tréninků a návaznost na e-shop — 23. 10. 2026

## Rozsah a hranice

Podrobná technická kontrola zdrojů, regresních testů a skutečných formulářů v prohlížeči. Tři dílčí revize: osoby/audit, tréninky/docházka, osoba–objednávka–program. Opravy navzájem nezávisle revidované. Tento protokol není lidská přejímka, rozhodnutí o zdravotní způsobilosti ani schválení účetní metodiky.

Pracovní kopie `C:\xampp\htdocs\evidencePavel-finance-b1-20261022`, větev `codex/people-training-acceptance-20261023`, výchozí main `dc75dcc55f115a883a1c3cf621f4bc3162253cab`. Původní pracovní kopie a cizí soubory v output nebyly měněny.

Veškeré nové provozní zápisy pouze standardním UI izolovaného B1 `http://127.0.0.1:18086`, databáze `evidence_b1` na portu 33426. Použita syntetická data `LOCALHOST NEPLATIT UAT-OSOBY23`; žádné nové účty, hesla, produkční osoby, platby, výdeje či e-maily. Účty 326/327 jsou existující místní superadmin účty s přepínanou pracovní pozicí; tento browser průchod sám nedokládá úplnou izolaci běžného trenéra. Tu ověřují oddělené automatické testy.

Před zápisy probe PASS, 326 čitelných vlastněných tabulek; chybějí pouze tři volitelné legacy tabulky. Vnější transporty vypnuté, soukromé úložiště mimo webroot. Záloha `evidence_2026-10-23_155443_b8fb9fb8.sql.gz` v privátním runtime `backups-people-training-20261023`: 326 tabulek, 2 triggery, 2 398 997 bajtů; SHA-256 `f687a8f9d96c5a1839e468e7711e44c6dd44ae181d6b898b5a84ae266035a233`.

## Nálezy a opravy

| Priorita | Původní problém | Oprava a důkaz |
|---|---|---|
| P1 | Starý formulář osoby mohl přepsat novější změnu jiného správce. | Revize profilu ověřená pod zámkem osoby i profilu. Opakované odeslání starého formuláře odmítnuto. Odkaz načte aktuální údaje skutečným GET, nikoliv jen přechodem na fragment. Browser + integrační testy. |
| P1 | Rychlý nákup kurzu neukládal `club_program_offer_id`; návazná kapacita a aktivace účasti mohly položku ignorovat. | Přesná nabídka se ukládá ve stejné transakci jako objednávka. Testy obou pořadí: přijetí osoby před úhradou i úhrada před přijetím; právě jedna účast a držená kapacita. Historické objednávky automaticky neměněny. |
| P1 | Neplatný JavaScript při předvyplnění měření zablokoval celou editaci tréninku. | Odstraněno přiřazení přes optional chaining. Skutečná kompilace skriptu a chování ověřeny Node; browser upravil běh 1000 m z 05:12.345 na 05:10.125. |
| P1 | Přepsání jména měření mohlo ponechat ID jiné osoby; klávesnicový výběr nebyl spolehlivý. | Při úpravě textu se staré ID zruší, výsledek nese rozlišitelnou identitu, click obslouží klávesnici. Browser Enter a automatické testy. |
| P1 | Běžná editace mohla odstranit spolutrenéry. | Výchozí trenéři zachováni z DB pod zámkem. Browser potvrzuje zaškrtnutého spolutrenéra 327 po editaci. |
| P1 | Odstraňování snímků neověřovalo vlastnictví vůči tréninku a při rollbacku mohlo poškodit soubory; čtení před zámkem mohlo přepsat souběžnou změnu. | Vlastnictví a cesta kontrolovány, snímky načteny až pod transakčním rowlock/FOR UPDATE, soubory se obnovují při rollbacku. Automatické testy; browser upload nebyl proveden. |
| P1 | Opakování formuláře vytvoření mohlo založit druhý trénink. | Session ochrana váže klíč na osobu a plán, před zápisem trvale ukládá pending a vrací dokončený výsledek. Reálný návrat prohlížeče Zpět navíc odhalil vydání nového klíče při obnoveném GET; návazná oprava a opakovaný důkaz jsou popsány níže. |
| P1 | Oprávnění k tréninku se mohla změnit mezi úvodní kontrolou a zápisem. | Opětovná kontrola aktéra a rozsahu uvnitř transakce při vytvoření, úpravě a mazání. Automatické testy. |
| P2 | Interní poznámka a její kanonická historie nebyly atomické. | Společná transakce; selhání historie vrátí i poznámku, neexistující osoba se odmítne. Po úspěchu zůstává otevřená záložka Poznámky. Browser a integrační rollback test. Sekundární obecný audit nadále používá existující best-effort mechanismus. |
| P2 | Auditní osa osoby vynechávala události vlastní Evidence osoby. | Přidán person_history zdroj filtrovaný přes konkrétní osobu a odkazy na historii karty. Nevypisuje syrové kontaktní JSON. Browser ukazuje poznámku, editace a odpojení kontaktu. |
| P2 | Historické soupisky mohly rozmnožit řádky objednávky a ukazovat nesprávný stav v Moje programy. | Vazba na konkrétní `club_program_enrollment:<id>`; test aktuální i historické účasti. |
| P2 | Odebrání ověření e-mailu nemuselo okamžitě skrýt navázané přehledy. | Přehled kontroluje současný aktivní a ověřený účet, nikoliv jen historického plátce. Druhý oprávněný rodič zůstává funkční. Deaktivaci již předtím blokoval auth middleware; nově uzavřený konkrétní problém je revokace ověření. |
| P2 | Chybný interval docházky zahodil rozepsané údaje. | Po odmítnutí zůstávají časy, důvody, účastníci, trenéři, potvrzení a původní verze; explicitní prázdný výběr se neobnovuje z DB. Browser a testy. |
| P2 | Odmítnutá přímá editace uzavřeného tréninku se vrátila bez viditelné chyby. | Escapovaná chybová zpráva s odkazem na verzovanou opravu stejného tréninku. Browser po POST 31 vidí odmítnutí; délka 1,25 h a původní účastník zachováni. |

## Skutečný browser průchod — osoby

1. Kontrola přesného jména a narození existující osoby 91 vrací „Přesná shoda — založení zablokováno“. Výjimka s důvodem nebyla použita.
2. Dvoukrokové založení nové osoby **92 Boris TEST LOCALHOST UAT-OSOBY23**, narozen 15. 4. 2016. Po založení Čekající, žádný účet, objednávka ani trénink.
3. Syntetický kontakt Petra připojen k osobě 92 a následně stejný existující kontakt k osobě 91. Editor výslovně upozornil na společný kontakt dvou sportovců. Změna telefonu se propsala do historie obou; odpojení pouze u 92 ponechalo vazbu u 91. Kontakt nevytvořil účet ani schválené rodinné oprávnění.
4. Dvě souběžně otevřené karty 92: novější telefon uložen, starší město odmítnuto i při opakovaném pokusu. Druhá zkouška: novější město „LOCALHOST Novější změna“ zachováno, starý Oddíl se neuložil. Odkaz Načíst aktuální údaje skutečně obnovil formulář.
5. Interní syntetická poznámka u 92 uložená a viditelná na správné záložce. Auditní osa obsahuje Evidence osoby: note, profile_edit, parent_edit a parent_unlink s odkazy na kartu téže osoby.
6. Osoba 92 archivována s výslovným syntetickým důvodem. Následný GET potvrdil **Archiv**, telefon 000000024, 0 účtů, 0 účastí a zachovanou historii. Neproběhlo fyzické mazání.

## Skutečný browser průchod — kurz, plán a trénink

Navazuje na [předchozí přejímku](../browser-acceptance-20261023/RESULTS.md): osoba 91 Anna TEST, program 16, nabídka 15, soupiska 20, uhrazená objednávka 31 za 1 200 Kč. V tomto průchodu se finanční záznamy neměnily. Kontakt či archivace jiné osoby nepřenesly objednávku ani účast.

- **Plán 15** vytvořen 23. 10. 2026, 10:00–11:00, Cvičení, soupiska 20, neveřejný. Příprava ukázala jednu očekávanou osobu 91 a jednu odpověď „bez odpovědi“; RSVP nebylo vydáváno za docházku.
- Z plánu přes skutečný formulář vznikl **trénink 31**, předvybraná osoba 91, dva trenéři 326/327, měření Běh 1000 m / 05:12.345. Plán následně Evidováno, žádný čekající záznam.
- Editace změnila měření na **05:10.125**, náplň na suffix „– opraveno“, zachovala osobu 91 a oba trenéry. Našeptávač fungoval i Enterem.
- Neplatný interval 10:00–09:00 odmítnut; po opravě formuláře zůstal celý rozepsaný obsah. Změna pouze konce na 11:00 umožnila **uzavření v1** v 18:05:16.
- Následná oprava do 11:15 s důvodem vytvořila **v2** v 18:06:08; historie obsahuje obě verze. Přímý POST editace uzavřeného tréninku byl odmítnut s viditelnou hláškou. Žádná odměna pro 31 nebyla vytvořena či vyplacena.
- Karta 91/Sport ukázala oba tréninky 30 a31 se skutečnou účastí. Zdravotní přehled správně uvedl, že sledovaný podklad není určen a musí se ověřit požadavek s registrářem; platnost nebyla domyšlena.

## Opakované vytvoření — dodatečně reprodukovaná chyba

Po prvním vytvoření tréninku **32** (23.10., 0,25h, náplň „LOCALHOST NEPLATIT UAT-OSOBY23 ochrana odeslání“) návrat Zpět vyvolal nový GET formuláře. Browser obnovil textové hodnoty, ale vydal se nový formulářový klíč. Druhé odeslání vytvořilo **33**, bez obnoveného JS výběru účastníka. Nešlo o opakování stejného serverového klíče; potvrzeno pouze čtecí kontrolou místního runtime. Původní porovnání atributu v browser nástroji nebylo dostatečným důkazem identity klíče.

Nasazení bylo do opravy tohoto toku pozastaveno. Záznamy 32/33 zůstávají syntetickými důkazy v B1, nebyly vymazány ani uzavřeny.

**Opakování po opravě PASS:** první GET po ověření oprávnění přesměruje na stabilní adresu s vlastním klíčem formuláře. Nový test vytvořil pouze **trénink 34**, 0,50 h, stejnou soupisku 20 a osobu 91. Tlačítko Zpět následně otevřelo přehled s textem „Tento formulář již byl odeslán. Nejprve ověřte výsledek v přehledu tréninků.“ Žádný druhý formulář nebyl nabídnut, 34 je v přehledu jednou. [Browser důkaz](training-back-protected.jpg). Jiný záměrně otevřený nový formulář stále funguje nezávisle. Poslední opravu nezávisle schválil druhý agent; samostatný replay stejného POST ověřují automatické testy, nikoliv tato Back zkouška.

## Testy a meze důkazu

Dosavadní plná Windows sada: **1648 testů, 32905 assertions, 1 selhání, 3 přeskočení**. Jediné selhání GlobalFunctionAvailabilityTest je známá platformní absence posix_geteuid na Windows; aplikační volání je chráněné function_exists. Skener ani aplikace nebyly obcházeny. [Celý výstup](phpunit-full-windows.txt). Následná změna ochrany browser Back vyžaduje novou cílenou sadu a finální Linux CI.

Před doplněním browser Back prošlo 8 Node testů, skutečná persistence session přes uzavření a opětovné otevření a lint 32 změněných PHP souborů. [JavaScript](javascript-tests.txt), [session](session-smoke.txt). Dílčí agentní sady: osoby 88/562, propojení 171/2156, tréninky 65/325; jejich počty se překrývají a nesčítají do nezávislého celkového počtu.

Rychlý anonymní checkout s novým účtem je pozitivně ověřen integračními testy, nikoliv browser založením nových přihlašovacích údajů. Historické položky s chybějící nabídkou nebyly opraveny odhadem; případná inventura a individuální náprava zůstávají samostatným krokem. Hromadné změny osob, kopie/rušení celých sérií, upload fotografií a všechny sportovní disciplíny nebyly v tomto browser průchodu opakovány.

Ochrana opakování je session, ne trvalá databázová idempotence: 50 formulářů, platnost 2 h, vazba na pracovníka/plán. Pád s nejasným výsledkem se automaticky neopakuje a odkazuje na kontrolu přehledu. Nový záměrně otevřený formulář ani jiná relace nejsou deduplikovány podle podobnosti náplně.

## Zápis výsledků a produkční ověření

Do `testovaci_scenare.php` doplněny **B01/B02/B05/B26/B29 = PARTIAL**, označené TECHNICKÁ KONTROLA AGENTEM. Zachovány starší výsledky B03/B09/B10/B15. Souhrn **PASS 0 / PARTIAL 9 / NETESTOVÁNO 31**; A01–A10 stále 0/10. [Skutečně stažený Markdown export](acceptance-hub-export.md) byl před zařazením přečten, bez přihlašovacích údajů a ostrých osobních dat. Novější lidská sada 43 scénářů zůstává NEPROVEDENO a účetní převzetí NEPŘEVZATO.

Při čerstvém načtení produkční administrace přihlášení vypršelo. Dříve otevřená stránka stále ukazovala starý release e78af565, ale není to aktuální read-only důkaz po reloadu. Přihlášená produkční prohlídka se proto odkládá; nebude nahrazena tvrzením, že úspěšný deploy rovná se úplné UAT.

Konečný stav vydání je uveden na konci protokolu.

Finální místní cílená sada po poslední opravě: **232 testů / 2491 assertions PASS**, [výstup](phpunit-targeted.txt). Tréninková dílčí sada po Back opravě 67/335 PASS. Opakovaný read-only B1 probe v 18:22:38 PASS: 326 tabulek, žádné chybějící povinné tabulky, transporty vypnuté. Finální export brány stažen a přečten v 18:23:07, zachovává všech devět PARTIAL a žádné lidské PASS.

## Konečný stav vydání

**Nasazeno:** [PR #175](https://github.com/KovoPraha/evidenceTreninku/pull/175), release **ee6cff62f9b7628ec1dda7bb4c3571b7272a9b30**; [chráněný deploy 39987332095](https://github.com/KovoPraha/evidenceTreninku/actions/runs/39987332095) **SUCCESS** v 18:33:59 SELČ. Deploy znovu spustil celou sadu **1 650 testů / 32 947 assertions PASS**, ověřil zálohu mimo webroot, zachování dosavadních VS a HTTP 200; soukromý klíč z HTTP vrací 403. UAT schválení zůstalo false a import kroužků byl vypnutý. Nebyla přidána migrace ani zapnuta integrace.

Finální PR CI 39987112156, CodeQL 39987105652 a následná main CI 39987280849 prošly včetně MariaDB 10.3 a 11.4. CodeQL původně odhalil neúplné rozpoznávání script tagů v novém testovacím extraktoru; rozpoznávání velikosti písmen a koncového HTML tagu bylo opraveno, bez vypnutí pravidla. Node testy po opravě 8/8 PASS. První pokus spustit deploy skončil HTTP 500 na GitHub API; ověřeno, že běh nevznikl, následný pokus založil jediný úspěšný běh.

Browser po nasazení načetl veřejný rozvrh a katalog kroužků. V říjnovém veřejném rozvrhu nejsou zveřejněné tréninky; tímto prázdným výstupem nelze dokazovat úplnost publikace ani všechny hranice soukromí. [Produkční veřejná obrazovka](production-public-training.jpg). Čerstvé otevření připravenosti administrace vrací přihlášení. **Přihlášená produkční prohlídka osob a tréninků zůstává odložená kvůli vypršené relaci**; provedený browser důkaz změnových toků pochází z B1.

[Strojové výsledky a odkazy](release-checks.json). Následný commit této dokumentace nemění běžící kód a nevyžaduje nové nasazení. Lidská přejímka, účetní převzetí, posouzení případných historických rychlých přihlášek bez nabídky a ostrý přechod ze starých systémů zůstávají otevřené samostatné kroky.
