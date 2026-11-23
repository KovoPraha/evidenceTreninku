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
| Předání / CI | Implementace `d07656bb` pushnuta do [PR #521](https://github.com/KovoPraha/evidenceTreninku/pull/521). CI posledního dokumentačního commitu čeká. |
| Merge / deploy / skutečná TV přejímka | NOT RUN. |

Žádné produkční produkty, obrázky, zásoby ani účetní záznamy se v diagnostice neměnily.
