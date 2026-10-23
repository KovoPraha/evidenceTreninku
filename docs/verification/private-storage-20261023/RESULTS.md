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

Navazuje samostatný admin/CSRF POST v `uat_readiness_admin.php`, který
vytvoří pouze vlastní náhodný soubor, ověří 0600, zápis a obsah a soubor
odstraní. GET zůstává read-only. Finální výsledek nasazení a webové zkoušky
se doplní po provedení; samotný předchozí read-only PASS jej nenahrazuje.

## Co zůstává otevřené

Skutečná uživatelská a účetní přejímka, doložené granty trenérů, počáteční
inventury a pravidla příspěvků zůstávají podle předchozího protokolu otevřené.
Nastavení starších TEST rodin, inboxu, kontrolora banky a testovacího okna
nebylo odhadnuto. Celkové BLOCKED na starším UAT přehledu tedy může trvat
i při zelené souborové zkoušce.
