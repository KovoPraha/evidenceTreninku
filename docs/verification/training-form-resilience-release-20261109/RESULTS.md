# Q2-TIME — produkční vydání ochrany rozepsaného tréninku

9. 11. 2026, Europe/Prague. Vlastník výslovně schválil merge, nasazení,
ověření a souborové předání. Vlastník práce: vývoj; lidská přejímka: testerka.
[Plán a rozsah](../../QUALITY_NEXT_SLICES_2026-11-08.md#q2-time--odolný-zápis-tréninku-9-11-2026),
[implementační ověření a omezení](../training-form-resilience-20261109/RESULTS.md).

## Integrace

[PR #412](https://github.com/KovoPraha/evidenceTreninku/pull/412) sloučen
2026-11-09T13:03:52Z: `4f92b225ec33464fd03c5fbf55e6922f12d74453`.
Otestovaný head `3fb1af8b9f3b71a13b28b18313cd769f027b8331`:
[11/11 CI kontrol SUCCESS](ci-checks.json),
[Linux a obě MariaDB](https://github.com/KovoPraha/evidenceTreninku/actions/runs/42620432762).
PHPUnit **2409 testů / 54943 assertions**, privátní úložiště 3/45 PASS.
Otestovaný a sloučený Git tree je přesně shodný:
`f428cb6f1ee70a63130a4f2634f1e48538d21453`.
Lokální cílené testy 34/131, Node 6/6 a browser fixture 18/18 PASS jsou
doložené v implementačním protokolu; nejde o produkční přihlášené UAT.

## Nasazení a čtecí ověření

[Chráněný deploy 42622226704](https://github.com/KovoPraha/evidenceTreninku/actions/runs/42622226704)
spuštěn pro přesný merge SHA s `NASADIT`, import=false a UAT=false.
**SUCCESS**, dokončení 2026-11-09T13:09:46Z; [metadata a kroky](deploy-summary.json).
Konkrétní neosobní výstupy dokládá [výběr kontrol z deploy logu](release-controls.json).
Deploy znovu provedl 2409 testů / 54943 assertions PASS. Ověřená záloha
389 tabulek / 2 triggery před aktivací, SHA256
`68121baae6520fa306dc7d4d3bb6fe9bd23f1c79265f103e92a88d21e1c5e99b`.
Obnova této konkrétní zálohy NOT RUN. Schéma current / pending [],
8 dosavadních platebních VS beze změny, konfigurace privátního úložiště
zachovaná (`config_appended=false`). Import produktů SKIPPED.
Aktivace, indexový preflight, kontrola zákazu přístupu ke klíči a HTTP smoke PASS.
[Před](before.json) i [po nasazení](after.json): šest anonymních GET kontrol PASS.
Produkční JS: **8293 bytů, přesná shoda s Git blobem vydaného commitu**,
SHA256 `f29119002d078c25e3f54b8103fb7a542e9d1dd59d43468c62f0e9924455abe1`.
První [porovnání s Windows checkoutem](after-worktree-comparison.json) hlásilo
rozdíl kvůli 147 zakončením CRLF oproti LF v Gitu/serveru (8440 vs 8293 bytů).
Ověřeno, že jediný rozdíl jsou tato zakončení; správné porovnání s přesným
Git blobem prošlo bez normalizace serverového souboru.
[Reprodukční probe](public-probe.py.txt): zkopírovat do `output/training-release-probe.py`
ve worktree vydané verze, spustit `python output/training-release-probe.py after`.
Žádné nové produkční tréninky, role ani skutečné osoby se pro ověření nevytvářejí.

## Předání

Důkazy připravuje větev `codex/training-resilience-release-proof-20261109`,
vlastní worktree `C:/Users/Marek/Documents/Codex/evidencePavel-training-resilience-20261109`.
Dokumentace se předává samostatným PR bez opakovaného nasazení webu.
Původní checkout a cizí WIP zůstávají mimo rozsah této změny.

## Přihlášená produkční přejímka — NOT RUN / blokace nástroje

Historický stav při vydání. **Nový pokus 9. 11.: [cílené UAT 3/3 PASS](../training-form-uat-20261109/RESULTS.md)**
pro chybný čas, reload a zavření samostatného okna. Úplný rozsah a úklid
nepřebírat jako hotový; přesné omezení uvádí nový protokol.

9. 11. 2026 Computer Use při výběru otevřeného Chrome ukončil ovládání:
„Computer Use has been stopped for this turn because it could not determine
the current browser URL on Windows with enough confidence to enforce policy.“
Žádný produkční formulář nebyl vyplněn ani odeslán. Blokace nebyla obcházena
jiným přístupem k session. HTTP kontrola ani shoda JS nejsou vizuální UAT.

Vlastník nebo testerka dokončí následující scénář v přihlášeném prohlížeči:

1. Otevřít nový zápis tréninku, vyplnit text a poznámku. Ověřit zprávu
   „Koncept uložen v tomto prohlížeči“. Použít testovací sportovce.
2. Přidat měření 200 m a čas `13,456`; pokus o uložení musí zůstat na
   formuláři, označit čas a zachovat všechna ostatní pole. Zopakovat
   s `00:13x456` a `00:00`. Nesmí vzniknout trénink ani POST s chybným časem.
3. Obnovit stránku: text, poznámka, účastníci, vzdálenost i chybný čas
   zůstanou; čas je stále označen jako chybný.
4. Přejít zpět/vpřed, zavřít kartu a otevřít nový zápis. U jiného formulářového
   klíče vybrat „Obnovit koncept“; text a měření se musí obnovit.
5. Změnit čas na `00:13.456`: chybová značka zmizí. Při oprávněném testovacím
   zápisu ověřit právě jeden uložený trénink a následné odstranění konceptu.
   Pouhé ověření této opravy nevyžaduje vytvoření skutečného ostrého tréninku.
6. Při editaci existujícího tréninku ověřit stejnou blokaci chybného času;
   automatická obnova konceptu není součástí editace.

Zapsat datum, roli, prohlížeč a PASS/FAIL pro každý bod. Úplná aplikace s DB,
reálný výpadek sítě při POST a následná kontrola historie zůstávají otevřenou
přejímkou; lokální browser fixture tyto serverové odpovědi pouze simuluje.
Přílohy je nutné vybrat znovu. Koncept funguje v témže prohlížeči, není
synchronizovaný mezi zařízeními a vymazání dat prohlížeče jej odstraní.
