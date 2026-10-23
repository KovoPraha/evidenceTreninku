# R07–R12 — společný základ oddílu

Lokální implementace navazuje na B1 (`2ccd30e27fa209302db4d1e17d4eb411a4c87cca`). Pracovní větev je `codex/club-foundation-r07-r12-20261022`, pracovní kopie `C:\xampp\htdocs\evidencePavel-finance-b1-20261022`. Původní checkout a jeho rozpracované změny se nemění. Tento balík neznamená dokončení finančních, skladových a migračních úkolů R13–R43.

**Produkce, vzdálený Git, ostré osoby, plánovače a aktivace integrací nejsou součástí této práce.** Dosavadní produkční údaje v jiných dokumentech nejsou nově ověřeným stavem ani povolením k nasazení tohoto balíku.

## Dodaný rozsah

| Úkol | Navázáno na existující systém | Doplnění |
|---|---|---|
| R07 — identita | Kanonická osoba `sportovci.id`, schvalování žádostí, `person-match-v1`, původní importní aliasy. | Neznámé/zástupné datum není důkaz shody. Podobnosti vyžadují ověření. Nová administrace zdrojových případů uchovává důkaz, kandidáty, rozhodnutí a revize. Žádná automatická fúze nebo přepis importního aliasu. |
| R08 — oddělené stavy | Členství, účty, schválené rodinné vazby, datované soupisky, účasti, předpisy. | Členství se neodvozuje z dluhu ani staré skupiny. Ruční změna vyžaduje důvod, audit a aktuální revizi. Přehled odděluje přístup, členství, účast a úhrady; potvrzené zastupování končí přesným okamžikem. Zůstávají dosavadní pravidla klubových cen podle soupisky. |
| R09 — oprávnění | Osm pracovních pozic, centrální kontrola cest, současná session validace. | Časově platné výslovné svěření skupin/soupisek trenérovi, audit přidělení a odvolání, kontrola konkrétních osob při čtení a zápisu. Kontroly přímých URL/POST, AJAX, příloh, vývozů a již otevřených profilů. Staré celoklubové sestavy patří vedoucímu sportu. |
| R10 — osobní obsluha a rodina | Rodinné účty, samostatný dětský přístup, verzované texty, soukromé úložiště. | Papírové přihlášky/souhlasy s účelem, podepisujícím, vztahem, datem podpisu, verzí, autorem a důkazem. Opravy jsou nové záznamy. Odvolání zastupování zneplatní i starší veřejné odkazy dané osoby; druhý rodič přes vlastní schválený účet zůstává oprávněný. |
| R11 — zprávy a dohled | Trvalé fronty, retry, worker, lokální outbox, diagnostika. | Atomické uložení rezervace a zprávy; kontrola příjemce, vztahů a platnosti před odesláním. Nejisté doručení vyžaduje kontrolu. Osobní vyřízení má vlastní audit a není označeno jako odeslaný e-mail. Registr devíti provozních úloh s doloženým stavem a dalším krokem. |
| R12 — společná obsluha | Provozní přehled, auditní osa osoby a rodinný sportovní přehled. | Případy s odpovědnou osobou, dalším krokem, termínem a historií předání; oddělené podle pracovní pozice. Zdrojovou přihlášku/platbu tím nelze schválit. Rodina vidí čekající propojení a omezení úplnosti převodu historie. |

Podrobnosti: [identita a členství](PERSON_FOUNDATION_R07_R08.md), [trenérské rozsahy](COACH_SCOPE_R09.md), [zprávy a provoz](R11_OPERATIONAL_DELIVERY.md).

## Pracovní postupy

