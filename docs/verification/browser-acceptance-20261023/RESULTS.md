# Souvislá technická přejímka v prohlížeči — 23. 10. 2026

**Souvislý obchodní řetězec a navazující členská/sportovní agenda byly ověřeny v izolovaném B1. Osm skupin nalezených chyb je opraveno a znovu ověřeno prohlížečem. Opravy jsou nasazeny na produkci: e78af565, chráněný deploy 39937236344 SUCCESS.**

Jde o technickou přejímku provedenou agentem přes skutečné UI, nikoli o převzetí odpovědnými lidmi. **43 scénářů lidské přejímky zůstává NEPROVEDENO, účetní převzetí NEPŘEVZATO.** Nevznikly skutečné platby ani produkční finanční zápisy. Půjčované vybavení je mimo dohodnutý rozsah.

## Prostředí a důkazní základ

- Větev codex/browser-acceptance-fixes-20261023, základ **6f574033a3b06ff3280542a6d268e087c54268be**. Opravy sloučeny v PR #173; odevzdaný head **653758dc7e937dd8de17c1a104c9130e10efe187**, nasazený squash commit **e78af565d6d89492e54f0b9e2ecb2b63a4b534c7**.
- Izolované UI http://127.0.0.1:18086, databáze evidence_b1 na 127.0.0.1:33426, vnější transporty vypnuté. Před zápisy ověřen probe izolace.
- Předchozí záloha evidence_2026-10-23_091911_e6019c50.sql.gz: **326 tabulek, 2 triggery**; SHA-256 **5e6c9da4613c464079cd3d5c7b957640fb594fd36d9d397bc8837b1b585f233d**.
- Doménové zápisy vznikly přes standardní formuláře, výhradně syntetická data LOCALHOST / NEPLATIT / SIMULACE UAT-BROWSER23. Existující dokladové řady a jejich čítače nebyly resetovány.
- Podklady: [kontrola účetního archivu](artifact-checks.json), [technické testy](test-results.txt), [skladový kontext](inventory-context.jpg), [prodejní okno programu](program-sales-window.jpg), [člen a program](member-program.jpg), [schválená odměna](reward-approved.jpg), [uzavřená vratka](return-closed.jpg).

## Sklad → prodej → hotovost → výdej → doklady → vratky

| Krok | Skutečně pozorovaný výsledek v B1 |
|---|---|
| Počáteční inventura M | Varianta **50**, doklad **SK-105**, dávka **49**: fyzicky 10 / rezervace 0 / blokace 2 / dostupné 8. Pořizovací cena zůstává neznámá. |
| Dodavatelský příjem | **SK-106**: přijaty skutečné **3 z očekávaných 4** kusů. Varianta 50: **13 / 0 / 2 / 11**. Dávka **50** oceněna hospodářem na **300 Kč/kus**, celkem 900 Kč. Tím se nedoplňuje cena původní dávky ani nezapisuje peněžní výdaj. |
| Počáteční inventura L | Varianta **51**, **SK-107**: **5 / 0 / 0 / 5**. Po změně varianty i po uložení zůstal správný filtr a vybraná varianta. |
| Osobní prodej | Objednávka **30**, **KP261023C3E8E9B159**, VS **2639800001**, **2 × 500 Kč**. Po opravě prodej používá existující platné syntetické bankovní nastavení v DB. Uložení prodeje pouze rezervuje zboží. |
| Platba a peněžní evidence | Platba **30** potvrzena jako hotovost v pozici Hospodář. Pokladna **28 UAT-BROWSER23** s doloženým nulovým počátkem k 23. 10.; příjem **79 +1 000 Kč**, příděl **79 +1 000 Kč** na objednávku 30. Po příjmu zůstatek 1 000 Kč. |
| Částečný výdej | Výdej **10**, pohyb **SK-109**: předán **1 kus**, další kus zůstává rezervovaný. Peníze se výdejem nemění. |
| Faktura | Doklad **11**, **TESTFA-2026-000004**, **1 000 Kč**, původní VS zachovaný; vydané PDF. |
| Zrušení nevydaného kusu | Oprava **12**, **HTTPOPR-2026-000006**, **−500 Kč**. Výdejové UI uvolnilo rezervaci jednoho nevydaného kusu; nevznikl fiktivní fyzický příjem. |
| Fyzická vratka | Případ **25**, vazba na původní výdejový pohyb **109**. Převzatý kus nejprve zvýšil vlastní blokované zásoby, po posouzení byl uvolněn k prodeji. |
| Oprava vráceného kusu | Doklad **13**, **HTTPOPR-2026-000007**, **−500 Kč**, provázaný s fakturou 11. |
| Uvolnění přídělů | Příděly **80/81 po −500 Kč** ruší původní příděl **79**; ten je plně uvolněn. Budoucí datum 24. 10. bylo odmítnuto a částečná částka −500 zůstala ve formuláři. Po opravě na 23. 10. zápis uspěl. |
| Peněžní vratky a uzavření | Rozhodnutí **5/6**, každé 500 Kč, následné výplaty zaznamenané přes UI jako pohyby **80/81**. Případ **25** provázán s opravou **13** a vratkou **6**, stav **UZAVŘEN**. |

