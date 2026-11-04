# Pátý autonomní funkční průchod — 4. 11. 2026

**73 HTTP scénářů PASS, 35 cílených testů / 288 assertions PASS.**
Souběžné publikování standardních i vlastních souhlasů na MariaDB rovněž PASS.
Nová funkční chyba aplikace se v tomto rozsahu nepotvrdila; runtime se touto vlnou nemění.
Výchozí head otevřeného PR #295: 46aa60738e0f6653969a1df89314f1d3f6419ef9.
Předchozí [oprava původu papírové docházky](../functional-uat-round4-20261104/RESULTS.md)
zůstává součástí téhož PR a čeká na samostatné sloučení/nasazení.
Změna hesla, použitý obnovovací odkaz a T07 nadále SKIPPED.

## Prostředí a metoda

Znovu spuštěná vlastní syntetická functional_round4, MariaDB 10.4.32 na
127.0.0.1:3322, samostatný Apache 127.0.0.1:18422. Původní fixture a
důkazy čtvrté vlny zachované; nové identity mají samostatné označení a ID.
Session/soukromé soubory mimo webroot, webové mail/cURL transporty blokované.
Vstup stále pochází z prázdného verzovaného schématu, migrací a syntetického seedu,
nikoli produkčního dumpu/configu. Produkce se v této vlně neotevírala ani neměnila.

**Browser NOT RUN:** vytvoření místní karty i následná obnova ovládání prohlížeče
skončily timeoutem. Zde uvedené výsledky jsou skutečné HTTP požadavky do aplikace,
kontroly serverového HTML a persistence MariaDB; nejsou novým browser důkazem.
Použity vlastní syntetické relace a CSRF, bez čtení cookies prohlížeče či vlastníka.
Účty mají jedinou pracovní pozici a nemají superadmina.

## Ověřené scénáře

| Oblast | Konkrétní výsledek |
|---|---|
| Vlastní souhlasy | Koncept zůstane skrytý a nezmění veřejný balíček. Uložený titul i text se escapují. Publikování a skrytí vyžadují samostatné potvrzení; neplatný CSRF ani jiná pracovní role nic nezapíší. Konkurenční koncept nepřepíše novější znění, opakování publikování nepřidá verzi. Změna povinnosti platí až po zveřejnění; původní text/metadata zůstávají shodné. |
| Papírové podklady | Konkrétní program a historická verze souhlasu zůstávají propojené i v opravě formuláře. Nesoulad programu/akce, chybějící konkrétní program a budoucí podpis nevytvoří částečný záznam. Zaznamenání a ověření jsou oddělené; nevznikne členství, ověření účtu, rodinný přístup ani online potvrzení. Nová oprava ponechá původní podpis/text a čeká na vlastní ověření; nahrazený podklad se znovu neopraví. |
| Vyřízení osoby | Čtení nevytváří pracovní případ. Platný zápis přidělí vlastníka, další krok a datum; souběžný starý formulář zůstane odmítnutý se zachovanou revizí/návrhem. Uzavření úkolu nemění členství, soupisky, přístupy ani závazky. Samostatné rozhodnutí o členství se projeví v historii a samo neukončí soupisku. |
| Zdravotní a rodinné návaznosti | Stav platnosti odpovídá dni aktivity, kontext lekce ignoruje podvržené datum. Svěření osoby neuděluje cizí lekci ani soukromý soubor. Rodič i vlastní sportovec vidí pouze oprávněné úkoly, bez umístění originálu či cizí osoby. Odvolání rodinné vazby nebo trenérského rozsahu se projeví při dalším requestu. |
| Souběžné zveřejnění | Dva pracovníci nad stejnou základní verzí: přesně jeden publikoval a druhý byl odmítnut. Totéž pro standardní i vlastní souhlas; opakované zveřejnění idempotentní. Skrytí ponechalo dřívější zaznamenané přijetí. |

[Souhlasy a papír: 43 kontrol](registration-paper.json) ·
[Vyřízení osoby a návaznosti: 30 kontrol](person-life.json) ·
[Strojový souhrn](verification.json).
Souhrny hashů potvrzují zachování vybraných řádků osoby/přístupů a historických textů;
nejde o obecnou inventuru celé databáze nebo produkční přejímku.

## Reprodukce a validace

Nový [43bodový HTTP scénář](registration-paper-http.php.txt) a jeho
[chráněný transport/fixture helper](http-prefix.php.txt) jsou uloženy jako textové důkazy.
Kopii v repozitáři jsme znovu provedli: [replay 43/43 PASS](published-replay.json).
Toto opakování se nepřičítá k 73 samostatným scénářům.
Pro stejný již připravený místní profil nastavte EVIDENCE_ROUND5_RUNTIME na adresář
vlastního syntetického descriptoru, session a databáze, potom spusťte:

    php docs/verification/functional-uat-round5-20261104/registration-paper-http.php.txt

Guard vyžaduje přesnou loopback DB/HTTP identitu, datový adresář a shodného vlastníka
checkoutu. Test přidává nové označené syntetické identity; není určen pro produkci.

Dalších 30 případů používá verzovaný tests/Support/PersonLifeDocumentsHttpAcceptance.php;
v dočasné kopii změněny explicitní cesty/porty vlastního sandboxu a limit transportu na 45 s.
První neúplný běh s limitem 30 s skončil HTTP 0 na rodinném GET, bez uloženého curl errno.
Není počítán jako PASS ani jako potvrzená chyba aplikace; nový úplný běh prošel.
Souběžnost ověřil nezměněný tests/Support/RegistrationTextsMariaDbSmoke.php
na vlastní dočasné DB evidence_registration_texts_test_round5_20261104, následně odstraněné.
SHA-256 původních verzovaných kontraktů je ve strojovém souhrnu.

Cílené PHPUnit: RegistrationTextsTest, CustomClubConsentsTest,
PaperRegistrationTest, PersonLifeDocumentsTest: **35/288**, bez skipů/selhání.
Úplná sada 1977/38403 a všech 11 kontrol prošly na předchozím přesném headu PR;
aktuální CI po dokumentačním doplnění určuje PR #295. Kód aplikace se zde neměnil.
Odkazy, dokumentační kontrakt a git diff --check ověřeny.

Úplné UAT všech pracovních rolí, nové browser ověření této vlny, produkční F06
štítky, poslední synchronizace z exportu, E8 a lidská/účetní přejímka zůstávají otevřené.
Vlastní místní servery se po dokončení opět vypínají; syntetické důkazy zůstávají zachované.
