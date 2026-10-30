# W7.5 — ověřené produkční vydání

30. 10. 2026, [kis.kovopraha.cz](https://kis.kovopraha.cz/).
[PR #235](https://github.com/KovoPraha/evidenceTreninku/pull/235) sloučen jako
`c6a260acef86218bb3e429c912d12746f712b75f`, [deploy 41051708252](https://github.com/KovoPraha/evidenceTreninku/actions/runs/41051708252) SUCCESS.
Nová čtecí kontrola potvrdila živé SHA a run ID. Vlastník autorizoval realizaci,
sloučení a nasazení této i dalších vln až do technického dokončení plánu.

## Změna a ověření

Registrář načítá pouze požadovanou stránku osob; statistiky zůstávají úplné.
ID stabilizuje shodné hodnoty řazení. Bez změny writerů a autorizace.
Při 10 000 osobách stránka 50: 10 003 → 53 načtených řádků, čtyři SQL.
CI stránka 100: paměť 36 833 768 → 434 800 B; medián MariaDB 10.3
78,29 → 0,93 ms a 11.4 60,68 → 7,77 ms. U malých seznamů je doložen
pevný overhead. Shodná data v 50 scénářích, smíšené kolace a nativní PDO.
SRI kontrola rozpoznává konstantní konkatenace i víceřádkové tagy; negativní
testy nadále odmítají chybějící integrity/crossorigin. HTML ověřeno AST.
[Měření a meze](../refactoring-w75-20261030/RESULTS.md).

CI přesného nasazeného main: [Current documentation 41051145037](https://github.com/KovoPraha/evidenceTreninku/actions/runs/41051145037), [Push on main 41051145192](https://github.com/KovoPraha/evidenceTreninku/actions/runs/41051145192), [PHP quality 41051145270](https://github.com/KovoPraha/evidenceTreninku/actions/runs/41051145270), [Testy 41051145060](https://github.com/KovoPraha/evidenceTreninku/actions/runs/41051145060). [Metadata](github-ci-main.json).
Deploy zopakoval 1825 testů / 36217 assertions.
Linux POSIX bez skipů, MariaDB 10.3/11.4, souběžné revokace, checkout,
PHPStan, styl, anonymous sweep a JS PASS. Lidské UAT z toho neplyne.

## Provozní důkazy

Vstupy: `potvrzeni=NASADIT`, `uat_schvaleno=false`, `importovat_krouzky=false`.
Ověřená záloha 336 tabulek / 2 triggery,
6063580 komprimovaných bajtů, `evidence_2026-10-30_115853_00c262fd.sql.gz`.
SHA-256 `1745d1500f2f60939faef64442ca7a250caafadf8ccde2ed6dbf5b4166d133c7`. Obnova v tomto vydání neopakována.
Migrační kontroly current / pending [], VS beze změny, dočasná konfigurace
odstraněna. [Výstupy deploye](deployment.json).
HTTP workflow: Přímé TCP spojení z GitHub runneru opakovaně timeout; fallback přes SSH potvrdil HTTP 200. Nezávislé GET kontroly jsou doloženy zvlášť..

Čtecí kontrola [před 41050327599](https://github.com/KovoPraha/evidenceTreninku/actions/runs/41050327599)
a [po 41052346215](https://github.com/KovoPraha/evidenceTreninku/actions/runs/41052346215)
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

W7.5 technicky dokončena. Autonomně navazuje W7.6 / W8.1: zbývající
R03/R04 a historická časová osa sportovce, poté zbytek W8–W15. Tyto oblasti
nejsou tímto vydáním označeny za dokončené. Lidská/účetní přejímka D12–D14,
ověřené provozní údaje, aktivace služeb a skutečné převody zůstávají oddělené.
