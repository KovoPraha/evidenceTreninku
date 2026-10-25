# Integrovaný KIS — produkční nasazení 25. 10. 2026

## Výsledek a povolený rozsah

Integrované vydání bylo sloučeno do `main` a nasazeno na `kis.kovopraha.cz`.
**Živá verze: `115a617d3660bc9e3cba48891737b56fc6ab068f`.**
[Deploy 40189517120](https://github.com/KovoPraha/evidenceTreninku/actions/runs/40189517120)
prošel všemi kroky. Metadata release uvádějí `2026-10-25T11:39:35+00:00`,
HTTP smoke po aktivaci skončil v 11:40 UTC (12:40 pražského zimního času).

Uživatel výslovně povolil sloučení a nasazení ověřené integrační větve, ověření
zálohy a návratu. Zakázal nové aktivace integrací, import skutečných dat a
produkční testovací zápisy. Tento rozsah je dodržen. Nutná dopředná migrace
schématu není import obchodních dat. Produkční lidská přejímka není tímto
protokolem schválena (`uat_approved=false`).

## Sloučení a testy

- [PR #191](https://github.com/KovoPraha/evidenceTreninku/pull/191): squash
  integrační větve na `00a438dc29fa0faa8414c6ab308e1d6269b1e187`.
  Strom odpovídal ověřenému integračnímu `12736e86`; zachovány bezpečnostní
  opravy předchozí produkce i dokončené klubové balíky.
- [PR #192](https://github.com/KovoPraha/evidenceTreninku/pull/192): čtecí CLI
  diagnostika pro ověření otisků release, konfigurace, návratové kopie a kandidátů
  přesunu příloh. Výsledný commit `115a617d` je přesně nasazená verze.
- [CI nasazeného commitu](https://github.com/KovoPraha/evidenceTreninku/actions/runs/40189420611)
  PASS: PHPUnit, MariaDB 10.3 a 11.4. Nasazovací workflow znovu provedlo
  **1 717 testů / 34 744 assertions PASS**, kontrolu syntaxe a závislostí.
  PR kontroly včetně CodeQL prošly před sloučením.
- Podrobnosti předchozí izolované přejímky, HTTP scénářů a souběhů jsou
  v [integračním protokolu](../integration-20261025/RESULTS.md).
- Ochrany větve a produkčního prostředí zůstaly zachované. První pokus o čtecí
  drill z pomocné větve byl odmítnut ochranou prostředí ještě před spuštěním;
  diagnostika následovala standardní PR a běžela z chráněného `main`.

## Záloha a návrat

Před změnou produkce workflow vytvořilo a checksumem ověřilo databázovou zálohu
mimo veřejný adresář:

- Soubor: `evidence_2026-10-25_113748_a2b42505.sql.gz`.
- SHA256: `fec775404af0c6a3fc828c3845d319f9d95ae5673878ea37237cad395612ac80`.
- Manifest: **330 tabulek, 2 triggery**; [strojový výsledek](backup-created.json).
- [Izolovaná obnova nové zálohy 40190272106](https://github.com/KovoPraha/evidenceTreninku/actions/runs/40190272106)
  **PASS**: přesně tento soubor, checksum a manifest ověřeny; MariaDB 11.4
  obnovila všech 330 tabulek s odpovídajícími počty řádků a oba triggery.
  [Krátký výpis](fresh-restore-evidence.txt), [stav všech kroků](fresh-restore.json).
  Dočasná databáze i stažený dump v CI byly po kontrole odstraněny.

Před nasazením proběhla také [obnova předchozí generace](https://github.com/KovoPraha/evidenceTreninku/actions/runs/40188912376)
se stejným počtem tabulek a triggerů. Produkční databáze se z dumpu nepřepisovala.
SQL dumpy nebyly vložené do repozitáře ani do tohoto předání.

[Návratová větev a postup](ROLLBACK.md) jsou připravené. Větev
`codex/integration-rollback-ready-20261025` na `4a7485c7` obsahuje předchozí
aplikaci, kompatibilní dopředné migrace a přesný seznam 24 nových PHP cest
k odstranění při návratu. Aplikační a testovací strom odpovídá otestovanému
`2f2f6004`: **1 685 testů / 33 638 assertions**, MariaDB 10.3/11.4 a zachování VS
PASS. Předchozí release `6bf57d6f` na hostingu stále existuje a jeho kontrolované
soubory odpovídají očekávaným otiskům. Skutečný návrat produkce nebyl proveden.

Toto je ověření obnovitelnosti databáze a připravenosti návratu kódu, nikoli
úplná zkouška obnovy celého hostingu včetně všech soukromých souborů. RTO/RPO
4 h / 1 h zůstávají plánovacím cílem. Návrat kódu neznamená automatické obnovení
starých dat a ztrátu následných zápisů.

## Migrace, nastavení a produkční kontroly

| Kontrola | Důkaz | Výsledek |
|---|---|---|
| Přesný živý SHA a otisky 54 změněných aplikačních cest | [Čtecí postflight 40189912839](https://github.com/KovoPraha/evidenceTreninku/actions/runs/40189912839), [souhrn](release-proof.json) | 54/54 shod, živý run odpovídá deployi |
| Účetní a skladové knihovny | [Čtecí audit](accounting-stock-audit.json), porovnání SHA256 s Git blob `115a617d` | 8/8 shod |
| Konfigurace a přepínače integrací před/po | [Před](preflight-release.json), [po](postflight-release.json) | Konfigurace byte-for-byte stejná; vybrané přepínače stejné |
| Migrace | Deploy a čtecí postflight | Pouze nový `20261024200000_payment_variable_symbol_index_repair`, `pending=[]` |
| Historické variabilní symboly | [Otisk před/po v deployi](variable-symbols-preserved.json) | `unchanged=true`, 8 dosavadních plateb |
| Účetní vazby | [Audit 40190146012](https://github.com/KovoPraha/evidenceTreninku/actions/runs/40190146012) | 14/14, bez porušení |
| Sklad, výdeje, vratky | Tentýž audit | 20/20, bez porušení |
| Objednávky, platby, osoby, rodiny, tréninky, akce | [Invarianty 40190226043](https://github.com/KovoPraha/evidenceTreninku/actions/runs/40190226043), [JSON](invariants.json) | 16/16, žádné přeskočení ani porušení |
| Anonymní HTTP GET bez prohlížeče | [21 výsledků](http-checks.json) | 21/21; veřejná stránka 200, chráněné vstupy login/403, interní soubory 403 |
| Soukromé úložiště | Deploy, před/po | Konfigurace zachována, žádné soubory nepřesunuty, kandidáti přesunu 0 |
| Workflow a repozitářové proměnné | [Porovnání](automation-preservation.json) | Stavy 24 workflows beze změny, repozitářové proměnné 0 před/po |

Čtecí účetní audit běžel v read-only transakci. Ostatní diagnostika používala
pouze SELECT, čtení souborů a anonymní GET. Nespouštěly se testovací objednávky,
platby, osoby, přihlášky ani změny docházky. Dočasné diagnostické skripty byly
uklizené a kopie konfigurace v release odstraněná. Standardní nasazení ověřilo
soukromý adresář krátkým souborovým zápisem/čtením/smazáním; nejde o obchodní data.

`FIO_IMPORT_ENABLED`, `FIO_AUTO_CONFIRM_ENABLED`, `SUMUP_ENABLED`, `STRIPE_ENABLED`
a `VELOCOTA_INTEGRATION` zůstaly `false`; `PACKETA_ENABLED` nemá explicitní
boolean hodnotu (`null`). Žádná z těchto hodnot se nezměnila. Porovnání GitHub
proměnných zahrnuje pouze repository scope, nikoli inventuru environment scope.
Nebyly provedeny příkazy k aktivaci workflows nebo úpravě proměnných. Import
kroužků byl ve workflow výslovně vypnutý a jeho krok přeskočený.

## Co tato přejímka neuzavírá

- Produkce má **8 objednávek**, ale zatím **0 účetních dokladů, pohybů,
  alokací a exportů**, stejně jako **0 pohybů nového skladového subsystému**.
  Úspěšné kontroly prázdných tabulek nejsou důkazem reálné end-to-end transakce.
  **855 katalogových variant nemá řízený skladový zůstatek**; tento počet není
  fyzická inventura ani pokyn k automatickému doplnění kusů.
- **Dvě již dříve chybějící přílohy účtenek** zůstávají nedostupné. Stejný počet
  byl před i po nasazení; nejde o ztrátu způsobenou tímto vydáním. Bez původních
  podkladů se obsah nedoplňoval ani neodhadoval.
- Nebyla provedena nová přihlášená prohlížečová lidská přejímka ani schválení
  účetní metodiky/vzorového předání. Nasazení nepovoluje automaticky import
  skutečných osob, počátečních stavů, přidělování oprávnění ani aktivaci služeb.
- Konkrétní odpovědné osoby, termín pilotu, účetní podklady a vymezení jednotlivých
  provozních agend zůstávají podle [registru D01–D20](../../CLUB_COMPLETION_DECISIONS.md)
  a [návrhu pilotu](../../CLUB_PILOT_D12_D14.md) otevřené.

Původní pracovní kopie s rozpracovaným `kis_rosters_admin.php` zůstala zachovaná.
Následný dokumentační commit může posunout `main`; dokud neproběhne další
výslovně povolené nasazení, produkční verze zůstává výše uvedené `115a617d`.
