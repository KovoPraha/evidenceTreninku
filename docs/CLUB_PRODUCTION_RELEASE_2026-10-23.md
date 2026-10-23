# Produkční vydání společného systému a browser přejímka

23. 10. 2026 uživatel výslovně požádal: „nasadíme to na produkci a otestujeme vše
z prohlížeče“. Tím je nově autorizované nasazení připraveného kódu a přiměřená
produkční přejímka. Dřívější zákaz nasazení pro tento konkrétní balík již neplatí.
Nejde o potvrzení skutečných částek, rolí osob, účetního převzetí ani odstavení
Shoptetu/původní Evidence. Staré služby ani externí integrace se automaticky nezapínají
nebo nevypínají.

## Kandidát a dosavadní produkce

- Vývoj a UAT balíček: `2158b52b`; aplikační základ `abbb4be9`.
- Aktuální `origin/main` před přípravou: `3fdd1e16` (produkční dokumentace).
- Sloučení aktuálního main do release větve: `7e52e917`, konflikty pouze v předáních;
  oba historické záznamy byly zachované.
- Přes produkční browser ověřeno dosavadní nasazení `cc9c9280f137465eb59f20523d827b5502416e20`,
  datum 22. 10. 2026; přihlášený vlastník má osm pracovních pozic.
- Nový kandidát zatím obsahuje 24 aditivních migrací a rozšíření společné aplikace
  R01–R44. Přímý import skutečných osob, financí či skladu není součástí deploye.

## Ochrana ostré evidence při testu

Účetní a skladové exporty nemají testový datový prostor ani filtr pro TEST účty,
řady, varianty a objednávky. `includes/accounting_export.php`,
`includes/accounting_stock_export.php` a `includes/accounting_club_export.php`
zahrnují tyto záznamy do běžných podkladů. `UNIQUE(kind,series_year)` u dokladových
řad neumožňuje paralelní testovací řadu téhož druhu/roku. Skladová politika je
globální. Samotný prefix TEST tedy není izolací.

Na produkci se provede čtení přehledů, vyhledávání, přepínání existujících rolí,
náhledů, ochrany přístupu a případně stažení existujícího dokumentu s pravdivým
auditem stažení. Žádné fiktivní hotovosti, faktury, inventury, vratky, příspěvky
ani odměny. Zápisové finanční scénáře zůstávají v izolované B1. Vytvoření nového
pracovního exportu by bylo samostatným pravdivým záznamem, nikoli účetním převzetím.

## Kontrola dopadu před aktivací

Nové ochrany mohou změnit dosavadní provoz:

- Trenér bez výslovného platného rozsahu neuvidí osoby; granty se z historie neodvozují.
- Zboží bez doloženého skladu nelze automaticky pokládat za dostupné; zejména NULL
  historická zásoba nově blokuje nákup.
- Měsíční členské předpisy vyžadují výslovnou dohodu a rozhodnutí o částce.
- Dřívější čekající souhrny bez autorizačního snímku nový worker zadrží.
- `/reports/*` je nově neveřejné; nové soukromé PDF potřebuje dostupné úložiště.

`bin/club-release-impact.php` proto před aktivací pouze čte agregované počty
v konzistentní READ ONLY transakci; nepouští aplikační bootstrap, migrace ani
opravy. Chybějící schema není vydáváno za nulu. Výstup není schválení nasazení.
Spouští se chráněným `production-drills.yml`, volba
`overit-dopad-kluboveho-vydani`, potvrzení `PROVEST`; bootstrap navíc potvrzuje
`OVERIT-DOPAD-VYDANI` a pevný cíl `kis.kovopraha.cz`.

Nové migrace nemají down; návrat samotného starého kódu není důkaz obnovy databáze.
Standardní deploy musí úspěšně projít zálohou, migracemi, zachováním VS a HTTP kontrolou.

## Stav

Příprava release a čtecího dopadu. Nasazení ani úplná produkční přejímka zatím
nejsou označené za dokončené. Starý produkční UAT přehled je BLOKOVÁNO: mimo jiné
chybí aktivní dětské testovací přístupy, potvrzené testovací okno/inbox a bankovní
kontrolor. Tato zjištění neopravujeme vymyšlenými souhlasy nebo aktivací Stripe.

## Ověření před prvním nasazením

- PR #167 sloučen jako `57c9e65ad1c00feb06916d7bdbb8fece058cd316`.
- CI [39919053665](https://github.com/KovoPraha/evidenceTreninku/actions/runs/39919053665): PHP testy, MariaDB 10.3/11.4 a obnova všech 281 tabulek čisté testovací databáze PASS.
- Produkční čtecí dopad [39919279588](https://github.com/KovoPraha/evidenceTreninku/actions/runs/39919279588): 36 aktivních trenérů bez nových grantů; 799 publikovaných variant bez nových skladových zůstatků, z toho žádná s NULL původní zásobou; jeden současný plán členských plateb bez nové dohody/rozhodnutí; žádný čekající rodinný souhrn.
- Soukromé úložiště nebylo v CLI prokázané. První verze diagnostiky při chybějícím nastavení nesprávně odvozovala lokální fallback i pro produkci; aktuální verze rozlišuje explicitní nastavení a lokální fallback. Původní výsledek sám nedokládá webovou dostupnost.

Nasazení nyní po ověřené DB záloze a před kopírováním konfigurace do release připraví úložiště v soukromém `data/kis-private`. Existující povolené nastavení zachová; neznámou cestu, symlink nebo prázdnou konstantu odmítne. Při doplnění konfigurace nejprve ověří její soukromou zálohu. Nemění DB, role, přílohy ani historická data. CLI ověřuje vlastní dočasný zápis/čtení/smazání; kvůli odlišnému webovému open_basedir se webová dostupnost ověří zvlášť v administraci.
