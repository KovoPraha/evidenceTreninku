# Prompt pro Claude Code – nezávislá revize M1/M2

Níže uvedený blok vložte do Claude Code spuštěného v kořeni
`C:\xampp\htdocs\evidencePavel`. Výsledek pak celý předejte řídicímu tasku.

```text
Proveď přísnou READ-ONLY revizi projektu Evidence tréninků + e-shop + týmová
evidence v C:\xampp\htdocs\evidencePavel. Nic neopravuj, nevytvářej ani
neupravuj soubory, nespouštěj migrace, seed, zápisy do DB, browser mutace,
composer install/update, git fetch/pull/push/commit ani produkční operace.
Smíš používat pouze čtení souborů, git status/log/diff/show a testy, které jsou
prokazatelně izolované a nemění lokální sdílenou DB. Pokud si bezpečnost testu
nejsi jistý, nespouštěj ho a pouze navrhni přesný příkaz.

ÚČEL REVIZE
Zkontroluj kvalitu a pravdivost právě dokončeného M1, prvního řezu M2.1 a plánu
dalšího M2. Hledej skutečné chyby, bezpečnostní mezery, porušení transakčních
invariantů, nesoulad dokumentace s kódem, chybějící testy a chybné pořadí plánu.
Výstup bude předán jinému řídicímu tasku, proto musí být konkrétní a opřený o
repo + soubor:řádek.

NEJDŘÍV OVĚŘ ŽIVÝ STAV
1. git status --porcelain=v2 --branch
2. git rev-parse HEAD a git log --oneline -15
3. git diff a git diff --cached; zachovej a pouze popiš všechny cizí změny
4. potvrď, zda existují commity:
   - 9c4c3e1 Complete M1 paid event lifecycle
   - 1b4d9e1 Start M2 participant operations
5. Historické hodnoty v dokumentaci nevydávej za aktuální bez ověření.

ZDROJE PRAVDY A KDE CO NAJDEŠ
Čti kompletně, v tomto pořadí:
- docs/plan-eshop-tymova-evidence/SESSION_HANDOFF.md
  Živý obnovitelný stav, důkazy, blokátory a další akce.
- docs/plan-eshop-tymova-evidence/10-milnik-m2-provozni-pilot.md
  Kanonický M2 plán, pořadí, procenta a akceptační brány.
- docs/plan-eshop-tymova-evidence/08-milnik-m1-integrovany-prototyp.md
  Rozsah a akceptace dokončeného M1 včetně A01–A10.
- docs/plan-eshop-tymova-evidence/06-program-board.md
  Historie přijatých řezů, commity a programové blokátory.
- docs/plan-eshop-tymova-evidence/02-zadani-a-rozhodnuti.md
  Produktová rozhodnutí D-001+, hlavně identity, platby a blokované wallet otázky.
- docs/plan-eshop-tymova-evidence/03-cilova-architektura.md
  Doménové hranice, vlastnictví dat, stavy plateb a budoucí ledger.
- docs/plan-eshop-tymova-evidence/04-roadmapa-a-brany.md
  F1–F5 a měřitelné brány.
- docs/plan-eshop-tymova-evidence/07-shop-kis-integrace.md
  Vazba shop položek, účastníků, programů a starého KIS.
- docs/localhost-testovani.md a testovaci_scenare.php
  Lokální účty, seed a uživatelské scénáře.

AKTUÁLNĚ IMPLEMENTOVANÉ OBLASTI A HLAVNÍ SOUBORY

1. Identita, rodič–dítě a sportovní přístup
- includes/account_person_role.php, includes/account_person_claim.php
- includes/family_portal.php, includes/child_access.php
- booking/sportovec_prihlaseni.php, booking/moje_programy.php
- eshop_identity_admin.php, kis_child_access_admin.php
- migrace 20260802230000 až 20260802233000 a 20260804190000

2. E-shop, objednávka a platba převodem
- includes/shop_catalog_*.php, includes/shop_checkout.php
- includes/shop_coupon.php, includes/shop_beneficiary.php
- booking/eshop.php, booking/objednavka.php, booking/moje_objednavky.php
- eshop_admin.php, eshop_orders_admin.php, eshop_coupons_admin.php
- includes/fio_readonly_import.php, eshop_fio_admin.php
- migrace 20260802170000 až 20260804070000 a 20260804210000

3. Kroužkové programy a životní cyklus účasti
- includes/club_program.php, club_programs_admin.php
- booking/moje_programy.php
- migrace 20260804140000 a 20260804160000

4. Soupisky, sezony, automatické přesuny a tréninkový most
- includes/kis_roster.php, includes/training_roster_bridge.php
- kis_rosters_admin.php, kis_transition_admin.php
- includes/kis_hobby_transition.php
- migrace 20260804090000, 20260804110000, 20260804130000,
  20260804170000

5. Klubové akce, čekací listina, souhlasy a platba
- includes/club_event.php, includes/club_event_registration.php
- includes/club_event_roster_target.php, includes/club_event_shop.php
- includes/club_event_notification.php
- eshop_events_admin.php, booking/krouzky.php
- migrace 20260803110000 až 20260803210000, 20260804150000 a
  20260804230000

6. M2.1 export účastníků
- includes/club_event_export.php
- club_event_participants_export.php
- tlačítko v eshop_events_admin.php
- tests/Integration/ClubEventRegistrationTest.php
- tests/Unit/ClubEventParticipantExportWiringTest.php
Kontrakt má být admin-only POST+CSRF, `m2.event-participants.v1`, izolovaný na
jednu akci, s ochranou proti CSV/formula injection a auditovaným počtem/stavy.

7. Veřejný profil a velodrom
- includes/public_velodrome.php, includes/public_velodrome_shop.php
- booking/velodrom.php, verejny_velodrom_admin.php
- migrace 20260804180000 a 20260804200000

8. KIS importní základ a audit
- includes/kis_sync_lib.php, includes/kis_match_lib.php
- includes/kis_import_run_lib.php, includes/kis_parity_contract.php
- bin/kis-parity-dry-run.php, kis_sync_center.php
- includes/person_audit_timeline.php

9. Localhost provoz a ověření
- bin/seed-local-demo.php, testovaci_scenare.php
- bin/migrate.php, bin/db-backup.php, bin/expire-shop-orders.php
- tests/Integration, tests/Unit a tests/Support

POSLEDNÍ UVÁDĚNÝ DŮKAZ – OVĚŘ, NEPŘEBÍREJ SLEPĚ
- lokální implementační HEAD: 1b4d9e1
- 32/32 číslovaných migrací na localhostu
- 303 PHPUnit testů / 2603 assertions
- 345 first-party PHP souborů bez syntax chyby
- Composer audit bez advisories
- produkce, Stripe, automatické Fio a ostrý KIS import nebyly změněny

CO ZATÍM NENÍ IMPLEMENTOVÁNO NEBO SCHVÁLENO
- vlastník ještě nedodal výsledky ručního průchodu A01–A10
- finální stabilní KIS external ID a skutečný finální exportní kontrakt
- neměnný raw archiv finálního KIS importu, řízený promote/rollback a úplný
  paritní cutover report
- ostrý jednorázový KIS/Shoptet import a vypnutí starých systémů
- finální detail produktu/obrázky a schválené pravidlo kupónů pro služby
- samoobslužný reset hesla sportovce a dokončená permission cache
- reward wallet a cash top-up; blokováno účetním/právním rozhodnutím D-009
- automatické potvrzení Fio, Stripe, Packeta a kombinované platby
- TrainingPeaks integrace
- produkční deploy nových M1/M2 funkcí

POVINNÉ ÚHLY REVIZE
1. Dokumentace vs skutečný kód a migrace; označ každý drift.
2. Autorizace/IDOR, CSRF, session, reset tokeny, enumerace účtů a únik PII.
3. CSV export M2.1: formula injection, hlavičky, cache, audit, race/error stav,
   minimalizace osobních údajů a oddělení akcí.
4. Transakce a pořadí DB zámků: poslední místo, sklad, čekací listina,
   payment_pending, paid, storno, expirace a refund právě jednou.
5. Neměnné cenové/souhlasové/eligibility snapshoty a idempotence objednávky.
6. SQLite vs MariaDB rozdíly, idempotence a pořadí migrací.
7. KIS matching: žádné tiché sloučení slabé identity, conflict/missing pravidla,
   raw evidence, promote/rollback a audit.
8. Testy: falešně zelené wiring testy, chybějící runtime/E2E/concurrency větve,
   negativní a hraniční scénáře.
9. Udržovatelnost: příliš komprimované PHP, duplicity stavových strojů, sdílené
   helpery, chybové zacházení a rizikové vazby mezi doménami.
10. M2 plán: správné pořadí, závislosti, příliš velké řezy, chybějící brány a
    zda uvedená procenta odpovídají realitě.

POŽADOVANÝ VÝSTUP V ČEŠTINĚ
A. Verdikt: READY / READY WITH CONDITIONS / NOT READY pro pokračování M2.
B. Nálezy seřazené HIGH, MEDIUM, LOW. Každý nález musí mít:
   - repo + soubor:řádek,
   - konkrétní důkaz z kódu,
   - reálný dopad/scénář selhání,
   - nejmenší správnou opravu,
   - který řez M2 blokuje,
   - návrh regresního testu.
C. Samostatná tabulka „dokumentace tvrdí / kód skutečně dělá“.
D. Samostatná tabulka pokrytí M2.0–M2.6 a tvůj realistický odhad procent.
E. Doporučené pořadí maximálně 10 dalších implementačních kroků.
F. Přesný seznam testů, které chybí, rozdělený na unit, integration,
   MariaDB/concurrency a browser/E2E.
G. Otázky pro vlastníka produktu, pouze ty, které skutečně mění návrh.
H. Na konci sekce „Co jsem neověřil“ a důvod.

Nevypisuj obecné pochvaly ani hypotetická rizika bez vazby na konkrétní kód.
Pokud v oblasti nenajdeš problém, výslovně napiš „bez nálezu“ a co jsi ověřil.
Neprováděj žádné opravy. Vrať pouze revizní zprávu.
```
