# AUTH-MAIL-05 — průběžný výsledek implementace (24. 11. 2026)

**Stav: implementace v PR, další oprava CI kontraktů připravena.** Vlastník Marek/klub
výslovně schválil implementaci a nasazení. Větev
`codex/auth-mail-admin-plan-20261124`,
[PR #524](https://github.com/KovoPraha/evidenceTreninku/pull/524) má poslední
commit `981f79be`. Merge, migrace, produkční deploy a lidská přejímka NOT RUN.

## Rozsah lokální změny

- Nová administrační stránka `mail_admin.php` a přesměrování původní
  `auth_mail_admin.php`: stránkovaný přehled všech šesti zjištěných front
  (`auth`, klubové události, soupisky, týdenní souhrny, upomínky, členství),
  přesné filtry adresy/reference, přehled požadavků registrace, nového ověření
  a obnovy, které neskončily ve frontě. Jen admin vidí adresy a BCC.
- Audit registračního a ověřovacího POST od vstupu přes odmítnutí/chybu po
  atomické `queued`; existující audit resetu dostává klíčovaný otisk adresy
  pro hledání, nikoli čitelnou adresu v auditní tabulce.
- Ruční opakování s CSRF, administrátorskou rolí, důvodem a potvrzením.
  Neautentizační zpráva jde zpět přes původní worker a jeho aktuální ochrany;
  platný selhaný autentizační odkaz lze vrátit do fronty. U starého/odeslaného
  odkazu vznikne atomicky nový token, nový řádek a vazba na původní ID;
  opakované kliknutí ze stejného ID je blokováno. Platnost nového ověření a
  rodičovského/dětského resetu je 3 dny, trenérského resetu 60 minut.
- BCC stejné zprávy ve všech šesti workerech: výchozí stav vypnuto,
  administrátor nastavuje jednu validovanou adresu pro konkrétní ještě
  neodeslaný řádek, důvod a potvrzení. Centrální přepínač má časový limit
  nejvýše 7 dní a zastaví kopie čekajících zpráv. Audit eviduje nastavení,
  zapnutí/vypnutí a přijetí kopie transportem.
- Migrace pěti tabulek a otisku adresy, doplnění zálohovaných tabulek,
  aktualizovaný `composer.lock` kvůli bezpečnostnímu gate.

## Dosavadní ověření

| Kontrola | Výsledek |
| --- | --- |
| `composer audit --locked` po aktualizaci knihoven | PASS, bez oznámených zranitelností |
| `MailAdminUnifiedTest` | PASS, 3 testy / 27 tvrzení: seznam, filtry, oprávnění, BCC a počet transportních přijetí, vypnutí a znovuzapnutí bez oživení staré kopie, resend, jednorázové nové ověření i reset |
| `AuthSecurityMigrationTest` po doplnění nové migrace | PASS, 1 test / 93 tvrzení, opakovaný běh migračního katalogu |
| `PasswordResetRequestAuditTest` | PASS, 7 testů / 68 tvrzení |
| `PasswordResetRequestPageTest` | PASS, 1 test / 8 tvrzení |
| `SharedUiShellTest::testNavigationCoversEveryPreviouslyLinkedPage` | PASS |
| Celá integrační sada před poslední opravou migračního seznamu | 1717 testů / 31077 tvrzení, 1 očekávaný kontraktní FAIL; následný konkrétní test PASS |
| Celá jednotková sada před obnovením odkazu v navigaci | 831 testů / 29347 tvrzení, 1 očekávaný kontraktní FAIL; následný konkrétní test PASS |
| Cílený PHPStan pro nové služby, stránky a migraci | PASS, bez chyb; plný běh na Windows překročil pětiminutový limit Composeru |
| `php tools/check-php-style.php` | PASS, 23 změněných PHP souborů |
| `php tools/check-current-docs.php`, `git diff --check` | PASS |
| První CI PR #524 na `e57b8455` | PHPUnit, docs a CodeQL PASS. PHPStan odhalil pět starých anotací callbacků; HTTP profily zachytily změnu registrace/hlavičky; profil front překročil starý SQL rozpočet o živé čtení BCC. Opravy pushnuté. |
| Druhé CI na `a8da3733` | PHPStan a sdílená hlavička PASS. Veřejný HTTP profil vyžadoval doslovnou shodu registračních odpovědí se starou verzí; přesná výjimka pro tři registrační varianty pushnuta v `981f79be`, stabilita každé varianty a zdrojový kontrakt zůstaly. Ostatní joby v době zápisu běží. |
| Třetí CI na `981f79be` | PHPStan, sdílená hlavička, docs a CodeQL PASS. Veřejný HTTP profil odhalil očekávaný zápis odmítnutého registračního požadavku do nové auditní tabulky; doplněna přesná kontrola jediného řádku. Historický profil čtení čekal jen dřívější přírůstek schématu; aktualizován na všech pět nových tabulek a jeden migrační záznam. MariaDB smoke a PHPUnit v době zápisu běží. |
| Produkční nasazení | NOT RUN |

**Meze důkazu:** Starší nezařazené žádosti bez historického auditu nelze
rekonstruovat. Stav `sent` a čas `bcc_accepted_at` znamená přijetí PHP `mail()`;
neprokazuje doručení hlavnímu příjemci ani kontrolní schránce. Skutečná příčina
konkrétní hlášené nedoručené registrace není z dosud dostupné reference
prokázána. BCC není automaticky zapnutá ani po případném nasazení.

Další krok: dokončit CI, sloučit, provést chráněný deploy a čtecí
živou kontrolu; přihlášené UAT a skutečné doručení vyžadují kontrolní schránku.
