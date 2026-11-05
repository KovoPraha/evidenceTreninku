# Desátý autonomní funkční průchod — 5. 11. 2026

Následné [sloučení PR #305 a produkční nasazení](../functional-round10-release-20261105/RESULTS.md) mají samostatný protokol. Níže je zachovaný původní místní stav před vydáním.

**F10 reprodukována a opravena místně: vratky zachovávají objednávku a celý přehled je dostupný po stránkách.**
Před opravou čtyři regresní testy FAIL; závěrečná rozšířená sada stránky **5/65 PASS**, související sada
**47/617 PASS**. Původní vratkový průchod **33 kontrol PASS před i po**,
nová sada **38 kontrol / 30 HTTP požadavků PASS**, skutečné souběhy MariaDB
**19 + 6 kontrol PASS**. Skutečný Chrome ověřil chybu i opravu a všech šest stran.
Oprava není sloučená ani nasazená. Heslo, použitý obnovovací odkaz a T07 SKIPPED.

## Prostředí

Čerstvý main `402f8afe0dd2aa6ad208a78fb26511ecd6877abb`, vlastní worktree a větev
`codex/functional-uat-round10-20261105`. Zachovaná syntetická MariaDB 10.4.32,
functional_round4 / 127.0.0.1:3322, vlastní Apache/PHP 8.2.12 na 127.0.0.1:18422.
Před zápisem se ověřilo jméno DB, port, datadir, vlastník checkoutu a descriptor;
chybějící migrace se aplikovaly pouze na tento sandbox. Žádný produkční dump/config.
Nové session a soukromé adresáře patří této vlně, webové mail/cURL transporty blokované.

Všechny objednávky, osoby, účetní částky i skladové kusy jsou syntetické LOCALHOST/NEPLATIT.
Příprava nastavila fiktivní stav paid pouze vlastní fixture, nic se neplatilo externě.
Existujícím účtům se heslo neměnilo. Omezený browser účet používá kopii existujícího
syntetického seedového hashe a jedinou pozici order_operator, bez superadmina.

## F10 — ztracený kontext a nedostupná historie

Odkaz **Vratka / reklamace** v položkovém výdeji obsahoval `order_id`, ale cílová
stránka jej ignorovala. Při přechodu z objednávky A tedy zobrazila i případ B a
nabídla jeho původní výdej pro založení dalšího případu. Chybělo zřetelné označení
vybrané objednávky. Nejde o rozšíření pracovní role: obsluha smí vidět více objednávek,
problémem byla záměna pracovního kontextu a možnost omylu při úkonu.

Globální přehled navíc bez stránkování končil po 100 případech. Původní výdej starší
než posledních 200 pohybů nešel vybrat ani z odkazu konkrétní objednávky.
[Červené regresní testy](context-red.xml), [HTTP reprodukce](context-before.json),
[browser před opravou](before-order-context.png).

Oprava:

- Explicitní objednávku ověří a zobrazí její označení, vlastní případy a vlastní výdeje.
  Neplatný nebo rozporný kontext odmítne 422, neexistující objednávku 404.
- Kontext zůstává ve formulářích a odkazech. Formulář s jiným případem/výdejem
  nebo nesouhlasným order_id se odmítne před voláním původní doménové služby.
- Úplné počty se vyhodnotí před omezením stránky na 25 případů. Předchozí/Další
  zachovávají objednávku; neexistující stránka se omezí na poslední.
- Každý případ i v globálním přehledu označuje svou objednávku. U konkrétní
  objednávky jsou dostupné také starší výdeje; globální nabídka posledních 200 zůstává.

Změněna je pouze stránka a její čtecí/navigační pomocník. Skladové, účetní a vratkové
doménové služby se neměnily. Stávající stránka je současně rozformátovaná podle
povinného PHP stylu; ověřena byla i čitelnost a zachování mezer v částkách/jednotkách.

## Funkční průchody a persistence

| Oblast | Skutečně provedené ověření |
|---|---|
| Původní vratky | 33 kontrol před/po: oddělené role, CSRF, potvrzení, částečný požadavek, idempotence, převzetí do blokace, skladové posouzení, cizí kus v péči, výměna velikosti a následná reklamace vyměněného kusu, odvolaná pracovní relace. |
| Objednávkový kontext | Dvě různé objednávky pro reprodukci; správný banner, zpětný odkaz, vyloučený případ i výdej jiné objednávky. Finanční a skladová role zachovají stejný kontext. |
| Dlouhý přehled | Třetí objednávka má skutečný syntetický výdej 130 kusů a 126 počátečních případů vytvořených doménovou službou. Šest stránek; nejstarší případ za původním limitem 100 je dostupný. Jednotkové testy navíc ověřují výdej za globálním limitem 200. |
| Vstupy a role | Nula, prázdné, textové, pole, přetečení a SQL-like order_id jsou odmítnuté; neexistující 404 a existující prázdná objednávka má výslovný stav. Trenér/anonym odmítnuti. |
| Zápisy | Platný CSRF nepovolí úkon s případem/výdejem jiné objednávky. Odmítnuté jsou nesouhlasné GET/POST, neplatný zdroj, chybějící potvrzení a nepovolená pracovní role. Platný formulář pouze s hidden kontextem založí správný případ jednou; změněné opakování a překročené množství jsou odmítnuté. |
| Invarianty | Všech 40 sledovaných doménových tabulek beze změny při GET a odmítnutých POST. Platné založení/opakování nezměnilo sklad, peníze ani ostatní sledované domény. Běžný technický accounting_ledger_mutex mění revizi při přijaté operaci; není finančním pohybem. |
| Skutečné souběhy | 19 kontrol vratek potvrzuje čekání konkurenta v InnoDB LOCK WAIT a právě jeden příjem kusů. Šest souvisejících scénářů výdeje prošlo v samostatné náhodné DB. Oba pomocníci uklidili své scratch DB. |

[Původní průchod před](returns-before.json), [po](returns-after.json),
[nová HTTP sada](context-http.json), [souběhy vratek](returns-race.json),
[souběhy výdejů](fulfillment-race.json), [ochranný prefix](http-prefix.php.txt),
[původní adaptovaný pomocník](returns-baseline.php.txt), [nový pomocník](context-http.php.txt).

První kontrola skrytých polí v pomocníku omylem zahrnula odhlašovací formulář hlavičky;
byla omezena na formuláře v main. Další kontrola zahrnula technickou revizi účetního
mutexu mezi finanční data. Porovnání finančních/skladových domén ji nyní výslovně
odděluje, čtecí porovnání ji nadále obsahuje. První platně založený místní případ #132
zůstal pro audit; závěrečný průchod přidal #133. Nešlo o další aplikační nález.

## Skutečný Chrome a hranice

Účet `round10-operator-R10-bda0e3` skutečně prošel odkazem z výdeje A a viděl případ B.
Po opravě a reloadu se zobrazila pouze A se čtyřmi případy a správným zpětným odkazem.
Následný odkaz z výdeje C otevřel 128 případů (126 přípravných + dva vlastní HTTP
požadavky z uvedeného ověřování). Pět skutečných kliknutí Další prošlo všech šest
stran, na poslední byl dostupný nejstarší C-000 a nebyla tam jiná objednávka.
[Browser záznam](browser.json), [snímek opravy](after-order-pagination.png).

Celý závěrečný čtecí browser průchod zachoval [všech 40 tabulek](browser-persistence.json).
Browserem se žádný doménový údaj neměnil. Jedno načítání při souběhu místních testů
překročilo timeout selektoru; čerstvý AX stav potvrdil dokončené načtení a další
kroky pokračovaly z něj. Snímky jsou vizuálně ověřené, obsahují jen syntetická data.

Výsledky testů a nástrojů shrnuje [strojový protokol](checks.json). Kompletní místní
běh před doplněním posledního POST regresního testu prošel 2001 testy / 38666 assertions
(tři Windows skipy). Nový POST test následně prošel v závěrečné cílené sadě 47/617;
CI ověřuje úplnou sadu na přesném předaném headu.
První PHPStan narazil na souběžně zamčenou sdílenou Windows cache; opakování používá
vlastní cache této vlny, bez zásahu do jiného běhu. Windows POSIX skipy se neoznačují
za plný Linux PASS; rozhoduje také CI konkrétního předaného headu.
Konečná úplná analýza PHPStan PASS: stránka nově výslovně ověří existenci PDO
připojení a původní baseline výjimka pro nedefinované PDO byla odstraněná.
Produkční UAT touto místní vlnou neproběhlo. Nový merge/deploy vyžaduje samostatné rozhodnutí.

## Aktuální základ před předáním

Po místních průchodech byl bez konfliktů připojen main `bf899521` (nezávislé TV změny
PR #303 a #304). Původní místní výsledky výše patří uvedenému výchozímu základu;
závěrečná cílená sada včetně dokumentace prošla 49 testy / 631 assertions.
Úplné CI PR ověřuje společný předaný head včetně TV změn.
