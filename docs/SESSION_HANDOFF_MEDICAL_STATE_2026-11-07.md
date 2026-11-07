# Předání P11-R13-S — zdravotní stavový filtr

7. 11. 2026: ROZPRACOVÁNO, push / [PR #381](https://github.com/KovoPraha/evidenceTreninku/pull/381).
[Plán](MEDICAL_STATE_READ_PERFORMANCE_2026-11-07.md), [výsledky](verification/medical-state-read-performance-20261107/RESULTS.md), [provozní příprava](verification/medical-state-release-20261107/RESULTS.md).
Větev `codex/medical-state-read-performance-20261107`, samostatný worktree
`C:/Users/Marek/Documents/Codex/evidencePavel-medical-state-performance-20261107`.
Base6b1860d5, převzaté #375/eafe835b a jeho bezpečné důkazy. Přijaté D13/E45/E46
zachované; vlastní rozhodnutí D14, vlastní E47–50. Aktuální testovaná hlava c6f7576e.

Aktuální zadání dovoluje commit/merge/deploy po shodě, přínosu a zelených CI.
Historické předání #352 bez deploye zůstává historií. Produkce stále e36c9ba6.
Původní WIP `kis_rosters_admin.php` SHA256
`7a7afd75bfa34f736f158096ab46a671b7fee6c6faedc3701f989c0cf64733e5` znovu shodný.

První prototyp parita PASS / výkon FAIL; V2 Platné neutrální. Oba důkazy v protokolu.
V3 agreguje records přímo bez opakovaných JOIN, pro verified stavy zúží historii;
jedno tenké okno COUNT/ROW_NUMBER, nejvýše25 plných sledování. Budoucí explicitní
náhrady kanonicky nad omezenou sadou; překročené limity/transakce/osoba původní cestou.
Práva, writer, dokumenty, stav bez filtru a integrační flags nezměněné.

V3 celý HTTP100k Platné690,82→321,14ms, Čekající680,90→284,76ms; HTML/13checksumů PASS. Důkazy iteration3. Kvůli regresi hledání v nativním11.4 profilu V4 zachová původní SQL/bindingy pro všechny doplňkové filtry. Malá sada100 má pevnou režii navíc. Finální pomocný profil vypíná SQL query cache na obou DB; staré neřízené10.3 poměry neuvádět jako přínos.

V4 lokálně18/356 PASS, styl beze změn. Další krok: finální CI a nativní i celé HTTP/Chrome měření; poté bezpečný archiv a schválený merge/deploy. Před produkčním krokem všechny kontroly zelené. Anonymní baseline5HTTP200, zdravotní přehled403; přihlášené produkční UAT NOT RUN.
