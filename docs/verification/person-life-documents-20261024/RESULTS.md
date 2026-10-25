# Balík B — Život osoby a podklady

24. 10. 2026. **Lokálně implementován a ověřen rozsah nezávislý na rozhodnutí
oddílu. Nenasaženo.** Datované členství a další rozhodovací části zůstávají
výslovně odložené, viz D17–D19 níže.

Větev `codex/person-life-documents-20261024`, navazuje na balík A `1925f88c`.
Pracovní kopie `C:\xampp\htdocs\evidencePavel-finance-b1-20261022`.
Zadání: [funkční analýza, balík B](../../NONSHOP_FUNCTIONAL_ANALYSIS_2026-10-24.md).

## Co se změnilo

### B1 — Vyřízení osoby a návazné kroky (F02, nezávislá část F03)

Registrář má v nabídce **Vyřízení osoby**, také přímo z editoru konkrétní osoby.
Vyhledá původní osobu a zvolí doporučený postup pro přijetí, přerušení, odchod
nebo návrat. Volba postupu je pomůcka, nikoli nový stav osoby.

Stránka propojuje identitu, rodinné přístupy, papírové podklady, současné
rozhodnutí o členství, zařazení, zdravotní podklady a osobní přijetí. Ukazuje
neukončené intervaly soupisek, plánované lekce v příštích 90 dnech, budoucí
účasti evidované v klubovém kalendáři a počet členských předpisů čekajících
na úhradu. Nákupy ani rezervace mimo klubový kalendář nezahrnuje a netvrdí bezdlužnost.

Pracovní krok lze uložit s odpovědným registrářem, termínem, důvodem a výslovným
potvrzením, později uzavřít nebo znovu otevřít. Používá stávající pracovní případy
`operational_cases` se zdrojem `person_followup` a jejich neměnnou historii událostí.
Případ se objeví i v provozním přehledu. Na osobu je jeden takový pracovní případ;
nové vyřízení navazuje další revizí. Čtení nevytváří úkoly za všechny osoby.

Uzavření úkolu **nemění členství, rodinný přístup, soupisku, účast ani závazky**.
Věcné změny se provádějí stávajícími formuláři. Souběžná oprava se odmítne;
rozepsaný návrh i původní revize zůstanou ve formuláři, dokud pracovník výslovně
nenačte aktuální verzi.

### B2 — Dohledatelnost členství a papírových podkladů (F02/F03)

Na vyřízení osoby je stránkovaná historie skutečně uložených rozhodnutí
`status_manual`, včetně zapisujícího pracovníka a důvodu. Čas zápisu je označen
jako čas zápisu; není použit jako účinnost členství. Chybějící historický obsah
nevytvoří odhadnutý stav. Rozhodnutí nadále zapisuje původní formulář členství.

Přehled kroků nově upozorňuje také na přijatý papírový podklad s neověřeným
zněním. Pouhé otevření souboru nemění jeho stav a neznámá verze se netváří
jako dokončené doložení všech souhlasů. Jednotlivé podklady mají přímý detail.

### B3 — Zdravotní podklady před aktivitou (F05)

**Podklady před aktivitou** umožňují zvolit pojmenovanou skupinu/soupisku a den,
nebo přejít z konkrétní lekce. Kontext lekce používá její datum, pravidelnou
soupisku, rezervované náhradníky a zapsané účastníky; nepředstírá skutečnou docházku.
Přehled lze hledat podle osoby, filtrovat stavem a stránkovat po 25 položkách.

Vyhodnocení používá původní pravidla stavu dokumentu včetně jeho náhrad.
Upozornění na končící platnost používá pouze již uložený počet dní u schváleného
účelu. **Platný podklad s upozorněním zůstává platný**. Není-li osobě určený
sledovaný podklad, zobrazuje se „Sledování neurčeno“, nikoli automaticky
„chybí povinná prohlídka“.

Trenér vidí jen svůj aktuální rozsah; znalost čísla osoby nebo lekce nestačí.
Registrář má skupinový přehled a přímé odkazy na administraci. Trenér ani vedoucí
nedostávají skeny, umístění originálů ani jiné soukromé údaje dokumentu.
Přehled neblokuje účast a neprovádí automatické zdravotní rozhodnutí.

### B4 — Rodinné úkoly a informace o komunikaci (F08)

Sportovní přehled obsahuje **Co zbývá vyřídit** rozdělené na úkoly rodiny
(včetně domluvy osobně) a čekání na klub. Zahrnuje vlastní žádosti o propojení,
členství k ověření, stav sledovaných zdravotních podkladů a nezodpovězené
tréninky v následujících 30 dnech. Odkaz na trénink zachová správný měsíc.