Kontrolní konec obchodního řetězce: varianta **50 = 13 fyzicky / 0 rezervace / 2 blokace / 11 dostupné**; pokladna 28 po prodeji a obou vratkách **0 Kč**; příděly **+1 000 −500 −500 = 0 Kč**; faktura a dvě opravy **+1 000 −500 −500 = 0 Kč**. Shodná čísla přídělů a pohybů označují různé evidence a nesmějí se sčítat jako další peníze. Pozdější platba kurzu zvýšila pokladnu na 1 200 Kč, viz dále.

## Stažený účetní export a jeho meze

Prohlížečem vznikl a byl stažen export **23, revize 1**, období **1.–23. 10. 2026**, soubor ucetni-podklady-2026-10-01-r1.zip. Snímek je z **09:41:10 UTC**. SHA-256 původního ZIP: **26e5de586e2eba8487b7dcf7482f404a967b4104cee0bfaf5744087be71c650d**.

[Nezávislá kontrola artefaktů](artifact-checks.json) obsahuje **20 kontrol PASS**: manifest a hashe všech 23 souborů, přesné částky/vazby JSON–XLSX, příděly a vratky, skladové množství a neznámé ceny, doklady 11–13 a jejich PDF. XLSX má 10 listů, bez chybových buněk; přečteno 21 PDF o 34 stranách, vizuálně ověřeno 19 stran. Zdrojový archiv nebyl upraven ani znovu exportován.

Balík je správně označen **NEÚPLNÝ**: obsahuje 118 výjimek a 14 nepřevzatých zdrojů z celé syntetické kopie. U tohoto případu zejména chybí úplné členění pohybů 79–81 a uzavřené odsouhlasení pokladny 28. Členění faktury nenahrazuje členění peněžních pohybů. Pokladna má doložený počátek až 23. 10.; začátek celého exportního období 1. 10. je **NEZNÁMÉ**, nikoli domyšlená nula.

Další omezení důkazu:

- Neoceněná původní dávka a vrácený kus mají cenu null / NEZNÁMÉ; známých 300 Kč/kus nové dávky se na ně nepřenáší.
- Uzavření vratkového případu 25 dokládá browser, nikoli samostatný stav případu v archivu. Archiv obsahuje jeho návazné pohyby a dokladové vazby.
- Skladová PDF SK-21/22 v tomto exportu patří jiným starším syntetickým případům. Sedm pohybů varianty 50 je doloženo JSON/XLSX; tento archiv nedokládá samostatné skladové PDF této varianty.
- Přehled klubového vypořádání drží u objednávky 30 nominálních 1 000 Kč a příděly 0 se stavem **NEOVĚŘENO**. Nelze z toho vyvodit nových dlužných 1 000 Kč po dvou opravách; doklady a skutečné peníze mají součet nula.
- **Export 23 správně nezahrnuje pozdější platbu kurzu, pohyb/příděl 82 ani pozdější odměnu.** Zůstatek 0 Kč v tomto snímku není v rozporu s pozdějším zůstatkem pokladny 1 200 Kč.

## Osoba → papírové podklady → kurz → účast

