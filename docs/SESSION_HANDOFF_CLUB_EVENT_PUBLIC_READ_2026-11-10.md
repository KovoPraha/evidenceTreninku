# Předání P11-EVENTREAD —10.11.2026

ODLOŽENO / nevybráno pro aktuální release. [Plán](CLUB_EVENT_PUBLIC_READ_PERFORMANCE_2026-11-10.md).
Base966f7013; poslední ověřený release9ef52268/42863352343, runtime/flags vlastní CURRENT.
Větev codex/club-event-public-read-performance-20261110, vlastní next-performance worktree.
Původní fio větev/WIP7a7afd75 zachované, nestashovat/neresetovat.
Vybraný mechanismus: opakované sessions/capacity/occupied/waitlist na event či
placenou variantu v booking/krouzky.php. Nová dávková read projekce, původní
writery a eligibility zůstanou beze změny. Přesná parita/typy, native2DB,
celé HTTP a skutečný Chrome, před/po; po všech PASS povolený merge/deploy.
Dosavadní HJS/RIX ani hotové CSS řezy nepřebírat. Další krok probe a baseline.
[Výsledky](verification/club-event-public-read-20261110/RESULTS.md); nová implementace a přejímka zatím NOT RUN.

SQLite pole/typy PASS, při500+500 akcích6506→17SQL. Prod nyní0veřejných karet; pro vydání přednost ASSET6 (2CSS na existujících nákupních formulářích). Nový helper pouze prototyp ve vlastní větvi, žádné view změněné, bez merge/deploy. Native/browser přejímka NOT RUN.

[Prototyp d8274970](https://github.com/KovoPraha/evidenceTreninku/commit/d8274970), pouze vlastní nevybraná větev; helper se do ASSET6 nepřebírá.
