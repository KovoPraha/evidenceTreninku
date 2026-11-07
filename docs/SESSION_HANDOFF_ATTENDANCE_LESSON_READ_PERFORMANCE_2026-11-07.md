# Předání P11-R06-LESSONS — 7. 11. 2026

[Plán](ATTENDANCE_LESSON_READ_PERFORMANCE_2026-11-07.md),
[výsledky](verification/attendance-lesson-read-performance-20261107/RESULTS.md).
Vlastní větev/worktree v plánu, původní WIP zachovaný. ROZPRACOVÁNO.
Reference a dávkový read hotové; 19 testů / 993 assertions PASS.
50 nabídek: trenér 153 → 6 SQL, superadmin 254 → 9 SQL.
Další krok: nativní CI a reporty 10.3/11.4, potom PR review.
Katalogový deploy #364 běží samostatně; tuto implementaci s ním nemíchat.
Místní testy a měření PASS; nativní CI této etapy NOT RUN, produkční změny žádné.

Nativní parita a 720 párů na 10.3/11.4 PASS, 6 otisků zdrojů shodných.
50 nabídek trenéra: 41,47/19,99 → 1,77/0,97 ms, 153 → 6 SQL.
[PR #367](https://github.com/KovoPraha/evidenceTreninku/pull/367); celkové CI dobíhá.

První runtime `b20715ec` má všech 11 CI kontrol PASS včetně 10.3/11.4.
Doplněný lokální okrajový test pro NULL/neaktivního/chybějícího vlastníka
a legacy ID 0 prošel; transakční kontrola a writer zůstávají původní.
