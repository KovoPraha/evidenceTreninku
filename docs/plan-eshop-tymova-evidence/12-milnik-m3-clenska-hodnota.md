# 12 – Milník M3: každodenní hodnota pro členy a klub

Stav: kanonický pracovní plán třetího produktového milníku

Aktualizováno: 5. 8. 2026

Prostředí: implementace a ověřování na localhostu; produkce beze změny

## Výsledek milníku

M3 má z již propojených dat Evidence, e-shopu a KIS vytvořit jednoduché přehledy,
ke kterým se rodič, sportovec a správce pravidelně vrací. Nezavádí nový zdroj
identity ani paralelní kalendářovou, finanční nebo autorizační logiku.

M3 může technicky začít před vlastníkovou prohlídkou M2, ale jeho produkční brána
zůstává uzavřená, dokud nejsou A01–A10 vypořádané. Připomínka z M2 má přednost
před dalším rozšiřováním M3.

## Pořadí řezů

| Řez | Stav | Odhad | Výsledek / brána |
|---|---|---:|---|
| M3.0 převzetí M2 | čeká na vlastníka | 0 % | PASS A01–A10, nulové blokátory a vypořádané důležité připomínky |
| M3.1 rodinný program | technicky hotovo | 100 % | read-only 30denní přehled tréninků, přihlášených akcí, rezervací a splatností nad stejným oprávněním jako rodinný ICS feed |
| M3.2 týdenní souhrn | technicky hotovo | 100 % | náhled, výchozí opt-out, dobrovolný opt-in, odhlášení jedním krokem, idempotentní fronta, audit a localhost-only outbox jsou ověřené; produkční transport zůstává samostatně blokovaný |
| M3.3 roční přehled plateb | probíhá | 70 % | přihlášený read-only přehled skutečně uhrazených členských předpisů a e-shopových položek je hotový; zbývá vlastníkovo ověření obsahu a rozhodnutí, zda vůbec vznikne schválený export |
| M3.4 provozní přehled správce | technicky hotovo | 100 % | read-only akční seznam čekajících plateb, kapacit, přihlášek a výjimek s odkazy do existujících auditovaných obrazovek |
| M3.5 datová kvalita sportovního progresu | návrh | 0 % | nejdřív normalizace měření, soukromí nezletilých a vysvětlení kvality; žádné zdravotní predikce |

Orientační technický stav M3: **55 %**. Procento nezahrnuje produkční aktivaci ani vlastníkovo přijetí.

## M3.1 – rodinný program

První řez je záměrně read-only. `familyCalendarAgenda()` volá kanonický
`familyCalendarItems()`, takže znovu používá aktivní účet, schválené vazby osob,
stav soupisek a stejnou izolaci rodin jako soukromý ICS feed. Webová stránka
nepřijímá `sportovec_id` ani jiný výběr osoby z URL.

Akceptační kritéria:

- rodič vidí v jednom časově seřazeném seznamu nejbližších 30 dní,
- zobrazují se jen plánované tréninky aktivních soupisek, relevantní přihlášky,
  aktivní rezervace a neuhrazené předpisy,
- u každé položky je české datum, čas nebo „celý den“, typ a dostupný kontext,
- odvolaná vazba osoby ji okamžitě odstraní stejně jako z rodinného kalendáře,
- prázdný stav je normální a nevydává se za chybu,
- funkce nic nezapisuje, nevytváří token a neodesílá oznámení.

## M3.2 – týdenní souhrn

Dokončený první řez (`82d41ac`) skládá prostý text pouze z kanonické sedmidenní
rodinné agendy. Přihlášený účet může bezpečně listovat po týdnech; datum je
striktní a omezené na 90 dní zpět až 370 dní dopředu. Náhled nepřijímá ID osoby,
neobsahuje HTML, nevytváří frontu a žádný transport se z webu nevolá.

Browser ověřil prázdný aktuální týden a týden 12.–18. 8. 2026 s jednou akcí a
jednou splatností. Cizí osoba se nezobrazila a stránka výslovně říká, že nic
neodesílá.

