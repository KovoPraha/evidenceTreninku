# Pokladna baru vlastní kasy uzávěrky a storna

5. 11. 2026, větev `codex/bar-kis-integration-20261105`, draft PR #306, pokračování z `7da18014` a doplněného `origin/main` `65969bb8`. Vlastník výslovně schválil B4 bez produkčního nasazení. [Plán a navazující etapy](../../BAR_INTEGRATION_PLAN_2026-11-05.md).

## Implementace

- Pokladna vybírá katalogové produkty a receptury. Souhrn se potvrzuje před zápisem. Peníze jsou v haléřích; zachovaná trenérská sleva 20 % zaokrouhluje cenu zboží dolů na celé desetikoruny. Vratné zálohy se neslevují. Rozdělení ceny po slevě na řádky zachová přesný celkový součet. Změna ceny, názvu, receptury nebo zálohy vyžádá nové potvrzení; samotný běžný pohyb skladu nový cenový souhrn nevyžaduje.
- Každý pracovník má nejvýše jednu otevřenou vlastní kasu, vynucenou také unikátním klíčem v DB. Spravuje její počáteční hotovost, vklady/výběry, skutečně napočítaný stav a vysvětlení rozdílu. Administrátor baru nezískává možnost zapisovat do cizí kasy ani číst cizí osobní doklady. Uzávěrka kontroluje verzi a čekající platby a ukládá neměnný souhrn. Pozdější vratka z nové vlastní kasy starý souhrn nepřepočítá.
- Prodej, snímky řádků a souhrnné potřeby surovin, skladový deník, platba, hotovost a audit se zapisují atomicky. Neznámý náklad zůstává `NULL`. Čekající karta/QR již rezervuje zboží odečtem z dostupné zásoby. Potvrzení ho podruhé neodepíše; zrušení obnoví přesné původní odpisy. Inventura rezervovaného místa a vypnutí/archivace či převod rezervované fyzické položky na recepturu počká na vyřešení platby, aby se rezervované kusy nezapočetly dvakrát.
- Karta a QR převod mají ruční evidenci skutečně přijaté platby a jejího vrácení s jedinečným podkladem. Stav výslovně rozlišuje čekání a ruční potvrzení. Napojení terminálu, generování platebního QR a automatické bankovní ověření patří do samostatné integrace. Nulová objednávka nevytváří čekající externí platbu.
- Správce nastavuje novou sazbu kelímku s důvodem a verzí. Vratka používá původní doklad, jeho sazbu a zbývající počet, i když se mezitím sazba změnila. Vrací se hotově z vlastní otevřené kasy, také proti dokladu jiné obsluhy; uživatel přitom nedostává cizí prodejní údaje. Storno započte již vyplacené zálohy a ověří potvrzenou částku vratky znovu pod zámkem.
- Storno přidává kompenzaci a zachovává původní řádky, cenu, platbu a nákladový i surovinový snímek. Vrací se původní množství na původní místo, i po změně receptury. Po uzávěrce se případná platba vrací z nové vlastní kasy. U potvrzené karty/QR musí obsluha doložit již provedenou externí vratku; aplikace peníze sama neposílá. Původní skladová položka musí být aktivní a sledovaná a nesmí být překročen limit zásoby. Úplné storno je oddělené od dílčích vratek kelímků.
- Vlastní spotřeba kontroluje výslovný grant po získání zámku. Obsluha odebírá ze stánku, oprávněný administrátor také z předskladu. Odběr má nákladový snímek a audit, nevytváří platbu ani tržbu a nepotřebuje otevřenou kasu. Vlastní již uložený odběr lze vrátit i po odebrání grantu; nové odběry se odmítnou.
- Společný registr požadavků váže token na aktora, operaci a úplný normalizovaný obsah. Opakování potvrzené operace vrátí původní výsledek také po uzávěrce, stornu nebo otevření jiné kasy. Při nejistém výsledku formulář ponechá původní požadavek. Zapisující služby získávají barový zámek před zamykající autorizací, včetně navazujícího katalogu/skladu/importu. Nepřebírá se žádná stará offline fronta.

