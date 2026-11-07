# Etapa 3 — skladový model a přejímka

> Historický návrhový a přejímací podklad z 22. 10. 2026, sdílený v Gitu od 7. 11.
> Původní formulace „plánováno“ a „neimplementováno“ níže popisují datum vzniku.
> Technické výsledky R01–R44 a provozní meze ověřujte v [současném přehledu](../README.md).
> Tento přenos nepovoluje nové produkční operace a není novým datovým auditem.


Příloha k [plánu etapy 3](00_ETAPA_3_PLAN.md), 22. 10. 2026. Jde o návrh budoucího chování, nikoli o implementovanou datovou strukturu nebo provedené testy.

## Rozhodnutí na začátku etapy

| Oblast | Doporučené minimum | Zbývá potvrdit |
|---|---|---|
| Sortiment | Fyzické zboží po variantách; služby bez skladu | Které položky opravdu sledujeme a zda existují předobjednávky |
| Jednotka | Oblečení v celých kusech | Případné jiné jednotky u ostatního sortimentu |
| Místa | Jeden hlavní sklad a blokovaný stav | Reálné zásoby mimo klub a prodej na akcích |
| Výdej | Až po potvrzené úhradě | Zda oddíl potřebuje výslovnou výjimku s pohledávkou |
| Osobní prodej | Člen i veřejnost; bez povinného zakládání sportovce | Kdo smí prodat, přijmout hotovost a přiznat individuální slevu |
| Ocenění | Doložená pořizovací cena, samostatně od prodejní | Metoda výdeje, vedlejší náklady, vratky a pozdní opravy s účetní |
| Vrácení | Oddělený zákaznický případ, fyzické kusy, doklad a peníze | Převzetí, lhůty, odpovědnost a pravidla dobrovolných výměn |
| Doprava | Osobní odběr; používaný dopravce s ručním štítkem | Skutečně používané kanály a jejich provozní konfigurace |
| Inventura | Krátké řízené pozastavení pohybů, počítání a schválení | Termín, rozsah, schvalující a náhradní postup |
| Přechod | Jeden autoritativní systém pro nové rezervace | Rozhodný okamžik, otevřené objednávky Shoptetu a ověřené počátky |

## Navržený význam skladových čísel

Pro jednu variantu a evidované místo:

- **P — fyzicky:** vlastní kusy skutečně přítomné v daném skladu, včetně připravených rezervovaných a blokovaných kusů.
- **R — rezervováno:** platná dosud nevydaná rezervace prodejného zboží.
- **B — blokováno:** vlastní kusy, které nelze nyní nabídnout, například poškozené nebo čekající na kontrolu.
- **A — dostupné:** volně prodejné množství, `A = P − R − B`.

Rezervované a blokované množství se nesmí překrývat. Přesun rezervovaného kusu do blokace musí současně vyřešit jeho rezervaci a nesplněný zákaznický příslib; prosté dvojí odečtení není přípustné. Nedostatek se ukáže jako problém, nikoli jako povolený prodej záporné dostupnosti.

Zboží zákazníka převzaté pouze k posouzení reklamace má samostatnou evidenci převzetí do péče. **Není automaticky vlastním zbožím oddílu.** Obdobně se zvlášť sleduje zásilka již předaná dopravci; není fyzicky na klubovém skladě. Účetní okamžik zachycení plnění se řídí metodikou etapy 2, nikoli pouhou shodou s interním názvem stavu.

U nesledované služby se tato čísla neuplatňují. U fyzického zboží nelze „nevíme množství“ zaměnit s vědomým režimem nesledovaného skladu.

## Události a jejich účinky

Tabulka popisuje model, který má být doplněn. Většina běžných událostí využije stávající audit a transakční základ.

