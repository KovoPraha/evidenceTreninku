# Návazné účetnictví baru: návrh před aktivací

Stav 5. 11. 2026, B6. Tento návrh je podklad další samostatné etapy; žádný adaptér ani automatické účtování se zde nezapíná. Vstup tvoří neměnný [kontrakt v1](BAR_ACCOUNTING_CONTRACT_V1.md), originály dodavatelských PDF a ověřená historie peněz KIS.

## Pořadí zpracování

1. Převzít originální JSON dávky a jeho otisk. Ověřit schema, měnu CZK, `source_timezone=UTC`, jedinečnost zdrojů, otisky událostí a podporované typy. Neoznačený starší časový režim musí do ručního posouzení.
2. Uložit vlastní importní dávku adaptéru, mapu `(systém BAR, source_key)` s jedinečným omezením a otiskem, stav `review_required` a nezměněný originál. Stejný zdroj se stejným otiskem je opakování, stejný klíč s jiným otiskem konflikt. Přijetí dávky samo nezakládá účetní ani bankovní pohyb.
3. Vyřešit chybějící originály a vazby kompenzací. Původní zdroj může být v jiné dřívější dávce; nepředpokládat pořadí podle souboru. Neznámý původ, neznámý náklad, nerozdělená DPH nebo neověřená úhrada zůstávají k rozhodnutí.
4. Nabídnout účetní náhled s konkrétními doklady a součty zboží, záloh, nákladů, příjmů a peněz. Teprve po schválení pravidel a konkrétní dávky uložit účetní doklady a vazby v jedné transakci s aktuální autorizací, uzávěrou období a auditem. Opakování ani souběh nesmějí založit další doklad.
5. Opravu provádět novým navázaným dokladem; nepřepisovat původní export, zaúčtovaný zdroj ani uzavřené období. Storno již používaného zdroje vyžaduje kontrolu zbývajících záloh i původního ocenění.

## Rozdělení zdrojů

| Zdroj | Budoucí účetní význam | Kontrola před zápisem |
| --- | --- | --- |
| Potvrzený prodej | Zboží, samostatný závazek vratných záloh, způsob úhrady | Schválené výnosové účty a daňové členění; prodejní `tax_breakdown=null` není nulová DPH |
| Storno prodeje / vratka kelímku | Navázaná kompenzace přesně zbývajícího zboží či zálohy | Původní doklad, skutečná vratka, žádné dvojí vrácení zálohy |
| Vlastní spotřeba / její storno | Oddělený náklad bez tržby a úhrady | Účet a způsob ocenění; neznámý náklad není nula |
| Dodavatelský příjem / jeho storno | Skladový a dodavatelský podklad | Originál PDF, schválené daňové údaje; příjem není dodavatelská platba |
| Vklad / výběr z kasy | Převod mezi konkrétními místy peněz | Doložená protistrana; nejde o nový výnos |
| Uzávěrka | Odsouhlasení očekávané a napočtené hotovosti | Vlastník, skutečný uzavírající, důvod rozdílu; souhrn se znovu neúčtuje jako tržba |

## Banka a karta

Ruční potvrzení QR nebo karty popisuje obsluhou doložený stav objednávky. Adaptér je nesmí použít k založení dalšího bankovního příjmu. Budoucí vazba vede na již existující bankovní/terminálový záznam KIS s ověřeným poskytovatelským identifikátorem a částkou/měnou. Samotná shoda částky ani textová poznámka nestačí. Nejednoznačnost zůstává ve frontě pro člověka.

Souhrnná platba terminálu může pokrýt více prodejů a obsahovat poplatek. Je nutná výslovná rozpadová mapa a odsouhlasení hrubé částky, poplatku a čistého vypořádání. Jeden bankovní záznam se nesmí celý přiřadit několika objednávkám. Časový rozdíl mezi prodejem, potvrzením a vypořádáním se uchovává; neurčuje se odhadem podle dne uzávěrky.

## Rozhodnutí vlastníka a účetní před implementací zápisů

- Cílové účetní doklady, číselné řady, účty, středisko baru a kompetence ke schválení.
- Plátcovství a pravidla DPH podle produktu a data, způsob zaokrouhlení a zacházení s vratnými zálohami. Dosavadní katalog neposkytuje spolehlivý rozpad prodeje podle daně.
- Ocenění zásob a vlastní spotřeby: nynější historický odhad není FIFO skladové účetnictví.
- Konkrétní ověřitelná vazba QR/terminálu a politika poplatků, vratky po uzávěrce, rozdíly kas a uzavřená období.
- Oddělené schválení aktivace finančních zápisů a provozní přejímka na odsouhlasené dávce.

## Akceptace budoucího adaptéru

Prokázat opakování stejné dávky, dva souběžné importy, změněný otisk pod stejným klíčem, kompenzaci před původem, částečně vyřešenou dávku a přerušení spojení před i po commitu. Ověřit bankovní souhrn s poplatkem bez zdvojení peněz, pohyb přes pražskou půlnoc a oba přechody času, uzavřené období, odvolání schvalovatele během čekání a obnovu DB spolu s originály. Technický export B5/B6 tuto přejímku automaticky nenahrazuje.
