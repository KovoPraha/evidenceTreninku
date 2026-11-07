# Lidé a přístupy — místní ověření 7. 11. 2026

[Zadání, rozsah, větev a další krok](../../ACCESS_OVERVIEW_2026-11-07.md).
Základ `89edfdbe`, PHP 8.2.12, SQLite; pouze syntetické účty LOCALHOST.

## Výsledek

- PASS: 44 cílených PHPUnit testů / 2413 assertions:
  `StaffAccessOverviewTest|StaffWorkspaceRegistryTest|StaffWorkspaceMigrationTest|StaffAccountLifecycleTest|BarFoundationTest`.
  Nových pět integračních testů ověřuje úplnost seznamu, kombinace filtrů,
  všechny kanonické role bez duplicit a samostatné auditované povolení spotřeby.
- PASS: [16 kontrol formulářů přes HTTP](http-results.json): hledání a kombinace
  filtrů, prázdný výsledek, uložení/persistence rolí, odmítnutí neplatného CSRF
  bez auditu, nepřiřazená výchozí role, povolení/odebrání spotřeby a dvě auditní
  události, zákaz správy pro trenéra HTTP 403, návrat syntetických rolí přes UI.
- PASS: skutečný Chrome / Playwright CLI, filtr Bar (3 ze 7 účtů včetně
  superadmina), přechod tlačítkem do editoru, desktop 1280×720 a mobil 390×844.
  Mobilní stránka má přesně 390 px bez přetečení; tabulka se posouvá uvnitř svého
  kontejneru. [Desktop](desktop.png), [mobilní přehled](mobile-overview.png).
- PASS: syntax změněných PHP souborů, PSR-12 a limit délky řádků pro pět změněných
  PHP souborů; dokumentační kontrakt a `git diff --check`.
- PASS: úplná statická analýza PHPStan level 5 nad projektovou konfigurací,
  `php vendor/bin/phpstan analyse --memory-limit=1G --no-progress`: bez chyb.
- PASS: samostatně zopakovaných pět nových testů / 25 assertions; konzole
  poslední stránky Chrome bez chyb a varování. Vlastní browser i server 8137 zastavené.

## Meze ověření a provozní předání

HTTP/browser použily kopii aktuální cílové stránky s nahrazeným bootstrapem
a hlavičkou. Bootstrap otevíral pouze syntetickou SQLite z `barTestDatabase()`
a nastavil syntetickou session správce. Samotná stránka, formuláře, CSRF,
registr pracovních pozic, změna rolí a služba `barSetConsumptionPermission()`
byly skutečný aplikační kód. Nejde o ověření úplného produkčního bootstrapu,
loginu nebo živých účtů. Při prvním prokliku editoru CLI vypršel krátký
navigační timeout; následný snapshot a mobilní screenshot potvrdily dokončený
přechod na správný účet. Úvodní chybně odkazovaný soubor v testovacím bootstrapu
byl opraven před úspěšným průchodem; aplikační kód tím nebyl dotčen.

Nové schéma není potřeba. Změny běžných rolí používají dosavadní auditovaný
POST; vlastní spotřeba používá existující transakční a databázovou autorizaci baru.
Superadminovi se zobrazují všechny dostupné role, ale zvláštní grant vlastní
spotřeby musí mít také explicitně. Trenérské soukromé rozsahy se nemění.

Merge, deploy, změny produkčních přiřazení a přihlášené produkční UAT: **NOT RUN**.
Další krok: revize, samostatně schválené nasazení a čtecí kontrola přehledu.
