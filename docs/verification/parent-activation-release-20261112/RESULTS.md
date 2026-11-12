# KIS-ACT-01 — vydání kódu aktivace importovaných rodičů

Datum: 12. 11. 2026, Europe/Prague. Rozsah: technické vydání PR [#463](https://github.com/KovoPraha/evidenceTreninku/pull/463), nikoli schválení nebo odeslání pozvánek. Vlastník povolil nasazení pokynem „nasadit“. Produkční pilot, kontrola identity, text zprávy a konkrétní adresáti zůstávají samostatnou provozní bránou.

| Kontrola | Výsledek | Důkaz / mez |
| --- | --- | --- |
| PR a společný strom | PASS | #463 sloučen na `main` jako `373b8d89b5c724c6e499c88d9cb1458334ef59d7`; poslední větev `bdece243` zahrnula souběžný #462. Všech 11 CI kontrol PASS, včetně PHPUnit, CodeQL, PHPStan, dokumentace a MariaDB 10.3/11.4 integračních i čtecích jobů. [CI](https://github.com/KovoPraha/evidenceTreninku/actions/runs/43320758219). |
| Chráněné vydání | PASS | [Workflow 43322186478](https://github.com/KovoPraha/evidenceTreninku/actions/runs/43322186478) SUCCESS, `headSha=373b8d89`; spuštěno s `potvrzeni=NASADIT`, `uat_schvaleno=false`, `importovat_krouzky=false`. Cíl `kis.kovopraha.cz`. |
| Záloha před migrací | PASS | Workflow ověřil SQL archiv, manifest a SHA-256 mimo webroot: 393 tabulek, 2 triggery, archiv `evidence_2026-11-12_224427_8d61e257.sql.gz`, digest `e4b79503c4fb1715ee8e022a835c6a3cd58203c94bbf5c3fc16e9064c2a1f99d`. Obsah zálohy zůstává pouze na hostingu. |
| Schéma a aktivace | PASS | Migrační kontrola po aplikaci: `ok=true`, `current=true`, katalog 163 PHP migrací, `pending=[]`. Workflow aktivoval release s novým souborem `migrations/20261112150000_imported_account_activation.php`; ochrana dosavadních variabilních symbolů `unchanged=true`. |
| Veřejný web | PASS | Workflow HTTP 200 na `/index.php`; vlastní anonymní GET po vydání: `/index.php` 200, `/booking/aktivace_uctu.php` 200 s titulkem a formulářem, `/kis_account_activation_admin.php` 403. Privátní klíč archivu HTTP 403. |
| Produkční funkční UAT | NOT RUN | Reálný token, e-mailová schránka, aktivace rodiče, přihlášení a zobrazení správných dětí nebyly zkoušeny. Žádný produkční účet ani vazba nebyly při této kontrole změněny. |
| Pozvánky a pilot | NOT RUN | Vydání samo pozvánky nevytváří. Tento průchod žádnou pozvánku nezařadil ani neodeslal. Stav existující fronty nebyl čtecím produkčním přístupem ověřen. |

Další krok: vedení schválí text pozvánky, způsob prokázání identity, pilotní adresáty a postup při nedoručení. Správce pak na ověřených vazbách provede malý pilot a zaznamená skutečné doručení, aktivaci a rodičovské UAT. Bez tohoto kroku nelze tvrdit, že rodiče již mají přístup.