- Osoba **91, Anna TEST LOCALHOST UAT-BROWSER23**, je aktivní a má **0 navázaných účtů**. Osobní přijetí nevytvořilo zákaznický účet ani rodičovský přístup.
- Papírové podklady: **106 přihláška**, **107 souhlas s verzí 41**, **108 storno podmínky s verzí 40**; všechny následně ověřené/přijaté. Přijetí bez přihlášky i bez potřebného souhlasu bylo odmítnuto. Po opravě formuláře zůstala rozepsaná přihláška po odmítnutí zachovaná.
- Wizard vytvořil **program 16, nabídku 15, soupisku 20**, cenu **1 200 Kč**, kapacitu **1**, období **16. 10.–22. 12. 2026**. Prodejní okno probíhajícího kurzu je **23. 10.–22. 12. 2026**.
- Objednávka **31**, **KP2610232CB836E42F**, VS **2610000014**, platba **31**, byla ve finanční pozici potvrzena jako hotovost. Následně byla pozorována **jedna účast**, datované zařazení na soupisku **16. 10.–22. 12.**, saldo případu **0 Kč**.
- Platba kurzu byla samostatně zařazena do peněžní evidence: **pohyb 82 +1 200 Kč**, **příděl 82** na objednávku **31**. Pozdější zůstatek pokladny **28 = 1 200 Kč**. Nejde o další platbu vedle původně potvrzené hotovosti, ale o její účetní návaznost.

## Svěřenci → skutečný trénink → nezávislé schválení odměny

- Před přidělením rozsahu nebyla testovací osoba přístupná. Následně vznikl pouze grant k syntetické **soupisce 20** pro pracovníka **326**, platnost **16. 10.–22. 12. 2026**; skutečné klubové skupiny nebyly tímto krokem přidělovány.
- Trénink **30**, **22. 10. 2026**, **Cvičení**, osoba **91**, trenér **326**. Uzávěrka **34, verze 1**, doložená skutečnost **16:00–17:00**, 60 minut, přepis papírové docházky. Karta osoby následně ukázala jeden trénink z 22. 10.
- Nárok na odměnu **14, verze 1** byl předložen na **300 Kč za 60 minut**. ID použitého pravidla nebylo samostatně ověřeno a není zde odhadováno.
- Pokus pracovníka **326** schválit vlastní nárok byl **ODMÍTNUT**. Jiný pracovník **327** nárok úspěšně schválil: **schváleno 300 Kč, vyplaceno 0 Kč**. Schválení není výplata.
- Klávesnicové ověření našeptávače bez dalších DB zápisů: účastníci **Anna → Tab na výsledek → Enter** přidalo štítek a vyčistilo vstup; měření **Anna → Tab → Space** vybralo osobu a zachovalo celé jméno.

## Opravy ověřené opakovaným browser průchodem

| Oblast | Původní problém | Výsledek opakování |
|---|---|---|
| Skladová navigace | Uložení ztrácelo filtr/variantu; inline změna varianty neobnovovala kontext pod CSP. | **PASS**: výběr 50 → 51 obnovil URL i kontext; počátek 51 po uložení zachoval variant_id a q. |
| Asistovaný prodej | Stránka ignorovala bankovní nastavení v DB a po chybě vracela množství na 1; nový prodej mohl měnit klíč při opakování. | **PASS**: objednávka 30 vznikla pro 2 × 500 Kč se správným nastavením; chybový formulář zachovává údaje a request key. |
| Účetní příděly | Pro uvolnění bylo nutné odhadovat ID nebo stahovat XLSX; chybový POST přepisoval částečnou částku předvoleným zůstatkem. | **PASS**: dohledán příděl 79 a připraveno uvolnění přes UI; po odmítnutí data částka zachována; samostatně uvolněno 500 + 500 Kč. |
| Wizard programu | Výchozí prodejní okno již probíhajícího kurzu končilo před otevřením. | **PASS**: nabídka 15 má platné prodejní okno. Explicitní termíny mají přednost; neplatná/reverzní data nejsou obcházena. |
| Rozepsaná osobní přihláška | Odmítnutí neúplných podkladů zahazovalo obsah formuláře. | **PASS**: osoba, nabídka, plátce, datum, vybrané podklady a platný request key po chybě zachovány. |
| Papírové souhlasy | Prázdné historické názvy neumožňovaly rozlišit povinný souhlas a storno podmínky. | **PASS**: zobrazen účel, verze a přesné ID; vazba se nepřiřazuje odhadem podle názvu. |
| Klávesnicový výběr sportovce | Našeptávače tréninku reagovaly jen na mousedown; odchod z inputu zavíral výsledky během klávesnicového výběru. | **PASS**: nativní click obslouží Enter/Space; focus ve výsledcích je zachován. Účastníci i řádek měření ověřeny v browseru. |
| Lokální testovací brána | Vestavěný PHP server B1 neposkytoval SERVER_ADDR a přísný localhost guard vracel 404. | **PASS**: pouze B1 router doplňuje chybějící adresu pro přesný lokální runtime; původní aplikační guard zůstává. Trenér přístup nedostal, Správce systému bránu otevřel. |