| Událost | P | R | B | Dopad na A a důkaz |
|---|---:|---:|---:|---|
| Ověřený počátek | Podle inventury | Podle otevřených případů | Podle kontroly | Počáteční protokol a ocenění; není nový nákup ani prodej |
| Příjem prodejného zboží q | +q | 0 | 0 | +q; příjemka a dodavatelský podklad |
| Příjem vadných vlastních kusů q | +q | 0 | +q | 0; zboží není nabízené, čeká na vyřešení |
| Rezervace objednávky q | 0 | +q | 0 | −q; vazba na konkrétní dosud nevydané položky |
| Zaplacení objednávky | 0 | 0 | 0 | 0; peníze samostatně, existující rezervace zůstává |
| Označení připraveno | 0 | 0 | 0 | 0; zboží ještě neopustilo sklad |
| Skutečný výdej rezervovaných q | −q | −q | 0 | 0; výdejka, osoba/kanál a čas |
| Uvolnění nevydané rezervace q | 0 | −q | 0 | +q; důvod, storno či expirace |
| Přijetí žádosti o vrácení | 0 | 0 | 0 | 0; případ a termíny, zatím žádný skladový pohyb |
| Převzetí cizího kusu k reklamaci | 0 | 0 | 0 | 0; samostatná evidence péče, identita a stav kusu |
| Fyzický návrat a převzetí zpět do vlastních zásob q | +q | 0 | +q | 0 do kontroly; vazba na původní výdej, bez dvojího převzetí z evidence péče |
| Uvolnění vlastního vráceného zboží do prodeje q | 0 | 0 | −q | +q; výsledek kontroly a oprávněná osoba |
| Blokace dosud volných vlastních kusů q | 0 | 0 | +q | −q; doložený důvod |
| Odpis blokovaných kusů q | −q | 0 | −q | 0; schválení a ocenění odpisu |
| Výdej pro vlastní spotřebu z volných kusů q | −q | 0 | 0 | −q; účel/příjemce, ocenění, nevytváří tržbu |
| Vratka peněz nebo opravný doklad | 0 | 0 | 0 | 0; vazba na případ, nikdy automatický fyzický příjem |
| Přesun mezi vlastními místy q | −q/+q | Podle výslovného přesunu rezervace | Podle stavu | Součet vlastních zásob beze změny; případné zboží na cestě má vlastní evidenci |
| Inventurní oprava | Schválený rozdíl | Bez tichého rušení | Podle skutečnosti | Inventurní doklad; samostatné řešení nedostatku proti rezervacím |

**Kontrolní příklad:** 10 kusů fyzicky, 0 blokovaných. Rezervace 3 → P=10, R=3, A=7. Výdej 2 → P=8, R=1, A=7. Přijatá vratka jednoho vydaného kusu do vlastních blokovaných zásob → P=9, R=1, B=1, A=7. Po kontrole a uvolnění kusu → P=9, R=1, B=0, A=8. Finanční vratka žádné z těchto množství sama nemění.

U výměny M za L proběhne návrat M a samostatná rezervace/výdej L, nikoli změna kódu původního výdeje. U částečného plnění se agregovaný stav objednávky odvodí z položek; některé mohou být vydané, jiné dosud rezervované a jiné zrušené.

## Minimální obsah evidence

| Objekt | Povinné údaje návrhu |
|---|---|
| Varianta | Stabilní ID a SKU, produkt, velikost/barva, jednotka a povolená přesnost, skladový režim, aktivita; cena odděleně od pořizovací hodnoty |
| Rezervace | ID, objednávka/položka, varianta, místo, původní a zbývající množství, stav, vznik/platnost, důvod změny, návazné výdeje/uvolnění |
| Pohyb | ID, typ, varianta, množství, místo a stav před/po, čas události a evidence, pracovník, důvod, zdrojový doklad/položka, opravený pohyb, jedinečný klíč operace |
| Příjemka | ID/číslo, dodavatel, jeho doklad, skutečné datum, pracovník, položky, převzaté/odmítnuté množství, ocenění a stav doložení |
| Výdejka | ID/číslo, objednávka/položky nebo interní účel, příjemce/kanál, skutečné množství, vydávající, čas a důkaz předání |
| Vratkový/reklamační případ | ID, původní prodej/výdej, zákazník, požadavek a jeho čas, druh řešení, položky/množství, převzetí a stav kusů, vlastnictví, odpovědný, termíny a výsledek |
| Výměna | Původní vratkový případ, nová varianta/výdej, dohoda o ceně, případný doplatek či vratka a jejich účetní vazby |
| Inventura | ID, místo/rozsah, rozhodný čas, režim přerušení pohybů, evidovaný a napočítaný stav, rozdíl/důvod, počítající, schvalující, výsledné pohyby a ocenění |

Technický návrh může rozšířit stávající tabulky nebo přidat potřebné vazby. Přejímka vyžaduje uvedené chování, ne předem přikázaný počet tabulek. Vydané skladové doklady musí mít dohledatelnou opravu a archiv stejně jako účetní podklady.

