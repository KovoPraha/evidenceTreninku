# P11-RAS — výsledky

8. 11. 2026: plán před implementací, baselinec548d1d3 a produkce82efa16d.
[Plán](../../AJAX_PROFILE_SESSION_PERFORMANCE_2026-11-08.md),
[předání](../../SESSION_HANDOFF_AJAX_PROFILE_SESSION_2026-11-08.md).
RGS nevybrané, jeho input je vypnutý. Aktivní dvě profilová AJAX čtení drží
session do konce; skutečný native souběh, parita a přínos zatím NOT RUN.
Bez produkční změny, WIP hash7a7afd75… shodný. Handler produkce neověřený.

## První CI pokus — FAIL infrastruktury, žádný release

[Run42529811126](https://github.com/KovoPraha/evidenceTreninku/actions/runs/42529811126),
head498a6cca. 10.3 odmítlo fixture ALTER AUTO_INCREMENT na referencovaném ID
(1833); 11.4 prošlo první měřicí dvojice, potom opakovaný login už přihlášené
session správně vrátil302. Žádná úplná měřicí zpráva, nepovažovat za PASS.
Vlastní fixture flag FOREIGN_KEY_CHECKS se pouze při přípravě dočasně vypne
v try/finally a obnoví1; žádná produkční DDL/migrace. Každý testovací login
použije nový vlastní cookie jar a skutečný formulář. Failed artifact ponechaný.
Při 10.3 selhání před configem nebyl standardní cleanup dokončen; celý vlastní
service container byl následně ukončen GitHub Actions. 11.4 standardní cleanup PASS.
Lokálně cílený PHPUnit33testů/819assertions PASS; vlastní PHP style PASS,
Python/JS syntax PASS, docs4926odkazů/108archivů PASS. Produkce beze změny.
PR[406](https://github.com/KovoPraha/evidenceTreninku/pull/406), merge/deploy NOT RUN.
