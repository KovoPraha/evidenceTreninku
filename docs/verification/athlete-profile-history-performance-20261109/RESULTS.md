# P11-RHI — výsledky a měření

Zahájení9. 11. 2026, parita/měření/CI/release NOT RUN.
[Plán](../../ATHLETE_PROFILE_HISTORY_PERFORMANCE_2026-11-09.md).
Baseline d666078e2480f16fbc5f1d5b716802054501d57e. Předchozí RHA/RAS/RAP/RAH
výsledky se nepřepisují; nové páry používají aktuální produkční history().

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
