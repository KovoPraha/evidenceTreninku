# Lidé a přístupy — produkční vydání 7. 11. 2026

ROLE01: [plán a schválení](../../ACCESS_OVERVIEW_2026-11-07.md),
[rozhodnutí](../../DECISION_LOG.md#d2026-11-07-02).
Vlastník výslovně schválil nasazení a ověření změn rolí při zachování všech
stávajících přístupů. Nové technické účty se po přejímce deaktivují a zbaví rolí.

## Implementace a přijetí

- [PR #356](https://github.com/KovoPraha/evidenceTreninku/pull/356) sloučený,
  výsledný commit `ebb8b2e31cc0505a83e091ae1b6dec5fd6eab5d8`.
- Všech 11 kontrol finální hlavy `e4e68e6d` PASS: 2325 testů / 42966 assertions, PHPStan/styl, CodeQL,
  dokumentace, PHP a obě MariaDB 10.3/11.4 včetně profilů čtení.
  [CI](https://github.com/KovoPraha/evidenceTreninku/actions/runs/42359428151).
- Výsledky místní aplikace a jejich meze:
  [protokol](../access-overview-20261107/RESULTS.md).
  Nová bezpečnost provozního testu: 5 testů / 17 assertions PASS;
  kontrakt povolených artefaktů 1 test / 55 assertions PASS.
- Souběžný dokumentační #357 zachovaný včetně nových pravidel a archivů.

## Stav před nasazením

- Čtecí [baseline 42360066301](https://github.com/KovoPraha/evidenceTreninku/actions/runs/42360066301) PASS.
  **46 původních účtů, 82 přiřazení pozic, 5 superadminů, 1 barový grant.**
  SHA-256 `debbfb081e675f110efbe5a8783c5f3cef31bbc26acb45745868c30bb2befb8b`.
  [Bezpečný souhrn](baseline-summary.json); úplná privátní obnovovací informace zůstává na serveru.
- [Anonymní HTTP před vydáním](http-before.json): veřejné stránky HTTP 200,
  správa rolí 302 na login, oba includes a CLI nástroj 403, bez PHP chyb.

## Nasazení a přejímka

- **NASAZENO / PASS:** [deploy 42360171293](https://github.com/KovoPraha/evidenceTreninku/actions/runs/42360171293),
  přesně `ebb8b2e31cc0505a83e091ae1b6dec5fd6eab5d8`.
  `importovat_krouzky=false`, `uat_schvaleno=false`; příznak netvrdí plné lidské UAT.
  Deploy zopakoval 2325 testů / 42966 assertions, migrace current / pending [],
  původní VS zachované, aktivace i HTTP smoke PASS. Runner použil serverový curl
  fallback s HTTP 200; nezávislý HTTP ze stanice prošel také.
- [Ověřená záloha](deploy-summary.json): 387 tabulek / 2 triggery,
  `evidence_2026-11-07_093456_2dcdff4b.sql.gz`, gzip/hash/manifest ověřené před aktivací.
  Obnova této konkrétní generace **NOT RUN**; starší obnova se za ni nevydává.
- **PRODUKČNÍ PŘEJÍMKA PASS:** [drill 42360515313](https://github.com/KovoPraha/evidenceTreninku/actions/runs/42360515313),
  16/16 kontrol skutečnými HTTPS formuláři a nad trvalou produkční DB.
  [Bezpečný strojový protokol](production-verification.json).
- [Anonymní HTTP po nasazení](http-after.json): 7/7, shodné očekávané statusy s bodem před vydáním,
  žádné PHP chyby, nové includes a CLI 403; správa rolí zůstává za loginem.
- Browser Computer Use se zastavil kvůli neurčené aktuální URL. Nejde o nový
  vizuální produkční Chrome UAT; používají se skutečné HTTPS formuláře a persistence.

| Oblast | Skutečný výsledek |
| --- | --- |
| Přehled a filtr | Přihlášený přehled HTTP 200, kombinovaný filtr najde právě nový cílový účet |
| Formulář rolí | Přidání coach/bar_operator/order_operator a výchozí bar_operator uloženy a znovu přečteny z DB |
| Neplatné požadavky | Chybné CSRF a nepřiřazená výchozí role odmítnuté |
| Účinný přístup | Nový cílový účet se skutečně přihlásil; bar HTTP 200, správa systému 403 |
| Vlastní spotřeba | Povolení i odebrání explicitního grantu přes běžný formulář PASS, bez odběru či prodeje |
| Odebrání rolí | Návrat na samotného trenéra uložený, původně dostupný bar následně HTTP 403 |
| Audit | Přesně dvě změny rolí a dvě změny zvláštního grantu |
| Původní lidé | Všech 46 účtů / 82 pozic / 5 superadminů / 1 grant zachovaných; SHA-256 před/po přesně shodné |
| Úklid | Pouze nové technické účty 63 a 64 deaktivované, jejich pracovní pozice i granty odvolané, relace zneplatněné |

**ROLE01 HOTOVO** v rozsahu implementace, nasazení a této technické produkční přejímky.
Stávajícím lidem se role, výchozí pozice, superadmin ani zvláštní grant nezměnily.
Účty 63/64 zůstávají neaktivní kvůli auditním vazbám; nevytvořily objednávku,
kasu, platbu ani změnu skladu. Hesla nebyla zapisovaná do souboru ani logu.

Předání výsledků vede větev `codex/access-release-20261107` ve stejném vlastním
worktree jako implementace. Dokumentační předání nevyžaduje další deploy.