## Návaznost na etapu 2

| Fyzická událost | Účetní a peněžní návaznost | Vlastník řešení |
|---|---|---|
| Příjem zboží | Přijatý doklad, závazek, pořizovací hodnota; platba může přijít jindy | E3-04 + E2-09 |
| Rezervace | Provozní dostupnost; sama nevytváří peníze ani fyzický výdej | E3-05 |
| Prodej a předání | Jedna pohledávka, prodejní doklad podle metodiky, skutečná úhrada zvlášť | E3-06/07 + E2-06/07 |
| Částečný návrat zboží | Položková oprava a případná peněžní vratka, původní prodej zachován | E3-09 + E2-08 |
| Výměna | Původní návrat a nové plnění; cenový rozdíl doložený, nezapočítávat odhadem | E3-09 + E2-08 |
| Odpis / inventurní rozdíl | Množstevní a hodnotový podklad, schválené členění | E3-10/11 + E2-03/11 |
| Přesun vlastních zásob | Změna místa, bez nové tržby či nové dodavatelské faktury | E3-11 |
| Skladový výstup měsíce | Počáteční stav + fyzické příjmy − fyzické výdeje ± opravy = konec; ocenění samostatně kontrolované | E3-13 + E2-11/12 |

Výpočet peněz patří do etapy 2; E3 jej neduplikuje. Obdobně se skladovým pohybem nezakládá druhý souběžný prodejní doklad. Vrácení peněz se nesmí bez opory v pravidlech zdržovat jen tím, že obsluha dosud neuzavřela interní kontrolu zboží; úkoly případu mají vlastní lhůty a návaznosti.

## Budoucí přejímací scénáře

Všechny nové zápisy patří do izolovaného prostředí se syntetickými daty. Žádný scénář níže nebyl při této přípravě spuštěn. Protokol později uvede revizi, prostředí, datum, zkoušejícího, výsledek a důkaz.

