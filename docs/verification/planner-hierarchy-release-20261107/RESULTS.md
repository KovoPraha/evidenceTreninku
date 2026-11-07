# P11-R05-H — produkční předání

7. 11. 2026: PŘIPRAVENO, merge/deploy/produkční smoke NOT RUN.
[Plán](../../PLANNER_HIERARCHY_READ_PERFORMANCE_2026-11-07.md),
[parita a měření](../planner-hierarchy-read-performance-20261107/RESULTS.md),
[předání](../../SESSION_HANDOFF_PLANNER_HIERARCHY_2026-11-07.md).

Aktuální zadání dovoluje standardní deploy po paritě, přínosu a zelených kontrolách.
Runtime65440e3b; finální zdrojové hashe v nativních a HTTP důkazech shodné.
Po výsledkovém CI: merge #374, ověřit fresh main a nepřítomnost souběžného deploye,
čtecí veřejný baseline, dispatch deploy-production.yml na main s potvrzením NASADIT,
importovat_krouzky=false a uat_schvaleno=false. Vyčkat zálohy, migrací, VS,
aktivace a HTTP smoke; zapsat přesné release SHA a run ID, opakovat veřejné GET.
Sondy jsou připravené, neprovedené. Původní WIP zachovat; produkční osoby/role/
platby/docházku ani flags neměnit. Přihlášené produkční UI UAT NOT RUN.
