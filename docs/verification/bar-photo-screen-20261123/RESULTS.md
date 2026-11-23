# BAR-PHOTO-SCREEN-01 — fotografie na obrazovce

23. 11. 2026. [Plán](../../BAR_DISPLAY_STOCK_PARITY_2026-11-10.md#bar-photo-screen-01--chybějící-fotografie-na-živé-obrazovce-23-11-2026). Vývoj `codex/bar-photo-screen-20261123` z `origin/main` `2ad73643`.

| Kontrola | Stav |
| --- | --- |
| Hlášení vlastníka | Prázdné snímky ve třech kartách; podle upřesnění je vidět jen menší fotografie tyčinky. Fotografie TV v reálném prostoru byla dodána do chatu, osobní přílohu neukládat do Gitu. |
| Živá veřejná projekce | 45 produktů, všech 45 s URL obrázku. Bolognese (`id=51`), Plzeň (`17`), VIT-HIT (`27`) a tyčinka (`46`) přes `HEAD` 200 `image/*`, celé GET originály dekódované. |
| Živý přenos | Bolognese JPEG 2048 × 2048, 2 585 938 B: 11,55 s; tyčinka PNG 1080 × 710, 479 217 B: 2,23 s. Obrazovka mění trojici každých 10 s. GET vzorky z jednoho spojení, rychlost se v provozu může lišit. |
| Živý prohlížeč | Playwright na 1920 × 1080 reprodukoval prázdné karty; při odečtu obrázky `complete=false`, některé ještě bez dekódovaného rozměru. Snímek v ignorovaném `.playwright-cli/`; není součástí Gitu. |
| Místní varianta originálů | PASS: čtyři skutečné veřejné produktové originály zkopírované pouze do ignorovaného `output/`. JPEG varianty: Bolognese 2 585 938 → 105 323 B (−95,9 %), Plzeň 2 401 073 → 73 354 B (−96,9 %), VIT-HIT 2 017 576 → 62 751 B (−96,9 %), tyčinka 479 217 → 49 279 B (−89,7 %). Delší strana 960 px, originály se neměnily. |
| Místní prohlížeč | PASS: stejný CSS/JS cyklus se třemi zmenšenými produktovými fotografiemi vykreslil všechny tři (`complete=true`, 960 × 960) v náhledu 1920 × 1080. [Snímek](after-synthetic-1920x1080.png). Místní server není produkční TV. |
| Jednotkové testy, JS a styl | 5 PHPUnit testů / 30 tvrzení PASS; JS smoke střídání karet 1/1 PASS; PHP CS Fixer 5 souborů PASS. PHP lint všech 5 změněných souborů PASS. |
| Dokumentační kontrakt a diff | `php tools/check-current-docs.php`: CURRENT 24 řádků, 5888 odkazů, 0 chyb; `git diff --check` PASS. |
| První PR / CI | [PR #521](https://github.com/KovoPraha/evidenceTreninku/pull/521), head `25d17272`: [dokumentace 45223753934](https://github.com/KovoPraha/evidenceTreninku/actions/runs/45223753934), [PHP kvalita 45223753837](https://github.com/KovoPraha/evidenceTreninku/actions/runs/45223753837), [Testy 45223753833](https://github.com/KovoPraha/evidenceTreninku/actions/runs/45223753833) včetně PHPUnit a MariaDB 10.3/11.4 — vše PASS. |
| První merge a deploy | [#521](https://github.com/KovoPraha/evidenceTreninku/pull/521) sloučený jako `bc0c3067`; [deploy 45226205445](https://github.com/KovoPraha/evidenceTreninku/actions/runs/45226205445) SUCCESS na stejném SHA: produkční testy, záloha, migrace a HTTP smoke PASS, `uat_schvaleno=false`, import kroužků false. |
| Živé fotografie po prvním vydání | 45/45 `screen=1` URL; Bolognese 105 325 B/1,54 s, Plzeň 73 343 B/1,48 s, VIT-HIT 62 734 B/1,42 s, tyčinka 49 275 B/0,95 s; všechny 200 `image/jpeg` a 960px. Originál Bolognese 2 585 938 B/200; nepovolené ID 999999/`screen=1` 404. Tři živé karty v prohlížeči měly `complete=true` a nenulové rozměry. |
| Přechod při prvním vydání | FAIL: samostatný živý snímek při výměně karet zachytil dvě prázdné karty, následný snímek po načtení všechny tři. To je kratší, ale stále skutečný vizuální problém; snímky v ignorovaném `.playwright-cli/`, do Gitu neukládat osobní okolí TV. |
| Přednačtení dalších karet | Místní JS smoke 2/2 PASS včetně chyby obrázku a textového zástupného obsahu. Prohlížeč se 3s umělým zpožděním každého obrázku: první karty `[3,5,4]`, během načítání stále `[3,5,4]`, poté nové `[2,1,6]`, všechny `complete=true`. Dotazový klíč JS změněn na `v=20261123`. PHPUnit 3 testy / 15 tvrzení, PHP lint 2 souborů, PHP CS Fixer 2 souborů, dokumentační kontrola 0 chyb a `git diff --check` PASS. |
| Druhé PR / CI | [PR #522](https://github.com/KovoPraha/evidenceTreninku/pull/522), head `c5e75606`: dokumentace 45231414779, PHP kvalita 45231414678, Apache/Chrome 45231414565, [Testy 45231414628](https://github.com/KovoPraha/evidenceTreninku/actions/runs/45231414628) včetně PHPUnit a obou MariaDB — vše PASS; analýza kódu PASS. |
| Druhé merge a deploy | [#522](https://github.com/KovoPraha/evidenceTreninku/pull/522) sloučen jako `7918ca05`; [deploy 45233456861](https://github.com/KovoPraha/evidenceTreninku/actions/runs/45233456861) SUCCESS na stejném SHA: testy, ověřená záloha, migrace a HTTP smoke PASS, `uat_schvaleno=false`, import kroužků false. |
| Živá kontrola druhého vydání | PASS: veřejná stránka vydává `bar-photos.js?v=20261123`. Dlouhé automatizované měření střídání se v relaci Playwright zaseklo; výsledek tohoto měření NOT RUN. Místní zpožděný browser test výměny PASS. |
| Skutečná TV přejímka | NOT RUN; veřejný browser není fyzická obrazovka baru. |

Žádné produkční produkty, obrázky, zásoby ani účetní záznamy se v diagnostice neměnily.
