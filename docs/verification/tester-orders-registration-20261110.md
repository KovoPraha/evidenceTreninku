# Reporting testerky: objednávky a registrační podklady

Datum: 10. 11. 2026. Stav: [PR #438](https://github.com/KovoPraha/evidenceTreninku/pull/438) sloučen do `main` jako `f39f3157` po 11 úspěšných CI kontrolách; produkční vydání a přihlášené UAT otevřené.

## Převzetí

Načteny `docs/HANDOFF_CURRENT.md`, `docs/CURRENT_STATE.md`,
`docs/plan-eshop-tymova-evidence/SESSION_HANDOFF.md`,
`docs/ADMIN-PRACOVNI-POZICE.md` a relevantní kód. Handoff a current state
zachycují stav k 11. 10. 2026 a nejsou důkazem dnešního produkčního commitu.
Pracovní větev obsahovala před touto opravou cizí změnu `kis_rosters_admin.php`
a nesledované lokální výstupy; nebyly upraveny.
Oprava je připravena v samostatné větvi
`codex/kis-tester-orders-registration-20261110` z aktuálně načteného
`origin/main` (`50ce79b2e290b926a70e46f8a729682f12a7b864`), později bez kódového konfliktu přenesena na `37f1ba8f`. Souběžné vydání UI aktiv #437 v CURRENT a deníku zachováno.

## Opravené body

1. `shopOrderAdminList()` nyní řadí `o.created_at DESC,o.id DESC` bez priority
   platebního stavu. Stav platby zůstává samostatným sloupcem přehledu.
2. Kontrola registračních podkladů dovolí pracovníkovi v aktivní a přiřazené
   pozici `registrar` auditovaný maskovaný náhled RČ. Stejná pozice smí auditovaně
   zobrazit interní fotografii. Běžný trenér je odmítnut. Odhalení celého RČ,
   oprava, výmaz a rotace klíče dál vyžadují dosavadní přísnější roli `admin`;
   tlačítko odhalení se ostatním nezobrazuje. Schválení registrace zůstává
   samostatnou akcí stránky s vlastními kontrolami a auditem.

Dosavadní administrátor s rolí `admin` má nadále maskovaný náhled i mimo aktivní
registrátorskou pozici. Odhalení celého RČ zůstává vyhrazené právě této roli.

Přiřazení pozice `registrar` spravuje vedením pověřený správce systému podle
`docs/ADMIN-PRACOVNI-POZICE.md`; samotná role `sports_lead` nestačí.

## Ověření

- Cílené PHPUnit testy citlivých údajů, registračního zapojení, řazení objednávek
  a checkoutu: 55 testů, 956 kontrol, úspěch. Objednávky používají tři syntetická data a
  stavy `pending`, `paid`, `refund_required`, včetně shodného času a rozhodnutí
  vyšším ID. Citlivý test ověřuje audit, povolenou masku, odmítnutého trenéra a
  odmítnutí odhalení/opravy/výmazu pro neadministrátorského registráře.
- PHP lint změněných PHP souborů, kontrola stylu PR souborů a `git diff --check`:
  úspěch.
- PHPStan podle `phpstan.neon.dist`: bez chyby.
- Po rebase doplněn regresní test dosavadního administrátora; `PersonSensitiveTest`,
  `ShopOrderAdminListSortTest` a `AthleteRegistrationAdminTest`: 13/168 PASS.
- Místní plná sada `composer test` byla ukončena po 366 z 2 427 testů bez dosavadního
  selhání. Na PR #438 poté prošel úplný GitHub PHPUnit, oba MariaDB smoke a profily,
  PHPStan, dokumentace a CodeQL; cílená místní sada výše je také dokončená.

## Produkční UAT po nasazení

Pověřený registrář po přihlášení otevře čekající registraci dítěte a klikne na
„Zkontrolovat registrační podklady“. Ověří masku RČ, kontakty, souhlasy a
případnou fotografii; správce ověří odpovídající záznamy
`osoba_citlive_pristupy` s `masked_view` a případně `photo_view`. Samostatně se
ověří odmítnutí běžného trenéra. V přehledu objednávek se ověří pořadí při
smíšených stavech platby. Tento krok zatím nebyl proveden; do produkce se v
tomto lokálním řezu nezapisovalo.

## Společné vydání obou reportů

Vlastník 10. 11. 2026 výslovně požádal „nasadit všechny změny“. Rozsah tvoří dvě opravy v tomto souboru a čtecí trenérská agenda [COACH-ROSTER-01 v draft PR #436](https://github.com/KovoPraha/evidenceTreninku/pull/436). Cizí rozpracovanou větev a další otevřené PR do tohoto požadavku nezahrnovat.

Postup: dokončit PR/CI tohoto řezu, sloučit oba schválené řezy do aktuálního `main`, ověřit shodu konečného stromu a dokončení CI. Před produkčním vydáním ověřit syntetický scénář Aligátorů: dítě až po schválení a kanonickém členství, cizí trenér odmítnut. Oddělené testovací KIS pro přihlášené UAT není doložené; skutečný přihlášený průchod a audit proto vykázat samostatně, bez tvrzení o PASS. Standardní workflow `deploy-production.yml` spustit na přesném SHA s `potvrzeni=NASADIT`, `uat_schvaleno=false`, `importovat_krouzky=false`; požadavek na nasazení není schválení lidského UAT ani autorizace nového importu. Po dokončení ověřit workflow, verzi produkce, veřejné HTTP a přihlášené čtení podle dostupného přístupu. Skutečné rodné číslo, účetní údaje a hesla neukládat do důkazů.
