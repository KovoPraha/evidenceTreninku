# Předání P11-ASSET6 —10.11.2026

**NASAZENO / cílové formuláře technicky PASS; controlfoto LIMIT.**
[Plán](BOOTSTRAP_ASSET_PERFORMANCE_2026-11-10.md#p11-asset6--rychlý-nákup-a-přihláška-10-11-2026),
[měření a meze](verification/checkout-assets-20261110/RESULTS.md),
[receipt](verification/checkout-assets-20261110/release/release-controls.json).

PR #445 →c9e5501a, celý tree97fa16dc totožný s testovaným d6af0a09;13CI PASS.
Odstraněné dvě CDN Bootstrap CSS duplicity rychlého nákupu/přihlášky.
Source kontrakt proti58848900: PHP tokeny a literal HTML/JS mimo2tagy stejné,
0vložených závorek. Payloady/manifest/TTL, SQL, ceny/stock/terms/CSRF/writery stejné.
Lokálně21/412, native42878204242/2DB desetHTTP/350checksumů naDB, denials,
408měřených+96warmup Chrome,24funkčních záznamů,6pixelpárů a cleanup PASS.
První fixture FAIL pro login302/stock404 zůstávají v historii, guardy nezměněné.

Cold CSS64454→31248 B v CI, decoded465606→232803 B; warm network0→0 B.
FCP goods72→56/68→52 ms, program96→76/88→76 ms, control68→68 ms;
mediány nejsou produkční SLA, backend zrychlení neprokázané.
Deploy42881039034 SUCCESS/c9e5501a, 2454/56238, backup389/2,
config/migrace/VS/index/HTTP200/cleanup PASS (runner0, servercurl200). Živých6celých HTML,
všech5assetverzí a CSP/privacy hlavičky shodné; CSS53957→31248 B, controlstejný.
PětUI+CDNpayload canonicalGit před/po stejné, JS/network errors0/overflowfalse.
Přesných5/6pixelpárů včetně všech4 upravených formulářů. Mobilní controlfotka
má RGB≤4 rozdíly pouze v obrázku; přesná příčina neprokázaná, striktní test
zůstává LIMIT. Diagnostika/čtyři opakované GET/PNG zachované, nic nemaskováno.
Runtime/flags vlastní [CURRENT](CURRENT_STATE.md), UAT/importfalse.
Lidské přihlášené UAT, fyzický telefon a obnova této zálohy NOT RUN.

Původní codex/fio-auto-reconciliation-recovery/d1221f35 a kis_rosters_admin.php
SHA256 `7a7afd75bfa34f736f158096ab46a671b7fee6c6faedc3701f989c0cf64733e5` zachované.
Vlastní worktree C:/Users/Marek/Documents/Codex/evidencePavel-next-performance-20261110,
doklady codex/checkout-assets-release-docs-20261110, [PR #446](https://github.com/KovoPraha/evidenceTreninku/pull/446).
Závěrečné doklady PR #446: sloučením jsou součástí main.
Žádný další runtime krok, **neopakovat deploy**.

[EVENTREAD](CLUB_EVENT_PUBLIC_READ_PERFORMANCE_2026-11-10.md) odložený nevybraný
prototyp d8274970: SQLite potenciál6506→17SQL, produkční veřejná nabídka0karet;
native/Chrome nepřijaté, helper nepřebírat. RIX/HJS2 WIP zachované.
ASSET1–5 zůstávají v historii plánu; tři historické ASSET4 katalogové hashy
nadále LIMIT (chybí beforeHTML), žádné dodatečné PASS. BAR #444 zachovaný.
Žádný další implementační řez není v tomto zadání rozpracovaný.
