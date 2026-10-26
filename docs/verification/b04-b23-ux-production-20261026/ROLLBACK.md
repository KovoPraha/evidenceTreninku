# Návrat pouze UX změn B04/B23

Připravená a publikovaná větev: `codex/b04-b23-ux-rollback-20261026`, commit `eac7c8f2cc6bb9ced766e1b877b664c2d663c920`.

Je založena na nasazeném main `d12e7c792ce796260f3fc5d07b25a395c310fcca`. Vrací UX změnu; aplikační strom odpovídá předchozímu ověřenému vydání `5df4b0278129a3d66b62a19657911567d7196dee`. Jediný rozdíl proti tomuto vydání je doplnění nasazovacího seznamu pro odstranění dvou nově přidaných helperů:

- `includes/staff_login_return.php`
- `includes/staff_page_feedback.php`

Kontrola deploy-cleanup v syntetickém izolovaném adresáři odstranila oba helpery a zachovala testovací `config.php` (`rollback-cleanup.json`). Nejde o provedený rollback produkce ani o nový kompletní CI běh návratové větve.

## Postup při regresi

1. Porovnat aktuální main a produkční SHA s tímto vydáním; při dalších nasazených změnách větev aktualizovat a znovu posoudit rozdíl.
2. Otevřít PR návratové větve do main, nechat projít povinné kontroly a sloučit přes ochranu main. Nepoužívat reset ani force push.
3. Spustit standardní `deploy-production.yml` z main s potvrzením `NASADIT`, `uat_schvaleno=false`, `importovat_krouzky=false`. Zachovat zálohu, kontrolu migrací, variabilních symbolů a následnou diagnostiku.
4. Čtením ověřit výsledné live SHA, konfiguraci a přístupnost dotčených stránek. Nezapínat integrace ani neprovádět import.

UX balík nemá nové databázové migrace. Pro návrat kódu neobnovovat produkční databázi: mohlo by to ztratit mezitím vzniklé skutečné záznamy. Záloha tohoto nasazení byla samostatně obnovena pouze do izolované MariaDB, viz `RESULTS.md`.
