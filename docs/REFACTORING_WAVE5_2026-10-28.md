# Pátá vlna — dávkové nabídky a vazby fyzických týmů

Výchozí main `0275e6acaaafae1de08adfc480175e577eaba343`, čtyři hlavní CI PASS.
Navazuje na nasazenou čtvrtou vlnu `e40df7d3`. Samostatné worktree
`evidencePavel-refactoring-wave5-20261028`, větev `codex/refactoring-wave5-20261028`.
Vlastník výslovně schválil implementaci, merge a deploy po úspěšném ověření.

## Plán

1. Zachytit profil a selhávající test počtu dotazů: po čtvrté vlně 218 SQL
   pro 50 programů, z toho 50 nabídek a 50 vazeb na fyzické týmy.
2. Sdílet SQL projekci nabídky mezi kanonickým a dávkovým čtením. V kontextu
   jedné veřejné operace přednačíst nabídky i vazby týmů v omezených dávkách.
   Zachovat fallback primárního týmu, řazení vazeb a chybějící legacy schéma.
   Nejednoznačné legacy varianty s více nabídkami ponechat kanonickému výběru.
3. Kapacitu nadále počítat původními funkcemi. Žádné dávkování přihlášek či
   rezervací, žádná cache mezi operacemi, žádná změna zamykání nebo checkoutu.
4. Regrese před/po, úplné výstupy, více fyzických týmů, sdílená kapacita,
   neaktivní/chybějící vazby, hranice dávky a čerstvé zamykající čtení.
   Ověřit SQLite, MariaDB 10.3/11.4, HTTP shodu a kompletní CI.
5. Po zeleném PR i přesném main chráněný deploy se zálohou; čtecí kontrola
   a stejné HTTP cesty před/po. Aktualizovat CURRENT_STATE a archivovat předchozí.

Bez nové migrace, změny cen, oprávnění nebo skutečných dat. Integrace/importy
se nezapínají; deploy `uat_schvaleno=false`, `importovat_krouzky=false`.
Plné lidské/účetní UAT a dosavadní provozní blokery zůstávají otevřené.