1. Registrář otevře **Ověření identity**, zapíše zdroj a podklad. Výslovně vybere osobu, odloží rozpor nebo zaznamená vyřazení. Ani přesná podobnost nevytváří přístup automaticky.
2. U osoby bez účtu lze evidovat **Papírové přihlášky a souhlasy**. Zapsání a přijetí podkladu jsou oddělené kroky. Neznámá verze zůstává viditelná; oprava odkazuje na nahrazený záznam. Soubor je soukromý, kontroluje se otisk a jeho čtení se audituje.
3. Změna členství probíhá v dosavadní kartě osoby s důvodem a kontrolou revize. Papír, zaplacená objednávka ani kontaktní e-mail ji nenahrazují. Stálé VS, existující závazky a trvalé soupisky se nepřepisují.
4. Vedoucí sportu ve **Svěřencích trenérů** výslovně přiřadí rozsah a dobu platnosti. Nové tabulky jsou bez automaticky odhadnutých grantů. Historická účast na tréninku není oprávněním k celé osobě.
5. V **Případech a odpovědnostech** obsluha zvolí vlastníka, další krok a datum. Uzavření se odmítne, dokud se nevyřeší zdrojová agenda. Souběžně otevřený starý formulář nepřepíše novější rozhodnutí. Historie uzavřených případů zůstává dostupná.
6. Fronta e-mailů umožňuje výslovně doložit osobní kontakt nebo ověření nejistého doručení. Uchová skutečný stav transportu i rozhodnutí člověka; další odesílání se neaktivuje samo.

Přehled případů čte identity, rodinné žádosti, papírové podklady a selhané fronty podle pozice. Nevytváří druhou evidenci osob ani účetní knihu. Nedostupný zdroj se označí jako nedostupný, nikoli jako nulový počet problémů. Zobrazení je stránkované.

## Oprávnění a hranice

| Kontext | Povolený rozsah | Důležitá hranice |
|---|---|---|
| Ověřený rodič / vlastní profil | Právě platná schválená vazba k osobě. | Kontaktní e-mail ani shodné příjmení nestačí. Cizí děti, papírové interní podklady a pracovní agendy nejsou dostupné. |
| Samostatný sportovní účet | Dosavadní omezený přístup vlastní osoby. | Odvolání jednoho rodiče nezruší jiný legitimní účet sportovce. |
| Trenér | Výslovně svěřené osoby a povolené vlastní sportovní pracovní postupy. | Žádné vlastní udělení rozsahu; žádné rodinné/sportovní oprávnění odvozené z historického tréninku; bez finančních odměn ve služebním profilu. |
| Vedoucí sportu | Celoklubové sportovní sestavy a správa svěření trenérům. | Nejde o automatický finanční nebo registrářský kontext. |
| Registrář | Identity, členství, rodinné vazby, papírové podklady a související případy. | Zápis papíru nezaloží ověření e-mailu, online souhlas ani platbu. |
| Katalog / sklad | Dosavadní katalogové a skladové agendy. | Znalost URL nezpřístupní přílohy registráře nebo finanční zápisy. |
| Objednávky | Dosavadní objednávky, zákaznické zprávy a případy doručování. | Uzavření úkolu není potvrzení platby. |
| Hospodář | Dosavadní peněžní agendy a vlastní připomínky plateb. | Uzavřenou finanční evidenci nadále chrání B1. |
| Správce systému | Technický dohled a registr provozních úloh. | Přehled sám nezapíná plánovače, platební brány ani automatické potvrzování. |

Odchod pracovníka ruší služební přístup v již existující session. Jeho nezávislé a stále platné rodičovské oprávnění zůstává zachované. Pracovní pozice se neslévají do neomezeného přístupu.

## Schéma, záloha a lokální prostředí

Pět nových migrací: `20261022210000_person_identity_reviews`, `20261022211000_paper_registrations`, `20261022212000_operational_delivery`, `20261022213000_operational_cases`, `20261022214000_coach_scope`. Uplatněny byly pouze na syntetické instanci `127.0.0.1:33426`; katalog má 117 ověřených migrací.

