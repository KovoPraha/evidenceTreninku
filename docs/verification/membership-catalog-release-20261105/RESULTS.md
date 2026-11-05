# Klubové členství: katalog, disciplíny a vystavovatel

## Vydání a ověření

- [PR #316](https://github.com/KovoPraha/evidenceTreninku/pull/316) sloučen 5. 11. 2026.
  Nasazený commit `ad09174ea969505154f9a135dad3324dcf632867`.
- [CI 42075009132](https://github.com/KovoPraha/evidenceTreninku/actions/runs/42075009132):
  Linux PHPUnit **2064 testů / 39368 assertions**, bez přeskočení; samostatné unixové
  hranice 3/45. MariaDB 10.3 i 11.4: migrace, opakované platby, souběh, PDF,
  ukončení, zálohování/obnova a rodinné HTML profily PASS. Všech 11 kontrol PR PASS.
- [Nasazení 42076138376](https://github.com/KovoPraha/evidenceTreninku/actions/runs/42076138376):
  SUCCESS. Zopakované PHPUnit 2064/39368; záloha před migrací
  `evidence_2026-11-05_173752_317159b6.sql.gz`, **355 tabulek / 2 triggery**,
  SHA-256 `9421b3803b0de439f0148258488b32635a3674bed8aab1e6a63b7acb569a4930`.
  Aditivní migrace `20261105200000_membership_catalog`; dosavadní platby a VS
  mají shodný kontrolní snapshot před/po. Přímé HTTP z runneru mělo síťový timeout;
  následný serverový HTTP 200 PASS. Nezaměňovat tento fallback s úplným zákaznickým UAT.
- [Čtecí kontrola 42077111028](https://github.com/KovoPraha/evidenceTreninku/actions/runs/42077111028)
  potvrdila přesný live SHA/run, nepozměněnou soukromou konfiguraci
  `d77909b9ac5d4348e6583e0bfa958ca95a0a3ce7e4fc58e75aa76d1566c5bb3c`,
  stávající Fio import/autoconfirm a SumUp zapnuté; Stripe a Velocota vypnuté.
  Starý srovnávací manifest auditu je historický; jeho source mismatches nejsou
  kontrolou shody s novým PR.
- [Produkční čtecí náhled 42077533853](https://github.com/KovoPraha/evidenceTreninku/actions/runs/42077533853)
  po založení konceptu: `apply=false`, členství 0, předpisy 0, doklady 0,
  zprávy ve frontě 0, odesláno 0, chyby `[]`. Koncept nezaložil zákaznický závazek.
- Místní Composer, PHPStan, syntaxe a styl PASS. Anonymní HTTP 34 veřejných /
  2 CLI / **241 chráněných**, bez chyby. Původní místní průchod našel chybějící
  CSP nonce nového skriptu; oprava prošla cíleně a následně celým Linux CI.

## Skutečně provedená produkční změna

Přihlášený Chrome, pracovní pozice **Hospodář a platby**:

1. Ověřen nový formulář `accounting_settings_admin.php`: předvyplněný název
   „Tělovýchovná jednota KOVO Praha“, adresa, IČ a účet prázdné. Uložení ani
   potvrzení neplátce DPH se neprovádělo.
2. Výslovnou akcí „Připravit produkt a soupisky“ založen neveřejný koncept
   **Závodní tým**, **2200 Kč za měsíc**, SKU `KP-CLEN-ZAVODNI-MESIC`,
   varianta **951**, kategorie **Členství v klubu**.
3. Skupina **86 Závodní tým**, podřízené soupisky **87 MTB**, **88 Silnice**,
   **89 Dráha**. UI po zápisu potvrdilo úspěch a všechny tři nabídnuté disciplíny.
   Nikdo nebyl přihlášen ani přesunut ze stávajících skupin/soupisek.
4. Formulář schválení předvybírá variantu, skupinu, tři disciplíny, měsíční
   interval, splatnost 15. den a podmínky. Vystavovatel a účetní pravidlo dosud
   nejsou schválené. Produkt není veřejně prodejný, nový členský závazek nevznikl.
5. Formulář fakturačních údajů byl ponechán otevřený vlastníkovi. Snímky
   přihlášené administrace jsou uloženy pouze místně mimo Git.

## Co zbývá k aktivaci

- Vlastník doplní skutečné fakturační údaje a potvrdí podporovaný režim dokladů.
  Modul nyní podporuje neplátce DPH; jeho platnost se neodhadovala.
- Uložením se připraví chybějící řady CLEN pro letošní/příští rok a členění
  CLEN / KLUB. Existující řady a čítače se zachovají. Poté schválit konkrétní
  tarif a zveřejnit produkt standardní publikací.
- Samostatně aktivovat nový prodej/účtování/plán a případně poštu podle runbooku.
  Repozitářové ani produkční environment proměnné `KIS_MONTHLY_MEMBERSHIP_*`
  nebyly při tomto vydání nastavené. Žádná rozesílka se nezapínala.
- Skutečný bankovní převod, PDF a doručení pro tento produkt ještě nejsou
  produkčně ověřené. Testovací důkaz není důkaz reálné úhrady.
- Roční tarify jsou podporované, ale bez zadané ceny nevznikl skutečný roční
  produkt. Roční období má 12 kalendářních měsíců od měsíce sjednání, plnou
  první cenu a ukončení na konci probíhajícího období.

Podrobnosti: [plán](../../CLUB_MEMBERSHIP_CATALOG_2026-11-05.md),
[obsluha a aktivace](../../MONTHLY_MEMBERSHIP_RUNBOOK.md).
