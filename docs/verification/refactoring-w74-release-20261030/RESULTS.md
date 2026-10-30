# W7.4 — ověřené produkční vydání

30. 10. 2026, [kis.kovopraha.cz](https://kis.kovopraha.cz/).
[PR #233](https://github.com/KovoPraha/evidenceTreninku/pull/233) sloučen jako
`a3fae123cf6dcb2a4ce5ffe37ddd892874e436fe`, [deploy 41047950067](https://github.com/KovoPraha/evidenceTreninku/actions/runs/41047950067) SUCCESS.
Nová čtecí kontrola potvrdila živé SHA a run ID. Vlastník autorizoval realizaci,
sloučení a nasazení této i dalších vln až do technického dokončení plánu.

## Změna a ověření

Členská pole se po čerstvé autorizaci načítají uvnitř jednoho volání po nejvýše
400 ID. Bez cache osob či oprávnění, bez změny writerů a kontrol po zámku.
Celý přehled 20 osob: 752 → 619 SQL (−17,7 %), členství 160 → 27 dotazů
(−83,1 %). Pro 0/1 osob žádný nárůst SQL. Shodné kompletní výsledky a HTML,
ostatní SQL, parametry, pořadí a počet řádků; historie +1000 účastí i POST PASS.
[Měření a meze](../refactoring-w74-20261030/RESULTS.md).

CI přesného nasazeného main: [Current documentation 41047505501](https://github.com/KovoPraha/evidenceTreninku/actions/runs/41047505501), [PHP quality 41047505574](https://github.com/KovoPraha/evidenceTreninku/actions/runs/41047505574), [Push on main 41047505219](https://github.com/KovoPraha/evidenceTreninku/actions/runs/41047505219), [Testy 41047505559](https://github.com/KovoPraha/evidenceTreninku/actions/runs/41047505559). [Metadata](github-ci-main.json).
Deploy zopakoval 1824 testů / 36200 assertions.
Linux POSIX bez skipů, MariaDB 10.3/11.4, souběžné revokace, checkout,
PHPStan, styl, anonymous sweep a JS PASS. Lidské UAT z toho neplyne.

## Provozní důkazy

Vstupy: `potvrzeni=NASADIT`, `uat_schvaleno=false`, `importovat_krouzky=false`.
Ověřená záloha 336 tabulek / 2 triggery,
6063580 komprimovaných bajtů, `evidence_2026-10-30_112726_6b8a7926.sql.gz`.
SHA-256 `3d092d21e5d041293dda8451d291528586ca02dda8ad97e2ec163dd9c2c2fe3f`. Obnova v tomto vydání neopakována.
Migrační kontroly current / pending [], VS beze změny, dočasná konfigurace
odstraněna. [Výstupy deploye](deployment.json).
HTTP workflow: Přímé TCP spojení z GitHub runneru opakovaně timeout; fallback přes SSH potvrdil HTTP 200. Nezávislé GET kontroly jsou doloženy zvlášť..

Čtecí kontrola [před 41045558222](https://github.com/KovoPraha/evidenceTreninku/actions/runs/41045558222)
a [po 41048662910](https://github.com/KovoPraha/evidenceTreninku/actions/runs/41048662910)
PASS; obě read-only bez doménových mutací.
[Před](pre-verification.json), [po](post-verification.json), [porovnání](release-comparison.json).
Shodná konfigurace, flags, ledger 141 a stav příloh.
Fio import/auto-confirm, SumUp, Stripe a Velocota vypnuté; Packeta nedefinovaná,
KIS canonical writes false. Dvě historicky chybějící přílohy nejsou opravovány.
Identita je doložena deployment metadata, nikoli novou inventurou hashů runtime.

[HTTP před](pre-http.json) a [po](post-http.json): 26/26 PASS, stejné produkty.
[Skript](http-smoke.py) používá anonymní GET bez cookies, POST či skutečné objednávky.
HTTP pozorování není produkční zátěžový test ani přihlášené UAT.

## Pokračování

W7.4 technicky dokončena. Autonomně navazuje W7.5: R03/R04, především
stránkování registráře, poté W8–W15 podle komplexního plánu. Tyto oblasti
nejsou tímto vydáním označeny za dokončené. Lidská/účetní přejímka D12–D14,
ověřené provozní údaje, aktivace služeb a skutečné převody zůstávají oddělené.
