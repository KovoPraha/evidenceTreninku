# Produkční test hotovosti a vratky – 21. 10. 2026

Výsledek: **PASS** pro celý objednávkový cyklus s testovacími daty.
Vlastník výslovně požádal o změnu převodu na hotovost, příjem platby a vratku
na TEST objednávce, s vyloučením skutečných objednávek a plateb.

Test proběhl standardním přihlášeným UI na `kis.kovopraha.cz`. Nevznikl žádný
skutečný převod, karetní transakce, výdej hotovosti ani zásah do skutečné
objednávky. Zápisy v TEST platebním předpisu představují simulaci; důvody změn
to výslovně uvádějí. Bankovní a platební integrace se nezapínaly ani nevolaly.

## Izolovaná data

| Položka | Hodnota |
| --- | --- |
| Objednávka | `KP2610218B7C69C006` |
| Částka | 1,00 CZK |
| Zákazník | `TEST Hotovost Vratka NEPLATIT`, nákup bez účtu |
| Kontakt | `cash-refund-20261021@example.invalid` – žádný skutečný příjemce |
| Nový produkt | #296, `TEST - Hotovost a vratka 21.10.2026 - NEPLATIT` |
| Nová varianta | #950, SKU `KP-TEST-CASH-REFUND-20261021` |
| Počáteční sklad | 1 syntetický kus |
| Reference vratky | `TEST-CASH-REFUND-20261021` |

Produkt je samostatné zboží bez vazby na člena, účast, program či skutečný
skladový artikl. Kvůli jednomu testovacímu nákupu byl krátce zveřejněn; ihned
po vzniku objednávky byl přes vlastní formulář stažen z prodeje. Při první
přípravě formulář správně odmítl SKU bez povinného prefixu `KP-`; před
pokračováním byl ověřen stav a opraven pouze TEST vstup. Nešlo o dvojí založení.

## Skutečně provedený průchod

Časy níže odpovídají hodnotám zobrazeným v produkční administraci.

| Krok | Pozorovaný výsledek |
| --- | --- |
| Založení TEST produktu | Koncept #296 s cenou 1 Kč a skladem 1. |
| Objednávka převodem, 14:51:55 | Vznikla jediná objednávka uvedená výše; stav Vytvořená objednávka, platba Čeká, způsob Bankovním převodem. Sklad nové varianty 1 → 0. |
| Stažení TEST nabídky | Produkt Stažený, varianta Neaktivní; existující objednávka zachována. |
| Přechod k hospodáři | Tlačítko z objednávky přepnulo pracovní pozici a otevřelo finanční úkol s filtrem přesného kódu objednávky. |
| Hotovost a příjem, 14:53:58 | Výběr Hotově, poznámka se slovem TEST a popisem simulace, potvrzení celé částky a odeslání. Objednávka Připravuje se, platba Zaplaceno, způsob Hotově. Zákaznický detail odpovídá. Audit zobrazuje přechod z převodu na hotovost, autora, čas a důvod. |
| Storno, 14:54:58 | Přes roli objednávek odesláno storno jen této objednávky. Stav Stornováno, platba Čeká na vrácení hospodářem. Zákaznický detail výslovně odlišuje storno a dosud neprovedenou vratku. |
| Samostatná vratka, 14:55:45 | Hospodář potvrdil simulovanou vratku s TEST referencí a výslovnou poznámkou o nulovém skutečném pohybu peněz. Konečný stav platby Vráceno, původní způsob Hotově zachován. Audit obsahuje referenci vratky hotovostní platby, autora, čas a důvod. |
| Konečný zákaznický detail | Stornováno, peníze vráceny, způsob Hotově; platební QR se již nenabízí. |
| Konečný sklad a nabídka | Sklad nové varianty opět 1, tedy 1 → 0 → 1. Produkt zůstává Stažený a varianta Neaktivní. Veřejný detail hlásí nedostupný produkt. |
| Pracovní kontext | Obnovena původní pozice Správce systému. |

Přijetí, storno a vratka byly dokončeny před aktivací navazujícího release
`574328421fff748b48f7d744a3ce454b9b50dd1e`: [deploy 39603396933](https://github.com/KovoPraha/evidenceTreninku/actions/runs/39603396933)
aktivoval kód v 12:56:25–12:56:27 UTC, tedy 14:56:25–14:56:27 Europe/Prague.
Platební průchod tudíž ještě běžel na předchozím release `f5d6276838239ace6426aa8c326c01a213730a36`;
závěrečná kontrola skladu a skrytí již proběhla po navazujícím deployi.

## Důkazy a konečný úklid

Lokální důkazy jsou v `output/browser/cash-refund-cycle-20261021/`:

- `01-test-product.png`: oddělený TEST koncept;
- `02-stock-reserved.png`: sklad 0 po checkoutu;
- `04-cash-paid-customer.png`: zákaznický detail zaplacený hotově;
- `05-cash-paid-audit.png`: administrace po přijetí hotovosti;
- `06-refund-required-customer.png`: storno vyžaduje samostatnou vratku;
- `07-refunded-customer.png`: zákaznický detail po vratce;
- `08-refunded-admin.png` a `order-final.txt`: konečný stav a TEST reference.

Snímky a text neobsahují přístupový token zákaznického odkazu, hesla ani
konfiguraci a nejsou součástí Gitu/deploye. Po stornu a vratce byly stavy
ověřeny také novým načtením zákaznického detailu. Při závěrečné kontrole
zákaznického detailu nebyl v konzoli warning ani error.

TEST objednávka, platba a audit zůstávají dohledatelné jako Stornováno/Vráceno.
TEST nabídka zůstává neveřejná a její jediný syntetický kus je vrácený na sklad.
Nic se nemazalo. Skutečné produkty, objednávky a platby nebyly měněny.
Pro nákup se nepoužil existující členský účet ani skutečný e-mail; případné
automatické notifikace tohoto TEST lifecycle míří na uvedenou adresu `.invalid`.
Doručování pošty nebylo předmětem ověření.

Tento průchod doplňuje [předchozí kontrolu formuláře](ORDER_PAYMENTS_2026-10-21.md).
Nepředstavuje test skutečných peněz, opakovaného/souběžného POST ani schválení
plného UAT aplikace. Aplikační kód se během této práce neměnil.
