# Pracovní upozornění – plán a přejímka

Zadání 6. 11. 2026: společný zvoneček a e-mail pro pracovní úkoly, rozšiřitelný o další události.

## Rozsah

1. Trvalá událost a osobní stav přečtení; autoritativní stav úkolu zůstává v původní agendě.
2. Nové žádosti o propojení i registraci sportovce: registráři, při jejich absenci aktivní superadministrátoři.
3. Zvoneček v hlavičce, počet nepřečtených, krátký seznam a úplný přehled; odkaz přepne pouze již oprávněnou pracovní pozici.
4. E-mail přes stávající transakční frontu. Kontrola oprávnění i aktuálního úkolu před odesláním; žádné osobní údaje dítěte v e-mailu.
5. Synchronizace v existujícím workeru doplní i dosavadní čekající žádosti a nové oprávněné pracovníky. Bez nového plánovače a bez odesílání při GET.
6. Ověření transakcí, deduplikace, oprávnění, přečtení versus vyřízení, změny příjemce, opakování workeru, migrace a zálohovacího kontraktu.

## Hranice

Upozornění neuděluje přístup, nepotvrzuje vztah ani nevytváří členství. Budoucí typ události musí registrovat vlastníka, text, stav a bezpečný odkaz. Push do telefonu a pravidelné eskalace nejsou součástí této první verze.

Nasazení a skutečné doručení musí být doložené samostatně; samotná implementace je nepotvrzuje.

## Implementace a obsluha

- Každý aktivní registrář dostane vlastní upozornění. Pokud není žádný aktivní registrář, příjemci jsou aktivní superadministrátoři. Běžný trenér tento úkol nedostane.
- Zvoneček je ve společné pracovní hlavičce i na mobilu. Počet a nabídka se obnovují po minutě, při návratu do okna a při otevření zvonečku. Celý přehled nabízí historii, filtr nepřečtených a stránkování.
- „Otevřít k vyřízení“ označí zprávu jako přečtenou, přepne na již oprávněnou pozici registráře a otevře konkrétní žádost. Rozhodnutí se stále provádí v ověřovací agendě. Samotný GET zprávu nemění.
- Přečtení je osobní. Schválení, zamítnutí nebo zrušení žádosti ukončí úkol všem příjemcům. Historie zůstává dostupná.
- Opakovaný běh nevytváří další kopie zprávy. E-mail se před odesláním znovu kontroluje: vyřízený úkol, přečtené upozornění, neaktivní pracovník, odebraná role nebo změněný e-mail znamenají zrušení neaktuální zásilky.
- Chybějící či technický e-mail nebrání upozornění v aplikaci. Doplnění platného e-mailu umožní pozdější zařazení dosud neodeslané zprávy. Pokud není žádný oprávněný příjemce, událost zůstane pro pozdější přiřazení; worker vrací počty `without_recipient` a `invalid_email`.
- Další události se doplňují do uzavřeného registru `staffNotificationTypes()` a transakčního volání u zdrojové agendy. První verze pracuje se zdroji se stavem `pending`; jiný životní cyklus vyžaduje rozšíření kontrol stavu a odkazu plus testy. Tabulky ani adresy se nepřebírají z požadavku uživatele.

## Přejímka a nasazení

Lokální syntetická přejímka: dva registráři, vlastní stav přečtení, oddělení od vyřízení, odebrání oprávnění, změna e-mailu, návrat po přihlášení, obnova po chybě fronty, opakovaný běh, doplnění starších žádostí, 205 úkolů a přesný odkaz mimo první stránku. Prohlížeč ověřil skutečnou hlavičku a stránku upozornění na desktopu i při šířce 390 px; cíl přesměrování byl lokální syntetický náhled, nikoli produkční schválení žádosti.

Composer konfigurace, syntaxe PHP/JS, styl změněných PHP, PHPStan a anonymní kontrola tras prošly. Lokální MySQL neběží; nová kontrola v CI ověřuje migraci opakovaně, nativní dotazy, deduplikaci a zrušení neaktuálních e-mailů na MariaDB 10.3 i 11.4. Výsledek celého CI a stav vydání patří do CURRENT_STATE.

Při následném schváleném nasazení:

