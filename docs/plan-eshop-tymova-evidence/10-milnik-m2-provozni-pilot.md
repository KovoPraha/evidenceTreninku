# 10 – Milník M2: provozní pilot na localhostu

Stav: kanonický pracovní plán druhého produktového milníku

Aktualizováno: 4. 8. 2026

Prostředí: pouze localhost a syntetická data; produkce beze změny

## Výsledek milníku

M2 má převést integrovaný prototyp M1 do podoby, na které lze zkoušet běžný
provoz klubu bez ručních zásahů do databáze. Na konci M2 má vlastník umět projít
celou cestu rodiče, sportovce, trenéra a správce, zaznamenat připomínku a u každé
změny dohledat její původ.

M2 není produkční cutover. Starý KIS a Shoptet se nevypínají, automatické
finanční operace se nezapínají a ostrá data se neimportují.

## Aktuální stav M2

Procenta vyjadřují splněná akceptační kritéria daného řezu, nikoliv množství
kódu.

| Řez | Stav | Odhad | Hlavní výstup / mezera |
|---|---|---:|---|
| M2.0 vlastníkova prohlídka M1 | čeká | 0 % | projít A01–A10 a sepsat chyby, UX připomínky a nové požadavky |
| M2.1 provoz klubové akce | hotovo lokálně | 100 % | auditovaný CSV export účastníků `m2.event-participants.v1` |
| M2.2 opravy a UX z prohlídky | čeká na M2.0 | 0 % | nejprve chyby, potom texty a zjednodušení obrazovek |
| M2.3 zkouška migrace KIS | základ existuje | 45 % | parser, bezpečný matcher a parity kontrakt existují; chybí finální exportní kontrakt, raw archiv, promote/rollback a úplný paritní report |
| M2.4 provozní e-shop | základ existuje | 70 % | objednávka a služby fungují; chybí finální detail/obrázky, pravidlo slev pro služby a vlastníkova provozní zkouška |
| M2.5 přístup a obnova účtu | částečně | 35 % | bezpečné session/tokeny a admin reset existují; chybí samoobslužný reset a dokončení permission cache |
| M2.6 integrovaná akceptace | čeká | 0 % | opakovatelný browser průchod, backup a společná závěrečná brána |

Orientační stav celého M2: **30 %**. Nezapočítává produkční deploy ani ostrou
migraci, které mají vlastní pozdější bránu.

## Implementační pořadí

### M2.0 – zpětná vazba vlastníka

1. Na `http://localhost/evidencePavel/testovaci_scenare.php` projít A01–A10.
2. U každého problému uvést scénář, roli, očekávání a skutečný výsledek.
3. Roztřídit výsledek na chybu M1, UX úpravu nebo nový požadavek M2.
4. Chyby bezpečnosti, oprávnění, peněz nebo kapacity mají přednost před další
   funkcí.

Brána: každá připomínka má vlastníka, prioritu a cílový řez M2.

### M2.1 – provoz klubové akce

Dokončeno v `1b4d9e1`:

- admin-only export přes POST + CSRF,
- stabilní kontrakt `m2.event-participants.v1`,
- oddělení dat jednotlivých akcí,
- stav potvrzeno / čeká na platbu / čekací listina / zrušeno,
- neutralizace tabulkových vzorců,
- audit počtu řádků a rozpadu stavů bez ukládání exportovaných osobních údajů.

Zbývající produktová kontrola: vlastník otevře CSV v běžném tabulkovém programu
a potvrdí, zda sloupce odpovídají práci trenéra.

### M2.2 – chyby a UX z A01–A10

Každá oprava je malý samostatný řez s reprodukcí, regresním testem a browser
důkazem. Tento řez nesmí současně měnit finanční nebo migrační pravidla.

Brána: žádná otevřená HIGH chyba; MEDIUM chyby mají rozhodnutý termín nebo
výslovné přijetí rizika; všechny opravy jsou znovu prokliknuté na localhostu.

### M2.3 – bezpečná zkouška jednorázové migrace KIS

1. Získat anonymizovaný vzorek přesně stejného formátu jako budoucí finální
   export KIS a určit stabilní externí ID osoby.
