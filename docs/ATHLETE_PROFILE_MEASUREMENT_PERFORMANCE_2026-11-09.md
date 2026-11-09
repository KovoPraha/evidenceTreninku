# P11-RHM — načítání měření profilu sportovce

9. 11. 2026. Vlastník vývoj/Codex. Stav: ROZPRACOVÁNO; implementace/CI/release čekají.
[Registr](WORK_PLAN_CURRENT.md), [předání](SESSION_HANDOFF_ATHLETE_MEASUREMENT_2026-11-09.md),
[důkazy](verification/athlete-profile-measurement-performance-20261109/RESULTS.md).

## Rozsah a oprávnění

Aktuální pokyn vlastníka výslovně povoluje optimalizaci měření a po shodě/přínosu
commit/merge/protected deploy. Import/UATfalse; žádné role, platby nebo osobní zápisy.
Baseline main5ff9fa2a65123aa1eef58be3678943cb4bfc2b53, runtime c3d34b87,
deploy42625502461 SUCCESS. Předchozí RHI/RAH/RAP úspory se novému řezu nepřičítají.
Větev codex/athlete-measurement-performance-20261109, izolovaný worktree
C:/Users/Marek/Documents/Codex/evidencePavel-medical-state-performance-20261107.
Původní C:/xampp/htdocs/evidencePavel/codex/fio-auto-reconciliation-recovery
WIP kis_rosters_admin.php SHA256
7a7afd75bfa34f736f158096ab46a671b7fee6c6faedc3701f989c0cf64733e5 zachovaný.
Stage pouze vlastní cesty; bez nových DDL/indexů/cache nebo změny DB nastavení.

## Diagnóza a varianty

Aktuální souhrn počítá ROW_NUMBER pro všechny vazby a znovu připojuje všechna
měření, aby dostal binární typy a první výskyt. Předchozí RHA odmítlo prosté
přenesení všech typů do PHP kvůli regresi; tuto variantu neopakovat.
Kandidát: binární GROUP BY s COUNT a jedním prvním klíčem přes MariaDB
GROUP_CONCAT(JSON_ARRAY(training_id,poradi,measurement_id) ORDER BY ... LIMIT1).
Pak připojit pouze první reprezentanty skupin a řadit jejich původní date/id/
nullableporadi/measurementid, bez číslování celé historie či hashových klíčů.
NULLtyp se počítá do all, nikoli do mapy typů, jako dřív; duplicity vazeb se počítají.

MariaDB LIMIT uvnitř GROUP_CONCAT podporuje od10.3.3. Před fastpath ověřit
PDO server verzi a aktuální SESSION group_concat_max_len alespoň128bytů;
klíč obsahuje pouze3integer/null hodnoty a vejde se do limitu. Při menším
limitu nebo jiném backendu použít původní okénkový souhrn, bez změny nastavení.
Produkční read-only indexový protokol posledního release uvádí MariaDB10.3.39.
[Oficiální syntaxe a truncation](https://mariadb.com/docs/server/reference/sql-functions/aggregate-functions/group_concat),
[verzová podpora](https://mariadb.com/docs/release-notes/community-server/old-releases/10.3/10.3.3).
Alternativa: ponechat souhrn a optimalizovat jen stránku klíčů, pokud agregační
kandidát nezrychlí celou odpověď. Při nedoloženém přínosu kandidáta neuvolnit.

## Akceptace

1. Plán commitnout před implementací; zmrazený reader+parent+endpoint z5ff9fa2a.
2. Přesná parita úplného návratového objektu a PHP typů proti zmrazené metodě:
   všechny stránky/filtry, case/mezery/diakritika/numerické a neznámé typy,
   NULL/empty typy, nulové/null datum, poradiNULL/0/negativní/extremy,
   remízy/duplicate vazby/měření ve více trénincích/orphans/foreign/unlinked,
   čerstvé změny. Native/emulated prepares a stringify, limity128 a4 pro fallback.
3. SQLite a skutečná MariaDB10.3/11.4; cacheOFF,1k/10k/30k tréninků a až270k
   vlastních vazeb.4warmups+17střídavých before/after párů, typ vše/exactcase/
   trailing space/neexistující/page2/last, velký/malý/prázdný profil.
   Wholefragment byte-parita, parentHTML/JS pouze nonce/CSRF normalizace.
4. Samostatná SQL/řádková/PHPpeak diagnostika mimo prosté HTTP časy; cílově
   alespoň20% úspory měření30k bez podstatné regrese kontrol či paměti.
5. Skutečný Chrome/PlaywrightCLI: default a uložené Měření,3cold+10warm/scénář/
   variantu, fetch→DOM+2frames/ResourceTiming/celá navigace odděleně,
   filtry/pagery/záložky/390px/chyby0. Bez stubů nebo změny UI kvůli benchmarku.
6.12staffrolí/bearer303/retarget/revokace/expirace/scope/CSRF/logout,
   2fallbacky parentu,11doménovýchchecksumů a vlastní úplný cleanup PASS.
7. Celé aktuálníCI, measured-source Gitbloby a whole tested/merged tree,
   protected deploy, backup/migrace/VS/config/index/smoke, veřejnéGET/DOM před/po.
   Produkční výkon/přihlášenéUAT/obnova této zálohy samostatně NOT RUN.
8. Průběžně plán/handoff/registr/deníky; finální bezpečné důkazy přes PR do main,
   dokumentace bez druhého deploye. Historie/header/writery/session/HTML nezměněné.

Další krok: implementovat kandidáta a native přesné testy, pak celé HTTP/Chrome.

## Lokální kandidát

Implementována MariaDB agregace s jedním JSON klíčem na binární typ a COUNT,
připojení reprezentantů až po agregaci. Typové filtry i stránka zůstávají
původní. Podmíněná verze>=10.3.3 a SESSIONlimit>=128; bez nastavování DB.
Původní window souhrn zachovaný pro SQLite/MySQL/krátký limit. Metadata dotaz
přidává1SQL na MariaDB, případný přínos se musí projevit v celémHTTP.

Lokálně SQLite10testů/1139assertions PASS (původníprofil/historie/header + nové
3testy/247), PSR12 všech6dotčenýchPHP, PythonAST a composer validate --strict PASS.
Jde o portable/fallback paritu včetně64bitklíčů; skutečný novýSQLfastpath a
limity128/4 musí prokázat nativní CI na MariaDB10.3/11.4. Zatím NOT RUN.
Žádná změna readeru mimo measurements()/nový helper, parent/endpoint/UI/
security/session/writery byte-nezměněné. Baseline3soubory zmrazené z5ff9fa2a.
Vlastní runner připraven pro1k/10k/30k,8scénářů/3profily,4warmups+17párů,
oddělenou SQL/PHP diagnostiku a52Chrome navigací obou režimů.
