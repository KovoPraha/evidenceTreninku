# Autonomní ověření trenérské obnovy — 20261101-2341

Začátek 1. 11. 2026 23:40–23:41 Europe/Prague; uzavření produkční části
23:56:50 Europe/Prague. Samostatný běh po produkčním
nasazení PR #269; zdroj main `bf8dc604`, živé vydání dle posledního úspěšného
deploye `acce3c1351cddbcad12fb5fea11afe6ae8936001` / run 41349120615.

Vlastník nově výslovně povolil autonomní práci s přihlášeným testovacím účtem
a jeho webmailem pro obnovu. Pouze schválený testovací účet trenéra 56, dvě
syntetické osoby 11783/11784 a kontrolní osoba 11785. Osobní adresy a hodnoty
odkazů se do protokolu nebo screenshotů neukládají. Do ostatních zpráv se nevstupuje.

## Pozorované výsledky

- Chrome: skutečná přihlášená identita `TEST - Trenérský pilot 20261031`,
  pracovní rozcestník Trenér, bez přepnutí na správce.
- Moji sportovci: právě odkazy na povolené profily 11783/11784.
- Platná relace odmítla `/eshop_payments_admin.php`,
  `/sportovec_karta.php?sportovec_id=11785` i přímý
  `/sportovec_detail.php?id=11785`. Poslední cesta výslovně zobrazila
  „Sportovec není ve vašem aktuálním pracovním rozsahu.“ Žádná cizí data.
- Moje tréninky: jediný záznam, ID 1604, označení původního běhu s „– upraveno“,
  1.00 h a Alfa/Beta. Žádné další vytvoření nebo úprava.
- **Právě jedna žádost o obnovu**, kliknuto po kontrolním bodu
  2026-11-01 23:41:26 Europe/Prague. Po dokončení navigace aplikace zobrazila
  generické přijetí. Neopakovalo se odeslání po přechodném nezměněném UI.
- **Skutečné doručení ověřeno přímo ve webmailu.** V cíleném filtru přibyla nová
  zpráva s předmětem trenérské obnovy a časem „Dnes 23:42“. Schválený adresát
  ověřen v hlavičkách pouze jako boolean; ostatní zprávy ani adresy se neexportovaly.
- Čerstvý doručený odkaz otevřen bez vypsání nebo uložení hodnoty odkazu:
  automatický předběžný POST ukázal právě jeden heslový formulář, dvě prázdná
  povinná heslová pole, limity 12–200 znaků, `autocomplete=new-password`.
  Fragment z adresy odstraněn, režim trenéra zachován.

| Kontrola | Stav | Důkaz / skutečnost |
|---|---|---|
| A01 — testovací identita a trenérská pozice | PASS | Správné zobrazené jméno a rozcestník Trenér. |
| A02 — povolený rozsah | PASS | Seznam obsahoval právě odkazy 11783/11784; oba profily se otevřely se správným testovacím jménem. |
| A03 — nepovolené cesty | PASS | Platná relace odmítla všechny tři uvedené cesty, včetně skutečné kontroly rozsahu detailu osoby 11785. |
| A04 — přetrvání existujícího tréninku | PASS | Jediný záznam 1604, správné označení, 1.00 h a zobrazená Alfa/Beta. Nevytvořil ani neupravil se další trénink. |
| A05 — jediné odeslání žádosti | PASS | UI po dokončení navigace potvrdilo generické přijetí; formulář se znovu neodesílal. |
| A06 — skutečné doručení | PASS | Nová testovací zpráva ve webmailu, čas 23:42 a shoda schváleného adresáta v hlavičkách. Bez screenshotu pošty nebo exportu jejího obsahu. |
| A07 — čerstvý platný odkaz | PASS | Skutečný automatický check POST, dvě prázdná pole, meze 12–200 znaků, odstraněný fragment a správný režim. [Snímek](fresh-valid-link.jpg). |
| A08 — opakované otevření před změnou hesla | PASS | Odkaz zůstal platný; předběžná kontrola jej nespotřebovala ani neodvolala přihlášenou trenérskou relaci. To není test opakování po spotřebování. |
| A09 — anonymní obnova | PASS | Tentýž čerstvý odkaz fungoval po řádném odhlášení, bez přihlášení a bez nového požadavku na obnovu. [Snímek](fresh-valid-link-anonymous.jpg). |
| A10 — oddělení účelů | PASS | Trenérský odkaz v režimu bez `pro=trener` server odmítl bez heslových polí. Následující otevření ve správném režimu stále fungovalo. |
| A11 — ovládání prázdného heslového pole | PASS | Zobrazit/Skrýt změnilo typ prázdného pole a `aria-pressed`, poté se stav vrátil. Žádné heslo se nezadávalo. |
| A12 — mobilní emulace | PASS | Skutečně naměřeno 390 × 844, `clientWidth=scrollWidth=390`, prázdná pole a dostupné ovládání bez ořezu. [Snímek](fresh-valid-link-mobile.jpg). Viewport vrácen. |
| A13 — ochrana po odhlášení | PASS | Přehled sportovců, Moje tréninky, editace 1604 a nový formulář vedly na přihlášení. Běžný logout se nepovažuje za revokaci po změně hesla. |
| A14 — celý T01 včetně spotřebování a opakování | BLOCKED | Nové produkční heslo se nesmí automaticky zadat/odeslat; po skutečné změně se odkaz v tomto běhu nezkoušel. |
| A15 — úplný produkční T07 | BLOCKED | Bez skutečné změny hesla nelze prokázat odvolání původní produkční relace ani přihlášení starým/novým heslem. Izolované rozšíření je níže. |
| A16 — produkční vyčkání na expiraci | NOT RUN | Čekání celých 60 minut nebylo povinnou součástí původního zadání; expiraci pokrývá izolovaná sada. |
| A17 — UI hledání kontrolní osoby | NOT RUN / neprůkazné | Hledání bylo vyplněno a odesláno, ale konečný stav výsledků nebyl zachycen před odhlášením. Není počítáno jako PASS ani chyba aplikace. Přímé odmítnutí profilu osoby je samostatně doloženo A03. |

