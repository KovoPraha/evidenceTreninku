# Aplikační vydání evidence členů bez sezon — 2. 11. 2026

**Aplikační kód a migrace jsou nasazené. Ostrý datový převod E7, vytvoření skupin,
přejmenování soupisek a odstranění historie zatím neproběhly.** Vlastník zadal
autonomní implementaci a následně schválil konkrétní mapu a počáteční stav.

## Vydání a ověření

- [PR #285](https://github.com/KovoPraha/evidenceTreninku/pull/285), ověřený head
  `1768811f0c8ef1216ce939555c56a0c9d972f04e`, všech 11 kontrol PASS;
  Linux **1953 testů / 38293 assertions**, MariaDB 10.3/11.4, profily a PHPStan/styl PASS.
- Sloučený strom `d920515952fe8bab36f1c34f8dddb44435264c3b` je shodný s ověřeným headem.
- [Chráněný deploy 41533206850](https://github.com/KovoPraha/evidenceTreninku/actions/runs/41533206850)
  SUCCESS. UAT zůstalo false, import kroužků false. Nová obnova ani seed se nespouštěly v produkci.
- [Čtecí ověření 41534082174](https://github.com/KovoPraha/evidenceTreninku/actions/runs/41534082174)
  potvrdilo živé SHA a deploy run. Starý porovnávací manifest není úplná inventura nového runtime.
- Schéma current, pending prázdné, katalog 148 a migrační ledger 149.
  Kontrola zachování dosavadních variabilních symbolů PASS.
- Integrace Fio/import/auto-confirm, Stripe, SumUp a Velocota zůstaly vypnuté,
  Packeta nedefinovaná, KIS canonical writes false. Dva dříve chybějící kandidáti příloh zůstávají chybějící.

Anonymní kontrola HTTP po nasazení: **38/38 PASS**, včetně členských stránek a ochrany CLI/helperů ([výsledek](post-http.json)). První běh [37/38](post-http-initial.json) očekával přesměrování u přípravy období; zdroj této stránky správně vrací nepřihlášeným 403. Očekávání bylo opraveno a doplněno ověření konkrétního textu odmítnutí; aplikace se kvůli tomu neměnila.

[Strojový souhrn](release-comparison.json) obsahuje pouze neosobní údaje ověření.

## Záloha a nácvik

Deploy vytvořil a ověřil zálohu **337 tabulek / 2 triggerů** před migracemi.
SHA-256: `9e546aecbac3fcc3a0de44f0cd39795cb029dc0bcf0e910276216b790c53e9ce`.
[Obnova nejnovější generace 41534088050](https://github.com/KovoPraha/evidenceTreninku/actions/runs/41534088050)
prošla v izolovaném kontejneru; živá DB se neobnovovala.

Před vydáním prošel [nácvik přesné schválené mapy 41532405191](https://github.com/KovoPraha/evidenceTreninku/actions/runs/41532405191)
nad předchozí ověřenou zálohou: 8 skupin včetně TEST, 44 hran, 435 převzatých
zařazení od 2. 11. 2026, odstranění 560 historických intervalů a 560 importních
událostí. Parita skupin, zachování pilotu, idempotence a následná obnova PASS.
[Podrobný protokol](../member-evidence-20261102/RESULTS.md).

## Zbývající provozní krok

Po nasazení lze přes `diagnose-member-evidence-production.yml` s `approved_preview=true`
sestavit aktuální soukromý manifest. Běží v databázové READ ONLY transakci,
porovnává původní identity s mapou a požaduje přesně 435/560 zařazení.
Do logu vrací jen otisky a souhrny; soubor s konkrétními ID zůstává mimo webroot
v `data/.kis-deploy/member-preview-RUN/manifest.json`. Jde o náhled za běžného
provozu, který se musí před aplikací znovu vytvořit po pozastavení zápisů.

Vlastník následně schválil samostatnou technickou identitu bez přihlašování.
[Provozní postup](../../MEMBER_EVIDENCE_OPERATION_2026-11-02.md) stanoví její dočasné
oprávnění a pozastavení zápisů. Žádná nová auditní identita tímto aplikačním vydáním nevznikla.
Zbývá provozní provedení E7, ověření příloh a přihlášená přejímka. Poslední
synchronizace čeká na nový export; E8 nesmí předčasně odstranit její technické mapy.
