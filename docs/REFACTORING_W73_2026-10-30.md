# W7.3 — metadata a sekce rodinného přehledu

Zahájeno 30. 10. 2026 z `c498110adf66ea66cbb40ea081a6021e50108e45`.
Větev `codex/refactoring-w73-schema-overview-20261030`, izolovaná pracovní kopie
`C:/Users/Marek/Documents/Codex/evidencePavel-refactoring-w73-20261030`.
Vlastník autorizoval implementaci, sloučení a nasazení po úspěšném ověření.

## Plán uložený před implementací

1. Navázat na skutečný controller a HTML W7.2, zmrazit výchozí stránku a rozdělit
   SQL na nastavení, profily, finance, objednávky, náhrady, program/RSVP,
   týdenní a roční přehled, úkoly a render. Instrumentaci oddělit od času/paměti.
2. Ověřit opakované kontroly metadat. Zavést explicitní krátký rozsah pouze
   při sestavení GET rodinného přehledu, vázaný na konkrétní PDO. Uchovávat
   pouze úspěšné výsledky existence tabulek/sloupců; žádná data osob, rolí,
   finančních částek ani oprávnění. Chyby se neukládají. Mimo rozsah zůstává
   původní čerstvé čtení; ukončení musí nastat i při výjimce.
3. Zachovat původní validační/driver podmínky helperů. Neprovádět runtime DDL,
   migrace ani přepínání databáze uvnitř rozsahu. Nezapínat rozsah pro POST
   a neměnit autorizační kontroly či zámky W7.1b. Existující GET synchronizace
   náhrad zůstává a při testování zapisuje jen syntetická data.
4. RED/GREEN testy: opakované kladné/záporné metadata, různé PDO, nový rozsah
   po změně schématu, výjimky/úklid, nezměněná revokace a data osoby. Porovnat
   kompletní výstupy/HTML 0/1/5/20 osob a širší historii; chybějící optional
   tabulky/sloupce zůstanou samostatnou charakterizací. Bez omezování historie.
5. Plná sada, PHPStan, styl, dokumentace, MariaDB 10.3/11.4 a přesný zelený
   PR/main. Před/po čtecí produkční audit a 26 HTTP kontrol, ověřená záloha,
   release identita a zachování konfigurace/VS. UAT/import kroužků false.
6. Aktualizovat stav, předání, registr cest a důkazy. R03/R04, lidské UAT
   a aktivace integrací nejsou tímto balíkem uzavřeny.

## Stav

Dokončeno, sloučeno v PR #231 a nasazeno po úspěšných kontrolách.
Sedm nových testů, RED/GREEN, shodné výstupy/HTML i nemetadata SQL.
Celý GET 20 osob SQL 2244 → 752, metadata 1590 → 98; historie +1000 vazeb,
POST a čerstvá revokace zachovány. PHPStan bez chyb, 26 legacy výjimek odstraněno.
Plná sada 1821 testů, POSIX i obě MariaDB CI PASS. Před/po čtecí audit,
HTTP 26/26, identita vydání, záloha a shodná konfigurace doloženy.
[Měření a meze](verification/refactoring-w73-20261030/RESULTS.md),
[produkční vydání](verification/refactoring-w73-release-20261030/RESULTS.md).
Navazuje W7.4: 160 opakovaných čtení členských polí, poté R03/R04.
Lidské UAT, účetní převzetí a aktivace integrací zůstávají otevřené.
