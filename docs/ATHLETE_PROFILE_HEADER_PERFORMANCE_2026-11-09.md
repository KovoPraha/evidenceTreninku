# P11-RAH — agregace roků a počtů profilu sportovce

Datum: 9. 11. 2026. Stav: ROZPRACOVÁNO; implementace, CI, merge a deploy čekají.
Vlastník: vývoj/Codex; zadavatel: vlastník aplikace, aktuální pokyn v této relaci.
[Registr](WORK_PLAN_CURRENT.md), [předání](SESSION_HANDOFF_ATHLETE_HEADER_2026-11-09.md),
[důkazy](verification/athlete-profile-header-performance-20261109/RESULTS.md).

## Rozsah a autorizace

Navazuje na dokončené P11-RAP #408/#409. Baseline main
`2c61df1495c570a5cfd9ba8032027595ab02de3c`, runtime odpovídá produkčnímu ca5fc219.
Uživatel výslovně povolil implementaci a při prokázané paritě/přínosu commit,
merge a protected deploy. Import a UAT flag false, žádné změny rolí/providerů.
Větev `codex/athlete-header-performance-20261109`, izolovaný worktree
`C:/Users/Marek/Documents/Codex/evidencePavel-medical-state-performance-20261107`.
Původní `C:/xampp/htdocs/evidencePavel` WIP kis_rosters_admin.php zůstává nedotčený,
SHA256 7a7afd75bfa34f736f158096ab46a671b7fee6c6faedc3701f989c0cf64733e5.
Stage pouze vlastní cesty. Bez produkčního DDL, indexu či cache.

## Volba a postup

Dosavadní diagnóza RAP ukazuje tři nákladné agregace; roky a počet opakují UNION.
Kandidát: jeden seskupený průchod vlastních attendance/measurement klíčů vrací
roky a oba součty. Původní tři public metody zůstávají nezměněné jako reference
pro testy a nezávislý fallback při PDOException. Alternativa: sloučit pouze
roky/počet a měření počítat zvlášť, pokud úplná agregace zpomalí celou stránku.
Žádné změny AJAX, poznámek, oprávnění, session, TTL nebo kanonických souhrnů.

1. Zapsat a commitnout plán před implementací; ověřit baseline/WIP.
2. Doplnit reader a parent; parita proti původním třem metodám: duplicity účasti,
   duplicitní vazby měření, orphan/cizí/unlinked, measurement-only/attendance-only,
   null/0rok, PDO typy/native i emulated prepares, prázdný profil a čerstvé změny.
3. Native MariaDB10.3/11.4, cacheOFF; zmrazit parent i reader z přesné baseline.
   Celá HTTP stránka 1k/10k/30k, primární/malý/prázdný profil,4warmup+17střídavých
   párů. SQL/paměť diagnostikovat odděleně, nikoli při časování.
4. Celý normalizovaný HTML/JS shodný (jen nonce/CSRF); raw AJAX byte-shodné.
   Skutečný login/bearer,12rolí, revokace/expirace/scope/CSRF, checksumy domény.
   Ověřit starý nezávislý fallback na syntetické chybě schématu s obnovou finally.
5. Skutečný Chrome/Playwright CLI: default i uložená záložka Měření,
   3cold+10warm na variantu; document TTFB/load, připravenost požadovaných fragmentů,
   filtry/stránky/záložky/390px, chyby0. Bez stubů/zpoždění a účelových UI změn.
6. Přínos celé parent cesty musí být měřitelný (cílově alespoň20% na30k),
   malé/prázdné profily bez podstatné regrese. Paměť reportovat, nemá umělý práh.
7. Aktuální celé CI, measured-source SHA256 a tested/merged whole-tree shodné;
   protected deploy, backup/migrace/VS/config/smoke, anonymní GET/DOM před/po.
8. Průběžné bezpečné důkazy a finální handoff přes PR do main. Dokumentace
   nevyžaduje druhý deploy. Produkční výkon a přihlášené UAT označit NOT RUN.

Další krok: implementovat a lokálně ověřit kandidáta, spustit native páry.
