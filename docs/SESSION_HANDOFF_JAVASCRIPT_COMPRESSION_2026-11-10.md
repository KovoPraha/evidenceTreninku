# P11-JSZ — předání

10. 11. 2026 · ROZPRACOVÁNO. [Plán](JAVASCRIPT_COMPRESSION_PERFORMANCE_2026-11-10.md).
Větev codex/javascript-compression-performance-20261110, base6dee3253.
Předchozí runtime a release flags vlastní [CURRENT](CURRENT_STATE.md).
Nová komprese zatím není implementovaná ani nasazená.
Další krok: izolovaný Apache, skutečný Chrome a výsledky [zde](verification/javascript-compression-20261110/RESULTS.md).
HJS prototyp je pouze na vlastní větvi, neslučovat; původní WIP zachovat.

Průběžně: [PR #427](https://github.com/KovoPraha/evidenceTreninku/pull/427) DRAFT.
Windows/Linux Apache + Chrome PASS, shodné bytes/DOM/pixely/funkční kontrakty;
přenos hlavních JS −70 %, časové zrychlení neprokázané. Default gzip revalidace
může vracet200 po30s, přesně zaznamenáno. Celé CI musí být zelené před merge.
Další krok: poslední ověřené CI, nový protected deploy a skutečný livegzip GET.
