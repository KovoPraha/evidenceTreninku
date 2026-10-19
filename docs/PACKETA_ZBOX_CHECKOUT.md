# Zásilkovna Z-BOX v e-shopu

## Zákaznický výběr

- Nastavení je v administraci na `eshop_packeta_admin.php`; defaultně je doprava vypnutá.
- Správce zadá cenu celé objednávky a veřejný 16znakový klíč widgetu. Nejde o heslo k API.
- Při způsobilém zbožovém košíku zákazník v členské i rychlé pokladně volí osobní odběr nebo Zásilkovnu. Widget je omezený na české Z-BOXy.
- Při checkoutu server kontroluje cenu z administrace a bod podle Zásilkovny; nepřebírá název/adresu z prohlížeče. Do objednávky ukládá bod jako neměnný snímek.
- Uloží se i telefon příjemce. Doprava zůstává nedostupná pro objednávky akcí, lekcí a programů.

Z-BOXy jsou ověřeny proti veřejnému feedu Packeta v5 (`country=cz`, `lang=cs`); server vybírá pouze aktivní, zobrazitelné záznamy typu `zbox`. Feed se cachuje čtyři hodiny. Když feed není dostupný nebo je bod neplatný, checkout selže s možností zvolit osobní odběr.

## Příprava a podání zásilky

1. Objednávka musí být zaplacená. Provozovatel ji zabalí a označí jako připravenou.
2. V Klientu Zásilkovny ručně vytvoří zásilku, vytiskne štítek a do administrace objednávky uloží tracking barcode a devítimístný podací kód. Zatím se zásilka nevytváří voláním API.
3. Balík vloží do libovolného Z-BOXu podle aktuálních pokynů Zásilkovny. Po zadání devítimístného kódu musí fyzicky potvrdit uložení/zavření schránky.
4. Až poté v administraci potvrdí podání. Objednávka přejde do dokončeného stavu a zákazník dostane tracking a zvolený cílový Z-BOX. Devítimístný kód se při potvrzení smaže; není v poznámce události ani v e-mailu.

Kód je dočasně viditelný jen administrátorům, kteří zpracovávají objednávky. Do potvrzení fyzického podání lze objednávku stornovat; před stornem je třeba ručně řešit již vytvořenou zásilku v Klientu Zásilkovny.

Packeta API nemá sandbox. Před zapnutím je nutné dokončit reálnou konfiguraci klíče widgetu, cenu a provozní ověření. Testovací zásilky tvořte pouze ve vyhrazeném klientském účtu a nevkládejte je do přepravní sítě. Než bude automatizováno zakládání zásilek/štítků, je potřeba samostatně rozhodnout integraci API, obchodní přihlašovací údaje, hmotnosti/rozměry variant, storno a refund chování.

## Oficiální podklady

- [Packeta API – testování](https://docs.packeta.com/cs/docs/getting-started/packeta-api)
- [Výběr výdejního místa a widget](https://docs.packeta.com/guides/integration-by-service/packeta-pudo)
- [Feed výdejních míst a jeho aktualizace](https://docs.packeta.com/docs/pudo-delivery/packeta-pudos)
- [Podání zásilky do Z-BOXu](https://docs.packeta.com/cs/guides/box-consignment)