| ID | Úkol | Příklad a očekávaný výsledek |
|---|---|---|
| C01 | E3-01 | Tričko ve velikostech M/L má různá SKU; 0, nesledovaný a neověřený stav se nezamění. Neověřená fyzická zásoba nepovolí neomezený nákup. |
| C02 | E3-01 | Zadat 0,5 kusu oblečení: server odmítne. U jiného sortimentu se množství řídí jeho výslovnou jednotkou. |
| C03 | E3-02/03 | Převod varianty s otevřenou, dokončenou a stornovanou objednávkou: počátek souhlasí s fyzickým počtem, historické rezervace se nepřičtou plošně. |
| C04 | E3-04 | Objednáno u dodavatele 10, dorazí 8: sklad přijme 8, rozdíl je dohledatelný. Dopříjem 2 se zapíše právě jednou. |
| C05 | E3-04 | Přijato 8, z toho 1 vadný: fyzicky 8, blokováno 1, dostupné nejvýše 7. |
| C06 | E3-04/13 | Zboží dorazí bez faktury a cena se doplní později: množství je pravdivé, hodnota do doložení označená neuzavřená; žádná falešná nula. |
| C07 | E3-04/13 | Oprava pořizovací ceny po prvním výdeji: zachovaná původní historie, kontrolovaná hodnotová oprava a dohledatelné dopady na export. |
| C08 | E3-05 | Web a osobní prodej současně rezervují poslední L: uspěje právě jeden, druhý nedostane falešné potvrzení ani záporný sklad. |
| C09 | E3-05 | Nezaplacená objednávka expiruje a dávka běží znovu: uvolnění rezervace právě jednou, fyzicky se nic nepřijímá. |
| C10 | E3-05 | Zaplacená objednávka čeká na vyzvednutí: neexpiruje jako nezaplacená a zboží zůstává rezervované. |
| C11 | E3-05 | Částečná/pozdní úhrada po uvolnění zboží: příjem peněz se neztratí; případ čeká na rozhodnutí a nevydá již nedostupný kus. |
| C12 | E3-06 | Veřejnost koupí tričko za hotové: bez sportovního účtu, správná cena, jeden prodej, příjem, doklad a výdej. |
| C13 | E3-06 | Člen s platným zařazením a osoba bez nároku: stejné pravidlo členské ceny na webu i u obsluhy; ruční výjimka má schválení a důvod. |
| C14 | E3-06/14 | Spojení vypadne po uložení, obsluha zopakuje úkon: stejný výsledek bez druhého výdeje či příjmu; stav fyzicky převzaté hotovosti je dořešitelný. |
| C15 | E3-07 | Ze 3 rezervovaných kusů vydány 2: fyzicky −2, rezervováno −2, dostupné podruhé neklesá; 1 zbývá k výdeji. |
| C16 | E3-07 | Opakované potvrzení stejné výdejky: žádný další pohyb. Pokus vydat více než zbývá je odmítnut. |
| C17 | E3-07 | Objednávka obsahuje kroužek a zboží v povolené kombinaci: fyzický výdej se týká jen zboží. Pokud kombinace není podporovaná, rozdělení je srozumitelné před nákupem. |
| C18 | E3-08 | Vytvořený štítek bez předání: zásilka není vydaná. Po podání je dohledatelné předání dopravci, nikoli falešný důkaz převzetí zákazníkem. |
| C19 | E3-08/09 | Nevyzvednutá zásilka se vrací: sklad se nezvýší při pouhé zprávě o vrácení; fyzický příjem a kontrola rozhodnou o dalším stavu. |
| C20 | E3-08 | Neplatný výdejní bod nebo nedostupná služba: žádná částečná objednávka/rezervace; uživatel dostane možnost dostupného způsobu odběru. |
| C21 | E3-09 | Zákazník vrací jedno ze dvou vydaných triček: příjem 1 kusu, správná oprava ceny a samostatná skutečná vratka; druhý kus zůstává prodaný. |
| C22 | E3-09 | Žádost o vrácení bez odevzdání zboží: fyzický sklad beze změny, případ a lhůty evidované. Peněžní postup se řídí příslušnými pravidly a důkazy. |
| C23 | E3-09 | Poškozený vrácený kus: nedostane se automaticky do prodejné nabídky; doložené rozhodnutí blokace/oprava/odpis. |
| C24 | E3-09 | Výměna M za L při posledním L: nový kus je bezpečně rezervovaný, M projde posouzením; historie a případný doplatek/vratka souhlasí. |
| C25 | E3-09 | Opakovaná vratka stejného již vráceného kusu: systém zabrání překročení dosud vratitelného množství i peněžní částky. |
| C26 | E3-09 | Převzetí kusu pouze na reklamaci: evidovaný cizí kus, žádné zvýšení vlastních prodejných zásob a žádné automatické uznání reklamace. |
| C27 | E3-09 | Online nákup s osobním odběrem, běžná velikost: přijetí požadavku na odstoupení není automaticky zamítnuto kvůli způsobu odběru či volbě velikosti. |
| C28 | E3-10 | Inventura 10 fyzických kusů, z nichž 3 rezervované: rezervované kusy jsou započítané do fyzického počtu, ne odečtené jako manko. |
| C29 | E3-10 | Napočítáno méně kusů, než pokrývají rezervace: viditelná výjimka a rozhodnutí, žádné tiché mazání rezervací. |
| C30 | E3-10/14 | Starý inventurní formulář po novém prodeji: konflikt nebo doložené započtení mezitím vzniklých pohybů; žádné přepsání nového stavu. |
| C31 | E3-11 | Trenér vezme 10 triček na prodejní akci, prodá 4 a vrátí 6: přesun není prodej; souhlasí výdeje 4, návrat 6 a skutečná hotovost. |
| C32 | E3-11/13 | Bezplatný výdej pro oddíl a odpis vady: schválené samostatné účely, správné množství/hodnota a žádná fiktivní tržba. |
| C33 | E3-12 | Skladník na telefonu hledá velikost: vidí fyzicky/rezervováno/blokováno/dostupné a konkrétní případ k dořešení. |
| C34 | E3-13 | Platba v říjnu, výdej v listopadu, vratka v prosinci: každý měsíc má správné fyzické pohyby a samostatné peněžní/dokladové vazby. |
| C35 | E3-14 | Skladník zkusí potvrdit peněžní vratku bez oprávnění; zákazník cizí výdejku: server odmítne i přímý požadavek. |
| C36 | E3-14 | Obnova skladu s otevřenými rezervacemi a vratkami: zůstatky, historie a soubory souhlasí, žádný automatický prodej či odeslání zpráv. |
| C37 | E3-15 | Přechod Shoptet → KIS se stejnou poslední zásobou: v rozhodném okamžiku je jediný autoritativní zdroj nových rezervací a převzaté otevřené případy. |
| C38 | E3-15 | Skladník a účetní zopakují celý cyklus příjem → prodej → výdej → výměna/vratka → inventura → export: vysvětlí každý rozdíl bez zásahu vývojáře. |