1. Chráněné workflow musí vytvořit zálohu s ownership kontraktem `2026-11-06.1` a aplikovat migraci `20261106090000_staff_notifications` před spuštěním nové verze. Migrace přidává dvě tabulky a rozšiřuje stav sdílené e-mailové fronty o `cancelled`.
2. Ověřit běh obecného workeru `bin/club-event-notifications.php` a jeho záznam `transactional_notifications` v provozním dohledu. Měsíční členská automatizace ani obnova trenérského hesla nejsou důkazem tohoto běhu. Bez workeru se nové události zatím nerozdělí do osobních přehledů ani do e-mailové fronty.
3. První běh doplní také dosavadní čekající žádosti, nejvýše 500 chybějících událostí za běh. Připraví upozornění všem aktuálně oprávněným příjemcům; případné skutečné rozesílání je nutné zahrnout do oprávnění k uvedení do provozu.
4. V přihlášené produkční administraci ověřit skutečné příjemce, zvoneček, přesný odkaz, vyřízení a stav dalšího běhu. Reálné doručení do schránky se potvrzuje zvlášť, stav fronty `sent` sám nezaručuje doručení.

V první implementační fázi nebyla odeslána žádná skutečná zpráva ani změněn produkční plánovač. Následné schválené uvedení do provozu dokládá [produkční protokol](verification/staff-notifications-release-20261106/RESULTS.md); aktuální stav určuje CURRENT_STATE a další kroky [průběžný plán](WORK_REGISTER.md).

## Schválené uvedení do provozu 6. 11. 2026

Vlastník následně výslovně schválil sloučení, nasazení, pravidelné zpracování a ověření zvonečku i e-mailů. Rozšíření provozní části mění bod 2 výše: nasadit a instalovat samostatný minutový worker `bin/staff-notification-operations.php`, který používá stejnou frontu a validaci, ale vybírá výhradně `staff_action`. Starší nesouvisející klubové zprávy ani obnovu účtů nezačne rozesílat. Obecný worker zůstává kompatibilní.

Postup: dokončit CI po připojení aktuálního main, sloučit #330, spustit chráněné `deploy-production.yml`, poté `staff-notifications-production.yml` nejprve `status`, následně `install` a nezávislé `status` po skutečném minutovém běhu. Instalace je idempotentní, cizí soubor odmítne přepsat, pracovní zámek brání souběhu, pouze hostingový běh zapisuje heartbeat. Samotný status nic neposílá ani nevyrábí důkaz běhu. Výsledky fronty se zapisují také do provozního dohledu pod `staff_notifications`.

Přejímka: použít existující čekající žádosti, porovnat počet událostí, osobních upozornění a přijatých e-mailů před/po běhu, zkontrolovat další běh bez duplikace a skutečnou produkční hlavičku i odkaz do žádosti. Zdrojové žádosti neschvalovat pouze kvůli testu. Doručení do schránky odlišovat od přijetí poštovním transportem.

## Navazující doložený stav 6. 11. 2026

PR #330 je sloučený a [nasazení 42172409868](https://github.com/KovoPraha/evidenceTreninku/actions/runs/42172409868)
SUCCESS. [Samostatný status](verification/operations-inventory-20261106/staff-status.json)
dokládá skutečný minutový běh a 18 zpráv ve stavu sent bez čekajících/chybných.
Předchozí seznam „při následném nasazení“ je postup, nikoli tvrzení o dnešním
nenasazení. [Provozní inventura](verification/operations-inventory-20261106/RESULTS.md)
uvádí meze tohoto důkazu. Další kroky vede P01 v [registru práce](WORK_PLAN_CURRENT.md),
živý release vždy CURRENT_STATE. [Produkční přejímka](verification/staff-notifications-release-20261106/RESULTS.md)
dokládá skutečný přihlášený zvoneček, přesný odkaz do žádosti, osobní přečtení
a návrat do původního kontextu. Tyto kroky již nejsou neprovedené.
Zbývá potvrzení příjemce o doručení stávající zprávy do schránky. Produkční
schválení/zamítnutí žádosti se kvůli zkoušce neprovádělo; mobilní průchod
je doložen synteticky, produkční prohlížeč byl desktopový.
Tato dokumentační relace neopakuje instalaci, rozesílání ani produkční zápis.
