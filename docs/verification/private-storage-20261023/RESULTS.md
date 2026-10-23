# Soukromé úložiště a navazující browser přejímka — 23. 10. 2026

## Změna hostingu

Vlastník výslovně schválil `open_basedir: Výchozí → Posunuto` pouze pro
`kis.kovopraha.cz`. Administrace Českého hostingu potvrdila uložení.
Jde o širší rozsah zahrnující i jiné subdomény, nikoli výjimku pro jediný
adresář. PHP zůstalo 8.2; ostatní nastavení PHP, DNS, SSL, DocumentRoot,
účty a oprávnění osob nebyly změněny.

Nový produkční webový požadavek v 10:32:03 na vydání `0b03b564`:
nastavený root, umístění mimo veřejný strom, existující adresář, zápis,
povolený rozsah open_basedir, realpath a stat **PASS**. Efektivní UID
webového procesu zůstává nedostupné. [Screenshot](readiness-pass.jpg).

## Navazující čtecí kontroly

1. Účetní export z pozice Správce systému odmítnut; po přepnutí na Hospodáře
   se zobrazil formulář a prázdný archiv. Export nebyl vytvořen.
2. Neexistující PDF (`id=0`) v hospodářské pozici bezpečně odmítnuto bez
   zveřejnění cesty či obsahu.
3. Neexistující export (`id=0`) bezpečně odmítnut jako nenalezený.
4. Přímé stažení PDF z kontextu správce katalogu odmítnuto na hranici pozice.
5. Přímé stažení účetního exportu z kontextu katalogu stejně odmítnuto.
6. Přímý odkaz na soukromou účtenku z kontextu katalogu odmítnut před čtením.
7. Přímý odkaz na soukromou fotografii sportovce z kontextu katalogu odmítnut
   před čtením i auditem zobrazení fotografie.

[Strojový záznam](browser-checks.json) obsahuje šest samostatných negativních
download kontrol a potvrzení přístupu k úložišti. První kontrola pracovní
pozice a otevření exportu jsou popsány výše. Nejde o důkaz autorizace všech
omezených účtů: používal se existující superadmin se změnou aktivního kontextu.

Žádné finanční doklady, peníze, skladové pohyby, objednávky, registrace ani
předání účetní při tomto průchodu nevznikly. Staré systémy nebyly vypnuty.

## Technická zkouška webového zápisu

PR [#171](https://github.com/KovoPraha/evidenceTreninku/pull/171) přidává
samostatný admin/CSRF POST v `uat_readiness_admin.php`, který
vytvoří pouze vlastní náhodný soubor, ověří 0600, zápis a obsah a soubor
odstraní. GET zůstává read-only.

[Nasazení 39926289201](https://github.com/KovoPraha/evidenceTreninku/actions/runs/39926289201)
**SUCCESS**, živé SHA `fb2e00c72eaeb576b79fc413000278df8174b153`,
deploy timestamp `2026-10-23T08:42:47+00:00`. Vydání ověřeno také v prohlížeči.
Deploy znovu ověřil zálohu 329 produkčních tabulek a zachování dosavadních
variabilních symbolů (`unchanged=true`). Import kroužků zůstal vypnutý.

**23. 10. 2026 v 10:44:11 Europe/Prague skutečný webový POST: PASS.**
Přístup, vytvoření vlastního souboru, nastavení a ověření práv 0600,
úplný zápis/flush, shoda přečteného obsahu a potvrzené odstranění = ANO.
Sonda proběhla jednou. Nebyl použit účetní doklad ani změněna databázová evidence.
[Celý panel](web-probe-pass.jpg), [všech šest kroků a práva 0600](web-probe-result.jpg).
**Původní P0 blok soukromého úložiště je uzavřený.**

## Ověření implementace

- Linux CI: **1 584 testů / 32 313 assertions PASS**, MariaDB 10.3 / 11.4
  integrační kontroly a CodeQL PASS. Stejné kontroly PASS také na výsledném
  merge commitu `fb2e00c72eaeb576b79fc413000278df8174b153`.
- Windows cílená sada: 41 testů / 2 135 assertions PASS; dva unixové
  symlinkové testy lokálně přeskočeny, skutečně provedeny v Linux CI.
- Testy zahrnují částečný zápis, chybu flush, nesouhlas obsahu, odmítnuté
  vytvoření/práva/mazání, ochranu nahrazeného symlinku a cizího souboru.
  Subprocess vykonává skutečný autorizační a POST blok stránky s testovacím
  session bootstrapem: anonymní/coach/nesprávný kontext/CSRF/GET nevolají
  sondu, oprávněný POST projde a uklidí soubor. Nejde o produkční zápisové
  testy s omezenými účty.
- Celý místní Windows běh našel jeden existující falešný poplach statického
  scanneru: volitelná funkce `posix_geteuid` v předchozí diagnostice není na
  Windows dostupná, ačkoli runtime volání chrání `function_exists`.
  Tento test nebyl oslaben ani vydáván za zelený; autoritativní Linux sada
  včetně tohoto scanneru prošla. Žádná další lokální chyba se neobjevila.

## Co zůstává otevřené

Skutečná uživatelská a účetní přejímka, doložené granty trenérů, počáteční
inventury a pravidla příspěvků zůstávají podle předchozího protokolu otevřené.
Nastavení starších TEST rodin, inboxu, kontrolora banky a testovacího okna
nebylo odhadnuto. Celkové BLOCKED na starším UAT přehledu tedy může trvat
i při zelené souborové zkoušce.
