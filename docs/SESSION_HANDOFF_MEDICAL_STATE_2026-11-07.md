# Předání P11-R13-S — zdravotní stavový filtr

7. 11. 2026: ROZPRACOVÁNO, vlastní větev, zatím lokální.
[Plán](MEDICAL_STATE_READ_PERFORMANCE_2026-11-07.md) určuje rozsah a akceptaci.
Base `6b1860d5`, větev `codex/medical-state-read-performance-20261107`, samostatný
worktree `C:/Users/Marek/Documents/Codex/evidencePavel-medical-state-performance-20261107`.
Aktuální zadání dovoluje commit/merge/deploy po shodě a měřeném přínosu a CI.
Historické předání #352 bez deploye je zachované jako historie.
WIP `kis_rosters_admin.php` SHA256 `7a7afd75bfa34f736f158096ab46a671b7fee6c6faedc3701f989c0cf64733e5` stejný.
Produkce zůstává e36c9ba6, nový deploy/aktivace ani skutečné zdravotní změny neprovedené.
Implementace hotová lokálně: referenční parita/18 testů344 assertions PASS, nový
kontrakt7/185 PASS, styl/lint PASS. Nativní měření/CI a celý HTTP/Chrome NOT RUN.
Další krok: push/PR a obě MariaDB v CI; bez prokázaného přínosu nenasazovat.

První nativní celý HTTP prototyp a7b1bee9: parita/HTML PASS, výkon FAIL (Platné106→197ms, Čekající105→221ms). Nebude nasazený. [Protokol](verification/medical-state-read-performance-20261107/RESULTS.md). Další varianta odstraní opakovanou agregaci; znovu všechny kontroly a měření.

Druhá varianta: sonda budoucích záznamů bez agregace, kontrola nejnovějšího verified pomocí NOT EXISTS; hlavní agregace jednou, COUNT(*) OVER a ROW_NUMBER pro počet i přesně oříznutou stránku. Podpora [MariaDB window funkcí](https://mariadb.com/docs/server/reference/sql-functions/special-functions/window-functions/window-functions-overview) a [ROW_NUMBER](https://mariadb.com/docs/server/reference/sql-functions/special-functions/window-functions/row_number); musí projít nativně na10.3/11.4. Před fallbackem se uvolní načtená sonda historie.

V2/64137a5b: parita celého HTTP PASS, výrazná regrese odstraněna; Platné64,85→64,80ms neutrální, Čekající63,65→58,61ms. Zatím neuzavřeno pro deploy. EXPLAIN ukazuje opakované vnitřní spojení lidí/sledování uvnitř souhrnu i pro neomezený seznam; pro ten nyní agregace jen nad records podle watch_id, vnější filtr a scoped varianta pro hledání/období zachované. Další nativní pár je podmínkou.

V3 navíc využívá doménový invariant: valid/expired/not_yet_valid mohou vzniknout jen z verified hlavy (včetně explicitní budoucí náhrady). Agregace pro tyto filtry proto pracuje pouze s přesnými verified záznamy; pending/revoked/missing zachovávají i nejnovější libovolný záznam. Výstup a řetězce stále kanonické. Před nasazením vyžadována celá parita i přínos.