Druhý řez dokončuje výchozí opt-out, dobrovolné zapnutí a odhlášení jedním krokem.
Preference, idempotentní snapshot fronta a audit mají vlastní migraci. Pro jeden
účet a počátek týdne může vzniknout právě jedna zpráva; před převzetím se znovu
ověří aktivní ověřený účet a zapnutý odběr. Vypnutí zruší čekající, rozpracované
i dříve selhané neodeslané zprávy.

Administrátor může na localhostu ručně připravit týden a uložit jednu zprávu do
chráněného souborového outboxu. Produkční host lokální transport odmítne a žádný
skutečný e-mailový transport není implementovaný. Browser ověřil celý tok
zapnout → připravit právě jednu zprávu → uložit do outboxu → vypnout, nulovou
frontu po zpracování a nulové chyby konzole. Provozní postup je v
`docs/family-weekly-summaries.md`.

## M3.3 – roční přehled uhrazených služeb

První řez (`63c8ec1`) je pouze přihlášený a read-only. Rok se validuje od 2000
po právě probíhající rok a výběr osoby nepřijímá z URL. Oprávněné profily se při
každém načtení znovu odvozují z aktivního ověřeného účtu a schválených vazeb
`self`/`guardian`; revokace vazby proto řádky okamžitě odebere.

Přehled zahrnuje jen členské předpisy i jejich platební záznam ve stavu `paid`
a e-shopové položky objednávek i plateb ve stavu `paid`, vždy podle skutečného
`paid_at` ve vybraném roce. Čekající, zrušené, vratkové a cizí řádky vynechává.
Členské předpisy a e-shop mají oddělené tabulky i součty po měnách, aby se při
přechodu KIS/e-shop jedna služba skrytě nesečetla dvakrát.

Stránka výslovně uvádí, že jde o informační přehled, nikoli účetní nebo daňový
doklad. Nevytváří PDF, potvrzení ani export. Browser na localhostu ověřil rok
2026 se skutečně zaplacenou e-shopovou položkou 1 530 CZK a vynechaným čekajícím
členským předpisem, prázdný rok 2025 i odmítnutí budoucího roku 2027.

## M3.4 – provozní přehled správce

Commit `12c2300` přidává pouze administrátorskou read-only obrazovku
`provozni_prehled_admin.php`. Skládá akční signály z existujících kanonických stavů:
platby po splatnosti, vratky, expirace objednávek, návrhy Fio, naplněné kapacity,
čekací listiny, čekající propojení osoby, selhané notifikace a konflikty posledního
KIS importu. Chybějící zdroj je označen jako nedostupný a není vydáván za nulu.

Přehled sám nic nemění. Každá položka vede do existující obrazovky, která si dál
vynucuje vlastní oprávnění, potvrzení, CSRF a audit. Browser ověřil přihlášení
syntetického administrátora, dostupnost stránky, čtyři sekce, nulový stav aktuálních
lokálních dat a žádnou chybu konzole.

## Brány a výslovně odložené oblasti

- Stripe, automatické Fio párování, Packeta a skutečné e-mailové transporty
  vyžadují samostatnou autorizaci, testovací účty a provozní postup.
- Wallet, dobíjení peněžního kreditu a směna tréninkové odměny zůstávají
  blokované rozhodnutím D-009 a účetním/právním návrhem.
- TrainingPeaks, Strava a `.FIT` potřebují samostatný kontrakt, souhlasy,
  retenční pravidla a určení vlastníka dat.
- Roční přehled v M3.3 je informační. Název, obsah ani export nesmí vytvářet
  dojem účetního či daňového dokladu bez schválení odpovědnou osobou.
- M3 nesmí změnit pravidlo jedné identity, oprávnění rodič–dítě ani sezonní model
  kroužků a závodních soupisek.

## Další konkrétní krok

Další technický řez je M3.5a: read-only inventura kvality existujících tréninkových
a výkonnostních dat, jejich jednotek, úplnosti a oprávnění. Nejdřív vznikne
vysvětlitelný přehled kvality; žádné zdravotní predikce ani externí synchronizace.
Současně zůstává otevřená vlastníkova kontrola M3.3 a celé A01–A10.
