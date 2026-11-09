# P11-RHI — načítání historie profilu sportovce

9. 11. 2026, vlastník vývoj/Codex. Stav: ROZPRACOVÁNO; implementace/CI/release čekají.
[Registr](WORK_PLAN_CURRENT.md), [předání](SESSION_HANDOFF_ATHLETE_HISTORY_2026-11-09.md),
[důkazy](verification/athlete-profile-history-performance-20261109/RESULTS.md).

## Rozsah a oprávnění

Aktuální pokyn vlastníka výslovně povoluje optimalizaci historie a po úspěšné
paritě/přínosu commit, merge a protected deploy. Import/UATfalse; žádné
produkční osobní zápisy, nové DDL/indexy nebo cache práv/dat.
Baseline main d666078e2480f16fbc5f1d5b716802054501d57e; runtime odpovídá
produkčnímu c47d4008/deploy42587406908. P11-RAH dokončené, znovu nepřipisovat úspory.
Větev codex/athlete-history-performance-20261109, izolovaný worktree
C:/Users/Marek/Documents/Codex/evidencePavel-medical-state-performance-20261107.
Původní C:/xampp/htdocs/evidencePavel/codex/fio-auto-reconciliation-recovery
WIP kis_rosters_admin.php SHA256
7a7afd75bfa34f736f158096ab46a671b7fee6c6faedc3701f989c0cf64733e5 zachovaný.
Stage pouze vlastní cesty, ostatní untracked artefakty a větve ponechat.

## Diagnóza a varianty

history() načte všechny id/délky a znovu buduje celý UNION pro25detailů.
Kandidát: korelované EXISTS zachová unique membership, vlastní/cizí měření
bez násobení duplicit a ověřuje vlastnictví i rok znovu při detailu.
První průchod číst postupně a držet jen požadovanou/poslední stránku klíčů.
Součet minut ponechat v PHP v původním seřazeném pořadí: SQL SUM by měnil
převod čárek, prázdných/nečíselných hodnot i floating-point pořadí.
Alternativa: optimalizovat pouze detail25ids, pokud EXISTS celé historie
na native DB regreduje. Rozhodnou páry celéHTTP, nikoli jen počet SQL.
PDO buffering neměnit globálně; reportovat skutečný PHP peak, ne slib O(1)
celkové paměti ovladače/DB. Auth/session/TTL/CSRF/poznámkové writery, měření,
header agregace i HTML zachovat. Změna pouze history readmodelu.

## Akceptace a postup

1. Plán commitnout před implementací. Přesná reference history() zmrazená z main.
2. Parita celého návratového objektu/typů: všechny stránky, žádná/prázdná/poslední,
   záporný/velký požadavek, rok0/konkrétní/nenalezený/null datum/zero datum,
   duplicate attendance/measurement, orphan/foreign/measurement-only, poznámky,
   čerstvé změny a délky0/null/čárky/scientific/float. Bez SQL SUM či cache.
3. MariaDB10.3/11.4 a SQLite. Dvě oddělenéHTTP verze, zmrazený reader i endpoint
   z d666078e, stejné vlastní syntetické schéma, cacheOFF,1k/10k/30k historie.
  4warmups+17střídavých párů, hlavní/malý/prázdný profil a několik roků/stránek.
   Celé fragmenty byte-shodné, parentHTML/JS shodné pouze po nonce/CSRF normalizaci.
4. Samostatná SQL/řádková/PHPpeak diagnostika AJAX historie mimo prosté časování.
   Cílově alespoň20% mediánové úspory historie30k a žádná podstatná regrese kontrol.
   Původní/nezdařené vzorky zachovat; performance gates nenutit změnou baseline.
5. Skutečný Chrome přes PlaywrightCLI: default a uložené Měření,3cold+10warm
   na variantu; fetch→DOM+2frames a celá navigace zvlášť, filtry/stránky/390px,
   chyby0. Původní JS/UI neměnit a nepoužívat request stuby/zpoždění.
6.12staffrolí, bearer303/retarget/expiry/revokace/scope/CSRF/logout, nezávislé
   fallbacky parentu,11doménovýchchecksumů a kompletní own cleanup PASS.
7. Celé aktuální CI, measured-source Git SHA256 a whole tested/merged tree,
   poté protected deploy a záloha/migrace/VS/config/index/smoke; veřejnéGET/DOM
   před/po. Přihlášené produkční UAT a produkční výkon samostatně NOT RUN.
8. Průběžně plán/handoff/registr/deníky; finální bezpečné důkazy do main
   samostatným dokumentačním PR bez druhého deploye dokumentace.

Další krok: kandidát a přesné testy, native diagnóza/pairedHTTP/Chrome.
