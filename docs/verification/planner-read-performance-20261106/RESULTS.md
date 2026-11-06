# P11 / R05 — optimalizace plánovače 6. 11. 2026

Vlastník zadal autonomní optimalizaci podle měření, stejná syntetická data,
zachování chování a WIP, bez produkčních změn. Výchozí main `84f5c7a2` / #352.
Větev `codex/planner-read-performance-20261106`, vlastní kopie
`C:/Users/Marek/Documents/Codex/evidencePavel-planner-performance-20261106`.
Původní `kis_rosters_admin.php` při převzetí SHA256
`7a7afd75bfa34f736f158096ab46a671b7fee6c6faedc3701f989c0cf64733e5`.

**Implementace v pracovní kopii hotová:** dávkové souhrny RSVP pro plánovač
a agregované historiové upozornění bez filtru soupisky. Regrese **38 testů /
584 assertions PASS**, JavaScript plánovače **4/4 PASS**, cílený PHPStan a styl PASS.
Párové měření a MariaDB parita se ověří v CI; dosud NOT RUN.
[JUnit](targeted-tests.xml).
[Výchozí metoda a šest R05 případů](../synthetic-performance-20261106/RESULTS.md).

## Postup a hranice

1. Načíst data pro počty RSVP v dávkách, zachovat živé členství a hierarchii
   ke dni každého plánu, deduplikaci osob a překryvů i odpovědi jen oprávněných
   účastníků. Nečíst jména, poznámky nebo jiná pole nepotřebná pro počty.
2. Sdílet pouze metadata tabulek v explicitním čtecím scope. Neukládat role,
   oprávnění ani data mezi požadavky. V transakci zachovat dosavadní cestu
   a její čerstvé kontroly/zámky; writer ani POST nepřepisovat.
3. Bez filtru soupisky vracet počet a nejstarší datum nezapsaných plánů
   přímo agregací. U hierarchického filtru zachovat současné datované filtrování.
4. Porovnat před/po na stejných 1/12/52 týdnech a rolích trenér/vedoucí,
   5 zahřátí + 30 vzorků, samostatné SQL/řádky/EXPLAIN. Kontrolovat úplná
   doménová data i HTML, odlišnosti metody výslovně popsat.
5. Doplnit paritu a čerstvost pro hraniční členství, hierarchii, filtry,
   chybějící/duplicitní plány a změny dat mezi voláními; ověřit CI 10.3/11.4.

Výkonový profil měří tělo GET a šablonu bez společného bootstrapu/navigace;
není to produkční SLA. R06 docházka a plné HTTP měření zůstávají další úkoly.
R13 #352 je pouze začleněné, jeho nasazení zde není povolené. Produkční stav
nadále určuje [CURRENT_STATE](../../CURRENT_STATE.md).


## Přesná metoda nového porovnání

Na stejném CI runneru/serveru nejprve checkout původní aplikace `84f5c7a2`,
potom současná aplikace. Stejných šest R05 scénářů, stejný migrovaný fixture
katalog a limity jako #349. Žádná místní MariaDB, produkční config ani dump
se pro tuto relaci nezakládá/nepoužívá. Místně proběhly pouze SQLite regrese.

[Zmrazený původní měřič](before-harness.php.txt) se liší od původního jen
přidaným HTML SHA po sjednocení CRLF/LF **mimo časový a paměťový interval**.
Kromě této konvence konců řádků a stávající procesní CSP nonce se HTML nemění
ani nenormalizuje. Nový měřič zaznamená také otisky planner helperů a scope.
Komparátor vyžaduje úplnou shodu doménových dat a HTML. Celý nový profil má
nadále 24 scénářů včetně již optimalizovaného R13; jeho dosavadní parita a
párové měření zůstávají součástí CI.

Nový planner reader nezasahuje do sdíleného `trainingRsvpPlanSummaries` ani
writeru. V transakci se na tuto původní funkci výslovně vrací. Budoucí kontrola
historie při vybraném hierarchickém filtru soupisky dál načítá kandidátní ID/data;
její chování bylo zachované a agregace se používá jen bez tohoto filtru.
