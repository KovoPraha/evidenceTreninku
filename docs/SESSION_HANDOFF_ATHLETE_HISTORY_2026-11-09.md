# Předání P11-RHI — 9. 11. 2026

[Plán](ATHLETE_PROFILE_HISTORY_PERFORMANCE_2026-11-09.md),
[výsledky](verification/athlete-profile-history-performance-20261109/RESULTS.md).
ROZPRACOVÁNO. Baseline d666078e, produkce c47d4008; zatím žádný nový deploy.
Větev codex/athlete-history-performance-20261109; původní WIP zachovaný.
Uživatel autorizoval podmíněný commit/merge/deploy po shodě a měřeném přínosu.
Další krok: implementace a přesná parita, poté vlastní native/HTTP/Chrome.

## Kandidát — lokální checkpoint

Upraveno pouze history(): EXISTS zachovává deduplikované vlastnictví,
detail znovu ověřuje scope i rok na vybraných25id; první výsledky se čtou
postupně, drží se nejvýše dvě stránky klíčů. Výpočet minut zůstává stejnýPHP
ve stejném pořadí. PDO buffering beze změny, paměť se teprve změří.
Reference celého readeru zmrazená z d666078e (jen přejmenovaná testovací třída).
SQLite původní3testy/611assertions a nové2/261 PASS. Nativní testy ověří navíc
emulated/native prepares i stringify režimy, finální výkon zatím NOT RUN.
PSR12 dotčených6souborů PASS, PythonAST PASS. Header/měření/parent/AJAX
endpoint/bezpečnost/writery jsou byte-nezměněné. Workflow zmrazí reader,
parent i AJAX endpoint a měří celé fragmenty i skutečný Chrome.

## První nativní měření — checkpoint

f9acd99f/run42621207366 oběDB2/513 a celýHTTP/Chrome prošly, historie30k
468→37 /758→48ms, PHPpeak14.5→1.56MB. Wholefragmenty/role/CSRF/cleanup přesné.
První Chrome default1181→818ms, savedMěření1579→1729ms (+9.5%); netvrdit
zrychlení všech záložek. Neexistujícírok7→20 /8→24ms. Další iterace přesune
levný YEAR před EXISTS a přidá browser ResourceTiming pro rozlišení souběhu.
[První bezpečné důkazy a meze](verification/athlete-profile-history-performance-20261109/RESULTS.md).
Místní7/892 i composer validate --strict PASS. Nový head potřebuje vlastní
celéCI/native/Chrome před merge a podmíněným release; WIP a produkce zachované.