## Technické kontroly a zápis do lokální brány

Výstupy jsou v [test-results.txt](test-results.txt). Cílená regrese: **71 testů / 651 assertions PASS**. Klávesnicový smoke samostatně **5/5 PASS**. Composer validate, platform requirements a audit prošly; PHP lint **1 071 souborů, 0 chyb**, git diff --check PASS. Finální Linux CI a znovu chráněný deploy potvrzují **1 614 testů / 32 689 assertions PASS**; integrační matice MariaDB 10.3 a 11.4 i CodeQL prošly.

Plná místní sada na Windows měla **1 604 testů / 32 520 assertions, 1 selhání a 3 přeskočení**. Selhání GlobalFunctionAvailabilityTest souvisí s chybějící funkcí posix_geteuid na Windows; aplikační volání je chráněné function_exists. Nebyl proveden bypass skeneru ani aplikace. **Plná místní sada proto není bezpodmínečné PASS.** Finální Linux CI a MariaDB integrační matice jsou doloženy v [release-checks.json](release-checks.json).

Starší lokální brána **testovaci_scenare.php** nejprve v B1 vracela **404 Nenalezeno** kvůli chybějícímu SERVER_ADDR. Oprava je omezena na B1 router: doplní pouze chybějící hodnotu při SAPI cli-server, SERVER_NAME/REMOTE_ADDR 127.0.0.1, portu 18086 a přesném Host 127.0.0.1:18086. Existující adresu nepřepisuje, hlavičky forwardovaného hostu/adresy nepoužívá; aplikační guard zůstává nezměněný. Browser potvrdil odmítnutí trenéra a dostupnost pod Správcem systému.

Do této brány byly zapsány **B03/B09/B10/B15 jako PARTIAL** s výslovným textem **TECHNICKÁ KONTROLA AGENTEM**. Výsledný souhrn této starší sady je **PASS 0 / PARTIAL 4 / NETESTOVÁNO 36**. Vlastnická A01–A10 zůstává **NETESTOVÁNO, 0/10**. Jde o jinou sadu než 43 připravených scénářů lidské přejímky; počty ani výsledky se neslučují. Stažený [Markdown export brány](acceptance-hub-export.md) byl přečten a zkontrolován před zařazením do Gitu; neobsahuje přihlašovací údaje ani ostré osobní údaje. Tento protokol nenahrazuje lidské schválení.

## Nasazení a dokončení předání

**Nasazení dokončeno.** [PR #173](https://github.com/KovoPraha/evidenceTreninku/pull/173) je sloučen; [chráněný deploy 39937236344](https://github.com/KovoPraha/evidenceTreninku/actions/runs/39937236344) skončil SUCCESS pro **e78af565d6d89492e54f0b9e2ecb2b63a4b534c7**. Před nasazením ověřena záloha 329 tabulek; kontrola osmi existujících plateb potvrzuje nezměněné VS. Vstupy uat_schvaleno=false a importovat_krouzky=false zachovaly oddělení technického vydání od lidského schválení a importu. Žádná integrace nebyla aktivována.

Živý prohlížeč potvrdil přesný release a **soukromé úložiště PASS**, nové prodejní okno programu, přehled přídělů, skladový výběr varianty přes GET, dostupnost asistovaného prodeje a přihlášek, rozlišené papírové souhlasy a formulář tréninku. Produkční testovaci_scenare.php nadále vrací Nenalezeno. Kontroly byly pouze čtecí kromě přepínání vlastní pracovní role; žádné produkční objednávky, doklady, peněžní ani skladové pohyby nevznikly. [Strojový protokol](release-checks.json), [produkční obrazovka](production-release.jpg).

Celková produkční připravenost zůstává **BLOKOVÁNO** kvůli zbývajícím provozním předpokladům a rozhodnutím. Lidská přejímka, rozhodnutí účetní a provozní přechod ze starých systémů zůstávají samostatné otevřené kroky. Testovací schválení odměny ani stažení exportu nejsou účetním převzetím nebo souhlasem s ostrým přechodem. Následné doplnění tohoto důkazního protokolu je pouze dokumentační změna; nevyžaduje nové produkční nasazení.
