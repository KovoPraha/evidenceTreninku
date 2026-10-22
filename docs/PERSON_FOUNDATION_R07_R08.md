# R07–R08: identita a oddělené stavy osoby

Lokální implementace 22. 10. 2026; žádný import reálných osob ani produkční zápis.

## Kontrakt identity

- Jediná osoba je `sportovci.id`. Přihlašovací účet, kontakt, UCI, jméno a VS nejsou náhradou tohoto klíče.
- Stávající schvalování přihlášek a ruční založení osoby znovu používají `person-match-v1`. Přesná shoda je kandidát pro výslovné rozhodnutí registráře, nikoli povolení automatického sloučení.
- Nový veřejný sportovní profil také prochází sdílenou kontrolou. Nalezený kandidát vrátí obecné upozornění s cestou k registráři / žádosti „Moje osoby“, bez zveřejnění cizích údajů. Nevznikne druhá osoba ani automatické propojení účtu. Registrace pouhého zákaznického účtu bez sportovního profilu zůstává oddělená.
- Datum `1900-01-01` je ve vyhledání osoby chybějící důkaz. Stejné jméno s neznámým datem na libovolné straně vytváří upozornění na podobnost; ne přesnou shodu. Původní archivní datum se tím nepřepisuje.
- Původní zdrojové vazby `kis_person_aliases` zůstávají beze změny. Nový aditivní registr `person_identity_reviews` zachycuje zdrojový jmenný prostor, stabilní zdrojové ID, otisk podkladu, kandidáty, stav ověření, cílovou osobu a autora rozhodnutí. Nová tabulka nezavádí další osobu ani účet.
- `review` a `deferred` nemají cílovou osobu; `verified` ji vyžaduje; `excluded` znamená výslovné ponechání bez převodu. Výsledek nepřidává oprávnění, soupisku, členství ani platbu.
- Shodný opakovaný podklad vrátí stejný případ. Stejné zdrojové ID s jiným podkladem se nepřepíše. Rozhodnutí vyžaduje důvod a označení důkazu; revize a řádkový zámek brání přepsání cizího souběžného rozhodnutí. Chyba auditu vrátí celou transakci.
- Dosavadní importní alias nelze touto administrací přesměrovat. Rozpor zůstane odložený; případné sloučení nebo historický převod patří do samostatně posouzené migrace.
- Původní a následné rozhodnutí uchovává `person_identity_review_events`. Obsluha pracuje na `identity_reviews_admin.php`; přístup vyžaduje správu osob a pracovní oprávnění registráře podle společné matice.

## Autorita údajů a význam stavů

| Údaj / oblast | Autorita a chování |
|---|---|
| Identita | Stabilní interní ID; změny jména či narození jsou úpravy existující osoby. Neznámé datum se neodhaduje. |
| Současné kontakty | Aktuální výslovná editace a její historie; archiv se používá jako podklad, automaticky nepřepisuje novější kontakty. |
| Zdrojová identita | Původní importní alias + explicitně ověřená mapa, s dohledatelným důkazem. |
| Členství | Evidovaný `stav_clenstvi`. Importované platby, dluh a text starých skupin jej již neodvozují ani nemění. Nedoložený ruční stav je označen „k ověření“. |
| Změna členství | Současné výslovné rozhodnutí registráře s důvodem a auditem; kontrola starého formuláře. Předání k ověření zachová evidovaný stav a odebere jeho ruční potvrzení. |
| Účet a zastupování | Aktivní ověřený účet + schválený vztah `self/guardian`, jehož interval právě platí. Kontaktní e-mail není zastupování. Konec vztahu je výlučný okamžik. |
| Soupiska | Stávající datovaná vazba osoby na tým. Platnost koncového dne je včetně; trvalé vazby mají konec `NULL`. Změna členství vazbu tiše nemaže. |
| Účast v programu | Stávající přihláška a účast se svým stavem a daty; není odvozena ze stavu účtu ani samotného nákupu. |
| Úhrada | Stav jednotlivé povinnosti/platby; není důkaz přijetí do oddílu. Absence předpisů nepotvrzuje bezdlužnost. |
| Klubová cena | Zachována dosavadní pravidla soupisek. Doplněna kontrola aktivního ověřeného účtu a přesného času ukončení zastupování; odvolané oprávnění nedává slevu do konce dne. |

`personStateSummary()` je společný čtecí model pro oprávněnou obsluhu. Obsahuje oddělené členství, přístupy, aktuální soupisky, programové účasti a předpisy. Není novým mechanismem přístupu nebo rozhodování o ceně. `personStateSummaryHtml()` je určen jen pracovníkům, ne veřejnému účtu: veřejný účet nesmí dostat údaje o všech dalších rodinných účtech.

## Ověření

`PersonFoundationTest` ověřuje opakovaný podklad, změnu důkazu, podobnost s chybějícím datem, odložení, výslovné potvrzení, revizi, chybějící osobu/důvod, konflikt s importním aliasem, vrácení při selhání auditu, oddělené členství/přístupy/platby a zachování stálého VS a trvalé soupisky.

Rozšířený `ShopMemberPricingTest` pokrývá deaktivovaný/neověřený účet a skončený vztah ve stejný den. Stávající testy rodinného a dětského přístupu zůstávají součástí přejímky. MariaDB a HTTP výsledek eviduje společný report balíku; SQLite test není vydáván za důkaz produkčního nasazení.

Převod celé historie, hromadné slučování, změna pravidel příspěvků a automatická aktivace importů nejsou součástí tohoto balíku. Výslovná změna členského stavu neruší existující obchodní závazky ani automaticky neukončuje jiné životní cykly.
