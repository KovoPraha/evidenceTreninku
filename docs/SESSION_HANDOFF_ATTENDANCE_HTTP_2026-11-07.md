# P11 R06 — společné celé HTTP/browser, 7. 11. 2026

Stav ROZPRACOVÁNO, pouze syntetický localhost; žádný deploy ani aktivace.
[Plán a akceptace](ATTENDANCE_PERSON_PICKER_2026-11-07.md#p11-r06-http-combined--celé-načtení-společné-verze-7-11-2026).
Větev `codex/attendance-integrated-http-20261107`, worktree
`C:/Users/Marek/Documents/Codex/evidencePavel-attendance-http-20261107`.
Měřená aplikace faae75e8, kontrolní d958fd6e; stejný nový kombinovaný seed.
Cíl 540 pozitivních HTTP časů a 234 Chrome pozorování tří rolí / devíti případů.
Dále parity/role/drafty, oddělená diagnostika, vlastní úklid a výsledkové PR.
Původní WIP `kis_rosters_admin.php` SHA256
`7a7afd75bfa34f736f158096ab46a671b7fee6c6faedc3701f989c0cf64733e5` zachovat.
Cizí worktree `attendance-picker-completion` se nepoužívá ani nemění.
Fyzický mobil a řízený souběh tímto nejsou automaticky přijaté. K0.2 otevřené.

Průběh: nový runtime `kis-attendance-http-20261107-e999e34b`, DB 33469 / HTTP 18105,
350 tabulek / 161 migrací; stejné doménové objemy jako plán. Standardní HTTP
login 12/12, původní aplikace 21 HTTP případů / 270 časů a 9 DOM kontrol PASS.
Rozšířený snapshot 20 skutečných doménových tabulek hotový; předběžná kontrola
odmítla nehotový seed a neexistující legacy tabulku ještě před časováním.
Používá se kanonický `coach_scope_grants`, seed byl vytvořen jednou.
Chrome baseline probíhá, první úplný případ má 13 pozorování; časy společné
verze a závěrečný úklid zatím NOT RUN. Vlastní DB PID 29536, web PID 25936.
Citlivé místní config/credentials zatím potřebné pro měření, nic z nich necommitovat.

Oba čtecí páry dokončené: 540 HTTP časů, 234 Chrome pozorování, 18 DOM kontrol,
všech 20 doménových checksumů shodných. Diagnostiky HTML parity PASS, 0 aplikačních
DML/DDL; širší lekce 203 → 72 SQL, trenérský detail 59 → 72, seznam 36 → 23.
JS/noJS obou současných draftů, klávesnice, výběr/host/390px PASS; 44 HTTP kontrol
včetně revize 53/201/paper, stale422 a legacy API omluvy PASS. Role/account revokace PASS.
Zbývá pozitivní browser omluva, DB kontrola a úklid, archiv a finální předání.
