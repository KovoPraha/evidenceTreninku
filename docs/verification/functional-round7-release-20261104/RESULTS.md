# Produkční vydání PR #297 a bezpečnostní odblokování — 4. 11. 2026

Vlastník povolil sloučení, nasazení a pokračování testování.
**PR #297 i nutná oprava závislosti #298 jsou sloučené; chráněné nasazení a následné technické ověření SUCCESS.**
Heslo, použitý obnovovací odkaz a T07 SKIPPED. Přihlášené produkční UAT nebylo dostupné.

## Kontrola a sloučení

[PR #297](https://github.com/KovoPraha/evidenceTreninku/pull/297), přesný head
`a2617900c64d65c33bf2f11265158e599afc553b`, měl všech **11 kontrol SUCCESS**.
[CI 41879234701](https://github.com/KovoPraha/evidenceTreninku/actions/runs/41879234701):
Linux **1982 / 38430**, zvlášť POSIX **3 / 45**; obě MariaDB, PHPStan/styl, dokumentace a CodeQL PASS.
Čerstvá místní cílená sada **6 / 24**, Composer validate PASS. Ochrana main strict a vynucení pro administrátory zachované.
Squash `a6f6bd9dd528568b1a22475dfb5b65d6f12f338d` má shodný strom s ověřeným headem.
[Metadata](pr297.json), [kontroly](pr-review.json).

První [deploy 41896863045](https://github.com/KovoPraha/evidenceTreninku/actions/runs/41896863045)
zastavil nově publikovaný advisory při `composer audit --locked`, ještě před SSH, zálohou a změnou produkce.
Nešlo o síťový timeout ani chybu F08. [Kroky zastaveného běhu](deploy-blocked-run.json).
[PR #298](https://github.com/KovoPraha/evidenceTreninku/pull/298) aktualizoval pouze vývojový
`symfony/filesystem` **7.4.21 → 7.4.23**. Kontrakt před opravou FAIL 1/1, po opravě PASS 1/3;
audit následně čistý, gate beze změny. [Protokol a oficiální advisory](../filesystem-security-20261104/RESULTS.md).
Head `93ac1a4055fd987d0ed1b6ae4a478d1302a0bc3a` měl všech **11 kontrol SUCCESS**,
[CI 41898110893](https://github.com/KovoPraha/evidenceTreninku/actions/runs/41898110893).
Squash **`0171ba5ad2c05c26caf4322db1837bb81fd396b7`** má shodný strom s ověřeným headem.
[Metadata](pr298.json), [kontroly](security-review.json),
[CI sloučeného main 41899161214](https://github.com/KovoPraha/evidenceTreninku/actions/runs/41899161214) SUCCESS,
[strojový výsledek](main-ci.json).

## Nasazení a záloha

[Deploy 41899167396](https://github.com/KovoPraha/evidenceTreninku/actions/runs/41899167396) SUCCESS
ze zdroje `0171ba5ad2c05c26caf4322db1837bb81fd396b7`.
`potvrzeni=NASADIT`, `uat_schvaleno=false`, `importovat_krouzky=false`.
Workflow prošel auditem, zopakoval **1983 testů / 38433 assertions bez skipů**, ověřil zálohu, schéma,
VS, soukromé úložiště, aktivaci a HTTP ochranu klíče archivu. Kopie konfigurace odstraněna.
[Souhrn](deployment.json), [kroky](deploy-run.json).

- Záloha **342 tabulek / 2 triggery**, **6254704** komprimovaných bajtů, SHA-256
  `d837d9d8b1400a9e8eba511329bf43fc53ae4486dcef6885cc83be1bcc42a7e2`.
  [Izolovaná obnova 41899840214](https://github.com/KovoPraha/evidenceTreninku/actions/runs/41899840214)
  SUCCESS na MariaDB 11.4: přesná generace, checksum/manifest, počty řádků všech tabulek a oba triggery.
  Produkční DB se neobnovovala; dočasná data uklizena. [Důkaz](restore.json), [běh](restore-run.json).
- Schéma current / pending `[]`, ledger **149**, dosavadní variabilní symboly zachované.
- Čtecí audity [před 41896508191](https://github.com/KovoPraha/evidenceTreninku/actions/runs/41896508191)
  a [po 41899829857](https://github.com/KovoPraha/evidenceTreninku/actions/runs/41899829857) SUCCESS.
  Nové živé SHA/run potvrzené; konfigurace, flags, kandidáti příloh, zákaz kanonických KIS zápisů i ledger shodné.
  Fio import/auto-confirm, SumUp, Stripe, Velocota false; Packeta nedefinovaná. Dvě chybějící přílohy zůstávají otevřené.
  [Porovnání](release-comparison.json), [před](pre-audit.json), [po](post-audit.json).
- Log aktivace dokládá oba runtime soubory F08. Historické pevné manifesty `old_source/new_source`
  v auditu nejsou inventurou nového souboru ani celého runtime. Důkaz dodání není úplná hash inventura.

## Funkční ověření a hranice

Anonymní GET-only HTTP **před 37/37, po 37/37 PASS**, včetně katalogu, produktů, chráněných
rodinných/pracovních cest, přehledu zapojení, přípravy období a soukromých cest.
Bez cookies a zápisových požadavků. [Před](pre-http.json), [po](post-http.json), [helper](http-smoke.py).

Dostupný in-app browser otevřel produkční administraci, ale dostal přihlašovací formulář.
Přihlášený Chrome konektor předchozích vln už nebyl dostupný. Nativní okna jiného úkolu nedotčená;
session/cookies se nekopírovaly a heslo se neměnilo. **Přihlášené produkční browser ověření F08 je NOT RUN.**
[Bezpečný souhrn](browser.json). Produkční doménová data se při ověření neměnila.

F08 nad 1002 osobami dokládá [místní browser a regrese sedmé vlny](../functional-uat-round7-20261104/RESULTS.md).
Navazující [osmý průchod](../functional-uat-round8-20261104/RESULTS.md) skutečně provedl browser test přípravy období,
25 modelových/HTTP/persistenčních kontrol a 21/190 cílených testů PASS. Nový runtime nález se nepotvrdil.
Zápisy jen do vlastní syntetické fixture, testovaný průvodce pouze četl.

Nevzniklo nové produkční členství, objednávka, platba, trénink ani zpráva; integrace/importy se neaktivovaly.
Úplné UAT rolí, poslední synchronizace, E8 a lidská/účetní přejímka D12–D14 zůstávají otevřené.
Dokumentace má samostatný PR bez dalšího deploye. Živé aplikační SHA zůstává výše uvedené,
i když se main posune o dokumentaci. Kontrola dokumentace, kontrakt 2/14 a git diff --check PASS.
