# Přejímka R07–R12 — 22. 10. 2026

**Lokální implementace a automatická přejímka: PASS. Produkce nezměněna.**

| Kontrola | Výsledek a důkaz |
|---|---|
| Celá regresní sada | **1 294 testů / 27 798 kontrol**, 0 chyb, 0 selhání, 0 přeskočených; [souhrn PHPUnit](phpunit-summary.json). |
| Skutečné HTTP formuláře a session | **110 scénářů PASS**; [úplný seznam](club-foundation-http-acceptance.json). |
| Souběh odesílání | Dva PHP procesy, **7 kontrol PASS**, jeden claim a jeden soubor lokálního outboxu, nejisté doručení zadrženo; [výsledek](notification-race.json). |
| Souběh odebrání práv a zápisu | **9 kontrol PASS**, přímo pozorovaný `INNODB_TRX LOCK WAIT`, právě jeden povolený zápis, další požadavek odmítnut; [výsledek](coach-scope-race.json). |
| Obnova DB | **254 tabulek, 2 triggery, všech 11 nových tabulek**, shodné počty proti manifestu, ověřený SHA-256; 24 soukromých odkazů má správný soubor a otisk; [výsledek](club-foundation-backup-restore.json). |
| Migrační katalog | **117 migrací aktuálních**, žádná čekající, `bin/migrate.php --check --json`; pouze izolovaná MariaDB. |
| Syntaxe a diff | **103 změněných/nových PHP souborů bez syntax errors**; dodatečně ověřen opravený formulář a test souběhu; `git diff --check` čistý. |

Testy proběhly ve worktree `evidencePavel-finance-b1-20261022`, PHP 8.2.12, PHPUnit 11.5.56, HTTP `127.0.0.1:18086`, MariaDB `127.0.0.1:33426`. Všechny osoby, účty a podklady jsou syntetické. Testy souběhu používají vlastní schémata `evidence_b2_notifications` a `evidence_b2_coach_scope`; obnova vytvořila nové `evidence_b2_restore_53ebc017`.

Přejímka pokrývá také odmítnuté požadavky: cizí osobu/soupisku, přímé POST, vývozy a soukromé soubory, chybějící CSRF, starý formulář, chybějící důvod/potvrzení, odebrané pracovní i rodičovské oprávnění, starý bearer odkaz, nedostupný audit a návrat databázových i souborových změn. Funkční případy obsahují dva rodiče, dvě děti, zachování legitimní soukromé rodinné role po odchodu pracovníka, plánování po explicitním svěření týmu a osobní vyřízení nedoručené zprávy.

Závěrečná kontrola odhalila a opravila dvě chyby testovaného toku: vykreslení odmítnutého plánovacího formuláře s prázdným popisem a nedostatečně inicializovanou náhradní stránku staré rozesílky. Další opakování odhalilo závod v samotném souborovém předávání signálu souběžného testu. Bariéra se nyní zveřejňuje atomicky; původní kontrola skutečného databázového čekání zůstala zachovaná. Následovalo šest úspěšných opakování a další nezávislé úspěšné spuštění hlavním agentem. Nešlo o vypnutí nebo přeskočení selhávající kontroly.

Obnova ověřuje SQL databázi a dostupnost neporušených příloh v izolovaném úložišti. **Není to obnova adresáře příloh z oddělené archivní kopie**, produkční UAT, potvrzení reálných map osob nebo schválení oddílem či účetní. Plánovače, produkční odesílání a platební integrace nebyly zapnuty. Nebyl proveden push, merge ani deploy.

Implementace, změny obsluhy a podmínky případného nasazení: [předání R07–R12](../../CLUB_FOUNDATION_R07_R12.md).
