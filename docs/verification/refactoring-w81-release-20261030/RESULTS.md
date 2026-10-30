# W7.6 / W8.1 — ověřené produkční vydání

30. 10. 2026, [kis.kovopraha.cz](https://kis.kovopraha.cz/).
[PR #237](https://github.com/KovoPraha/evidenceTreninku/pull/237) sloučen jako
`df09eab94ccc6545e57b71a41bc24aa0011850c0`, [deploy 41055183388](https://github.com/KovoPraha/evidenceTreninku/actions/runs/41055183388) SUCCESS.
Nová čtecí kontrola potvrdila živé SHA a run ID. Vlastník autorizoval realizaci,
sloučení a nasazení této i dalších vln až do technického dokončení plánu.

## Změna a ověření

Časová osa načte historické členství jedné osoby a hrany jednou za volání.
Bez změny writerů a autorizace, kolace/stavy ponechány SQL. Roční profil
52 týdnů / 20 týmů: 4176 → 16 SQL, členské dotazy 2082 → 2 a přesně
shodná celá data. CI MariaDB 10.3 medián 1390,42 → 10,58 ms; 11.4
1059,23 → 14,09 ms. Paměť 1 364 536 → 1 269 960 B. Malý scénář může
načíst několik historických řádků navíc. R03 má pevně sedm dotazů;
nepoužívaný accountPersonCanManage nebyl přepisován, finanční historie R04
pokračuje ve W10. První CI odmítlo odsazení YAML; opravený head prošel všemi branami.
[Měření a meze](../refactoring-w81-20261030/RESULTS.md).

CI přesného nasazeného main: [Current documentation 41054548544](https://github.com/KovoPraha/evidenceTreninku/actions/runs/41054548544), [PHP quality 41054548565](https://github.com/KovoPraha/evidenceTreninku/actions/runs/41054548565), [Testy 41054548557](https://github.com/KovoPraha/evidenceTreninku/actions/runs/41054548557), [Push on main 41054548082](https://github.com/KovoPraha/evidenceTreninku/actions/runs/41054548082). [Metadata](github-ci-main.json).
Deploy zopakoval 1831 testů / 36291 assertions.
Linux POSIX bez skipů, MariaDB 10.3/11.4, souběžné revokace, checkout,
PHPStan, styl, anonymous sweep a JS PASS. Lidské UAT z toho neplyne.

## Provozní důkazy

Vstupy: `potvrzeni=NASADIT`, `uat_schvaleno=false`, `importovat_krouzky=false`.
Ověřená záloha 336 tabulek / 2 triggery,
6063579 komprimovaných bajtů, `evidence_2026-10-30_122736_f89245f1.sql.gz`.
SHA-256 `ad1f32a7853090b1f3fc0ec99a5067ab06116ffe4432254cee2a850a31a0ea2f`. Obnova v tomto vydání neopakována.
Migrační kontroly current / pending [], VS beze změny, dočasná konfigurace
odstraněna. [Výstupy deploye](deployment.json).
HTTP workflow: Přímé TCP spojení z GitHub runneru opakovaně timeout; fallback přes SSH potvrdil HTTP 200. Nezávislé GET kontroly jsou doloženy zvlášť..

Čtecí kontrola [před 41053431505](https://github.com/KovoPraha/evidenceTreninku/actions/runs/41053431505)
a [po 41055979874](https://github.com/KovoPraha/evidenceTreninku/actions/runs/41055979874)
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

W7.6 / W8.1 technicky dokončena. Autonomně navazuje W8.2: historické filtry
plánovače a náhled počtu kopírovaných plánů, poté zbytek W8–W15. Tyto oblasti
nejsou tímto vydáním označeny za dokončené. Lidská/účetní přejímka D12–D14,
ověřené provozní údaje, aktivace služeb a skutečné převody zůstávají oddělené.
