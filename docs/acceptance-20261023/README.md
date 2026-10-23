# Uživatelská přejímka a ukázkové předání účetní

Připraveno 23. 10. 2026 nad aplikačním základem `abbb4be9`, větev
`codex/club-acceptance-package-20261023`. Pracovní kopie
`C:\xampp\htdocs\evidencePavel-finance-b1-20261022`.

## Co je hotové

- 43 konkrétních scénářů / 188 kroků: 7 provozních, 16 členských a sportovních,
  20 obchodních, skladových a finančních. U každého role, priorita, návaznosti,
  obrazovky, předpoklady, testovací údaje, očekávané výsledky a požadované důkazy.
- 38 scénářů P0 a 5 P1. Odhad provedení je 882 minut (14,7 hodiny), bez přípravy
  podkladů a odstraňování vad. Není to odhad zbývající implementace.
- Přenosný HTML průvodce umožňuje hledání, filtrování, místní záznam výsledků
  a jejich export/import JSON. Nezapisuje přejímku ani schválení do aplikace.
- Tisknutelný PDF průvodce: 53 stran, včetně účetních kontrol a prázdných protokolů.
- Ukázkové předání účetní: původní export #22 / revize 5, XLSX s 10 listy,
  18 PDF / 30 stran, vysvětlení souvislého příkladu a 18 kontrol UC01–UC18.

**Lidské scénáře jsou všechny NEPROVEDENO. Převzetí účetní je NEPŘEVZATO.**
Příprava dokumentů a technická kontrola artefaktů nejsou přejímkou uživatele.

## Co otevřít

V pracovní kopii:

- `output/KOVO-prejimka-a-ucetni-20261023.zip` — celý přenosný balík; rozbalit vše.
- `output/uat-accountant-20261023/index.html` — výchozí průvodce.
- `output/uat-accountant-20261023/ucetni/PRECTETE-UCETNI.html` — účetní ukázka.
- `output/pdf/PRIJIMACI-PRUVODCE-20261023.pdf` — kompletní tiskový postup.

Samotný balík nepotřebuje internet. Odkazy do aplikace vyžadují připravený místní
server `127.0.0.1:18086`. Přístupy zůstávají v soukromém runtime, nejsou v ZIP ani Gitu.
Přenesení balíku na jiný počítač nepřenese databázi ani testovací účty.

## Doporučené pracovní bloky

| Pořadí | Kdo | Rozsah a návaznost |
|---|---|---|
| 1 | Správce | S01, inventář podkladů, samostatné účty; negativní přístupy až s omezeným účtem. |
| 2 | Registrář / koordinátor | P01–P08: osoby, rodiče, papírové přihlášky, kroužek, osobní přijetí, členství a předpisy. |
| 3 | Vedoucí sportu / trenér | P09–P16 a S06: rozvrh, docházka, opravy, náhrady, prohlídky, svěřený rozsah a soukromý report. |
| 4 | Hospodář / sklad / prodej | F01–F13: finanční počátky, uzávěrka, sklad, prodej, výdej, doklady, vratky a inventura. |
| 5 | Koordinátor / hospodář | F14 přebírá objednávku z P06; F15–F18: výdaje, zálohy, nový samostatný 60min trénink pro odměnu a chybějící podklady. |
| 6 | Hospodář / účetní | F19–F20 a UC01–UC18: export, jeho revize, návrat k doplnění a skutečné převzetí. |
| 7 | Správce / vedení | S03–S05, S07, soupis vad a závěr. Pilot ani produkční přechod tím nejsou povolené. |

Uvnitř bloků mají přednost konkrétní návaznosti scénáře. Příprava je samostatný
údaj; generátor přenáší chybějící předpoklady i na závislé scénáře a kontroluje cykly.
Nejasný finanční výsledek se zaznamená jako vada, neopravuje se dalšími pokusy naslepo.

## Připravená data a odložené body

[Inventář](fixtures.md) popisuje dvě lokálně zveřejněné syntetické položky
44/45, varianty 50/51/52, vlastní testovací kategorii 91 a dva stávající plné účty
326/327. Žádný skladový počátek, objednávka ani platba touto přípravou nevznikly.
Počátky provede tester ve F04/F13. Zápisy proběhly pouze přes místní UI.

Dva různé plné účty stačí pro kontrolu oddělení podatele a schvalovatele,
ale neprokazují oprávnění běžného trenéra. Vytvoření omezeného účtu je odložené:
použitý nástroj pro obsluhu prohlížeče vyžaduje předání zadání nového hesla člověku.
Žádný existující účet nebyl resetován ani zbaven oprávnění. Další potřebné osoby,
ověřené rodičovské účty, programy a tréninky jsou uvedeny u scénářů.

[Rozhodnutí D01–D16](../CLUB_COMPLETION_DECISIONS.md) zůstávají odložená.
Jde zejména o účetní metodiku, program účetní, reálné sazby/pravidla,
skutečná migrační data a odpovědnosti za pilot a přechod. Syntetické hodnoty
nepředstavují rozhodnutí za vedení nebo účetní.

## Meze účetní ukázky

[Podrobný průvodce](accountant-sample.md) a [strojový protokol](accountant-sample.json).
Oddíl podle uživatele vede jednoduché účetnictví a není plátcem DPH.

Export je **NEÚPLNÝ**, smíšený z více syntetických testů, za 24. 9. – 23. 10. 2026,
nikoli kalendářní měsíc. Obsahuje 111 výjimek a 14 nepřevzatých zdrojů.
Součet všech řádků není konečný zůstatek ani skutečné účetnictví oddílu.
Souvislý příklad účtu #27 končí na 4 800 Kč; jde o bankovní typ účtu, nikoli pokladnu.
Dokladová ukázka faktury 250 Kč a opravy −125 Kč patří jinému případu.
Testovací přílohy LOCALHOST TEST nejsou podepsaným podkladem skutečné osoby.
Účetní zatím nebylo nic posláno.

## Ověření a opakovatelnost

[Protokol ověření](verification.md) rozlišuje novou kontrolu artefaktů od historických
aplikačních testů. Nativní XLSX/PDF/ZIP nebyly upraveny; ověřeny SHA-256, délky,
obsah listů a renderování PDF. Skutečné otevření XLSX v programu účetní je UC01/UC16.

Generátor `tools/build_club_acceptance_package.py` čte scénáře a již připravené kopie
nativního exportu ve složce `output/uat-accountant-20261023/ucetni`; nepřipojuje se do DB.
Vyžaduje Python s reportlab/pypdf a Arial ve Windows. Při obnově na jiném počítači
nejdříve rozbalit přenosný balík do této výstupní složky. Ověřovač
`tools/verify_club_acceptance_html.cjs` používá místní Playwright a Chrome v novém
dočasném profilu s blokovanou sítí; neotevírá aplikaci ani produkci.

Generované artefakty a náhledy nejsou v Gitu. Zdrojové scénáře, inventář, kontrolní
protokoly a nástroje jsou verzované. Aplikační kód se v tomto balíku neměnil.
Žádný push, merge, nasazení, produkční import ani aktivace integrací.
