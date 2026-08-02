# Shoptet katalog – první read-only dry-run

Stav: **prozatímní kontrakt do ověření reálného anonymizovaného exportu**.

Tento krok pouze lokálně přečte produktový CSV export, zkontroluje SKU, varianty,
ceny a podporovaná pole a vypíše náhled. Nevytváří katalog, nepřipojuje databázi,
nemění Shoptet a nemá režim `--apply`.

Dry-run také navrhne provozní typ každé nabídky. Jde pouze o klasifikaci pro
ruční kontrolu, nikoliv o oprávnění k importu nebo založení objednávky.

## Použití

```powershell
php bin/shoptet-products-dry-run.php --input="C:\cesta\produkty.csv"
```

Výchozí výstup je stručný lidsky čitelný souhrn. Pro deterministický JSON na
standardní výstup použijte:

```powershell
php bin/shoptet-products-dry-run.php --input="C:\cesta\produkty.csv" --json
```

Nástroj nikdy nevytváří report na disku. Přesměrování standardního výstupu je
vědomá akce operátora mimo tento kontrakt. Parametry `--apply` a `--report` jsou
odmítnuty s exit kódem `64`.

Exit kódy:

- `0` – soubor splnil prozatímní katalogový kontrakt,
- `2` – existující CSV byl přečten, ale obsahuje blokující obsahový problém,
- `64` – chybný nebo zakázaný parametr, duplicitní `--input`, neexistující či
  nečitelná lokální cesta nebo cesta k jinému typu souboru než `.csv`.

## Bezpečnostní hranice

- pouze CLI, při webovém spuštění vrací skript 404,
- pouze lokální regulární `.csv` soubor, nikdy URL ani symlink,
- nejvýše 10 MiB, 10 000 datových řádků, 200 sloupců a 64 KiB na pole,
- podporované kódování je UTF-8 (včetně BOM) a Windows-1250,
- žádný `db.php`, `config.php`, session, síťový požadavek nebo zápis souboru,
- obrázky se pouze evidují jako textové URL a nestahují se,
- hodnoty podobné tabulkové formuli se nikdy nevyhodnocují a vyvolají varování,
- HTML popis zůstává nedůvěryhodným textem a nesmí se bez sanitizace vykreslit.

Zdrojový export nepatří do Gitu. Permanentní exportní URL Shoptetu může obsahovat
přístupový hash; nevkládejte ji do repozitáře, issue, chatu ani CI logu.

## Podporovaný kontrakt CSV

Povinné názvy sloupců jsou přesně:

```text
code, pairCode, name, price
```

`pairCode` musí být v hlavičce, ale u produktu bez variant smí mít prázdnou
hodnotu. Podporované volitelné sloupce:

```text
priceRatio, currency, includingVat, percentVat, ean, stock, decimalCount,
negativeAmount, productVisibility, variantVisibility, defaultCategory,
categoryText*, shortDescription, description, itemType, variant:*, image*
```

Popisky `imageDesc*` zatím nejsou mapované; pokud obsahují data, dry-run je stejně
jako každý jiný nepodporovaný neprázdný sloupec zablokuje.

Neznámý nebo zatím nepodporovaný sloupec s neprázdnými hodnotami je blokátor,
nikoliv tiše zahozená informace. To se týká například dalších ceníků,
víceskladových `stock:*` polí, akčních cen, sad, záloh a příplatkových parametrů.
Z `itemType` jsou v první verzi přijaty jen hodnoty `product` a `service`.

SKU se uchovává jako text. Výchozí Shoptet prefix `$`, který chrání úvodní nuly
před Excelem, se odstraní a změna se zapíše mezi normalizace. Například
`$000123` se změní na `000123`, nikdy na číslo `123`. Duplicitní SKU po této
normalizaci import zablokuje.

Varianty se seskupují podle neprázdného `pairCode`; produkt bez variant dostane
klíč odvozený od SKU. Podporovány jsou nejvýše tři sloupce `variant:*`.

Ceny se převádějí přes přesnou desetinnou reprezentaci na integer haléře, bez
`float`. Hodnoty `1234,50`, `1 234,50`, `1 234,50` a `1 234,50` tedy znamenají
`123450` haléřů. Měna musí být v této první verzi vždy explicitně přítomná v
každém řádku. Chybějící `currency` je vědomý prozatímní blokátor; nástroj nikdy
skrytě nepředpokládá CZK. Pouze explicitní `CZK` má nyní známý převod na haléře.
Jiná syntakticky platná měna zůstane ve výstupu, ale vyvolá blokátor
`unsupported_currency_minor_unit` a její `amount_minor` bude `null`. Nejde o
rozhodnutí jiné měny zakázat; jejich minor-unit pravidla musí nejdříve potvrdit
reálný export a produktové rozhodnutí. Chybějící `includingVat` je viditelné
varování a hodnoty DPH zůstávají `null`; dry-run žádnou sazbu ani daň nedopočítává.

