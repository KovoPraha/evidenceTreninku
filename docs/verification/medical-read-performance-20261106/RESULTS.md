# P11 / R13 — optimalizace zdravotního přehledu 6. 11. 2026

## Zadání a stav

Vlastník zadal autonomní optimalizaci podle předchozího měření, porovnání na
stejných syntetických datech, zachování chování a WIP, bez produkčních změn.
Výchozí main `f7c944e6`, větev `codex/medical-read-performance-20261106`, kopie
`C:/Users/Marek/Documents/Codex/evidencePavel-medical-performance-20261106`.
Původní `kis_rosters_admin.php` SHA256 při převzetí
`7a7afd75bfa34f736f158096ab46a671b7fee6c6faedc3701f989c0cf64733e5`.

**Implementace v pracovní kopii hotová:** stránka načítá jen vlastní metadata,
stavový filtr pracuje po 200 kandidátech, výběr osob má hledání a stránkování.
První místní profil dokončený (50 224 → 132 načtených řádků u 10k sledování),
finální opakování po kontrole UI a přesné zdrojové identity ještě zbývá.
Regrese **32 testů / 14 143 assertions PASS**, MariaDB 10.4 parita **36 kombinací
filtrů/stránek PASS** proti původnímu algoritmu. Styl a PHPStan PASS.
Skutečný místní browser otevřel stránku s navigací, našel osobu #10100 a zachoval
ji po změně hledání mimo první stránku nabídek. Kontrola další stránky a
finální měření ještě běží; CI zatím NOT RUN. Produkce se nemění.
[Výchozí měření a přesná metoda](../synthetic-performance-20261106/RESULTS.md),
[registr práce](../../WORK_PLAN_CURRENT.md).

## Postup a meze

1. Bez stavového filtru nejprve počítat celkový počet a vybrat stránku 25
   sledování, potom načíst pouze jejich stavová metadata. Zachovat pořadí,
   vyhledávání, datum, osobu, počty, prázdný výsledek i ořez příliš vysoké stránky.
2. Stavový filtr dál používat stejný kanonický PHP výpočet, včetně řetězců
   nahrazení a odvolání. Kandidáty zpracovávat po omezených dávkách; zachovat
   úplný počet a správnou poslední stránku bez držení celého výsledku v paměti.
3. Výběr osoby omezit vyhledáváním a stránkováním; zachovat možnost vybrat
   libovolnou existující osobu včetně archivních i osobu z předaného kontextu.
   POST, ověřování dokumentů, oprávnění a soukromé stahování neměnit.
4. Zopakovat stejnou syntetickou matici před/po na stejném vlastním localhost
   serveru. Oddělit čas/paměť od SQL instrumentace, ověřit doménové výsledky;
   změnu HTML výběru osob popsat jako záměrnou změnu způsobu výběru.
5. Doplnit paritní regrese stavů/filtrů/stránek a omezeného výběru osob,
   ověřit MariaDB 10.3/11.4 v CI a aktualizovat handoff s konkrétními výsledky.

## Co zůstává mimo rozsah

R05 plánovač, R06 docházka, produkční nasazení/UAT, skutečné podklady a platby,
heslo/T07 a souběžný ranní audit. Plné HTTP/bootstrap/nav/browser měření
zůstává samostatné; tento profil není produkční SLA. Stavový filtr může pro
přesný počet stále potřebovat projít všechny kandidáty; dávkování neznamená
automaticky méně prohledaných řádků na databázovém serveru.
