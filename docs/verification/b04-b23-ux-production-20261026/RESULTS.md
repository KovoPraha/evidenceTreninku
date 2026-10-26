# B04/B23 UX – produkční nasazení 26. 10. 2026

UX změny byly sloučeny přes PR #207 a nasazeny na produkci. Jde o nasazení kódu a čtecí kontrolu; nejde o schválení ostrého importu ani úplné provozní přejímky celého oddílu.

- PR: https://github.com/KovoPraha/evidenceTreninku/pull/207
- Produkční SHA: `d12e7c792ce796260f3fc5d07b25a395c310fcca`.
- Předchozí ověřené vydání: `5df4b0278129a3d66b62a19657911567d7196dee`.
- Nasazení: https://github.com/KovoPraha/evidenceTreninku/actions/runs/40317875183 – PASS.
- PR CI: https://github.com/KovoPraha/evidenceTreninku/actions/runs/40317452719 – 1743 testů / 35152 assertions, bez přeskočení; explicitní unixová kontrola 3 testy / 45 assertions. MariaDB 10.3 a 11.4 PASS.
- Main CI: https://github.com/KovoPraha/evidenceTreninku/actions/runs/40317788236 – PASS.
- CodeQL: https://github.com/KovoPraha/evidenceTreninku/actions/runs/40317449047 – PASS.

## Záloha a návrat

Nasazení vytvořilo `evidence_2026-10-26_115105_bc54d9d0.sql.gz`, 336 tabulek / 2 triggery, SHA-256 `5f4159d1e0edab8da34a85dce5cfce02ee394579ee48c06c706d64753a2080f3`.

Izolovaná obnova této konkrétní zálohy prošla v běhu https://github.com/KovoPraha/evidenceTreninku/actions/runs/40318328082 (pokus 2). První pokus selhal při startu dočasného databázového kontejneru; opakovaný běh ověřil checksum, obnovil 336 tabulek a 2 triggery. Produkční databáze se neobnovovala. Důkaz: `restore-evidence.txt`, `restore.json`, `backup-created.json`.

Připravená návratová větev a postup jsou v `ROLLBACK.md`. Návrat na produkci nebyl proveden.

## Čtecí kontroly produkce

Před nasazením: běh 40317544595, původní SHA a všech 44 souborů diagnostického manifestu souhlasily. Po nasazení: běh 40318331606 potvrdil nové live SHA a run ID. Manifest je stále pro předchozí vydání: 40 souborů souhlasí, čtyři očekávané rozdíly jsou `includes/kis_review_handoff.php`, `kis_review_admin.php`, `sync_evidence.php`, `zavod_vysledky.php`. Diagnostika nevrací skutečné hashe všech osmi UX souborů; není to úplná bajtová kontrola nového vydání.

Konfigurace má před i po nasazení SHA-256 `326c832d891a99d2780099391854a8daee3936adcdacf62c62f0a00b616fb772`. Fio import a automatické potvrzování, SumUp, Stripe a Velocota zůstaly vypnuté; Packeta nemá aktivní příznak. Kanonický KIS zápis zůstává vypnutý. Migrace byly aktuální bez čekajících změn a krok zachování variabilních symbolů prošel. Import kroužků byl přeskočen; `uat_schvaleno=false`.

V přihlášeném Chromu byly ověřeny:

| Kontrola | Výsledek |
| --- | --- |
| Anonymní B04/B23 | Přihlášení do KIS s uchovaným bezpečným návratem na původní stránku. |
| B23 v kontextu Správce systému | Srozumitelná stránka jiné pracovní pozice a funkční návrat na rozcestník. |
| Seznam závodů v kontextu Vedoucí sportu | Nový prázdný stav s vysvětlením, odkud se spravují výsledky. |
| Neexistující závod B04 | Standardní rozvržení, „Závod není dostupný“, návrat na seznam a pracovní rozcestník. |
| Neexistující běh B23 v kontextu registráře | „Tento importní běh neexistuje. Vyberte uložený běh v KIS centru.“ a funkční návrat. |
| Importní průvodce | České popisky „1. Nahrání souborů“, „3. Náhled“, „Uživatelé z KIS (.xlsx)“. |
| Úklid relace | Obnovena původní pracovní pozice Správce systému. |

Snímky: `production-race-feedback.png`, `production-kis-feedback.png`, `production-kis-wizard.png`. Nebyly nahrány soubory, aplikovány importy ani vytvořeny testovací obchodní záznamy. Přihlášení a přepínání vlastního pracovního kontextu jsou jediné UI změny relace; běžné provozní/auditní logování může proběhnout.

## Rozsah závěru

Nové UX je dostupné a čtecí kontrola prošla. Nebyly prováděny zápisové scénáře s reálnými výsledky ani importy; aktuální seznam závodů a uložených importních běhů byl prázdný. Role byly ověřovány kontextem přihlášeného superadmina, nikoli samostatným omezeným účtem trenéra. Dřívější provozní body (rozsahy přístupu trenérů, počáteční fyzické sklady, členské dohody a částky) se tímto nasazením neuzavírají; jejich inventura je v `postflight.json` a není důkazem plné provozní připravenosti.
