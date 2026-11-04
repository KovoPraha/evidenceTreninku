# Produkční vydání třetí funkční vlny — 4. 11. 2026

Vlastník výslovně povolil kontrolu, sloučení a produkční nasazení PR #293.
**Sloučení, chráněné nasazení a následné technické ověření SUCCESS.**
Změna hesla, skutečné opětovné použití obnovovacího odkazu a T07 jsou SKIPPED.
Deploy běžel 13:11–13:15 UTC, tedy 14:11–14:15 Europe/Prague.

## Kontrola a rozsah

[PR #293](https://github.com/KovoPraha/evidenceTreninku/pull/293), head
`db97fe09b10d0bf9f2251917be2eb61f27a9a70a`, měl všech **11 kontrol SUCCESS**.
[CI přesného headu](https://github.com/KovoPraha/evidenceTreninku/actions/runs/41831263105):
Linux **1971 testů / 38392 assertions**, bez skipů; zvlášť tři POSIX případy / 45 assertions.
MariaDB 10.3/11.4 smoke a objemové profily, PHPStan/styl, dokumentace a CodeQL PASS.
Čerstvé místní cílené ověření **30 testů / 228 assertions** a Composer validate --strict PASS.
Ochrana main strict a vynucení pro administrátory zachovány. Bez blokujícího nálezu.
[Metadata a kontroly PR](pr-review.json), [sloučení](pr293.json).

Oprava F05 promítá skutečné intervaly členství do náhledu ukončení/převodu,
zachovává původní konce a vysvětluje zrušení před počátkem. Vyžaduje kladná ID
zdrojové a cílové soupisky. Nemění potvrzovací fingerprint ani doménový zápis.
F06 přidává české stavy Budoucí/Aktivní/Ukončeno/Zrušeno s inkluzivní platností
a dnem určeným v Europe/Prague. Původní rodinný SQL reader zůstává beze změny.
14 nových regresních případů a místní browser dokládá
[funkční protokol](../functional-uat-round3-20261104/RESULTS.md).
Jeho 119 kontrol přístupů je místní syntetická matice, nikoli produkční UAT všech rolí.

Squash merge **`94982c44d7b7737bddcca46d3ebb9fb4f698abef`** má shodný Git strom
s ověřeným headem. [CI sloučeného main 41835624839](https://github.com/KovoPraha/evidenceTreninku/actions/runs/41835624839) SUCCESS.

## Nasazení a provozní stav

[Deploy 41835865547](https://github.com/KovoPraha/evidenceTreninku/actions/runs/41835865547)
SUCCESS ze zdroje `94982c44d7b7737bddcca46d3ebb9fb4f698abef`.
`potvrzeni=NASADIT`, `uat_schvaleno=false`, `importovat_krouzky=false`.
Deploy zopakoval **1971/38392**, ověřil zálohu, schéma, zachování VS, soukromé
úložiště, aktivaci, HTTP dostupnost a zákaz přístupu ke klíči archivu.
Dočasná kopie konfigurace byla odstraněna.
[Souhrn](deployment.json), [kroky workflow](deploy-run.json).

- Záloha **342 tabulek / 2 triggery**, 6254622 komprimovaných bajtů, SHA-256
  `bd429f420f6fd0592b52f1a93ad2967b938731fa6ee881f8c5676bb1b182a553`.
  [Izolovaná obnova 41837623683](https://github.com/KovoPraha/evidenceTreninku/actions/runs/41837623683)
  SUCCESS na MariaDB 11.4: přesně stejný název generace, kontrolní součet a manifest,
  počty řádků všech 342 tabulek a oba triggery. Produkční DB se neobnovovala.
  [Strojový souhrn](restore.json), [metadata běhu](restore-run.json).
- Schéma current / pending `[]`, ledger **149**, dosavadní variabilní symboly zachované.
- Před/po shodný hash konfigurace, integrační flags, soukromé kandidáty,
  zákaz kanonických KIS zápisů a ledger. Fio import/auto-confirm, SumUp, Stripe,
  Velocota false; Packeta nedefinovaná. Dvě dříve chybějící přílohy zůstávají otevřené.
- Čtecí audit napočítal **30** aktivních pracovních účtů s pozicí trenéra,
  **29** bez aktuálního rozsahu. Nejde o nové přidělení ani úplné ověření pokrytí.

[Audit před 41835277266](https://github.com/KovoPraha/evidenceTreninku/actions/runs/41835277266)
a [audit po 41836644728](https://github.com/KovoPraha/evidenceTreninku/actions/runs/41836644728)
SUCCESS. Po aktivaci potvrzují živé SHA, run a čas 13:14:28 UTC.
[Porovnání](release-comparison.json), [před](pre-audit.json), [po](post-audit.json).
`deployment_ready=false` odráží otevřené provozní převzetí. `old_source/new_source`
jsou historické pevné manifesty 44 souborů, nikoli kontrola celého aktuálního runtime.
Log rsync potvrzuje dodání všech čtyř runtime souborů PR #293; důkaz dodání tvoří
úspěšný workflow, živá release metadata a browser kontrola, nikoli úplná hash inventura.
Součástí nového main jsou také dřívější opravy CLI převodu PR #289/#290;
datový převod ani import se při tomto vydání neopakovaly.

## Skutečné ověření produkce

Anonymní GET-only HTTP **před 30/30 a po 30/30 PASS**: veřejné stránky,
chráněné agendy, formuláře a soukromé cesty. Bez cookies a zápisových požadavků.
[Před](pre-http.json), [po](post-http.json), [helper](http-smoke.py).

Skutečný přihlášený Chrome po nasazení ověřil **F05 náhled ukončení** v registráři:
existující syntetický TEST-PR137, osoba 11782, soupiska TEST – UAT děti,
jednodenní členství 20. 10. 2026. Náhled ukázal skutečný interval
**2026-10-20 – 2026-10-20**, nikoli den nesouvisejícího pole formuláře;
dotčené budoucí tréninky i akce 0. Tlačítko „Potvrdit uvedenou změnu“
nebylo stisknuto, členství se neměnilo. [Bezpečný DOM výňatek](browser.json).
Zachycení screenshotu náhledu selhalo na timeoutu prohlížeče; obrázek se nedokládá.

Přihlášený **rodinný sportovní přehled se načetl**, ale veřejný účet vlastníka
nemá schválené propojené sportovce. Zobrazil se očekávaný úvod „Začněte propojením
sportovce“. **Produkční browser ověření konkrétních F06 štítků proto není uzavřené.**
Stavy pokrývá místní browser a regrese/CI; produkční propojení ani oprávnění se
kvůli testu nevytvářela. Původní pracovní kontext Správce systému obnoven,
pomocná rodinná karta zavřena. Heslo a T07 se netestovaly.

Žádné nové členství, objednávka, platba, trénink, zpráva ani aktivace integrací.
Proběhly standardní provozní kroky autorizovaného deploye a obnova pouze v izolaci.
Úplné UAT všech rolí, poslední synchronizace z nového exportu, E8 a lidská/účetní
přejímka D12–D14 zůstávají otevřené. Technický PASS je neuzavírá.

Dokumentační změny mají samostatný PR. Nevyžadují další deploy; skutečným živým
aplikačním zdrojem zůstává výše uvedené SHA, i když se main posune o dokumentaci.
