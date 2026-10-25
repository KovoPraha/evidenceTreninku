# Balík C — Akce a klubová administrativa

24. 10. 2026. **Implementováno a ověřeno lokálně, NENASAZENO.** Rozsah F06,
F07 a přehledy odměn podle [funkčního plánu](../../NONSHOP_FUNCTIONAL_ANALYSIS_2026-10-24.md).
Větev `codex/event-administration-20261024`, navazuje na balík B `f521a10d`.
Pracovní kopie: `C:\xampp\htdocs\evidencePavel-finance-b1-20261022`.

## Výsledek pro obsluhu

### Jedna akce a navazující práce

Nová stránka **Zajištění akce** (`club_event_work_admin.php`) je v pracovním
menu a v detailu kalendáře. Akce lze hledat názvem, obdobím a stavem; výsledky
i účastníci se stránkují po 25. Zobrazuje termíny, zajišťující pracovníky,
rezervace vozidel, plánovanou účast a poslední doloženou sportovní skutečnost.

U každého účastníka vede na stávající formulář sportovní činnosti se správnou
osobou a akcí. Zápis je jednotlivý, s důvodem, podkladem a skrytou revizí.
Nominace se nepřevádí na start. Z formuláře lze přejít zpět na účastníky akce.

Trenér musí mít aktuální pověření k akci a současný rozsah pro konkrétní osobu.
Odvolání jedné z těchto vazeb se projeví při dalším načtení. Vedoucí sportu a
hospodář mají svůj existující pracovní rozsah. Registráři se přístup nepřidává.
Trenérský přehled neobsahuje soukromé kontakty, podklady účtenek, příjemce plateb
ani peněžní pohyby. U vyúčtování ukazuje pouze název, vlastníka, termín a stav
případu, počet přímo navázaných dokladů k posouzení a otevřených záloh.

Hospodář otevře detail vyúčtování nebo nový finanční případ s předvyplněnou
akcí. Původní provozní akce se připojuje výslovně pojmenovaným výběrem;
žádná se automaticky nezakládá ani nepáruje. Existující finanční případy jsou
viditelné před založením dalšího. Více případů jedné klubové akce zůstává
povolené podle stávajícího modelu; starší provozní vazba je nadále jedinečná.

Rezervace vozidla je označena jako rezervace. Hospodář může otevřít skutečné
jízdy daného vozidla. **Nevytváří se nová vazba jízda–akce**: shoda vozidla a času
není doklad příslušnosti jízdy. Zajištění řidiče, jízda a výdaj zůstávají oddělené.

### Výdaj, příloha, záloha a vypořádání

Ve **Výdajích a zálohách** jsou pojmenované výběry osob, akcí, případů, účtenek,
verzí, záloh, peněžních pohybů a opravných přídělů. Běžná obsluha neopisuje ID.
Nabídky nejsou omezeny na posledních 100/200 položek; obsahují i starší záznamy.
Peněžní pohyb je označen datem, referencí, částkou a již přiřazenou částí.
Volnou část po všech rezervacích a opravách autoritativně kontroluje původní
funkce při uložení, přehled neslibuje dostupnost jen podle jednoho součtu.

Výdaj má tabulku položek s částkou, uznáním, případem, činností a účelem.
Lze přidávat a odebírat řádky; celý doklad včetně neuznané části musí souhlasit.
Chybný součet vrací formulář s rozepsanými údaji. Starý textový vstup je zachován
pouze pro existující volající, nová obsluha jej nepotřebuje. Případné původní
skladové vazby při opravě zůstávají zachované; prodejní či skladové postupy se nemění.

Jméno vybrané osoby načítá server. Podvržený popisek není nová identita.
Opravná verze zachovává původní označení příjemce, i když se mezitím změnilo
jeho jméno v evidenci. Dodavatele lze nadále uvést výslovně textem.

Z finančního případu se otevírá původní formulář nahrání účtenky. Po uložení
se vrátí k výdaji s vybranou přílohou, částkou a datem. Nahrání není předložení
ani schválení výdaje. Příloha zůstává v soukromém úložišti a schválený podklad
se nepřepisuje. Kontext ověřuje otevřený případ a správnou původní provozní
vazbu; chyba přenosu přílohy nesmí být vydávána za úspěšné uložení.

Detail případu ukazuje jeho doklady a opravné verze, zálohy, salda, chybějící
doložení a historii. Zahrnuje i výdaje připojené pouze položkou, nikoli hlavičkou.
Detail výdaje ukazuje položky, originál, historii verzí a doložené pohyby/čerpání.
Detail zálohy ukazuje předání, vrácení, čerpání, převody a historii vyřízení.
Při výběru vypořádání se rozlišuje výdaj a záloha i tehdy, když mají stejné číslo.

