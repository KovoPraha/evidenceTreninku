# Databázový podklad pro localhost

`local-demo-schema.sql` obsahuje pouze strukturu 190 aplikačních tabulek a
triggerů. Neobsahuje žádné řádky, uživatele, hesla, objednávky ani osobní údaje.
Hodnoty `AUTO_INCREMENT` jsou normalizované a `DEFINER` není součástí souboru.

Soubor je vstupem pro `bin/setup-localhost-testing.ps1`. Po importu skript vždy
spustí kanonický migrační katalog a potom vloží výhradně syntetická data přes
`bin/seed-local-demo.php`.

Tento snapshot není produkční záloha a nesmí se používat pro obnovu produkce.
Obnovovací SQL dumpy a lokální datové adresáře zůstávají mimo Git.
