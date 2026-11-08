# Předání P11-RGS — globální hledání/session

8. 11. 2026: ROZPRACOVÁNO, plán před implementací.
[Plán](GLOBAL_SEARCH_SESSION_PERFORMANCE_2026-11-08.md),
[výsledky](verification/global-search-session-performance-20261108/RESULTS.md).
Basec548d1d3, produkce82efa16d/deploy42521982178 SUCCESS.
Větev codex/global-search-session-performance-20261108, vlastní medical-state
worktree. Původní checkout/WIP/hash zachované. RHA už hotové, neopakovat.

Vybrán další mechanismus analýzy5/6: session zamčená během globálního hledání.
Nejprve skutečné souběžné HTTP se stejnou/jinou session na vlastním files handleru,
MariaDB10.3/11.4, více PHP workerů, žádné vložené zpomalení či forged login.
Pak kandidátní uzavření session až po fresh auth/lifecycle před čistým čtením.
SQL, výsledky, oprávnění/TTL/CSRF/rotace beze změny. Výsledek nutno změřit.
Kanonické odhlášení, ztráta práv, zachování session a Chrome skutečné hledání
před merge/deploy. Production handler/lock wait neověřené, UAT NOT RUN.
Další krok fixture a první páry; implementace/CI/merge/deploy zatím NOT RUN.

## Uzavření výběru kandidáta — NEVYBRÁNO

Aktuální c548d1d3 má v hlavicka.php řádek859 `if (false && $is_logged_in)`
a JS ukončí hledání bez tohoto inputu. Globální hledání není v běžné hlavičce
aktivní. Dílčí návrh byl vrácen pouze ve vlastní kopii; žádný runtime/produkční
zásah nebyl sloučený ani nasazený. Koncepty zůstávají verzované.
Mechanismus session se přesouvá na aktivní profilové AJAX v P11-RAS:
[plán](AJAX_PROFILE_SESSION_PERFORMANCE_2026-11-08.md). Původní WIP beze změny.
