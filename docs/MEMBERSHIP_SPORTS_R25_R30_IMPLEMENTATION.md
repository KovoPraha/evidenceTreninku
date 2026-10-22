# R25–R30 — členská a sportovní agenda

Lokální implementace navazuje na R18–R24, commit `12f28b611a8781968ca6b73821c0df3e2e4075dc`.
Pracovní kopie: `C:\xampp\htdocs\evidencePavel-finance-b1-20261022`, větev
`codex/membership-sports-r25-r30-20261023`. Původní checkout zůstal nedotčen.

**Pouze izolované syntetické prostředí. Bez push, merge, nasazení, produkčních změn,
odesílání zpráv a aktivace plánovačů.** Vytvořené testovací politiky nejsou
schválením skutečných klubových, zdravotních ani účetních pravidel.

## Dodaný rozsah a návaznosti

| Úkol | Existující základ | Doplnění tohoto balíku |
|---|---|---|
| R25 — přijetí a příspěvky | Kanonické osoby, ověřené rodinné vazby, papírové dokumenty, objednávky, předpisy a platby. | Registrářské osobní přijetí s výslovným plátcem, přesnou nabídkou, cenou, datem vstupu a přijatými verzemi papírových podkladů. Podporuje i doložené přijetí bez internetového účtu. Dohoda plátce a schválená částka měsíce jsou samostatné auditované záznamy. |
| R26 — plán a skutečnost | Plánovač, rezervace sportovišť, soupisky, RSVP a dosavadní záznam tréninku. | Transakční kontroly kolizí; auditované zrušení/přesun; explicitní uzavření skutečné účasti, času a trenérů až po konci lekce. Oprava vytváří další neměnnou verzi. Původní editory nemohou uzavřený podklad přepsat nebo smazat. |
| R27 — náhrady | Nároky, kapacity, rezervace, expirace a převody náhrad. | Nárok a spotřeba vycházejí z dokončené, uzavřené lekce; nikoli z RSVP či začátku tréninku. Osobní omluva má samostatný skutečný čas přijetí, autora a důvod. Opravy, zrušení a souběhy zachovávají jediný nárok a jediné použití. |
| R28 — akce a sportovní historie | Kalendář, přihlášky, závody, měření a profily. | Nominace, potvrzení, registrace a skutečná účast/start mají odlišné stavy. Auditované verze výsledku a původu dat; jednotné ověřené součty v profilu, rodině, časové ose a exportu. Neznámý výsledek ani délka se nevydávají za nulu. |
| R29 — prohlídky | Osoby, role, svěřenci, soukromé úložiště a provozní případy. | Výslovně potvrzený účel a pravidla evidence, sledování chybějících podkladů, nahrání/ověření/nahrazení/odvolání, platnost ke dni aktivity, soukromý soubor nebo umístění papírového originálu, audit a osobní vyřízení. |
| R30 — pokračování a přehledy | Opakované nabídky, přechody soupisek, rodinný přehled a finanční ochrany. | Navazující nabídka vzniká jako koncept k posouzení. Věkový přechod vyžaduje doložený věk. Rodina vidí ověřenou činnost a stav podkladů. Vedoucí dostává neměnný podklad skutečné činnosti; pozdější oprava se označí jako změna k posouzení. |

## Osobní obsluha a finance

Registrář otevře **Osobní přijetí do programu**, vybere existujícího sportovce,
přesnou nabídku a dohodnutého plátce. Přijatá papírová přihláška a požadované
souhlasy musejí patřit téže osobě a programu a odpovídat účinným verzím.
Papírový záznam se nepředstírá jako online kliknutí plátce.

U rodiny bez účtu se uchová výslovný snímek plátce a jeho vztahu podle přijaté
papírové přihlášky. Nevytváří se účet, ověření e-mailu ani automatické rodinné
oprávnění. Účast vzniká přes dosavadní kanonický platební postup po potvrzení
skutečné úhrady; registrář nepřipisuje peněžní příjem.

Osobní přijetí nemění cenu nabídky poměrným odhadem. Přestup odkazuje na
ukončenou původní účast a doložené samostatné finanční vyrovnání. Obsluha má
kroky k původní objednávce, náhradám a finanční opravě. Nejde o automatické
převedení peněz mezi objednávkami.

Hospodář v **Plánech příspěvků** výslovně potvrdí plátce a období dohody;
žádný rodič se nevybere jako první nalezený. Částku, úlevu či souběh plánů
schvaluje pro konkrétní osobu a období. Chybějící rozhodnutí je viditelná
překážka v náhledu. Vystavený předpis se nepřepisuje a opakování běhu jej
neduplikuje. Pozdní příchod po provedeném měsíčním běhu používá navazující
postup jednotlivého předpisu. Stálé VS se nemění a dluh neukončuje členství.

## Tréninky, náhrady a podklady pro odměny

