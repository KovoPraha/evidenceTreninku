# Předání P11-RAP — počáteční profil sportovce

9. 11. 2026: plán před implementací, vlastní větev/worktree, WIP zachovaný.
[Plán](ATHLETE_PROFILE_PARENT_PERFORMANCE_2026-11-09.md),
[výsledky](verification/athlete-profile-parent-performance-20261109/RESULTS.md).
Main02904936, produkce95cb33e5/deploy42531190845 SUCCESS.
Další doložená rezerva: parent profil~2,7sTTFB při30kfixture. Nejdříve samostatný
SQL profil celé route, potom pouze potřebná projekce; úplný kanonický souhrn/
hash/export musí zůstat přesně stejný. Žádná cache čerstvých práv či nové DDL.
Větev codex/athlete-profile-parent-performance-20261109, původní WIP/hash shodné.
RHA/RHC/RAS hotové, RGS vypnuté/nevybrané; neopakovat/neaktivovat.
Diagnostika/implementace/CI/merge/deploy zatím NOT RUN; podmíněná autorizace
aktuálního vlastníka platí, před release úplná parita/HTTP/Chrome a aktuální CI.

Diagnostika42570580742 obaDB PASS:94752nepoužitých řádků,649/1665ms, peak96,6MB.
Zvolen nový reader3součtů se stejnými kanonickými aktivitami; full snapshot
beze změny. PR#408, další krok přesná parita a prokládané HTTP/Chrome páry.

61e84220: native10.3 celá parent route/12rolí/CSRF PASS,30k1981→888ms,
SQL94771→15řádků a peak96,6→1,26MB. 11.4/Chrome čekají, bez merge/deploy.