Scénáře pro nepoužívanou dopravu nebo interní přesuny lze označit „mimo pilot“ s důvodem a vypnutou funkcí; nelze tak vynechat agendu, kterou oddíl v pilotu skutečně provozuje. Automatické testy doplnit cíleně pro nové invarianty a souběhy, uživatelskou přejímku provést i s obsluhou.

## Výstupní brány

1. **Návrh připraven:** dohodnuté varianty, význam množství, oceňování, role, události a vztah k dokladům. Tento dokument nezakládá autorizaci implementovat.
2. **Implementace převzata v testu:** model a relevantní scénáře procházejí na známé revizi; nejsou otevřené zásadní chyby množství, duplicit nebo přístupů.
3. **Připraveno na skutečný přechod:** ověřený fyzický počátek, otevřené případy, účetní hodnoty, obsluha, náhradní postup a jeden zdroj rezervací. Chybějící ocenění je výslovný účetní blokátor, nikoli „sklad hotový“.
4. **Převzato v provozu:** až po autorizovaném přechodu prokázané skutečné postupy v dohodnutém rozsahu. Samotné nasazení, dostupná stránka či import katalogu tuto bránu nenahrazují.

## Ověřené zdroje

Odkazy míří na kontrolovanou neměnnou revizi. Existence kódu neprokazuje aktuální aktivaci dopravce ani výsledek nové přejímky.

