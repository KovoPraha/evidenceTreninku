# Autonomní funkční ověření — 2. 11. 2026

Následné autorizované [sloučení PR #272 a nasazení](../functional-release-20261102/RESULTS.md) mají samostatný protokol.
Níže je zachován rozsah původního testovacího běhu před nasazením.

Chrome, Europe/Prague; časové body nálezů jsou uvedeny níže.
Výchozí main `e916f0861f3994891dbc7e5e5ba9fa7cfe285187`, větev
`codex/functional-uat-20261102`. Vlastník povolil další testování, opravy a testy;
změnu hesla a T07 výslovně odložil. Žádné nové nasazení.

**36 PASS a 3 FIXED** v [podrobných pozorováních](evidence.json).
PASS znamená jen uvedenou konkrétní kontrolu, nikoli přejímku celé aplikace.
Opravy byly ověřeny místně; produkční přihlášené ověření těchto oprav neproběhlo.

## Prostředí a hranice

Produkční aplikace měla při vstupu odhlášenou relaci. Proběhly pouze veřejné
čtecí průchody; žádná nová žádost o obnovu, přihláška, objednávka či rezervace.
Heslo se nečte, neobnovuje ani nemění. Schránka ani původní uživatelské karty
se v tomto běhu nepoužívaly.

Zápisové průchody proběhly výhradně na vlastní místní instanci
`127.0.0.1:18419`, čerstvé MariaDB 10.4.32 na portu 3319. Vstupem bylo pouze
verzované prázdné schéma, migrace a syntetický demo seed. Žádný produkční
config, dump ani skutečné osoby. Všechny externí integrace false, funkce mail
a cURL transporty na webovém serveru vypnuté; session a soukromé soubory mimo
webroot. Původní pracovní kopie v htdocs a její WIP nedotčeny.

Místní seed má již vytvořené rodinné, sportovní a pracovní účty. Nevznikl nový
účet ani nové přidělení práv. Demo správce má existující pracovní pozice;
jejich přepnutí není testem omezeného trenéra. Trenérský kontext má **0 grantů**
a plán správně odmítl. Zápis z plánu proto ověřen ve stávající pozici vedoucího
sportu; žádný grant se nepřidal. Produkční trenérské zápisy se neopakovaly.

## Opravené nálezy

### F01 — P2: zakryté odkazy účtového menu

Pozorováno 00:24:30; `/booking/moje_objednavky.php`, desktop 1536 px.
Reprodukce: rodič otevře stránku e-shopu a rozbalí Můj účet. Lišta kategorií
se z-index 1010 překryje Profil a Moje osoby; jejich prostřední bod zasáhne
`app-shop-nav-inner`, nikoli odkaz. Kliknutí tak neotevře požadovanou stránku.
[Před opravou](account-menu-before.jpg).

Horní veřejná navigace nyní vytváří vlastní vrstvu 1020. Všech pět odkazů
prošlo skutečným hit-testem a Profil/Moje osoby se otevřely přes UI.
[Po opravě](account-menu-after.jpg), [mobil 390 × 844](account-menu-mobile-after.jpg).
Rozšířena browser fixture o skutečnou lištu kategorií a kontrola o hit-testy.
Samotný kompletní pomocný skript UiShellBrowserCheck se zde nespouštěl;
jeho nová geometrická kontrola byla provedena přes podporovaný browser nástroj
na skutečných stránkách aplikace, včetně mobilu.

### F02 — P3: chybějící přístupné názvy dynamických polí měření

Pozorováno 00:32:10; `/formular.php?plan_id=1`, po výběru typu Kolo.
Viditelné popisky nebyly propojené s nově vytvořenými poli; například
Vzdálenost a Poznámka neměly programový název. Globální doplnění názvů při
DOMContentLoaded již u později vložených polí neproběhne.
[Původní atributy](measurement-accessibility-before.json).

Doplněny explicitní názvy v zadání i editaci, včetně nového měřicího řádku
editace. Browser ověřil všech šest typů v obou formulářích.
[Matice po opravě](measurement-accessibility-after.json).
Node regrese spouští skutečné renderovací funkce: oba formuláře × šest typů ×
prázdné/neprázdné katalogy, celkem 24 kombinací.

### F03 — P2: skrytá poznámka cyklistického měření při úpravě

Pozorováno 00:35:02; `/edit_trenink.php?id=1`.
Reprodukce: vytvořit Kolo s poznámkou a otevřít úpravu. Hodnota zůstala
předvyplněná, ale její kontejner měl `col-md-3 d-none`; poznámku nešlo upravit.
Nešlo o prokázané smazání uložené poznámky.

Pole se nyní zobrazuje pro Kolo i Běh, stejně jako při zadání.
Poznámka upravena přes UI a její nové znění skutečně potvrzeno v místní DB.
[Opravené pole](cycling-note-after.jpg). Regrese kontroluje i nepřítomnost
skrývajícího kontejneru poznámky u všech typů.

## Pokrytí skutečným prohlížečem

| ID | Prostředí | Pozorovaný výsledek |
|---|---|---|
| P01–P04 | Produkce, anonymní | Rozcestník a katalog; Bidon právě 1 výsledek; Knihy právě 2; vzestupné ceny 250 a 450 Kč se zachováním kategorie. |
| P05–P09 | Produkce, anonymní | Kroužky, rozvrh, klubový kalendář, individuální lekce a velodrom zobrazily skutečný nadpis a obsah bez aplikační chyby. Pouze navigace/render, nikoli dokončená rezervace. |
| L01–L02 | Místní | Nulový výsledek hledání, zrušení filtru, produktový detail, kapacita a termín. |
| L03 | Místní | Přihlášení rodiče vrátilo produkt; veřejná cena 249 Kč, klubová 224,10 Kč. |
| L04–L05 | Místní | Jedna položka pro A, poté samostatná pro B; celkem 448,20 Kč. |
| L06–L07 | Místní | Neexistující kupón odmítnut; existující LOCAL10 správně neplatil na programové položky; košík zachován. Kladné uplatnění na zboží se netvrdí. |
| L08 | Místní | Bez dvou povinných souhlasů zůstalo 0 objednávek a 2 položky. |
| L09 | Místní | Jedna objednávka ID 1 se dvěma položkami za 448,20 Kč, skutečně uložená a zobrazená v seznamu; čeká na platbu, účet LOCALHOST TEST - NEPLATIT. |
| L10–L11 | Místní | Schválené dvě děti a vlastní profil rodiče; telefon syntetického profilu uložen a potvrzen po reloadu. |
| L12–L13 | Místní | Rodinný přehled promítl obě položky objednávky; plán z překrývajících se soupisek jen jednou; odpověď a poznámka k tréninku přetrvaly po reloadu. |
| L14 | Místní | Obě kroužkové přihlášky čekají na platbu, žádná aktivní účast před úhradou. |
| L15 | Místní emulace | 390 × 844; clientWidth=scrollWidth=375 kvůli nativnímu scrollbar, všech pět účtových odkazů dosažitelných; skutečný mobilní proklik na Moje osoby. |
| L16–L18 | Místní | Rodič neotevře pracovní rozcestník; přepnutí existující pracovní pozice funguje; trenér bez grantů výslovně odmítnut. |
| L19–L20 | Místní | Vedoucí sportu otevřel plán s A a dvěma soupiskami; neplatný čas 00:65/missing unit nezaložil trénink ani měření. |
| L21–L22 | Místní | Právě trénink ID 1, původně 1,50 h, A, 500 m/00:45.250, tag, vazba na plán. Úprava náplně a poznámky zachovala počet i měření. |
| L23–L24 | Místní | Fiktivní uzavření vytvořilo verzi 1 s intervalem 00:01–00:02 a syntetickým důvodem. Délka se přepočetla na 0,02 h; sportovní účet vidí potvrzenou vlastní účast a pouze vlastní podíl platby. |
| L25–L27 | Místní | Kalendář listopad → prosinec → listopad; sportovní účet odmítnut na čtyřech rodinných/pracovních cestách při jinak platné relaci; po logoutu chráněn i vlastní přehled. |

[Bezpečný konečný stav místní DB](local-state.json), [celá pozorování](evidence.json).
Toast/HTTP 200 nebyly důkazem uložení: objednávka, trénink, měření a poznámka
byly navíc čtecím dotazem potvrzeny ve vlastní místní databázi.
U nejasné pointer akce se uložení netvrdilo; příslušný stav se dohledal,
ověřilo se klávesové ovládání a teprve potom stav/persistence.

## Automatické kontroly

- Kompletní finální Windows sada: **1902 testů / 38196 assertions**, 3 známé
  POSIX/symlink skipy, exit 0; 3 min 1 s. Není to plný Linux PASS.
- JavaScript: **19/19 PASS**, včetně nové matice dynamických měření.
- Cílená bezpečnost a rodičovský průchod: **16/388 PASS**; UI shell **15/90 PASS**.
- PHPStan level 5, syntaxe změněných PHP, Composer validate, changed-file style
  (5 PHP souborů / 0 chyb), dokumentační odkazy a git diff --check PASS.
- Linux a MariaDB 10.3/11.4 ověření určuje CI přesného finálního commitu v PR.

[Strojový souhrn](validation.json). První úplný běh odhalil dvě slabiny testů:
extrakční regex nepodporoval víceřádkový výraz po nutném zalomení template;
SQLite rodičovská fixture kombinovala UTC paid_at s místním dnešním datem po
půlnoci. Regex nyní přijme zalomení při zachování XSS assertions a fixture
použije skutečně uložený syntetický den platby. Účetní runtime se neměnil.
Finální úplná sada byla po těchto opravách spuštěna znovu a prošla.
Formátování dotčených legacy PHP bylo nutné pro povinnou kontrolu stylu.

## Co se neověřilo a ukončení

Změna hesla a T07 jsou SKIPPED dle vlastníka. Přihlášené produkční funkce,
nové produkční zápisy, reálná platba, skutečné rezervace, importy, rozesílky,
účetní přejímka a úplná matice osmi pracovních pozic neproběhly.
Demo trenérské zápisy bez grantů jsou blokované fixture; odmítnutí je správné,
není důvodem k rozšíření práv. Jednotlivé pixelové/časové prodlevy nástroje
nebyly samy o sobě označeny za chybu aplikace.

Místní účty na konci odhlášeny, ochrana po logoutu ověřena, viewport resetován
a agentovy karty zavřeny. [Vlastní místní servery zastaveny](cleanup.json), oba porty zavřeny;
syntetické diagnostické soubory zůstávají mimo Git a webroot.
Produkční runtime, konfigurace, účty a dosavadní pilotní trénink 1604 se neměnily.
