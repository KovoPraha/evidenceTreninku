# Konkrétní řešení B04 a B23

Aktualizace: návrh byl následně implementován a ověřen v izolaci; viz [navazující přejímka B04/B23](../b04-b23-20261025/RESULTS.md). Níže zůstává původní návrh a stav v okamžiku předchozí přejímky. Ostrý a finanční přechod B23 nadále čeká na provozní rozhodnutí.

Oba body zůstávají BLOCKED. Jde o chybějící nebo nahrazenou funkčnost, kterou samotná přejímka nemůže označit za hotovou. Návrh neaktivuje import ani nezasahuje do produkce.

## B04 — interní i externí závodní výsledky

**Zjištěný stav:** závod a jeho kategorie/URL/měření fungují. Historická tabulka `zavod_sportovec` vyžaduje osobu a váže záznam na dvojici závod–sportovec. Nemá úplnou podporu externích účastníků. Starý `import_vysledku_zavodu.php` byl odstaven, protože mazal výsledky před úplným ověřením vstupu a pároval osoby podle jména. Neobnovovat jej.

**Doporučený první rozsah pro malý oddíl:** ruční zadávání výsledků konkrétního závodu (`zavod_id`) s možností vložit externího účastníka bez založení klubové osoby. Hromadný import přidat až po ověření tohoto zapisovacího rozhraní.

| Pořadí | Konkrétní úkol | Podmínka dokončení |
|---|---|---|
| B04.1 | Vytvořit účast závodníka se samostatným ID, povinným `zavod_id`, volitelným `sportovec_id`, snapshotem veřejně potřebného jména/klubu/kategorie. Přidat jednoznačný zdrojový klíč pro import. | Externí závodník nezakládá fiktivního člena, kolize jmen nezpůsobuje sloučení osob. |
| B04.2 | Výsledek navázat na účast a disciplínu/jízdu. Znovu použít existující kontrakt sportovního měření (migrace `20260805050000_sports_measurement_contract.php`) a jeho parsery. | Čas, pořadí, jednotky a stavy DNS/DNF/DSQ mají jednoznačnou validaci. Více jízd stejné osoby se nepřepisuje. |
| B04.3 | Formulář konceptu: vlastní osoba z oprávněné soupisky nebo externí účastník; náhled, řádkové chyby, souhrn změn, důvod. Publikaci řídit oprávněním k závodu. | Neplatný řádek nezmění uložené výsledky. Cizí trenér nemůže měnit závod ani obejít rozsah přímým POST. |
| B04.4 | Kanonická transakční služba uložení/publikace, kontrola verze a neměnná auditní revize. Oprava publikovaných výsledků vytvoří novou revizi. | Opakovaný požadavek nezaloží duplikát; souběžná editace vyžaduje nový náhled; chyba vrátí celou transakci. |
| B04.5 | Čtecí převod historických vazeb do náhledu nové evidence; teprve samostatně schválený migrační krok. | Počty, původní závod/osoba a měření jsou dohledatelné; historické vazby se nepřepárují odhadem. |
| B04.6 | CSV import do stagingu přes tutéž validační službu; ruční potvrzení nejednoznačných osob a publikace jedné sady. | Nepoužívat mazání všech výsledků před validací; opakovaný soubor má stejný výsledek. |

**Přejímka:** v izolaci jeden interní a dva stejně pojmenovaní externí účastníci, dvě disciplíny, platný/neplatný čas, DNS/DNF, úprava existujícího výsledku, souběh, rollback chyby, kontrola oprávnění a HTML escapování. Následně celý původní B04 přes UI.

**Nasazení/pilot:** blokuje nahrazení stávajícího procesu zadávání externích výsledků. Běžnou evidenci tréninků nemusí blokovat, pokud pilot tuto agendu výslovně ponechá mimo rozsah. Potvrdit později: používané disciplíny, veřejná pole a kdo výsledky publikuje; technický základ lze připravit bez importu skutečných dat.

## B23 — bezpečný přechod ze staré synchronizace Evidence

**Zjištěný stav:** `sync_evidence.php` má přímý aplikační krok záměrně odstavený. Současný KIS tok má náhled, manifest zdrojů, fingerprint a aplikaci/rollback do odděleného sandboxu; B24 je nyní prokázán i UI. To ještě není potvrzení, že sandboxový převod lze zapnout jako produkční zápis do všech kanonických agend.

**Doporučení:** starý čtvrtý krok trvale nahradit předáním konkrétního importního běhu do KIS centra. Nenabízet druhý paralelní způsob přímých databázových změn.

| Pořadí | Konkrétní úkol | Podmínka dokončení |
|---|---|---|
| B23.1 | Jednotný předávací kontrakt: `run_id`, manifest zdrojů osob/plateb/soupisek, verze mapování a fingerprint. Starý formulář po náhledu odkáže na tento konkrétní běh. | Není možné zaměnit běh nebo potvrdit jiný obsah než zobrazený náhled. Chybějící povinný zdroj blokuje příslušnou část převodu. |
| B23.2 | Registrář vyřeší nejednoznačné vazby podle stabilních KIS ID; ukázat nové osoby, opravy, duplicity a osoby mimo import odděleně. | Nikdo se nepáruje jen podle jména. Nepřítomnost ve vstupu automaticky nearchivuje člena ani neodvolává přístupy. |
| B23.3 | Dokončit nebo ověřit kanonické adaptéry zvlášť pro osoby, soupisky a finanční předpisy. Finanční část má vlastní paritní protokol a rozhodnutí hospodáře. | Stejné VS a účetní historie zůstanou zachované; potvrzené platby nevzniknou pouhou migrací předpisu. Uzavřená evidence je chráněná. |
| B23.4 | Před aplikací znovu spočítat fingerprint i verze cílových záznamů, zobrazit změny, vyžadovat oprávnění, důvod a potvrzení konkrétního běhu. | Zastaralý náhled a kolize se zastaví; nevznikne částečně provedený smíšený běh. Opakování je idempotentní. |
| B23.5 | Protokol změn se snapshoty před/po a identifikátory kanonických zápisů. Kompenzační návrat pouze u záznamů nezměněných následným provozem. | Návrat nepřepíše novější ruční opravu. Konfliktní návrat se odloží s konkrétním seznamem k ručnímu řešení. Audit se nemaže. |
| B23.6 | Nový UI scénář: upload/předání → náhled → ruční párování → sandbox → parity report → autorizovaná kanonická aplikace → kompenzační návrat v izolaci. | Test pokryje chybný manifest, duplicitní ID, chybějící finance, drift, opakování, selhání uprostřed a zachování novější změny. Až potom lze B23 uzavřít. |

**Nasazení/pilot:** blokuje ostrý import a nahrazení staré evidence jako zdroje pravdy. Samotné nasazení kódu a provoz bez importu je jiný krok. Pro ostrý přechod později doplnit rozhodnutí vlastníka: zdroj pravdy pro jednotlivé agendy, finální exportní balík a termín uzávěrky; účetní potvrdí finanční paritu. Do té doby pouze syntetický sandbox, bez zapnutí integrací.
