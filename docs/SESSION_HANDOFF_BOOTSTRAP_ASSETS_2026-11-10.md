# Předání P11-ASSET5 — 10. 11. 2026

ROZPRACOVÁNO. [Plán](BOOTSTRAP_ASSET_PERFORMANCE_2026-11-10.md#p11-asset5--veřejný-kalendář-a-velodrom-10-11-2026).
Base37f1ba8f; větev codex/public-calendar-assets-performance-20261110, vlastní
worktree C:/Users/Marek/Documents/Codex/evidencePavel-calendar-assets-performance-20261110.
Odstranit pouze CDN Bootstrap z booking/kalendar.php a booking/velodrom.php;
booking/klubovy_kalendar.php je control. Zachovat veškerou logiku, CSS payloady,
manifest, TTL, role/rezervace/kapacity a původní WIP7a7afd75 / fio větev.
Nová native/browser/live přejímka a měření NOT RUN. Při všech PASS povolené
commit/merge/deploy. Další krok: bezpečná veřejná baseline a source kontrakt.
Provozní runtime/flags jsou v [CURRENT](CURRENT_STATE.md), dosud94c10708.

## Předchozí P11-ASSET4


**NASAZENO / technická přejímka nového řezu PASS.**
[Plán](BOOTSTRAP_ASSET_PERFORMANCE_2026-11-10.md#p11-asset4--katalogová-diagnóza-a-další-měřený-řez-10-11-2026),
[měření a meze](verification/catalog-audit-assets-20261110/RESULTS.md),
[release receipt](verification/catalog-audit-assets-20261110/release/release-controls.json).

PR #435 → `94c10708`; celý tree totožný s testovaným `d439f5db`, 19 CI SUCCESS.
Offline obsahové verze pěti CSS/JS; původní bytes a TTL zachované, při GET se
nehashuje. Změna assetu vyžaduje tools/build-ui-asset-versions.php --write;
zastaralý manifest zastaví testy/deploy. Opravena jen nonce značka fallbacku,
CSP guard zachovaný. Žádné SQL/indexy, změny fotografií ani persistentní data cache.

MariaDB 10.3/11.4 celé HTTP/345 tabulek PASS, 408 měřených Chrome navigací,
4 syntetické pixelové páry. Při dalším nezměněném vydání 78508→0 B v JS TTL;
po 31s 78508→26618 B, CSS úspora 51890 B. První cold objem se nezměnil.
Skutečná změna CSS invaliduje právě jednu URL se správným hashem; fallback PASS.
Deploy 42837011604 SUCCESS; 2432/55977, záloha 389/2, config/migrace/VS/indexy/HTTP
i oba úklidy PASS. Runtime a flags vlastní [CURRENT](CURRENT_STATE.md).
Živý katalog 5/5 včetně strana=2, opakování a 4 produkční pixelové páry shodné.
PHP libcurl transport selhal; explicitní --system-curl zachovává HTTPS a plnou paritu.

Historické tři hashy nejsou zpětně vysvětlené: stranka=2 opakovala default,
before HTML chybí. Audit #434 / readonly 42826637716 PASS; samotné fotografie
nejsou prokázanou úplnou příčinou. Nová přísná baseline je uchovaná v protokolu.
Lidské přihlášené produkční UAT, fyzický telefon a obnova této zálohy NOT RUN.
Původní větev codex/fio-auto-reconciliation-recovery a WIP kis_rosters_admin.php
SHA-256 `7a7afd75` zachované. HJS/RIX prototypy nepřebírat; nestashovat/neresetovat.

Doklady: codex/stable-ui-assets-release-docs-20261110; [PR #437](https://github.com/KovoPraha/evidenceTreninku/pull/437).
Po sloučení finálního PR **neopakovat deploy**. Další kandidát KONCEPT: duplicita
CSS veřejného kalendáře/velodromu, ověřit vlastní před/po a všechny negativní kontroly.

## P11-ASSET2 — nasazeno a technicky ověřeno

Historie: [workspace release](verification/workspace-bootstrap-assets-20261110/release/RESULTS.md).

## Aktuální předání P11-ASSET3

Historický řez: [veřejné účty a původní meze katalogu](verification/public-account-assets-20261110/release/RESULTS.md).

ASSET5 checkpoint: source contract a1/12 negativní unit PASS; živý before6/6, cíle CSS2/53957 B a control1/31248 B. [Průběžné výsledky](verification/public-calendar-assets-20261110/RESULTS.md). Native dvojí DB/Chrome a přijetí ještě čekají.
