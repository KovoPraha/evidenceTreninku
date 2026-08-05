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
| M3.2 týdenní souhrn | probíhá | 35 % | přihlášený read-only náhled, prázdný stav a omezené listování po týdnech jsou hotové; zbývá opt-in, odhlášení, idempotentní fronta a localhost outbox |
| M3.3 roční přehled plateb | plánováno | 0 % | read-only přehled skutečně uhrazených klubových služeb; nesmí se označovat za daňové potvrzení bez právního a účetního schválení |
| M3.4 provozní přehled správce | plánováno | 0 % | akční seznam čekajících plateb, kapacit, přihlášek a výjimek s odkazy do existujících auditovaných obrazovek |
| M3.5 datová kvalita sportovního progresu | návrh | 0 % | nejdřív normalizace měření, soukromí nezletilých a vysvětlení kvality; žádné zdravotní predikce |

Orientační technický stav M3: **16 %**. Procento nezahrnuje produkční aktivaci.

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
neodesílá a odběr zatím nelze zapnout.

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

Další řez M3.2 může přidat dobrovolný opt-in a idempotentní frontu, ale nejprve
jen s ručním localhostovým náhledem/outboxem. Produkční transport zůstane vypnutý
a jeho aktivace vyžaduje schválený text, odhlášení jedním krokem a omezení
četnosti.
