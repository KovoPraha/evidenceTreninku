# Předání P11-EVENTREAD —10.11.2026

ROZPRACOVÁNO / výběrový probe před implementací. [Plán](CLUB_EVENT_PUBLIC_READ_PERFORMANCE_2026-11-10.md).
Base966f7013; poslední ověřený release9ef52268/42863352343, runtime/flags vlastní CURRENT.
Větev codex/club-event-public-read-performance-20261110, vlastní next-performance worktree.
Původní fio větev/WIP7a7afd75 zachované, nestashovat/neresetovat.
Vybraný mechanismus: opakované sessions/capacity/occupied/waitlist na event či
placenou variantu v booking/krouzky.php. Nová dávková read projekce, původní
writery a eligibility zůstanou beze změny. Přesná parita/typy, native2DB,
celé HTTP a skutečný Chrome, před/po; po všech PASS povolený merge/deploy.
Dosavadní HJS/RIX ani hotové CSS řezy nepřebírat. Další krok probe a baseline.
[Výsledky](verification/club-event-public-read-20261110/RESULTS.md); nová implementace a přejímka zatím NOT RUN.
