# Předání P11-HJS3 —10.11.2026

ROZPRACOVÁNO. [Plán](SHARED_HEADER_SCRIPT_PERFORMANCE_2026-11-10.md#p11-hjs3--společný-skript-po-odstranění-duplicitních-stylů-10-11-2026),
[výsledky](verification/shared-header-cache-20261110/RESULTS.md).
Base5d6434dc, runtimec29642f1/deploy42905781826, flags vlastní CURRENT.
Větev codex/header-cache-performance-20261110, vlastní worktree
C:/Users/Marek/Documents/Codex/evidencePavel-header-cache-performance-20261110.
Původní fio/d1221f35 a WIP7a7afd75 beze změny. HJS/HJS2/RIX prototypy zachovat.
Vybraný společný inlineJS≈14,8kB: přesun do obsahově adresovaného veřejného
staff-header.<SHA256>.js, cache7d/immutable pouze pro tento soubor. Dynamických
6hodnot zůstává na každém privátním HTML; žádná cache práv/session/tokenů.
Ostatní JS30s/payloady/SQL/writery/API/pořadí beze změny. Source/VM/CodeQL,
2DB/wholeHTTP/checksumy, gzip/cache/security,544měřenýchChrome a8pixelpárů
nově ověřit;17párů/4warmupy, cold gate5%+1024B, warm skutečný síťový přínos.
Native/browser/release zatím NOT RUN. Další krok selektivně prověřit HJS2
zdroje a přidat fingerprint/cache/SRI; neslučovat celou starou větev.
Staré HJS/HJS2 nevybrání zůstává historicky platné. ASSET1–7 nasazené,
ASSET4/6 LIMIT zachované; EVENTREAD0karet odložený bez runtime helperu.
Při všech aktuálních PASS merge/deploy autorizovaný; UAT/importfalse,
přihlášené živé UAT podle dostupnosti, finální doklady bez redeploye.
