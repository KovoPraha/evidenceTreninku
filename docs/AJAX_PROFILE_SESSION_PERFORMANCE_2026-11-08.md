# P11-RAS — souběh čtecí historie a měření profilu

8. 11. 2026: ROZPRACOVÁNO, plán před implementací.
Vlastník: vývoj podle aktuálního zadání Marka; commit/merge/deploy při přesné
shodě, skutečném přínosu a aktuálních kontrolách. [Předání](SESSION_HANDOFF_AJAX_PROFILE_SESSION_2026-11-08.md),
[výsledky](verification/ajax-profile-session-performance-20261108/RESULTS.md).

## Důvod a rozsah

[Analýza6](verification/performance-analysis-20261107/RESULTS.md): sdílená session
může zablokovat další požadavek, i když jsou samotná čtení již omezená.
[Uzavřené RHA](ATHLETE_PROFILE_READ_PERFORMANCE_2026-11-08.md) čte po25, ale profil
při uložené záložce Měření posílá obě AJAX čtení. Oba režimy stejného endpointu
nechávají session otevřenou až do konce. Profil měření má při10k historii
stále několik set ms čtení, kdy může druhý požadavek pouze čekat.
[Původně prověřovaný RGS](GLOBAL_SEARCH_SESSION_PERFORMANCE_2026-11-08.md)
nevybrán: hledací input je v aktuální hlavičce vypnutý. Nezapínat jej.

Basec548d1d3b49ef63645d8e9ff421f823ce55e1712; produkce82efa16d,
deploy42521982178 SUCCESS. Větev codex/ajax-profile-session-performance-20261108,
worktree C:/Users/Marek/Documents/Codex/evidencePavel-medical-state-performance-20261107.
Původní checkout codex/fio-auto-reconciliation-recovery a WIP zachované,
kis_rosters_admin.php SHA256
`7a7afd75bfa34f736f158096ab46a671b7fee6c6faedc3701f989c0cf64733e5`.
RHA/RHC neopakovat, neslučovat cizí otevřené PR; přebírat jen přijatý main.

## Postup a akceptace

1. Zmrazit celý ajax_sportovec_treninky.php na base. Nový zásah jen tento route:
   uzavřít session až po lifecycle/fresh DB revokaci, resolve profile session,
   person check a sportovecNoteCanWrite; před dlouhými čistými projekcemi.
   Mít lokálně vše potřebné. Žádná globální cache práv, TTL/rotace/CSRF změna,
   nový handler/index/DDL, změna čtecího SQL nebo zápisových endpointů.
2. Vlastní migrované schéma, MariaDB10.3/11.4, cacheOFF, skutečné standardní
   login/bearer303 toky. Dva oddělené PHP HTTP worker clustery: zmrazená route
   a nová route, ostatní runtime soubory stejné. Souběžně přístup jen na localhost,
   explicitní files handler, žádné podstrčení session, sleep/SQL SLEEP nebo stuby.
3. Stejná data1000/10000/30000 tréninků a vlastních/cizích měření. Čtyři zahřátí
   a17střídavých before/after párů se střídáním stejné/jiné session téhož aktéra.
   Dvojice Měření +Historie (skutečná startovací práce) a krátký prázdný rok jako
   kontrola samotného čekání; jednotlivé požadavky zvlášť. Časy bez instrumentace,
   přímé pozorování běžící SQL a file locku odděleně od měření.
4. Striktní shoda celé fragmentové odpovědi, statusů, pořadí, stránkování a
   úplných souhrnů pro oba režimy/roky/typy, vlastních poznámek i práv ovládání.
   Nepřenášet skutečné profily, soukromé HTML, credentials, cookies/session do Gitu.
5. Staff/bearer/rodinný gate zůstává stejný. Dvanáct standardních staff rolí,
   cizí/neplatný/odvolaný přístup, token303 a čerstvá revokace. Uložená profile
   session a CSRF přežijí čtení; kanonické CSRF odhlášení souběžně s čtením
   musí zůstat platné a staré dokončené čtení nesmí session oživit.
   Žádné produkční změny rolí, účtů, poznámek nebo záznamů.
6. Chrome podle skillu Playwright: skutečný profil, savedtab Měření, dvě souběžné
   odpovědi i celá navigace/fetch→DOM/dvě animační smyčky; filtry/stránky/rychlé
   přepnutí/desktop390px. Žádný nový route ani aktivace vypnutých UI.
7. Checksumy domén a cleanup vlastního schématu/configu/účtu/browseru a všech
   workerů vlastního process group; bezpečné vzorky/zdrojové hashy do repa.
   Nepředstírat, že menší souběžný wait znamená rychlejší samotné SQL.
8. Aktuální celé CI, measured-source a whole tested/merged tree shoda, chráněný
   deploy import/UATfalse, backup/VS/migrace/smoke, čtecí produkční kontrola.
   Finální důkazy/plan/handoff do main, bez nadbytečného druhého deploye dokumentace.

Produkční session handler ani skutečný lock wait zatím NEOVĚŘENÉ. Závěr pro files
handler z vlastní fixture není produkční SLA. Přihlášené produkční UAT samostatné.
Pokud běžící SQL nezpůsobuje prokazatelný wait nebo není čistý přínos celé cesty,
tento kandidát neuvolnit. Další krok: vlastní fixture a první before/after páry.

## Implementace 8. 11. 2026

Lokálně přidáno guarded session_write_close po všech fresh přístupových kontrolách.
Read SQL/HTML zůstávají stejné. Vlastní runner porovnává celý zmrazený route
na basec548 proti nové verzi, dva čtyřworkerové servery a skutečné login/grant/
bearer303, původní doménové checksumy, 3objemy a17párů po4zahřátích.
Skutečná dvojice odpovídá savedtab Měření+Historie aktuálního roku; prázdný
rok2099 izoluje čekání. Chrome používá existující UI v obou variantách.
Lokální style helper hlásí staré untracked output/probe PHP; ty neměněny.
Vlastní syntax/style ověřit samostatně a v čistém CI. Merge/deploy NOT RUN.