Oprávnění se načítá z aktuálních schválených vazeb aktivního ověřeného účtu.
Platí pro rodiče i vlastní sportovní profil. Odvolaná vazba odstraní úkoly osoby
při dalším načtení. Čekající žádost bez schváleného profilu nezobrazuje údaje
nalezeného sportovce ani nevyzývá k podání stejné žádosti znovu.

Pevné tvrzení o neaktivním produkčním transportu bylo odstraněno. V prostředí,
kde je PHP mail blokovaný, je uvedeno skutečné blokování. Jinde text rozlišuje
preferenci odběru, zpracování fronty a neprokázané doručení. Transport volí CLI
při konkrétním běhu; stránka proto netvrdí, že byl aktivován plánovač nebo zpráva
doručena. V tomto balíku se odesílání ani plánovače nezapínají.

## Odložená rozhodnutí

Podrobnosti jsou v [registru D17–D19](../../CLUB_COMPLETION_DECISIONS.md).

- **D17:** účinnost přijetí, přerušení, odchodu a návratu, zpětné/budoucí změny,
  překryvy a opravy. Zatím bez intervalů kmenového členství a bez sestav členů
  k minulému datu; stará historie nebyla převedena odhadem.
- **D18:** účely zdravotních podkladů pro činnosti, pravidla účasti při chybění
  a navazující retence. Používají se jen již evidované účely; žádné nové pravidlo.
- **D19:** online žádosti o změnu osobních údajů a nahrávání podkladů rodinou.
  Existující osobní obsluha a žádosti o propojení zůstávají dostupné.
- Odesílání a plánování komunikace dál spadá pod **D12**.

## Izolace a ověření

Web pouze `http://127.0.0.1:18086`, MariaDB port `33426`, schema `evidence_b1`.
Probe ověřil skutečný port/datadir, vlastníka checkoutu, 327 čitelných tabulek,
soukromé úložiště mimo webroot a blokované vnější transporty. Chybějí jen tři
volitelné kontraktní tabulky `ucto_gs_*`; aplikace je pro tento balík nepotřebuje.
HTTP přejímka odmítne jiné připojení nebo vlastníka a používá výhradně nové
syntetické osoby a účty `LOCALHOST NEPLATIT`, adresy `example.invalid`.

| Ověření | Výsledek | Důkaz |
|---|---|---|
| Cílená sada pro změněné agendy a přístupy | **155 testů / 2563 assertions PASS** | [PHPUnit](phpunit.txt) |
| Nový průchod přes skutečné PHP formuláře a MariaDB | **30 scénářů PASS** | [HTTP B](http-acceptance.json) |
| Návaznost na předchozí balík | **32 scénářů PASS** | [HTTP A](package-a-regression.json) |
| PHP syntax | **18 souborů PASS** | Lokální PHP lint |
| Whitespace | `git diff --check` PASS | Lokální Git |

Nová integrační sada ověřuje mimo jiné opětovné otevření téhož pracovního
případu bez duplikace, stale revizi, neodvozené datum členství, stránkování,
nezpřístupnění soukromých hodnot, neověřené znění přijatého podkladu,
odvolanou rodinnou vazbu a rozdíl mezi čekáním na klub a novým požadavkem rodiny.
HTTP průchod ověřuje též skutečný oddělený formulář členství, jeho historii,
budoucí klubovou akci a funkční role registráře, trenéra, vedoucího a rodiny.

Opakování v připraveném B1 prostředí:

```powershell
& C:\xampp\php\php.exe tests/Support/PersonLifeDocumentsHttpAcceptance.php
& C:\xampp\php\php.exe tests/Support/DailyCoachRegistrarHttpAcceptance.php
& C:\xampp\php\php.exe vendor/phpunit/phpunit/phpunit --filter 'PersonLifeDocumentsTest|DailyWorkReadModelsTest|OperationalCasesTest|MedicalClearanceTest|StaffWorkspaceRegistryTest|FamilyPortal|FamilyWeeklyDelivery|FamilyCalendarWiring|AccountPersonRoleTest|AccountPersonClaim|PaperRegistrationTest|SportovecEditor|TrainingClosure|TrainingRsvp|TrainingSubmission|SportsActivityTest'
```

Bez změn schématu, produkčních čtení/zápisů, push, deploy a zapnutí transportu.
E-shopové postupy nebyly upravovány ani znovu přejímány; úprava veřejného
sportovního přehledu se omezuje na rodinné úkoly, odkazy na vlastní profily a text
komunikace. Zdravotní přehledy vyhodnocují metadata celé vybrané skupiny a následně
stránkují; nejde o zátěžovou certifikaci pro velké registry.

Jde o automatizovanou funkční přejímku HTML/HTTP, nikoli vizuální prohlídku
v prohlížeči nebo ostrou uživatelskou přejímku. Další nezávislá realizace může
pokračovat balíkem C; D17–D19 se tím nepovažují za schválené ani splněné.
