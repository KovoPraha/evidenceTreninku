# Předání P11-ASSET5 —10.11.2026

**NASAZENO / technická přejímka PASS.**
[Plán](BOOTSTRAP_ASSET_PERFORMANCE_2026-11-10.md#p11-asset5--veřejný-kalendář-a-velodrom-10-11-2026),
[měření a meze](verification/public-calendar-assets-20261110/RESULTS.md),
[release receipt](verification/public-calendar-assets-20261110/release/release-controls.json).

PR #439 →8182489e, celý treee40cdc71 totožný s testovaným39f3f104;13 CI SUCCESS.
Odstraněné dvě CDN Bootstrap CSS duplicity: booking/kalendar.php (lekce)
a booking/velodrom.php. Klubový kalendář nezměněný control; payloady/manifest/
TTL, SQL, rezervace, kapacity i práva stejné. Source kontrakt pinovaný na37f1ba8f
ověřuje přesně dvě odstranění a16 vyjmenovaných guard závorek. První strict diff
odhalil4 zdrojové mezery tagu; opraven přesný comparator, regresní unit1/13 PASS.

Finální native42853530222/39f3f104:MariaDB10.3+11.4,8HTTP/350checksumů naDB,
anonymní POSTdenials,408 Chrome měřených+96warmup,24funkčních variant,
6decoded pixel pairs a cleanup PASS. Cold Bootstrap CSS64454→31248 B v CI;
decoded465606→232803 B; warm síť0→0 B. Control také časově kolísá, proto
neslibovat procentní produkční zrychlení; kompletní medians jsou v protokolu.

Deploy42855987339 SUCCESS;2443/56108, backup389/2, config/migrace/VS/indexy,
HTTP200 a oba úklidy PASS. Živý Chrome158, desktop/mobile:6 celých HTML otisků
i6 pixelových párů přesně shodných,5assetverzí zachovaných, CSS53957→31248 B,
control31248→31248 B, bez JS/network chyby a přetékání. Baseline čerstvá po
schváleném společném vydání #438/#436; jejich věcné záznamy zachované.
Runtime a flags vlastní [CURRENT](CURRENT_STATE.md); import/UATfalse.
Lidské přihlášené produkční UAT, fyzický telefon a obnova této zálohy NOT RUN.

Původní větev codex/fio-auto-reconciliation-recovery a WIP kis_rosters_admin.php
SHA256 `7a7afd75bfa34f736f158096ab46a671b7fee6c6faedc3701f989c0cf64733e5`
zachované, nestashovat/neresetovat. Vlastní doklady ve worktree
C:/Users/Marek/Documents/Codex/evidencePavel-calendar-assets-performance-20261110,
větev codex/public-calendar-assets-release-docs-20261110; [PR #442](https://github.com/KovoPraha/evidenceTreninku/pull/442).
Další krok: po všech CI sloučit dokumentační PR, **neopakovat deploy**.

Historie ASSET1–4 zůstává v [tematickém plánu](BOOTSTRAP_ASSET_PERFORMANCE_2026-11-10.md).
Tři historické katalogové hashy mají stále chybějící before HTML (LIMIT),
nikoli dodatečné PASS; [ASSET4 důkazy](verification/catalog-audit-assets-20261110/RESULTS.md).
Žádný další nový implementační řez není v tomto zadání rozpracovaný.

Souběžné doklady #441/d0f5fee7 převzaté se zachováním TESTER/COACH intro a všech věcných záznamů; finální11CI #442 se ověřuje znovu, bez dalšího deploye.
