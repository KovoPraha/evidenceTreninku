# BAR → KIS: B6, integrační přejímka

Datum: 5. 11. 2026, Europe/Prague. Výsledek: místní technická přejímka PASS; konečné Linux CI přesného headu je samostatná brána v [draft PR #306](https://github.com/KovoPraha/evidenceTreninku/pull/306). Tato etapa nepovoluje produkční nasazení, skutečný import ani finanční automatizaci.

Pracovní kopie: `C:\Users\Marek\Documents\Codex\evidencePavel-bar-integration-20261105`, větev `codex/bar-kis-integration-20261105`. Původní Evidence a původní BAR s rozpracovaným refaktoringem nebyly měněné. Všechna zapisovaná data jsou syntetická; vlastní MariaDB běží na loopback portu 3327 a browser na localhost:8895. E-mailový transport je vypnutý. Skutečný zdrojový katalog, fotografie a lucka nebyly exportované ani přenesené.

## Dodané chování

- **Opuštěná kasa:** bar_admin smí vypořádat kasu vypnutého účtu nebo účtu bez barového přístupu. Aktivní vlastník uzavírá svou kasu sám. Každou čekající kartu/QR je třeba zvlášť doložit, nebo při potvrzeném vrácení zboží zrušit s nulovou peněžní vratkou. Teprve potom lze fyzicky spočítat a uzavřít hotovost. Historie nemění vlastníka; audit a uzávěrkový snímek zachovávají skutečného administrátora, důvod a `closure_kind=recovery`.
- **Ztracená odpověď:** jedna původní tokenizovaná operace na kartu prohlížeče, oddělená podle uživatele a verze relace. Čtecí ověření výsledku používá chráněný endpoint a hlavičku `X-Bar-Request-Token`; identifikátor není v URL. Uložený výsledek má odkaz; do jeho otevření či výslovného pokračování se nevytvoří další operace. Při neznámém výsledku lze ručně zopakovat pouze původní obsah a token s čerstvým CSRF. Není zde offline fronta ani automatické opakování finančních POSTů.
- **Nedostupná DB:** chráněný BAR má samostatnou stránku 503 bez dalších dotazů na nefunkční PDO. Platný přihlášený formulář uchová stejný obsah pro ruční opakování; upload vyžádá tentýž soubor znovu. Neplatná relace či CSRF nevrací finanční formulář. Změna v `db.php` je opt-in pouze pro BAR.
- **Čas a účetnictví:** nové finanční události jsou UTC, obchodní období a zobrazení Europe/Prague. Sdílená zóna PDO se nemění. Existující nedoložená historie dostane `legacy-local` a nové finanční zápisy/export se odmítnou; žádné odhadované přeznačení historie. Export uvádí `source_timezone=UTC`, stále nic nezaúčtuje.
- **Obnova:** manifest kontroluje nynější i zachované starší fotografie a originály PDF podle velikosti, MIME a SHA-256. Nácvik obnovuje skutečný aplikační SQL backup i privátní soubory do oddělených cílů a ověřuje neměnný export i opakování původního prodeje.

B6 současně opravila skutečně nalezené překrytí nativních vlastností formuláře poli `name=method/action/target`: barová obnova i společná ochrana dvojího odeslání používají atributy formuláře. Úprava společného UI je krytá samostatným Node testem.

## Místní automatické ověření

| Oblast | Výsledek |
| --- | --- |
| Celé PHPUnit | **2192 testů / 41117 assertions**, PASS; 3 unixové symlink testy přeskočené pouze na Windows |
| Nová integrační přejímka | 22 testů / 143 assertions; matice všech 11 pozic, vlastní spotřeba, nouzová kasa, soukromí stavového endpointu, UTC a oba přechody času |
| JavaScript | **19/19** BAR recovery + ochrana odeslání + názvy polí; 14 scénářů samotné obnovy včetně uchování výsledku přes reload |
| PHPStan | Celý projekt bez chyb |
| Styl | 103 změněných PHP souborů, nula chyb |
| Anonymní HTTP | 258 chráněných / 36 veřejných / 2 CLI; nula chyb |
| Nativní MariaDB 10.4.32 | B4 pokladna, B5 dokumenty, převod/sklad i nová B6 obnova PASS |
| Úplná obnova | **335 tabulek**, včetně porovnání naplněných 28 tabulek BAR a **3 privátních souborů** |

Nový nativní test prokazuje ztracenou odpověď po skutečném commitu a skutečné `KILL` spojení před commitem po provedení všech zápisů. Druhý případ používá testovací trigger s čekáním: neúplný zápis se celý vrátí zpět a stejný token po připojení vytvoří právě jeden výsledek. UTC je ověřeno při nezměněném sdíleném offsetu +09:00. Dále prokazuje reaktivaci vlastníka a odvolání administrátora během skutečného čekání na zámek i jediného vítěze dvou souběžných nouzových uzávěrek.

Naplněná obnova obsahuje příjem originální PDF faktury, dvě generace fotografie, katalog/recepturu, sklad, placený a čekající prodej, vratku kelímku, storno, vlastní spotřebu, nouzovou uzávěrku a účetní export. Obnovené řádky všech 28 BAR tabulek mají shodné otisky. Chybějící i poškozený soubor jsou správně odmítnuté. Původní export je identický a opakování prodeje po obnově nezmění žádnou z 28 tabulek ani hlavní peněžní evidenci.

Výstupy místního ověření jsou v ignorovaném `output/bar-b6-*.log`; neobsahují produkční zálohy. Linux CI navíc samostatně spouští všechny 3 unixové symlink scénáře bez skipů a nativní MariaDB 10.3/11.4 včetně nového B6 testu a naplněné obnovy. Zelený historický head B5 nenahrazuje tuto bránu.

## Skutečný místní browser a kontrola uloženého stavu

1. Lucka má vlastní kasu s počátkem 1000 Kč. Prodej 35 Kč byl dokončen na serveru a odpověď 303 úmyslně zahozena. Po otevření pokladny nabídla aplikace původní uložený doklad; nevznikl další prodej, odpis ani peněžní pohyb.
2. Při další objednávce byla lokální DB skutečně vypnutá před potvrzením. Browser dostal 503 s původním požadavkem. Po startu DB ruční opakování vytvořilo jediný doklad: celkem tři placené prodeje po 35 Kč, zásoba 17 z původních 20 a hotovost 1105 Kč. První dřívější běh této zkoušky byl přerušený ztrátou stránky browseru bez commitu; započítaný průchod je až dokončené opakované měření.
3. Čtvrtý prodej 35 Kč zůstal čekající QR a rezervoval kus. Testovací účet lucky byl vypnutý. Administrátor skutečným formulářem zrušil nevydanou objednávku a uzavřel původní kasu: owner 7004, closed_by 7003, recovery, očekáváno i napočteno 110500 haléřů, rozdíl 0, active_owner_id NULL. Zásoba opět 17, platba čtvrtého prodeje cancelled, vratka 0. Jde pouze o syntetické částky.
4. Hospodář: účetní podklady a originály faktur HTTP 200; pokladna, sklad, vypořádání a práva HTTP 403. Vytvořil export pěti zdrojů (tři placené prodeje, otevření a uzávěrka), dvě stažení JSON jsou identická, UTC a obě identity uzávěrky správné. Hlavní `accounting_movements` zůstává prázdná.
5. Trenér: všech 11 ověřovaných barových agend HTTP 403. Obsluha nemá katalog, sklad, faktury, nabídky, vypořádání ani účetní přehled. Administrátor baru nemá správu vlastních přístupů; ta zůstává systémovému administrátorovi. Kompletní doménová matice pozic je v automatických testech.

Místní screenshoty `output/playwright/bar-b6-lost-response-fixed.png` a `bar-b6-recovery-closed.png` byly vizuálně zkontrolované. Browser průchod je technická přejímka syntetických účtů, nikoli lidské provozní UAT.

## Zbývající hranice a další krok

- Skutečné produkty/fotografie/lucka zatím nepřevedené; není ověřená parita skutečného zdroje. Pracovní účet má technický e-mail bez automatické obnovy nebo zákaznického propojení.
- [Účetní adaptér](../../BAR_ACCOUNTING_ADAPTER_PLAN_2026-11-05.md) má konkrétní návrh návaznosti, ale vyžaduje rozhodnutí o účtech, DPH, ocenění a ověřitelné bankovní vazbě. BAR nyní do účetnictví ani banky nezapisuje.
- Finanční provoz vyžaduje dostupnou DB. Ruční obnova nepřetrvá smazání úložiště prohlížeče nebo zavření jeho karty; v takovém případě obsluha nejdřív zkontroluje poslední doklady a skutečnou kasu. Idempotence platí pouze se stejným původním tokenem a obsahem, nikoli jako deduplikace dvou nových prodejů.
- Není podporovaný hromadný přesun cizí kasy, odhadované potvrzování plateb, částečné storno faktury, OCR, dobropisy ani zálohové faktury.
- Další je příprava lidské přejímky a schválený [postup B7](../../BAR_PRODUCTION_READINESS_2026-11-05.md). Sloučení, skutečný import, přepnutí domény, produkční nasazení i finanční aktivace zůstávají nepovolené.

Před uzavřením B6 začleněn aktuální main `9ffe964b` (měsíční členství a zastupování): zachované obě migrační sady i vlastněné tabulky, společný zálohovací kontrakt **2026-11-05.7**. Nad společným stromem znovu PHPUnit **2192/41117**, PHPStan a anonymní HTTP **258/36/2, 0 chyb** PASS; naplněná DB a přílohy znovu obnovené, nyní **335 tabulek**. Browser doklady výše byly pořízené před přidáním main; barové browser služby tím nebyly změněné, společný strom má novou testovací/CI bránu.
