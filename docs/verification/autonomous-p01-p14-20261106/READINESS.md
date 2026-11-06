# Připravené navazující kroky P02–P13

Tento dokument doplňuje [průběžný plán](../../WORK_PLAN_CURRENT.md), nenahrazuje
skutečné údaje a nepředstírá provedenou lidskou přejímku. Výsledky této relace
jsou v [protokolu](RESULTS.md).

## P02/P03 — první skutečná směna BAR

Nabídka 1 je zveřejněná, nezakládat další. Připravit záznam směny s datem,
obsluhou a konkrétním skutečným stavem; heslo Lucky se nikam nezapisuje.

| Krok | Doložit / vyplnit | Podmínka přijetí |
| --- | --- | --- |
| Lucka se přihlásí původním heslem | Obsluha, datum, výsledek bez hesla | Pokladna a čtecí sklad; bez správy katalogu, nákupních cen a vlastní spotřeby bez grantu |
| Fyzická inventura | Produkt, místo, jednotka, spočtené množství a skutečný příjmový podklad | Neodhadovat podle katalogu a nepřebírat staré testovací zásoby |
| Vlastní kasa | Obsluha a skutečně vložená hotovost | Jediná odpovídající otevřená kasa, skutečný doklad vkladu |
| První reálný prodej | Zboží, množství, případné kelímky, skutečná platební metoda | Cena z aktuální nabídky, odpovídající sklad a jediný platební záznam |
| Vratka/storno | Jen skutečný důvod a doložený výdej peněz | Nevytvářet umělé skutečné vratky kvůli testu; syntetické důkazy již existují |
| Uzávěrka | Skutečně napočtená hotovost a důvod případného rozdílu | Bez nevyřešených čekajících plateb, neměnný uzávěrkový podklad |
| Tisk nabídky | Přihlášený tisk/PDF existující nabídky | Čitelné ceny/fotografie, žádná nová publikace; produkční přihlášený tisk zatím NOT RUN |

[Technická přejímka](../bar-acceptance-20261106/RESULTS.md) již prokázala
hotovost/kelímky/čekající platby/storna/uzávěrku v syntetické DB.

## P04 — podklady účetní a implementace adaptéru

[Kontrakt](../../BAR_ACCOUNTING_CONTRACT_V1.md) a
[návrh adaptéru](../../BAR_ACCOUNTING_ADAPTER_PLAN_2026-11-05.md) se nemění.
Export není účetní adaptér. Zbývá implementace mapy zdrojů, dávky, autorizovaného
schválení a účetních zápisů; bez následujících pravidel nesmí odhadovat cílové doklady.

| Oblast | Chybějící rozhodnutí / příklad podkladu |
| --- | --- |
| Cíl účetnictví | Druhy dokladů, číselné řady, účty/kategorie/středisko, oprávněný schvalovatel |
| Daňová pravidla | Potvrzené plátcovství, pravidla pro produkt a datum, zaokrouhlení, vratné zálohy; null není nula |
| Sklad / spotřeba | Schválené ocenění a účetní zacházení s vlastní spotřebou; neznámý náklad neúčtovat jako nulu |
| Bezhotovostní platby | Autoritativní QR/terminálové ID, rozpad souhrnného vypořádání, poplatky, vazba na existující peníze |
| Opravy | Částečné vratky, storna po uzávěrce, rozdíly hotovosti a uzavřená období |
| Převzetí | Konkrétní odsouhlasená dávka a osoba účetní přejímky; aktivace není totéž co implementace |

Připravená akceptační matice implementace: stejné source_key/otisk znovu bez
nového dokladu, změněný otisk jako konflikt, dva souběžné importy bez duplicity,
kompenzace před původem do review, null DPH/náklad neúčtovat odhadem,
uzavřené období odmítnout, výpadek před commitem bez částečného zápisu a replay
po commitu bez druhého dokladu. Kasa/uzávěrka/potvrzení karty nesmějí podruhé
vytvořit bankovní peníze. Dosavadní exportní testy nenahrazují testy budoucího adaptéru.

## P08/P09 — lidská a účetní přejímka

Omezený trenér ověří vlastní pohled, zastupování a zákaz nepříslušných soukromých
agend; chybějící soupiska neblokuje již povolené zastupování. Test skutečného hesla
ani T07 se neprovádí a grant pilotu se automaticky neprodlužuje.
D12–D14 potřebují skutečné kompetence, vazby, dohody, částky, ocenění a dvě
původní chybějící přílohy. Při dohledání porovnat otisky a vazby, nevyrobit nový
soubor jako náhradu nezvěstného originálu. [Rozhodnutí](../../CLUB_COMPLETION_DECISIONS.md),
[pilot](../../CLUB_PILOT_D12_D14.md), [scénáře trenéra](../../TRAINER_UAT_POSTUP_2026-11-01.md).

## P10/P11 — zachování WIP a měření

Staré větve ani návratové verze neslučovat hromadně. Aktuální vlastní změny
přidávat po konkrétních cestách; lokální kis_rosters_admin.php zůstává cizí WIP.
Benchmark kapacity v této složce měří pouze čistý evaluator, nikoli SQL ani celé
HTTP. Výsledky neopravňují slibovat rychlost celé aplikace nebo odstranit zámky.
Plošné K0.2 je stále samostatná neprovedená etapa; nová optimalizace musí mít
měřený přínos a zachovat sémantiku i oprávnění.

## P13 — kandidáti dalších pracovních upozornění

Nejde o aktivované události; konkrétní prioritní typy zatím vlastník nevybral.
Před realizací porovnat se stávajícími frontami a upozorněními, aby nevznikly duplikace.

| Kandidát | Rozhodnutí před implementací |
| --- | --- |
| Chybějící registrační podklad | Kdo má doplnit co, příjemce úkolu, počátek a konec; soukromý obsah neposílat do e-mailu |
| Nevyřešená platební výjimka | Hospodář, přesný typ výjimky, deduplikace oproti stávajícím upomínkám |
| Provozní selhání workeru | Práh a čerstvost, adresát, zrušení po opravě, interval bez spamování |

Každý schválený typ vyžaduje bezpečný odkaz, aktuální autorizaci, transakční
vznik/ukončení, deduplikaci a odvolání stale e-mailu; push a eskalace jsou jiný rozsah.