## Syntetická W0-G contract matrix

Repozitář obsahuje pouze fiktivní testovací data bez osob, adres, objednávek a
skutečných produktů:

- `products-variant-matrix.csv` – dvě variantní osy, tři varianty a jeden produkt
  bez variant,
- `products-duplicate-sku.csv` – dvě nezávislé SKU kolize včetně kolize po
  odstranění Shoptet prefixu `$`,
- `products-money-vat.csv` – přesné CZK haléře, cena s/bez DPH, nulová a
  desetinná sazba a explicitně neznámé VAT údaje,
- `products-catalog-scope-boundary.csv` – syntetická order/payment/wallet/delivery
  pole, která katalog povinně odmítne jako nepodporovaná.
- `products-offer-types.csv` – zboží, kroužek, tábor, rezervovaná služba,
  půjčovna, individuální nabídka a záměrně konfliktní klasifikace.

Tato matice nevytváří objednávkový ani platební model. Dokazuje pouze katalogový
kontrakt a jeho hranice. Bez schválení D-009 až D-013 se nepřidávají payment
stavy, wallet operace, checkout, storno ani doprava.

## Návrh typu nabídky

Každý produkt ve výstupu obsahuje `offer_classification`:

```json
{
  "type": "club_event",
  "confidence": "high",
  "needs_manual_review": false,
  "signals": ["category:club_event"]
}
```

Podporované staging typy jsou:

- `goods` – fyzické zboží, knihy a doplňky,
- `club_event` – kroužek nebo jiná opakovaná klubová aktivita,
- `camp` – tábor s pevným termínem,
- `bookable_service` – trénink, zážitek nebo test vyžadující rezervaci,
- `rental` – půjčovna nebo pronájem zdroje,
- `custom_quote` – individuálně domlouvaná nabídka,
- `unclassified` – chybějící nebo konfliktní signály; povinná ruční kontrola.

Klasifikátor používá přesné segmenty kategorií, nikoliv pouhý výskyt slova v
názvu. Kategorie `Volnočasové oblečení > Cyklo kroužek - trička` proto zůstane
zbožím. Pokud jedna položka současně odpovídá více doménám, výsledek je vždy
`unclassified`; dry-run žádnou z možností sám neupřednostní.

Souhrn obsahuje počty `offer_type_counts` a `manual_review_products`. Ani vysoká
jistota klasifikace nemění read-only povahu nástroje: produkční import, DB zápis,
registrace dítěte, rezervace termínu ani skladový pohyb se neprovedou.

## Co získat ze Shoptetu

V administraci vytvořte malý vlastní produktový export ve formátu CSV. Pro první
ověření stačí fiktivní nebo anonymizovaný výběr zahrnující:

- produkt bez variant,
- produkt s velikostí nebo barvou,
- skrytý či blokovaný produkt,
- vyprodaný produkt,
- produkt s akční cenou, pokud ji obchod používá.

Vyberte minimálně `code`, `pairCode`, `name`, `price`, `priceRatio`, `currency`,
`includingVat`, `percentVat`, `productVisibility`, `variant:*`, `stock`,
`decimalCount`, `defaultCategory`, `image*`, `itemType` a `ean`. Soubor ponechte
mimo repozitář. Před návrhem DB tabulek nebo skutečného importu musí proběhnout
dry-run nad tímto vzorkem a ruční kontrola všech nepodporovaných neprázdných polí.

Oficiální dokumentace:

- [Shoptet – Export produktů](https://podpora.shoptet.cz/export-produktu/)
- [Shoptet – Import produktů a pravidla polí](https://podpora.shoptet.cz/import-produktu/)

Shoptet podle dokumentace podporuje produktové exporty CSV/XLSX/XML, vlastní
výběr polí, textový `code`, seskupení variant přes `pairCode` a ochranu úvodních
nul prefixem `$`. Konkrétní sloupce však závisejí na nastavení obchodu, proto je
tento kontrakt až do kontroly skutečného klubového exportu pouze prozatímní.