Ownership kontrakt zálohy `2026-10-22.2` zahrnuje všech 11 nových tabulek: identity a jejich události, papírové podklady a události, kontext rezervací, běhy úloh, otisky oprávnění zpráv, provozní případy a události, trenérské rozsahy a události. Papírové přílohy používají existující soukromý adresář a kategorii `paper-documents`.

HTTP přejímka běží na `http://127.0.0.1:18086`, v DB `evidence_b1`, s daty `LOCALHOST NEPLATIT` a adresami `example.invalid`. Test souběhu zpráv má oddělené schéma `evidence_b2_notifications` na téže izolované instanci. Zkouška obnovy vytváří nový náhodně pojmenovaný `evidence_b2_restore_*`, jehož uživatel nemá právo měnit zdrojovou DB. Soubory a výsledky jsou pod `%LOCALAPPDATA%\EvidencePavelB1\finance-b1-20261022`.

Záloha syntetické DB byla skutečně obnovena: **254 tabulek, 2 triggery, všech 11 nových tabulek**, počty souhlasí s manifestem a ověřeným SHA-256. Odkazy obnovených papírových podkladů mají dostupné soubory se správným otiskem. Tato kontrola **nebyla obnovou soukromého adresáře ze samostatné archivní kopie** a nenahrazuje provozní nácvik úplné obnovy R42.

## Ověření

**PASS: 1 294 testů / 27 798 kontrol, 110 skutečných HTTP scénářů, dva souběžné testy (7 + 9 kontrol), obnova 254 tabulek.** [Úplný protokol a strojově čitelné výsledky](verification/r07-r12/RESULTS.md). Opakovatelné vstupy:

- `php vendor/phpunit/phpunit/phpunit` — společná regresní sada včetně skutečného běhu migračního katalogu na testovacím schématu.
- `php tests/Support/ClubFoundationHttpAcceptance.php` — skutečné lokální formuláře, soubory, CSRF, role, konflikty, rodiče a odvolání.
- `php tests/Support/OperationalDeliveryMariaDbRace.php` — dva skutečné procesy, jediný claim a jediný lokální outbox, nejisté doručení.
- `php tests/Support/CoachScopeMariaDbRace.php` — skutečný souběh odvolání rozsahu a zápisu, pozorované čekání na zámek, oddělené syntetické schéma `evidence_b2_coach_scope`.
- `php tests/Support/ClubFoundationBackupRestore.php` — obnova poslední lokální zálohy do nového izolovaného schématu.

HTTP a databázové podpůrné testy obsahují kontrolu konkrétní izolované instance a odmítnou jiné prostředí. Nenahrazují účetní ani uživatelskou přejímku oddílem.

## Co musí předcházet případnému nasazení

- Samostatné povolení vlastníka k nasazení. V tomto úkolu se neprovádí push, merge ani deploy.
- Potvrdit skutečné svěření skupin/soupisek trenérům. Migrace je neodhaduje a nepřidělený trenér po zapnutí této verze osoby neuvidí.
- Vyjasnit neověřené historické členství a případy identity skutečnými podklady; tato implementace nepředstírá hotovou migrační mapu R38.
- Ověřit úplnou zálohu DB i soukromých souborů a návratový postup pro skutečné nasazení.
- Samostatně určit režim každé provozní úlohy a příjemce. Technický registr není důkazem existence cron plánu. Zálohovací úloha bez zaznamenaného běhu se hlásí jako nedoložená.
- Produkční doručení a platební integrace zůstávají samostatnými provozními kroky. Volitelný WebPush zachovává dosavadní best-effort režim; trvalé doručovací záruky tohoto balíku se týkají e-mailových front.

Následující realizační balík je **R13–R17: finanční základ, účetní doklady a exporty**. Jednoduché účetnictví, neplátcovství DPH a předávání XLSX + faktur zůstávají platným zadáním; tento balík je nenahrazuje novou účetní politikou.