| ID | Podklad | Co dokládá |
|---|---|---|
| Z1 | [Rezervace v checkoutu](https://github.com/KovoPraha/evidenceTreninku/blob/68b22bd2b3b666dbb4fb571a2731eae5b7f056d9/includes/shop_checkout.php#L393), [rychlý nákup bez účtu](https://github.com/KovoPraha/evidenceTreninku/blob/68b22bd2b3b666dbb4fb571a2731eae5b7f056d9/includes/shop_guest_checkout.php#L154) | Množství se odečítá při objednání a vzniká `reserve`, také u hosta. |
| Z2 | [Storno](https://github.com/KovoPraha/evidenceTreninku/blob/68b22bd2b3b666dbb4fb571a2731eae5b7f056d9/includes/shop_checkout.php#L927), [uvolnění rezervace](https://github.com/KovoPraha/evidenceTreninku/blob/68b22bd2b3b666dbb4fb571a2731eae5b7f056d9/includes/shop_checkout.php#L1157) | Povolené stavy před dokončením a návrat objednaného množství právě jednou; není obecný návrat vydaného kusu. |
| Z3 | [Příprava a osobní výdej](https://github.com/KovoPraha/evidenceTreninku/blob/68b22bd2b3b666dbb4fb571a2731eae5b7f056d9/includes/shop_checkout.php#L1025) | Přechody celé objednávky, kontrola platby, audit a odmítnutí kroužkových objednávek; nikoli položková výdejka. |
| Z4 | [Původní tabulka pohybů](https://github.com/KovoPraha/evidenceTreninku/blob/68b22bd2b3b666dbb4fb571a2731eae5b7f056d9/migrations/20260803230000_shop_checkout.php#L220), [doplnění správy katalogu](https://github.com/KovoPraha/evidenceTreninku/blob/68b22bd2b3b666dbb4fb571a2731eae5b7f056d9/migrations/20260817110000_shop_catalog_admin.php) | Existující pohybový základ; původní unikátnost položka + typ a následné rozšíření pro ruční korekce. |
| Z5 | [Korekce skladu](https://github.com/KovoPraha/evidenceTreninku/blob/68b22bd2b3b666dbb4fb571a2731eae5b7f056d9/includes/shop_catalog_management.php#L95), [kontrola očekávaného stavu](https://github.com/KovoPraha/evidenceTreninku/blob/68b22bd2b3b666dbb4fb571a2731eae5b7f056d9/includes/shop_admin_catalog.php#L94), [auditní pohyb](https://github.com/KovoPraha/evidenceTreninku/blob/68b22bd2b3b666dbb4fb571a2731eae5b7f056d9/includes/shop_manual_catalog.php#L469) | Korekce s důvodem, transakcí, auditovaným rozdílem a ochranou proti zastaralému formuláři. |
| Z6 | [Expirace](https://github.com/KovoPraha/evidenceTreninku/blob/68b22bd2b3b666dbb4fb571a2731eae5b7f056d9/includes/shop_checkout.php#L745) | Náhled, dávka a kontrolovaný přechod nezaplacené objednávky. Není důkazem běhu produkčního plánovače. |
| Z7 | [Členské ceny](https://github.com/KovoPraha/evidenceTreninku/blob/68b22bd2b3b666dbb4fb571a2731eae5b7f056d9/includes/shop_member_pricing.php), [správa skladu](https://github.com/KovoPraha/evidenceTreninku/blob/68b22bd2b3b666dbb4fb571a2731eae5b7f056d9/eshop_sklad_admin.php), [správa variant a množství](https://github.com/KovoPraha/evidenceTreninku/blob/68b22bd2b3b666dbb4fb571a2731eae5b7f056d9/includes/shop_manual_catalog.php#L375) | Existující cenová pravidla a UI; obecný parser dovoluje desetinné množství. |
| Z8 | [PPL podání](https://github.com/KovoPraha/evidenceTreninku/blob/68b22bd2b3b666dbb4fb571a2731eae5b7f056d9/includes/shop_checkout.php#L1068), [Packeta příprava/podání](https://github.com/KovoPraha/evidenceTreninku/blob/68b22bd2b3b666dbb4fb571a2731eae5b7f056d9/includes/shop_checkout.php#L1090), [postup Packety](https://github.com/KovoPraha/evidenceTreninku/blob/68b22bd2b3b666dbb4fb571a2731eae5b7f056d9/docs/PACKETA_ZBOX_CHECKOUT.md) | Ruční štítky a evidence podání; `completed` vzniká při podání, nikoli z potvrzeného převzetí zákazníkem. |
| Z9 | [Potvrzení peněžní vratky](https://github.com/KovoPraha/evidenceTreninku/blob/68b22bd2b3b666dbb4fb571a2731eae5b7f056d9/includes/shop_checkout.php#L989) | Ověření již provedené celé vratky po stornu; neodesílá peníze a nenahrazuje fyzickou vratku. |
| Z10 | [Testy checkoutu](https://github.com/KovoPraha/evidenceTreninku/blob/68b22bd2b3b666dbb4fb571a2731eae5b7f056d9/tests/Integration/ShopCheckoutTest.php), [MariaDB souběhy](https://github.com/KovoPraha/evidenceTreninku/blob/68b22bd2b3b666dbb4fb571a2731eae5b7f056d9/tests/Support/ShopCheckoutMariaDbSmoke.php) | Existující testovací základ. V tomto dokumentačním úkolu nebyl znovu spuštěn. |
| Z11 | [Účetní plán etapy 2](../etapa-2-2026-10-22/00_ETAPA_2_PLAN.md), [kontrakt exportu](../etapa-2-2026-10-22/01_KONTRAKT_EXPORTU_PRIJEMKA_A_ZDROJE.md) | Vlastnictví finančních vratek, dokladů, cenové metodiky a listu Sklad. |

Kontrola pojmů vrácení vychází z [oficiálního přehledu ČOI](https://coi.gov.cz/faq/7-odstoupeni-od-smlouvy-do-14-dnu-u-zbozi/). Konkrétní pravidla prodejce, lhůty a výjimky musí být správně promítnuté do postupů; systém nemá automaticky posuzovat všechny případy podle jediného příznaku „vratka“.

## Omezení a skutečně provedená práce

Byl prověřen zdrojový kód, navazující zadání a příslušné podklady. Při hledání skladových postupů nebyla v prověřené implementaci doložena úplná agenda příjemek, pořizovacích cen, položkových výdejek a inventury; současné obecné korekce se za tyto agendy nepovažují.

Vznikly pouze dva místní dokumenty. Nebyla provedena inventura skutečných zásob, změna katalogu/skladu, objednávka, úhrada, refundace, zásilka, import, aktivace služby ani nasazení. Zapsané scénáře jsou přejímací zadání, nikoli výsledky jejich běhu.