2. Uložit raw vstup jako neměnný artefakt s hashem, časem a verzí kontraktu.
3. Dry-run musí pro každý řádek vrátit `create`, `exact_match`, `conflict`,
   `ambiguous`, `invalid` nebo `missing_without_archive` a vysvětlit důvod.
4. Slabá shoda jen podle jména nebo e-mailu se nikdy automaticky nepotvrdí.
5. Připravit explicitní promote s fingerprintem preview, transakcí, auditem a
   idempotencí; v M2 pouze nad izolovanou testovací DB.
6. Připravit kompenzační rollback/obnovu a paritní report osob, členství,
   soupisek a platebních předpisů.

Brána: 100 % řádků má vysvětlený výsledek, konflikty se nepřepisují, opakovaný
dry-run je stejný a žádný testovací běh nezmění produkci.

### M2.4 – provozní zkouška e-shopu

1. Doplnit srozumitelný detail produktu a bezpečnou práci s obrázky.
2. Rozhodnout a otestovat, zda se kupón smí použít na zboží, kroužek, událost a
   velodrom; výchozí stav pro nejasnou službu je zamítnutí.
3. Projít zboží i každou službu přes košík, neměnný cenový snapshot, QR,
   ruční testovací úhradu, výdej/aktivaci, storno, expiraci a refundaci.
4. Ověřit přehled objednávek rodiče i správce a srozumitelné chybové stavy.

Brána: žádná dvojitá objednávka, záporný sklad ani překročená kapacita; storno a
expirace vracejí zdroje právě jednou; každý peněžní stav je auditovatelný.

### M2.5 – přístup a obnova účtu

1. Doplnit samoobslužnou žádost o reset bez enumerace účtů.
2. Použít hashovaný, jednorázový a expirovaný token; po změně hesla revokovat
   staré relace.
3. Dokončit permission cache tak, aby revokace role nebo vazby platila ihned.
4. Otestovat rodiče s více dětmi, samostatný dětský účet, cizí osobu, zrušenou
   vazbu a souběžné použití tokenu.

Brána: IDOR a tokenové regresní testy jsou zelené a žádná odpověď neprozradí,
zda účet existuje.

### M2.6 – integrovaná akceptace

- deterministický seed lze bezpečně spustit opakovaně,
- migrace jsou aktuální a idempotentní na podporovaných DB,
- plná testovací sada, first-party PHP lint a dependency audit jsou zelené,
- A01–A10 a nové scénáře M2 projdou v prohlížeči bez ručního SQL,
- vznikne čerstvá localhost záloha a ověření její struktury,
- `SESSION_HANDOFF.md` obsahuje přesný aktuální stav a další jedinou akci.

## Co lze dělat souběžně

- vlastník může procházet A01–A10, zatímco probíhá read-only externí revize,
- po revizi lze samostatně připravovat KIS kontrakt a návrh produktového detailu,
- implementace M2.3, M2.4 a M2.5 se může dělit pouze při jasném vlastnictví
  souborů a migrací; sdílený `includes/shop_checkout.php`, auth bootstrap,
  migrační katalog a plánové dokumenty mají vždy jednoho editora.

## Výslovně blokované oblasti

- reward/cash wallet do potvrzení D-009 a účetních pravidel,
- automatické potvrzení Fio a Stripe do samostatné finanční akceptace,
- TrainingPeaks do potvrzení integračního rozsahu a vlastnictví dat,
- ostrý import a vypnutí KIS/Shoptetu do samostatného cutover plánu,
- produkční deploy nebo produkční DB změna bez výslovného souhlasu vlastníka.

## Brána celého M2

- vlastník prošel A01–A10 a připomínky jsou vypořádané nebo zařazené,
- trenér umí připravit akci, pracovat s účastníky a bezpečně je exportovat,
- rodič projde kroužek, placenou událost, objednávku a storno bez zásahu do DB,
- KIS migrační dry-run vysvětlí každý řádek a nemění produkční data,
- přihlášení, obnova účtu a oprávnění mají automatické IDOR/regresní testy,
- full test, lint, migration check, seed, backup a browser průchod jsou zelené.