Původní doménové funkce nadále zajišťují nezávislého schvalovatele, idempotenci,
neměnné verze, skutečné peněžní vypořádání a ochranu uzavřených případů.
Schválení zálohy nevytvoří peníze. Oprava přiřazení není skutečná vratka.
Přehled překážek nenahrazuje závěrečnou kontrolu při potvrzení uzavření.

### Přehledy odměn

Existující stránka odměn nově nabízí samostatný detail konkrétního nároku a filtr
**Zbývá doplatit / K vrácení / Schválené a vypořádané**. Filtr používá schválenou
částku a skutečné výplaty/vratky, nikoli částku posledního neschváleného návrhu.
Zachovává období, jmenné filtry, stránkování a omezení trenéra na vlastní výkazy.
Volba peněžního pohybu už neskrývá pohyby starší než posledních 200 položek.

Žádné skutečné sazby, daňová metodika ani noví schvalovatelé nejsou doplněni.

## Ověření

Izolovaný web `http://127.0.0.1:18086`, MariaDB `127.0.0.1:33426`, schema
`evidence_b1`. Probe ověřil skutečný datadir, vlastníka checkoutu, soukromé
úložiště mimo webroot, 327 čitelných tabulek a blokované vnější transporty.
Tři volitelné kontraktní tabulky `ucto_gs_*` nejsou potřeba pro tento balík.
Nová HTTP přejímka kontroluje vlastníka prostředí, konfiguraci i skutečnou DB
a zapisuje pouze nové syntetické záznamy `LOCALHOST NEPLATIT` / `example.invalid`.

| Ověření | Výsledek | Důkaz |
|---|---|---|
| Upravené oblasti, role a finanční ochrany | **79 testů / 2110 assertions PASS** | [PHPUnit](phpunit.txt) |
| Nová přejímka akce, formulářů, soukromé přílohy a vyúčtování | **49 scénářů PASS** | [HTTP C](http-acceptance.json) |
| Původní výdaje, vratky a zálohy | **47 scénářů PASS** | [Výdaje](expense-regression.json) |
| Odměny včetně nových filtrů a detailu | **29 scénářů PASS** | [Odměny](reward-acceptance.json) |
| Návaznost na každodenní práci A | **32 scénářů PASS** | [HTTP A](package-a-regression.json) |
| PHP syntax | **15 souborů PASS** | Lokální lint |
| Whitespace | `git diff --check` PASS | Lokální Git |

Nová přejímka čte a odesílá skutečné HTML formuláře, pro soukromou přílohu používá
multipart HTTP. Prověřuje mimo jiné podvržené CSRF, cizí akci/osobu, starou revizi
výsledku, nesouhlas položek, opakovaný zápis, podvržené jméno, oddělení schválení,
opravu po přejmenování osoby, blokaci nesplacené zálohy a zápis do uzavřeného
případu. Odměnová sada ověřuje SQL také proti místní MariaDB.

Při přípravě syntetického scénáře byly opraveny jeho hodnoty enumů účtenky,
CSRF druhého schvalovatele a výslovné doložení opakovaného testovacího obrázku.
Produkční validace kvůli průchodu testu nebyla uvolněna.

```powershell
& C:\xampp\php\php.exe vendor/phpunit/phpunit/phpunit --filter 'ClubAdministrationTest|ClubExpensesTest|ClubRewardsTest|DailyWorkReadModelsTest|SportsActivityTest|StaffWorkspaceRegistryTest|FinancialEventGuard'
& C:\xampp\php\php.exe tests/Support/ClubAdministrationHttpAcceptance.php
& C:\xampp\php\php.exe tests/Support/ClubExpensesHttpAcceptance.php
& C:\xampp\php\php.exe tests/Support/ClubRewardsHttpAcceptance.php
& C:\xampp\php\php.exe tests/Support/DailyCoachRegistrarHttpAcceptance.php
```

Nejde o vizuální prohlížečovou přejímku ani reálné účetní převzetí. Pojmenované
nabídky načítají celý příslušný seznam; tento balík není zátěžová certifikace
pro rozsáhlé registry. Částky a sazby v přejímce jsou výhradně syntetické.

## Odložené rozhodnutí a hranice

- **D20:** zda trenéři sami předkládají výdaje a žádají zálohy. Do rozhodnutí
  zachován osobní přepis hospodářem; žádný nový přístup trenéra k dokladům.
- **D04/D06:** skutečné sazby, účetní/právní titul odměn a metodika předání.
- **D05:** konkrétní schvalovatelé a případné výjimky v malém oddílu. Stávající
  oddělení schválení je zachované, výjimka nebyla odhadnuta.
- Sporné historické vazby/podklady dál pod D07/D08; zprávy pod D12.

Bez změn schématu, push, nasazení, produkčních čtení/zápisů či aktivace odesílání.
E-shop nebyl implementován ani znovu přejímán. Navazující nezávislá práce je
balík D; rozhodnutí z B i C zůstávají otevřená.
