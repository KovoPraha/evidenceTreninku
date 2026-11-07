# Kalibrace a hranice P11-SCALE

Studie se spouští nad neměnnou aplikací 69f49033. Časy jsou orientační pro dané
nevyhrazené Windows PC; rozhodující strukturální důkazy jsou řádkové čítače,
objem HTML, špička PHP a shoda výstupů. Nejde o produkční SLA nebo před/po opravy.

- První příprava po FullHttpReadFixture použila staré CLI ID registráře 7.
  Fixture už má role oddělené, takže autorizační kontrola správně odmítla
  vytvoření politiky. Opraveno na skutečné ID podle generovaných credentials.
  Nebyl změřen žádný HTTP vzorek; vlastní syntetická zdravotní data se znovu založila.
- Druhá příprava odhalila FK nabídky na původní ukázkovou soupisku. FK nebyla
  vypnutá ani odstraněná. Nová rozšíření používají pouze vyhrazená ID 10000–10049;
  původní ukázkové soupisky zůstávají a počty je výslovně zahrnují.
- První dokončený pomalý per-row 10k seed byl jen příprava, jeho protokol zůstal
  v ignorovaném výstupu mimo časové reporty. Před jakýmkoli časováním proběhl
  nový dávkový seed, stejným postupem jako všechny další sady. Tři stavové
  záznamy/sledování a pořadí jejich historie zůstaly stejné; žádná produkční data.
- Při přípravě zdrojových otisků byl chybný název auth.php. Nejde o soubor
  aplikace. Export opraven na 16 skutečných souborů a jejich git blob shodu
  ještě před prvním prostým HTTP časováním. Aplikace se neupravovala.
- Mezi první sadou a dalšími se zpřísnila pouze vstupní kontrola SELECT-only
  grantu v měřiči; vlastní časovací smyčka se neměnila. Základní sada už měla
  grant skutečně odvolaný před měřením a diagnostika prokázala nula zápisů.
- Jednorázový export definic/indexů z vlastního serveru proběhl při začátku
  časování 50k. Jeho vliv na časy není vyčíslený; časové poměry nejsou kontrolovaný
  kauzální experiment. Všechny vzorky zůstávají, žádné odlehlé hodnoty se nevyhazují.
  Budoucí opakování má metadata zachytit ještě před časováním.
- PHP 1G limit slouží jen CLI paritě se zmrazeným původním seznamem, který
  načítá velké pole. Tato parita a SQL projekce jsou mimo prosté HTTP časování.
  Diagnostický prepend/general/slow log se nikdy nepřičítá do prostých časů.
- Před dalšími restartovanými fázemi byl v testovacím PHP serveru prodloužen
  session.gc_maxlifetime z 1440 na 14400 s, aby dlouhý běh náhodným GC neodstranil
  nepoužívanou standardně přihlášenou relaci. Zdravotní prosté běhy do 100k měly
  původních 1440 s; od 100k diagnostiky 14400 s. Aplikační auth/idle/role guardy
  se nemění a žádný obsah session se nevyrábí ani neupravuje.
- Raw JSON neobsahují config, cookie jars ani hesla; do Gitu se archivuje
  explicitní whitelist až po kontrole skutečných dočasných secret hodnot.
- Testovací legacy schéma není kopií produkce: například index plánů začíná
  veřejností a ostatní legacy indexy produkce se automaticky nekopírují.
  Aktuální definice/indexy devíti relevantních tabulek jsou v schema-metadata.json.
- Serverové Rows_examined jsou reálné čítače daného schématu, nikoli EXPLAIN
  odhad ani počet všech fyzických I/O. Rows_sent nejsou totéž jako PHP fetched rows.
  Slow log může mít navíc protokolové Quit s 0/0 řádky, proto počty jeho položek
  nemusí být totožné s Query/Execute z general logu.
- Členství a počet podřízených soupisek rostou společně. Historie a zdravotní
  objemy jsou oddělené osy, ale tato studie neizoluje členy od šířky hierarchie.
- Nový výkon prohlížeče, fyzický mobil, síť/CPU limit, studený DB/OS start,
  dlouhé řetězce zdravotních náhrad a proměnlivá historická topologie nejsou měřené.
- Souběžná optimalizace plánovače v PR #374 patří jiné relaci. Nesmí se
  zaměnit její kód/deploy s zde měřenou aplikací 69f49033.

Další případné neúplné běhy a změny rozsahu musí být doplněné před předáním.

## Neúplná 100k fáze a vlastní tmpdir

První 100k časování mělo dva kompletní případy a při Odvoláno skončilo HTTP500:
MariaDB HY000 error6, OS error2, chybějící uživatelský TEMP #sql…MAI soubor.
Příčina mizení tohoto souboru není určená. Nešlo o porušení doménové parity.
Vlastní web se ve finally zastavil, všech21 doménových checksumů zůstalo shodných.
Před/po restartu pouze vlastního mysqld a po obnovení12 standardních přihlášení
jsou kontrolní body shodné. Vlastní my.ini dostal tmpdir uvnitř výhradně vlastního
runtime; společná DB ani globální uživatelský TEMP se neopravovaly či nemazaly.
Po restartu se celá100k sada opakuje včetně zahřátí, parity a diagnostiky.
Neúplné časování a chybový výřez jsou oddělený artefakt mimo finálních2160vzorků.
První pokus znovupřihlásit aktivního klienta správně vracel login302; vlastní
cookie jars byly zahájeny znovu, poté všech12 nativních formulářových loginů PASS.
Žádné session se nevyráběly ani obsahově neupravovaly. SELECT-only obnovený před měřením.
