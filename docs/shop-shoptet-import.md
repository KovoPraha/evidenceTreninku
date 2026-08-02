# Shoptet katalog – první read-only dry-run

Stav: **reálný XML export ověřen; kontrakt zůstává prozatímní do vyřešení
explicitně nalezených blokátorů**.

Tento krok pouze lokálně přečte produktový CSV nebo XML export, zkontroluje SKU, varianty,
ceny a podporovaná pole a vypíše náhled. Nevytváří katalog, nepřipojuje databázi,
nemění Shoptet a nemá režim `--apply`.

Dry-run také navrhne provozní typ každé nabídky. Jde pouze o klasifikaci pro
ruční kontrolu, nikoliv o oprávnění k importu nebo založení objednávky.

## Použití

```powershell
php bin/shoptet-products-dry-run.php --input="C:\cesta\produkty.csv"
```

Podporovaný je také systémový XML export. Nástroj jej rozpozná podle obsahu i tehdy,
když byl z Google Drive uložen s příponou `.csv`:

```powershell
php bin/shoptet-products-dry-run.php --input="C:\cesta\shoptet-products.xml"
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
- `2` – existující CSV nebo XML byl přečten, ale obsahuje blokující obsahový problém,
- `64` – chybný nebo zakázaný parametr, duplicitní `--input`, neexistující či
  nečitelná lokální cesta nebo cesta k jinému typu souboru než `.csv`/`.xml`.

## Bezpečnostní hranice

- pouze CLI, při webovém spuštění vrací skript 404,
- pouze lokální regulární `.csv` nebo `.xml` soubor, nikdy URL ani symlink,
- nejvýše 10 MiB, 10 000 datových řádků, 200 sloupců a 64 KiB na pole,
- podporované kódování je UTF-8 (včetně BOM) a Windows-1250,
- žádný `db.php`, `config.php`, session, síťový požadavek nebo zápis souboru,
- obrázky se pouze evidují jako textové URL a nestahují se,
- hodnoty podobné tabulkové formuli se nikdy nevyhodnocují a vyvolají varování,
- HTML popis zůstává nedůvěryhodným textem a nesmí se bez sanitizace vykreslit.

XML navíc odmítá `DOCTYPE` a deklarace entit a používá zákaz síťového načítání přes
libxml. Elementy obrázků jsou pouze zaznamenány jako URL; soubory se nestahují.

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

## Normalizace systémového XML exportu

Každý `SHOPITEM` se seskupuje podle stabilního atributu `id`. Produkty bez variant
vytvoří jednu katalogovou variantu; elementy `VARIANTS/VARIANT` vytvoří samostatná
SKU. Přenášejí se názvy, SKU, cena, měna, sklad, viditelnost, kategorie, obrázky a
nejvýše tři parametry varianty.

`PRICE` je podle kontraktu Shoptetu cena bez DPH a dry-run proto nastaví
`includes_vat=false`. Pokud export obsahuje `PRICE_VAT`, označí cenu jako cenu s
DPH. `PURCHASE_VAT` je sazba nákupní ceny a záměrně se nepoužívá jako sazba DPH
prodejní ceny. Dry-run žádnou chybějící sazbu nedopočítává.

Komerčně významná XML pole, která současný katalogový model ještě neumí bezpečně
přenést, se nezahazují potichu. `ACTION_PRICE`, `STANDARD_PRICE`, jednotka,
dostupnost a aktivní bezplatná doprava nebo platba se převedou na explicitní
nepodporované sloupce a kontrakt je zablokuje do jejich vědomého namodelování.

Ověřený klubový export z 2. srpna 2026 obsahuje 241 produktů a 807 variant. Tři
položky půjčovny mají `PRICE_RATIO=0` a vyžadují rozhodnutí o skutečné ceně. Dvě
položky zůstávají záměrně v ruční klasifikaci: tričko zařazené současně mezi
oblečení a kroužky a pronájem velodromu zařazený současně mezi zážitky a pronájem.
Skutečný export zůstává v `var/imports/` a nepatří do Gitu.

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
  půjčovna, individuální nabídka a záměrně konfliktní klasifikace,
- `products-export.xml` – fiktivní XML produkt bez variant a produkt se dvěma
  variantami, kategoriemi, obrázkem, skladem a DPH.

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

## Jak export později obnovit ze Shoptetu

Pro další kontrolu lze v administraci vytvořit vlastní produktový export ve formátu
CSV nebo znovu stáhnout kompletní systémový XML export. Menší kontrolní výběr má
zahrnovat:

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
