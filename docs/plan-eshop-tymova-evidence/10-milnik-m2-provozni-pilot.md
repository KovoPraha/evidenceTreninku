# 10 – Milník M2: provozní pilot na localhostu

Stav: kanonický plán druhého produktového milníku

Zahájeno: 4. 8. 2026

Prostředí: localhost a syntetická data; produkce beze změny

## Cíl

M2 převádí integrovaný prototyp M1 do podoby, na které lze zkoušet skutečnou
každodenní práci trenéra, rodiče a administrátora. Neprovádí ostrý KIS/Shoptet
cutover a nezapíná automatické finanční operace.

## Pořadí přírůstků

1. **M2.1 Provoz klubové akce** – auditovaný export účastníků včetně čekajících,
   čekajících na platbu a zrušených; ochrana CSV proti tabulkovým vzorcům.
2. **M2.2 Připomínky z A01–A10** – opravit chyby M1 a sjednotit texty/UX podle
   vlastníkova průchodu.
3. **M2.3 KIS migrační zkouška** – finální datový kontrakt, raw archiv, preview,
   konflikty a paritní report nad anonymizovanými daty; bez ostrého promote.
4. **M2.4 Shop provozní zkouška** – pravidla slev pro služby, úplný detail
   produktu a provozní kontrola objednávky až po výdej/storno.
5. **M2.5 Přístup a obnova** – samoobslužný bezpečný reset hesla sportovce a
   dokončení permission cache bez produkční aktivace.

## Výslovně blokované oblasti

- reward/cash wallet do potvrzení D-009 a účetních pravidel,
- automatické potvrzení Fio a Stripe do samostatné finanční akceptace,
- TrainingPeaks do potvrzení integračního rozsahu a vlastnictví dat,
- ostrý import a vypnutí KIS/Shoptetu do samostatného cutover plánu,
- produkční deploy bez výslovného souhlasu vlastníka.

## Brána M2

- vlastník prošel A01–A10 a připomínky jsou vypořádané nebo zařazené,
- trenér umí připravit akci, pracovat s účastníky a bezpečně je exportovat,
- rodič projde kroužek, placenou událost, objednávku a storno bez zásahu do DB,
- KIS migrační dry-run vysvětlí každý řádek a nemění produkční data,
- přihlášení, obnova účtu a oprávnění mají automatické IDOR/regresní testy,
- full test, lint, migration check, seed a localhost browser průchod jsou zelené.

## M2.1 – akceptace exportu účastníků

- export je dostupný pouze administrátorovi a pouze přes POST + CSRF,
- soubor má stabilní kontrakt `m2.event-participants.v1`,
- obsahuje stav přihlášky a provozně nutné údaje, nikoliv hesla nebo celé texty
  souhlasů,
- data jedné akce se nesmějí smíchat s jinou akcí,
- buňky začínající jako tabulkový vzorec jsou neutralizované,
- každý export zapíše počet řádků a rozpad stavů do auditu.
