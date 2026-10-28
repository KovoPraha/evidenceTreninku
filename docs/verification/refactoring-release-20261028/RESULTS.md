# Produkční vydání první vlny refaktoringu — 28. 10. 2026

Na výslovný pokyn vlastníka „Takze sloučit a nasadit“ byl sloučen
[PR #209](https://github.com/KovoPraha/evidenceTreninku/pull/209) jako
`20ab863701bfb1b90e3a7f5a7c1357cceda3c30b` a nasazen na
[kis.kovopraha.cz](https://kis.kovopraha.cz/).
[Deploy 40701923049](https://github.com/KovoPraha/evidenceTreninku/actions/runs/40701923049)
skončil SUCCESS 28. 10. 2026 v 14:05:38 UTC (15:05:38 Europe/Prague).

## Rozsah a ověření

- Cílené načítání detailu, metadata pouze pro jediné čtení, lehká navigace
  s dávkovými vazbami a sdílené strukturální kontroly prodejnosti.
  PhpSpreadsheet 5.8.1 → 5.10.0; žádná nová migrace ani změna doménových stavů.
- CI přesného sloučeného SHA: [Testy](https://github.com/KovoPraha/evidenceTreninku/actions/runs/40701687558),
  [PHP quality](https://github.com/KovoPraha/evidenceTreninku/actions/runs/40701687766),
  [CodeQL](https://github.com/KovoPraha/evidenceTreninku/actions/runs/40701687067)
  a [dokumentace](https://github.com/KovoPraha/evidenceTreninku/actions/runs/40701688066), PASS.
  Linux 1 757 testů / 35 705 assertions; MariaDB 10.3 i 11.4, PHPStan, sweep
  a bezpečnostní audit závislostí PASS. Deploy znovu provedl PHPUnit 1 757 / 35 705.
- Produkční preflight PHP 8.2.35, bez varování. Záloha
  `evidence_2026-10-28_140330_0da29596.sql.gz`: 336 tabulek, 2 triggery,
  6 063 423 komprimovaných bajtů, SHA-256
  `3738b63d12173dcb68d16d9707bdcd8fab9c7baee9ab31c2d21d87b3e40dbb50`.
  Vytvoření a ověření zálohy PASS; izolovaná obnova v tomto vydání neopakována.
- Kontrola schématu před/po: current true, katalog 140 migrací, pending [].
  Otisky dosavadních VS zachovány (`unchanged:true`). Příprava release mimo
  webroot, aktivace a odstranění dočasné kopie produkční konfigurace PASS.
  [Výpis vybraných výsledků deploye](deployment.json).
- [Následná čtecí kontrola 40703151968](https://github.com/KovoPraha/evidenceTreninku/actions/runs/40703151968),
  SUCCESS; potvrdila živé SHA `20ab8637`, run `40701923049`, `uat_approved:false`,
  `read_only_session:true`, `domain_mutations:false`, ledger 141 záznamů.
  [Vybraná strojová evidence](release-verification.json). Konfigurace SHA-256
  `326c832d891a99d2780099391854a8daee3936adcdacf62c62f0a00b616fb772`
  a integrační flags odpovídají [záznamu z 26. 10.](../dozor-release-20261026/release-comparison.json).
- [HTTP smoke](post-http.json): **14/14 PASS**, anonymní GET bez cookies,
  následování redirectů a obchodních zápisů. Úvod, katalog, přihlášení,
  tři existující detaily (247, 218, 221), neexistující detail 404, chráněné
  uživatelské/admin stránky přesměrují na přihlášení a interní soubory vracejí 403.
  [Reprodukovatelný skript](http-smoke.py); odpovědi HTML se neukládají.

## Hranice ověření

`uat_schvaleno=false`, `importovat_krouzky=false`; importní krok byl přeskočen.
Fio import/auto-confirm, SumUp, Stripe a Velocota zůstávají vypnuté;
Packeta flag není definovaný a KIS canonical writes jsou false.
Nevytvářely se testovací objednávky, registrace ani platby v produkci.
Dvě historicky chybějící přílohy účtenek přetrvávají; žádný nový kandidát
k přesunu a žádná chyba migrace příloh (`missing=2, errors=0`).

Čtecí workflow používá pevné historické manifesty `old_source/new_source`
a historickou cestu `previous_release`. Jejich počty neshod nejsou kontrolou
tohoto vydání a nejsou zde vydávány za ověření nových souborů; ze zkráceného
JSON jsou vynechány. Identitu aktuálního vydání dokládá živé metadata SHA/run,
úspěšná aktivace konkrétního release a HTTP průchod. Nový runtime hash manifest
všech změněných souborů se v této přejímce neprováděl.

Jediný produkční HTTP průchod naměřil katalog 15,6 s a tři detaily 6,8–14,8 s.
Jde o zbývající výkonový problém k samostatnému měření, nikoli doklad regrese
nebo párový benchmark. [Syntetické SQL/HTTP výsledky](../refactoring-wave1-20261028/RESULTS.md)
nelze převést na procento zrychlení celé produkční stránky. Programové nabídky,
kapacity a členské ceny zůstávají v navazujícím backlogu.

Toto je technické nasazení s anonymním čtecím ověřením. Nenahrazuje přihlášené
produkční UAT, lidské/účetní převzetí, pilot D12–D14, skutečnou inventuru,
ocenění ani doplnění ověřených rodinných vazeb, trenérských rozsahů a dohod částek.
Historické účetní, skladové a obnovovací kontroly nebyly v tomto vydání opakovány.

## Návrat a pracovní stromy

Předchozí doložené živé vydání je `fc0d3779306e70362e013f088f30974725bbf8ff`.
Vlna nemá novou migraci; návrat samotného refaktoringu nevyžaduje obnovu DB.
Případný rollback provést podle [NASAZENI](../../NASAZENI.md) a zachovat
bezpečnostní aktualizaci PhpSpreadsheet, nevracet ji automaticky na 5.8.1.
Produkční rollback nebyl proveden ani simulován.

Původní checkout `C:\xampp\htdocs\evidencePavel` a jeho WIP zůstaly zachovány.
Zdroj vydání a následného provozního záznamu je oddělené worktree
`C:\Users\Marek\Documents\Codex\evidencePavel-refactoring-wave1-20261028`.
Dokumentační commit po vydání není nový produkční deploy; nasazené SHA zůstává
výše uvedené `20ab8637`.
