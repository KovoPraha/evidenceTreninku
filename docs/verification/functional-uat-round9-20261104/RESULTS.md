# Devátý autonomní funkční průchod — 4. 11. 2026

**F09 opravena místně: souhrn nesmí vzniknout z obsahu sestaveného před změnou rodinného oprávnění ani po odhlášení odběru.**
37 funkčních kontrol / 23 HTTP požadavků PASS před opravou i po ní; tyto běžné
scénáře samy souběh nepokrývaly. Dva nové regresní testy nejprve FAIL a po opravě PASS.
Skutečný místní prohlížeč ověřil stránkování, filtr, escapovaný náhled a návazný
čtecí přehled komunikace. **Žádný merge ani deploy této opravy.**
Změna hesla, použitý obnovovací odkaz a T07 zůstávají SKIPPED.

## Prostředí a rozsah

Čerstvý main `5eb64c95e4b199fc339076524578e9a44efb3414`, větev
`codex/functional-uat-round9-20261104`, izolované worktree stejného názvu mimo htdocs.
Vlastní Apache/PHP 8.2.12 na 127.0.0.1:18422 a zachovaná syntetická MariaDB 10.4.32
na 127.0.0.1:3322 / functional_round4. Datadir před použitím ověřený; žádný produkční dump/config.
Nové soukromé adresáře a session patří této vlně. Webové mail/cURL transporty blokované;
CLI použilo pouze místní file outbox a řízené callbacky, nikdy mail sender.
Souběžný kontrakt používá další náhodně pojmenovanou syntetickou DB, kterou sám uklidí.
Cizí WIP v hlavním checkoutu ani služby ostatních projektů nebyly upravené.

Testování vytvořilo dvě vlastní fixture (R9-ee16a0 před opravou, R9-0fa16f po opravě):
jednoho pracovníka `order_operator` bez superadmina, rodinný účet/osobu/vazbu a
31 historických souhrnů na každý průchod. Nové účty přebírají existující syntetický
seedový hash; žádnému existujícímu účtu se heslo nenastavovalo ani neměnilo.
Následně pouze vlastní lokální fronta, preference, vazba a kontakt sloužily testům.

## F09 — změna během sestavování

Generátor načetl přihlášené odběratele, sestavil obsah a teprve potom uložil otisk
rodinných oprávnění. Pokud mezitím jiná relace odebrala vazbu rodiče, starý obsah
se označil novým otiskem. Kontrola workeru pak mohla propustit obsah sestavený před
odebráním. Pokud místo toho proběhlo odhlášení odběru, generátor zařadil nový
čekající souhrn až po zrušení dosavadní fronty.

- [Dva původně selhávající testy](race-red.xml): starý kód zařadil souhrn po opt-out
  a v druhém případě skutečně zavolal testovací sender po odebrání vazby.
- [MariaDB před opravou](race-red.json): dvě nezávislá připojení, řízená změna po
  sestavení a před zařazením. V případě revokace sender callback volán **1×**.
  Jde o místní reprodukci, nikoli doklad skutečného úniku v produkci.
- [MariaDB po opravě](race-green.json): oba případy queued=0, skipped=1,
  žádný nový řádek, sender callback **0×**.

Oprava načte otisk rodiny **před sestavením**, v transakci zamkne a znovu ověří odběr,
aktivitu, ověření a nezměněný kontakt. Potom porovná uložený otisk s výchozím;
při rozdílu vrátí transakci včetně fronty a otisku. Změnu po zařazení nadále hlídá
stávající kontrola workeru před odesláním. Zámek serializuje zařazení s odhlášením:
buď se zařazení přeskočí, nebo následné odhlášení jeho řádek zruší.
Bez změny schématu, transportu, plánování nebo aktivace integrací.

## Provedené kontroly

| Oblast | Konkrétní důkaz |
|---|---|
| Seznam, filtry a stránky | 31 řádků, stránky 25+6, krajní stránka se omezí, navigace zachová hledání/stav/datum. Koncový den zahrnuje i 23:59:59; hledání `%_` je doslovné. |
| Náhled a odmítnutí | Uložené HTML payloady se zobrazí jako text. Neexistující náhled vrací řízenou chybu; registrář, trenér, rodina a anonym jej nesmějí číst. Chybné filtry vrátí upozornění a nula výsledků. |
| Zápisy HTTP | Neplatný CSRF a chybějící potvrzení nic nezařadí. Rodina zapne/vypne jen vlastní odběr; generování je idempotentní. Outbox vytvoří právě jeden soubor a opakované zpracování jej nezdvojí. Jeho HTTP cesta je 403. |
| Změny po zařazení | Změna kontaktu nebo odvolání rodinné vazby zadrží souhrn bez volání senderu. Nejasný výsledek transportu a starý claim se automaticky neopakují. |
| Souběhy při sestavení | Nové testy pokrývají opt-out, revokaci, změnu e-mailu, deaktivaci a zrušené ověření. Nový čerstvý souhrn po obnovení podmínek lze stále zařadit a zpracovat právě jednou. |
| Persistence | Dvanáct nesouvisejících doménových tabulek má shodné počty i hashe před/po HTTP sadě. Další čtecí browser průchod zachoval všech 16 sledovaných tabulek včetně fronty, preferencí, událostí a otisků. |

[HTTP před opravou](queue-http-before.json), [HTTP po opravě](queue-http.json),
[16 tabulek browser průchodu](browser-persistence.json), [generátor a HTTP scénáře](queue-http.php.txt),
[ochranný prefix](http-prefix.php.txt), [řízené souběhy](race.php.txt).
První běh pomocníku před přípravou dat použil chybné jméno `member_charges`;
opraven na skutečné `club_member_charges`. První rozšířená reprodukce narazila
na vlastní starší pending řádek; izolace obou pokusů byla opravena a červený důkaz
opakovaně potvrzen. Nešlo o další aplikační nálezy.

## Testy a skutečný browser

- Cílená sada: **34 testů / 365 assertions PASS**.
- Rozšířený [MariaDB kontrakt](mariadb-contract.json): **49 kontrol PASS**, z toho
  28 nových kontrol pěti změn během preview a čerstvého opakování. Nové scénáře mají
  dvě nezávislá připojení s řízeným pořadím; původní část navíc skutečné dva worker procesy.
  Kontrakt je zapojený do stávajícího CI pro MariaDB 10.3 a 11.4.
- Úplná místní sada: **1986 testů / 38417 assertions**, bez selhání; viz [strojový souhrn](checks.json).
  Tři Windows skipy POSIX testů se nepovažují za plné Linux ověření.
- PHPStan, styl a syntaxe změněných souborů bez chyby; Composer validace PASS.

IAB použil účet `round9-order-R9-ee16a0`, jedinou pracovní pozici order_operator,
bez superadmina. Skutečně klikl na Další (6 zbývajících řádků), vybral zrušené
souhrny (jediný správný řádek), otevřel Náhled a následně Stav komunikace.
Náhled zůstal čitelný po reloadu opraveného kódu. Snímky obsahují pouze syntetická data.
[Browser souhrn](browser.json), [vizuálně ověřený náhled](queue-preview.png).
Neúspěšný přesný label selektor filtru nic nezměnil; použit byl jediný viditelný select.

Produkční karta přesměrovala na login, přihlášené produkční UAT proto **NOT RUN**.
Testy nezapínají skutečné rozesílání ani neprokazují doručení do e-mailové schránky.
Připravený PR je oddělený od schválení merge/deploy a úplné lidské přejímky.
Konečný úklid vlastních služeb a další krok jsou uvedené v [plánu](PLAN.md).
