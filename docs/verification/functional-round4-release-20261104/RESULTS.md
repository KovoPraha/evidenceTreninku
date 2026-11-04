# Produkční vydání PR #295 — 4. 11. 2026

Vlastník výslovně povolil kontrolu, sloučení, produkční nasazení a aktualizaci dokumentace.
**Kontrola PR, sloučení, chráněné nasazení a následné technické ověření SUCCESS.**
Heslo, použitý obnovovací odkaz a T07 zůstávají SKIPPED.

## Kontrola změny

[PR #295](https://github.com/KovoPraha/evidenceTreninku/pull/295), přesný head
`171a2a868fe4a5823c6a0ec740ef31f7f7fd78ee`, měl všech **11 kontrol SUCCESS**.
[CI 41867180679](https://github.com/KovoPraha/evidenceTreninku/actions/runs/41867180679):
Linux **1977 testů / 38403 assertions**, zvlášť POSIX **3 / 45**; oba MariaDB smoke/profily,
PHPStan/styl, dokumentace a CodeQL PASS. Čerstvé místní ověření formuláře **10 / 37**
a Composer validate --strict PASS. Ochrana main strict i vynucení pro administrátory zachované.
[Kontroly headu](pr-review.json), [metadata sloučení](pr295.json).

Jediná runtime změna F07: formulář opravy docházky přebírá `record_source=paper`
z aktuálního kanonického snapshotu. Nové/starší záznamy mají dosavadní výchozí `coach`;
výslovná změna v chybovém formuláři má přednost. Nový důvod, potvrzení a kontrola verze
zůstávají zachované. Writer, schéma, oprávnění a historické verze se nemění.
Šest regresních případů obsahuje hlavní FAIL před opravou / PASS po ní.
[Čtvrtá vlna](../functional-uat-round4-20261104/RESULTS.md) dokládá místní browser opravu paper → paper;
[pátá](../functional-uat-round5-20261104/RESULTS.md) a [šestá](../functional-uat-round6-20261104/RESULTS.md)
přidaly funkční důkazy bez další změny runtime.

Squash merge **`7e7a6364a3527bce1c6ec4b789468bd716f28fb4`** má shodný Git strom
`a4aaed189517c82febba32994aae26dc7c16e645` s ověřeným headem.
[CI sloučeného main](https://github.com/KovoPraha/evidenceTreninku/actions/runs/41870936605)
je doloženo v [main-ci.json](main-ci.json); deploy navíc celou sadu zopakoval.

## Nasazení a záloha

[Deploy 41870966110](https://github.com/KovoPraha/evidenceTreninku/actions/runs/41870966110)
SUCCESS ze zdroje `7e7a6364a3527bce1c6ec4b789468bd716f28fb4`.
`potvrzeni=NASADIT`, `uat_schvaleno=false`, `importovat_krouzky=false`.
Workflow znovu ověřil **1977 / 38403**, zálohu, schéma, zachování variabilních symbolů,
soukromé úložiště, aktivaci a HTTP ochranu klíče archivu. Kopie konfigurace z release odstraněna.
[Souhrn](deployment.json), [kroky workflow](deploy-run.json).

- Záloha **342 tabulek / 2 triggery**, **6254663** komprimovaných bajtů,
  SHA-256 `9f4d39a022d78e44ea2eb434c87bfb3e19007566d8850443df24cfa4bdc5ee11`.
  [Izolovaná obnova 41871628355](https://github.com/KovoPraha/evidenceTreninku/actions/runs/41871628355)
  SUCCESS na MariaDB 11.4: shodná generace a checksum, počty řádků všech 342 tabulek a oba triggery.
  Produkční DB se neobnovovala; dočasná izolovaná data uklizena. [Důkaz](restore.json), [běh](restore-run.json).
- Schéma current / pending `[]`, ledger **149**, dosavadní variabilní symboly zachované.
- Čtecí audit [před 41870449791](https://github.com/KovoPraha/evidenceTreninku/actions/runs/41870449791)
  a [po 41871614594](https://github.com/KovoPraha/evidenceTreninku/actions/runs/41871614594) SUCCESS.
  Potvrdil nové živé SHA/run, shodný hash konfigurace, integrační flags, kandidáty soukromých souborů,
  zákaz kanonických KIS zápisů i ledger. Fio import/auto-confirm, SumUp, Stripe, Velocota false;
  Packeta nedefinovaná. Dvě dříve chybějící přílohy zůstávají otevřené.
  [Porovnání](release-comparison.json), [před](pre-audit.json), [po](post-audit.json).
- Log aktivace výslovně dokládá `includes/training_closure_form.php`; staré pevné manifesty
  `old_source/new_source` v auditu nejsou inventurou nového souboru ani celého runtime.
  Důkaz dodání tvoří ověřený deploy, aktivační log a živá release metadata, nikoli úplná hash inventura.

## Skutečná produkční kontrola

Anonymní GET-only HTTP **před 35/35, po 35/35 PASS**: veřejný katalog a produkty,
chráněné pracovní/rodinné agendy, nové dotčené registrační a docházkové cesty, soukromé soubory.
[Před](pre-http.json), [po](post-http.json), [helper](http-smoke.py).
První rozšíření helperu nesprávně očekávalo login redirect u tří registrářských stránek;
ty podle zdrojových guardů správně vracejí 403. [Původní výsledek](pre-http-initial.json)
je zachovaný; opravená očekávání prošla. Nešlo o chybu produkce.

Přihlášený Chrome v kontextu Vedoucí sportu otevřel existující syntetický trénink **#1604**
`TEST-UAT-AUTO-20261101-1955-a7c2 – upraveno`. Je ve stavu **Rozepsaná docházka**;
formulář správně načetl výchozí **Zápis trenéra**, dovolil místní výběr **Přepis papírové docházky**,
vyžadoval nový důvod a nezaškrtnuté povinné potvrzení. Formulář nebyl odeslán.
**Produkční uložení opravy existující papírové docházky se netestovalo**;
tento pozitivní scénář pokrývá místní browser, regrese/CI a doložené dodání opravy.
Superadminův pracovní kontext není důkazem oprávnění omezené role.
[Bezpečný browser souhrn](browser.json), [obnovená produkční administrace](production-registrar.png).

Původní přihlášení a registrářský kontext zachované; pomocná karta po kontrole zavřená.
Žádné nové členství, objednávka, platba, trénink, zpráva ani aktivace integrací.
Proběhly standardní provozní kroky autorizovaného deploye a obnova pouze v izolaci.
Úplné UAT všech rolí, poslední synchronizace, E8 a lidská/účetní přejímka D12–D14
zůstávají otevřené. Technický PASS je neuzavírá.

Dokumentace je dodaná samostatným PR bez dalšího runtime nasazení. Živé aplikační SHA
zůstává výše uvedené, i když se main posune o dokumentaci. Dokumentační kontrakt
2 testy / 14 assertions a kontrola aktuálních odkazů/archivu PASS; git diff --check PASS.
