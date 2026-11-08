# Předání P11-RAS — profilové AJAX/session

8. 11. 2026: ROZPRACOVÁNO, plán před implementací.
[Plán](AJAX_PROFILE_SESSION_PERFORMANCE_2026-11-08.md),
[výsledky](verification/ajax-profile-session-performance-20261108/RESULTS.md).
Basec548d1d3, skutečná produkce82efa16d/deploy42521982178 SUCCESS.
Větev codex/ajax-profile-session-performance-20261108, vlastní medical-state
worktree; původní WIP/hash zachované. RHA/RHC hotové, neopakovat.
RGS nevybrané: globální hledací input má aktuálněfalse guard; nezapínat.

Aktivní profil při savedtab Měření načítá dvě AJAX projekce. Prověřit filelock
na native HTTP, až po fresh profile/person/note gate uzavřít session před čistým
čtením. Read SQL/HTML/oprávnění/TTL/CSRF/rotace a writery beze změny.
Skutečné dvě worker skupiny, stejná data, alternující varianty a same/different
session, bez vloženého zpomalení. Oddělený SQL/filelock důkaz, Chrome a logout
bez oživení session. Production handler neověřený, UAT NOT RUN.
Další krok fixture/páry, implementace/CI/merge/deploy zatím NOT RUN.