Výsledek živé části: **13 PASS, 2 BLOCKED, 2 NOT RUN/neprůkazné**.
Žádný nový nález P1–P3 se neprokázal. Nejde o přejímku celé aplikace.

## Izolované rozšíření T07

Přidán regresní test `tests/Integration/TrainerResetSessionRevocationTest.php`,
bez změny produkčního aplikačního kódu. Používá syntetickou SQLite databázi,
dvě skutečně oddělené souborové PHP relace s rozdílnými ID a skutečné helpery
resetu, ověření přihlášení a odvolání session. Nejde o dvě karty se společnými
cookies ani o dvě produkční browser relace.

Test provede reset syntetického účtu v B, následné obnovení uložené A,
odmítnutí staré trenérské relace, změnu jejího ID a CSRF, odmítnutí starého hesla
a přijetí nového přes přesný matcher z `login.php`. Následný replay nezmění
nový hash/verzi a neodvolá čerstvé přihlášení. Oba datové případy rozlišují
výslovně propojenou veřejnou identitu (také odvolána) a nepropojenou identitu
(zachována; její heslo a verze se nezmění). Propojení se neodvozuje z e-mailu.

Aktuální cílená sada: **57 testů / 442 assertions PASS**, včetně obou nových
případů. JavaScript skutečné fragmentové větve **2/2 PASS**; syntaxe nového
PHP a changed-file style 1 soubor / 0 chyb PASS. Kompletní lokální sada po přidání
testu: **1902 testů / 38187 assertions, 3 Windows skipy**, exit 0, 3 min 50 s.
Composer validate --strict, dokumentační kontrakt 2/14, kontrola odkazů a
git diff --check PASS. Linux a MariaDB kontroly jsou dostupné u navazujícího PR;
Windows výsledek není vydáván za plný Linux PASS.

Bezpečný souhrn konkrétních pozorování a testů: [evidence.json](evidence.json).

V produkčním prohlížeči bylo kromě trenérské identity viditelné také menu
veřejné identity; její osobní/rodinné agendy se neotevíraly a netvrdí se její
propojení s trenérem. Izolované testy tento rozdíl výslovně kontrolují.

## Hranice a zbývající ověření

Pravidla browser nástroje vyžadují předání uživateli před zadáním a odesláním
nového autentizačního údaje. Žádné produkční heslo se nečte, negeneruje ani nezadává.
Proto se neprovádí skutečná změna hesla, následné použití již spotřebovaného
odkazu ani úplný T07. Tyto kroky nejsou PASS; nejde o chybějící souhlas vlastníka,
ale o povinné předání při změně přihlašovacího údaje.

V tomto běhu se nevyžaduje další aktivita vlastníka. Testovací aplikace byla
odhlášena, agentem vytvořené karty uzavřeny a viewport vrácen. Webmail zůstal
přihlášený, v původním pohledu na doručenou poštu s vymazaným vyhledáváním;
ostatní zprávy ani nastavení schránky/domény se neměnily. Nový odkaz nebyl
spotřebován změnou hesla; jeho životnost se nijak neprodlužovala.

Tento běh přidal pouze jednu výslovně povolenou žádost o obnovu a její
doručenou zprávu. Neproběhlo vytvoření účtu, změna oprávnění, rezervace,
platba, import, další úprava tréninku ani nové nasazení. Nová změna v Gitu
obsahuje jen izolované testy a dokumentaci; produkční runtime se nemění.