**Docházka a uzavření** odděluje rozpracovaný záznam od potvrzení skutečnosti.
Obsluha potvrzuje účastníky, skutečné trenéry, začátek a konec; u hosta důvod.
Papírové dodatečné zapsání zachovává skutečné datum zvlášť od okamžiku zápisu.
Prázdná docházka se nepotvrdí pouhým uplynutím času nebo RSVP.

Uzavřená verze je neměnná. Další oprava vyžaduje důvod a očekávanou aktuální
verzi. Raw editace či smazání nemůže obejít kontrolu závislostí, měření,
náhrad ani uzavřených odměnových období. Schválené částky se nepřepočítávají
potichu; dotčený podklad dostane záznam k posouzení.

Zrušení či přesun plánu zachová audit a případnou vazbu na náhradní termín.
Provozní zprávy používají existující doručovací frontu s opětovnou kontrolou
oprávnění příjemce; osobní dořešení není označeno za odeslaný e-mail.
Tento balík nespouští worker ani plánovač.

**Sportovní činnost a podklady** umožňuje dohledat konkrétní skutečné aktivity
za období, vytvořit snímek a stáhnout CSV. Snímek zachovává zdrojové identity,
verze a otisk; nová oprava označí rozdíl, historický export se nezmění.
Sazby, schválení odměn a vyplacení patří R31–R34. Tento přehled sám nic
nevyplácí ani neoznačuje jako zaplacené.

## Prohlídky a ochrana podkladů

Registrář nejprve potvrdí konkrétní účel, právní důvod, pravidlo uchování,
předstih kontroly a postup při chybějící platnosti. Pak začne sledovat
podklad konkrétní osoby. Platnost je převzatá z dokumentu; nepředpokládá
se univerzální roční lhůta. Zátěžový test zůstává oddělený. Licence a
kvalifikace nejsou bez potvrzené potřeby přidány.

Nahrání je **čeká na ověření**. Předchozí ověřený podklad zůstává použitelný,
dokud není nový ověřen. Poté je předchůdce označen jako nahrazený.
Vyhodnocení ke staršímu dni sleduje výslovnou historii náhrad, respektuje
začátek i konec platnosti a mezery. Odvolání neobnoví starý dokument.
Také chybně ověřený historický podklad lze auditovaně odvolat.

Trenér v aktuálním svěřeném rozsahu a rodina s právě platným oprávněním
vidí pouze stav a další krok. Soukromý soubor, jeho umístění a interní
poznámky jim stavový endpoint ani rodinná stránka nevrací. Dokument čte
jen registrář, přímé URL znovu kontroluje živé oprávnění a audit čtení.
Soubory jsou mimo webroot, kontroluje se SHA-256.

Končící, chybějící a rozpracované podklady mají provozní případ s vlastníkem,
termínem a dalším krokem. Osobní kontakt lze doložit bez e-mailu; nezmění
sám platnost dokumentu. Upozornění v tomto balíku znamená pracovní seznam
a rodinný stav, nikoli nově aktivovanou automatickou e-mailovou rozesílku.
Pravidlo uchování je uložený pokyn, ne automaticky zapnutý výmaz dokumentů.

## Společné pracovní přehledy

- Registrář: identity, rodinné vazby, papírové podklady, nedokončená přijetí
  a chybějící/končící prohlídky.
- Vedoucí sportu: neuzavřená minulá docházka, auditované sportovní podklady
  a změny skutečné činnosti.
- Trenér: vlastní tréninky a svěřené osoby, uzavření docházky a skutečné
  sportovní záznamy; nikoli celoklubový seznam osob nebo zdravotní skeny.
- Rodina: existující portál s ověřenou činností, programy, financemi,
  budoucími termíny, náhradami a stavem podkladů. Nevzniká druhý portál.

Provozní úkol nelze uzavřít jako náhradu za vyřešení zdrojové agendy. Služební
ani rodinná oprávnění se neodvozují z podobného jména nebo historické účasti.

## Schéma, testování a předání

Pět migrací `20261023010000_member_admissions`,
`20261023010100_offline_program_admissions`, `20261023020000_training_closure`,
`20261023030000_sports_activity`, `20261023040000_medical_clearance`.
Celkem 16 nových tabulek; existující účast může být doložená bez účtu.
Migrační katalog má 130 položek. Zálohovací kontrakt `2026-10-23.1`
zahrnuje nové tabulky i změnu významu nullable účtu a snímku plátce.

Ověření a opakovatelné příkazy: [výsledky R25–R30](verification/r25-r30/RESULTS.md).
Testy používají výhradně syntetickou instanci `127.0.0.1:33426/evidence_b1`
a HTTP `http://127.0.0.1:18086`. Souběžné testy a obnova vytvářejí vlastní
nová schémata na ověřené izolované instanci; nepracují s produkcí.

Před případným nasazením oddíl potvrdí skutečná pravidla příspěvků a podkladů,
svěření rolí a soupisek, proces osobního ověřování a provozní dohled.
Historická data se tímto balíkem neimportují ani nevydávají za ověřenou
skutečnou činnost. Sazby a výplaty, úplné účetní předání, ostrá migrace,
pilot a nasazení zůstávají dalšími úkoly realizačního plánu.
