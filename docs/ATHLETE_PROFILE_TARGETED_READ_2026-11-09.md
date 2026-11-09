# P11-RIX — cílené čtení profilu podle skutečného schématu

9. 11. 2026. Stav: ROZPRACOVÁNO, fáze diagnózy/výběru. Vlastník: vývoj/Codex.
[Registr](WORK_PLAN_CURRENT.md), [předání](SESSION_HANDOFF_ATHLETE_TARGETED_READ_2026-11-09.md),
[důkazy](verification/athlete-profile-targeted-read-20261109/RESULTS.md).

## Zadání, oprávnění a základ

Aktuální pokyn vlastníka: další optimalizace s největším přínosem podle analýzy,
striktní shoda a párové před/po; pouze při PASS commit, merge a protected deploy.
Zachovat WIP, průběžný plán i session handoff v repu. Oprávnění zahrnuje
nutnou čtecí diagnózu a syntetické testy, nikoli role/platby/importy nebo
převod skutečných dat. Produkční UAT evidovat samostatně, import/UAT false.

Base main b66cc059b7064081ec1ef5eddd8dec2ba81d55ec, poslední runtime17110a7f,
deploy42679050552 SUCCESS. Rozdíl main/runtime pouze dokumentace.
Větev codex/athlete-profile-targeted-read-20261109, izolovaný worktree
C:/Users/Marek/Documents/Codex/evidencePavel-medical-state-performance-20261107.
Původní C:/xampp/htdocs/evidencePavel, větev codex/fio-auto-reconciliation-recovery,
WIP kis_rosters_admin.php SHA256
7a7afd75bfa34f736f158096ab46a671b7fee6c6faedc3701f989c0cf64733e5 beze změny.

## Výběr a zjištěná nejistota

Původní analýza P11 priorit1–4 je již implementovaná v navazujících řezech;
neopakovat jejich úspory. Návaznost zde: cílené čtení/tréninkové projekce a
EXPLAIN/indexy (body4/5 původní analýzy). Současná RHMS diagnóza ukazuje
souhrn325/540ms versus detail5/6ms, parent/historie stovky ms. Malé/empty
profily přitom platí skoro stejný průchod klubem. Bootstrap jednotlivé SQL
jsou v této diagnóze nízké jednotky ms. To je nejsilnější současný kandidát,
nikoli tvrzení o návštěvnosti nebo měřeném produkčním výkonu.

Zásadní nejistota: poslední plně migrovaná syntetická fixture má pouze PK
na mereni_zaznamy a trenink_mereni, zatímco database/local-demo-schema.sql
obsahuje idx_sportovec a idx_mereni. Nelze tvrdit, že indexy chybí produkci,
ani podle neúplné fixture plánovat duplicitní produkční DDL. Dřívější
syntetické výsledky zůstávají platné pro tehdy výslovně doloženou fixture;
nejsou výkonem produkce. Všechny staré raw/schema/statistiky zachovat.

## Postup a brány přijetí

1. Čtecí metadatová inventura pěti sportovních tabulek přes chráněný workflow:
   treninky, trenink_sportovec, trenink_mereni, mereni_zaznamy a sportovec_poznamka.
   Jen definice sloupců/indexů, engine, odhady kardinality a verze serveru.
   READ ONLY transakce, žádné hodnoty osob/poznámek/měření, produkční COUNT
   historie, EXPLAIN ANALYZE, ANALYZE, DDL nebo změna nastavení/cache.
   Pomocné soubory jen do soukromého deploy adresáře a přesně vyjmenovaný úklid.
   Podpůrný CLI/workflow merge po vlastních testech/CI bez deploye aplikace;
   potom spustit z main. Příprava tohoto nástroje není výsledná optimalizace.
2. Podle metadat sladit pouze vlastněnou syntetickou fixture před OBOU
   variantami; metadata a rozdíly zveřejnit. Žádná úprava aplikace kvůli
   chybějícím fixture indexům. Nové produkční indexy zatím nejsou vybrané.
3. Zmrazit aktuální reader/parent/endpoint jako oracle. Celý bootstrap
   diagnózy na10.3/11.4 s reprezentativními indexy, small/old/empty/velký,
   header/historie/Měření. Vybrat konkrétní nejdražší SQL podle těchto dat.
   Kandidát: nejprve vlastněné klíče a potom data místo full-club EXISTS
   průchodu; zachovat přesné PHP sčítání délek i pořadí/pagination/scope.
4. Striktní plné návratové objekty/PHPtypy a celé HTML/JS proti baseline,
   owner/foreign/orphan/duplicate/NULL/zero datum, signed64, exact typy,
   všechny filtry/stránky, čerstvé změny, role/token/revokace/CSRF a canonical
   checksumy. Žádná persistentní cache oprávnění nebo změna writerů.
5. Párové celé HTTP před/po na stejné DB: 1k/10k/30k tréninků, 17 střídavých
   párů po4warmupech, cacheOFF a společné čerstvé statistiky. Hlavní cílová
   cesta alespoň20% úspora na obou DB, kontrolní scénáře bez významné regrese
   (po< před*1.2+20ms); výsledky všech scénářů zveřejnit. SQL/EXPLAIN a PHP
   profiler oddělit od neinstrumentovaných časů. Skutečný Chrome default/
   uložené Měření desktop/390px, konzole a snímky; žádné mock requesty.
6. Všechna aktuální CI SUCCESS, exact testovaný/sloučený strom a runtime,
   pak protected deploy z main s úplnou ověřenou zálohou, migrace/VS/config/
   smoke a veřejné čtecí před/po. Finální důkazy samostatným docs PR bez
   druhého nasazení. NOT RUN zůstává viditelné, samotný support PR nestačí.

Další krok: implementovat/testovat metadatový nástroj, CI/merge a read-only
produkční inventura; poté konkrétní nativní diagnóza a výběr optimalizace.
Nový app merge/deploy: NOT RUN. Původní produkce a WIP zachované.