Migrace `20261105190000_bar_checkout` zakládá deset tabulek `kis_bar_*`: nastavení, požadavky, kasy, hotovostní pohyby, prodeje, řádky, surovinové snímky, platby, vratky kelímků a storna. Všechny jsou v ownership kontraktu `2026-11-05.4`. Již existující migrace zůstaly beze změny.

## Ověření

| Kontrola | Výsledek |
| --- | --- |
| Cílené domény | 44 testů / 211 assertions PASS, včetně ochrany rezervované zásoby, historického storna, rolí, uzávěrky a rollbacku |
| Úplná místní sada | 2115 testů / 40036 assertions PASS; 3 unixové symlink scénáře přeskočené na Windows |
| Statická analýza a styl | PHPStan 854 souborů a styl všech 69 změněných PHP souborů proti main PASS; žádné nové potlačení |
| Anonymní HTTP | 249 chráněných tras, 34 veřejných výjimek, 2 CLI, 0 chyb |
| MariaDB 10.4.32 | Opakované migrace, skutečný souběh dvou obsluh o poslední kus, dvě identické současné objednávky, odebrání pozice/grantu během skutečného čekání na DB zámek, uzávěrka proti prodeji i stornu a ochrana inventury rezervovaných kusů PASS |
| Převodový nácvik | Po doplnění schématu B4 znovu PASS; převod zůstává opakovatelný a zachovává následné zásoby i úpravy |
| Záloha a skutečná obnova | 319 vlastněných syntetických tabulek PASS |
| Skutečný místní browser | Lucka: otevření kasy 1000 Kč; hotovostní objednávka 75 Kč + zálohy 40 Kč; vrácení jednoho kelímku 20 Kč; storno zbývajících 95 Kč; čekající QR objednávka 37,50 Kč; odmítnutá uzávěrka do vyřešení platby; ruční potvrzení a doložené storno QR; vlastní spotřeba a její vrácení; konečná uzávěrka PASS |
| Kontrola dat po browseru | 3 doklady / 3 storna / 2 platební záznamy / 1 vratka kelímku. Hotovost 100000 haléřů, napočítáno 100000, rozdíl 0, aktivní vlastník kasy `NULL`. Stánek obnoven na 20 kusů; předsklad beze změny 7 kusů. Vlastní spotřeba nemá platbu ani tržbu |

Snímky `output/playwright/bar-b4-confirmation.png` a `bar-b4-closed-cashbox.png` jsou místní ignorované artefakty; uzávěrka byla vizuálně prohlédnutá. PHP testovací server má zakázané odesílání mailu. Všechny částky, jména, podklady plateb a databáze jsou syntetické. Nebyla kontaktována banka ani terminál a nevznikla skutečná platba, vratka, zpráva či účetní zápis.

Čistý souběhový nácvik lze zopakovat přes `BAR_SMOKE_DB_PORT` a `php tests/Support/BarCheckoutMariaDbSmoke.php`; vytvoří a odstraní pouze vlastní náhodnou loopback databázi `evidence_bar_test_checkout_*`. Ověřuje skutečně čekající SQL spojení, nikoli pouze pořadí dvou běžných volání. CI tento nácvik spouští na MariaDB 10.3 i 11.4. Tři unixové symlink scénáře zůstávají ve Windows přeskočené a musí projít v Linux CI.

Pro nový syntetický browser cíl slouží `BAR_BROWSER_CHECKOUT_DEMO=1 php tests/Support/BarBrowserFixture.php`, s vlastním MariaDB portem přes `BAR_SMOKE_DB_PORT`. Fixture odmítne existující konfiguraci, vytvoří nový testovací cíl, dodá 20 testovacích kusů a explicitní testovací grant lucky. Nepoužívá produkční konfiguraci.

## Další postup

B5 doplní faktury, nabídky, přehledy a účetní rozhraní. B6 provede širší integrační a lidskou přejímku, provozní obnovu DB i souborů, ztrátu spojení a případné předání opuštěné kasy. Společná skladová inventura zůstává v B3; hotovostní uzávěrka uzavírá pouze kasu pracovníka. Skutečný katalog, fotografie a lucka zatím nebyly převedené. Produkční přechod, poskytovatelé a účetní zápisy vyžadují samostatné schválení; tato etapa je neprovádí.
