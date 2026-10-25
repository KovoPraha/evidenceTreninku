**Aktualizace:** tento dokument zaznamenává izolovanou přejímku před vydáním.
Opravy byly následně nasazené; aktuální produkční důkazy jsou v
[protokolu společného vydání](../release-acceptance-20261025/RESULTS.md).
# Dokončení izolované přejímky — 25. 10. 2026

**Dokončena technická přejímka vymezených návazností trenér–registrář–rodina. Nic nebylo nasazeno ani zapisováno do produkce.** Tento protokol nahrazuje otevřené místní technické blokace v `PREJIMKA.md`; původní produkční pozorování zůstávají historickými pozorováními, nikoli nově ověřenými fakty.

## Prostředí a obnova MariaDB

- Pracovní kopie: `C:\xampp\htdocs\evidencePavel-browser-acceptance-20261025`, větev `codex/browser-acceptance-20261025`, základ `f7dfd509e32b81c40158bc7837675113d2cef923`.
- Web `http://127.0.0.1:18096`, MariaDB `127.0.0.1:33436/evidence_b1`. Pouze syntetické osoby a účty LOCALHOST / NEPLATIT. Samostatné sessions, soukromé úložiště a outbox mimo webroot.
- Databáze patří výhradně této kopii; před zastavením se ověřoval vlastník runtime, příkaz procesu, datový adresář a loopback listener. Jiné databáze a původní rozpracovaný `kis_rosters_admin.php` nebyly měněny.
- Původní inicializace s native AIO uvázla. Při kontrole byl navíc nalezen osiřelý `aa_treneri` (ERROR 1932). Původní soubory se zachovaly v `%LOCALAPPDATA%\EvidencePavelB1\browser-acceptance-20261025\mysql-interrupted-20261025`; založilo se nové syntetické schéma, ne kopie ostrých dat.
- Vypnutí native AIO umožnilo první běh. Následná kontrola restartu odhalila další skutečný pád MariaDB při nebufferovaném čtení (Windows událost InnoDB: partial read, OS error 203/403). Místní spouštěč nyní používá `--skip-innodb-use-native-aio --innodb-flush-method=normal`; server hlásí `innodb_use_native_aio=0`, `innodb_flush_method=fsync`.
- Po této úpravě proběhlo spuštění i další řízené vypnutí/spuštění úspěšně. Všech 327 povinných přítomných tabulek je čitelných, nejsou čekající migrace a původní testovací zápisy z browseru se zachovaly.
- Reprodukce: spustit místní `output/browser-acceptance-20261025/setup-finance-b1-sandbox.ps1` v uvedené pracovní kopii. Adaptované CLI přejímkové nástroje jsou v soukromém runtime `acceptance-tools`, mimo webroot. Původní obecný spouštěč jiných prostředí se neměnil.
- Fio, SumUp, Stripe, Velocota integrace i automatické potvrzování jsou vypnuté. HTTP PHP blokuje odchozí mail, socketové/cURL a procesové funkce. Kontrola nezjistila žádnou notifikaci označenou jako doručenou.

## Skutečné průchody prohlížečem

Role se ověřovaly přihlášením samostatných omezených syntetických účtů. Správce byl použit pro zápis výsledků do přejímkového přehledu, nikoli jako náhrada testu oprávnění.

| Oblast | Provedený scénář | Výsledek |
|---|---|---|
| Trenér | Rozcestník zobrazí starší nevyplněný plán a odkaz otevře správný týden s filtrem Moje | PASS; plán starší než 14 dní se neztrácí |
| Trenér | Zápis skutečné docházky, uzavření 60 minut, odůvodněná oprava na 75 minut | PASS; původní revize zachována |
| Trenér | Nový plán se dvěma očekávanými účastníky, skutečný zápis jen jednoho, uzavření 60 minut | PASS; očekávaná účast se nevydává za skutečnou |
| Registrář | Kontrola shod, vytvoření jedné osoby, přijetí, archivace a návrat | PASS; právě jedna osoba a tři rozhodnutí v historii |
| Registrář | Přidání osoby do soupisky a historické odebrání | PASS; záznam `removed` a interval platnosti zůstaly zachovány |
| Registrář | Hromadná interní poznámka pro dvě vybrané osoby přes náhled a potvrzení | PASS; právě jedna poznámka a auditní událost u každé vybrané osoby |
| Registrář | Karta osoby: poznámka a auditní historie po hromadné akci | PASS; ověřeno v UI i čtením databáze |
| Rodič A | Dva vlastní profily, samostatné potvrzení účasti A a omluvení B | PASS; nezávislé odpovědi, počet čekajících odpovědí klesl na nulu |
| Rodič A | Návaznost skutečné docházky trenéra na rodinný přehled | PASS; A má 2 tréninky / 135 minut, B má 0 minut |
| Rodič A | Omluvení lekce bez placeného programu | PASS; nevznikl neoprávněný kredit na náhradu, UI vysvětluje výsledek |
| Rodič B | Zobrazení pouze vlastního dítěte C, přímý pokus otevřít náhrady cizího dítěte A | PASS; přístup k cizímu profilu odmítnut |
| Sportovec | Vlastní sportovní účet a pokus přejít do rodičovské správy osob | PASS; pouze vlastní profil, rodičovská agenda vyžaduje nové odpovídající přihlášení |

V místním `testovaci_scenare.php` jsou uloženy výsledky **A01, A02, A07 a B26 PASS**. Ostatních 36 scénářů tím nebylo automaticky přijato. PASS je technický výsledek přejímky, nikoli potvrzení vlastníka nebo povolení nasazení.

## Opravy aplikace

1. **Starší nezaevidované tréninky:** zrušeno skryté omezení rozcestníku na posledních 14 dní. Počet zahrnuje všechny minulé nevyplněné plány aktuálního trenéra; odkaz vede na nejstarší z nich. Přidán regresní test vyloučení cizích, budoucích, zrušených a již evidovaných plánů.
2. **Rodinný přehled:** při skoku na profil nebo sekci už na desktopu nepřekrývá nadpis přichycená navigace. Ověřeno screenshotem před/po (`family-actual-attendance.png`, `family-anchor-fixed.png`).
3. **Výsledky přejímky:** počet uložených PASS se již neoznačuje zavádějícím „Vlastníkem potvrzeno“. Nové označení výslovně odděluje test od souhlasu s nasazením.
4. **Odkaz B26:** přímý GET na `sportovci_hromadne.php` končil chybou chybějícího požadavku. Odkaz nyní vede na seznam osob a postup vysvětluje zaškrtnutí a otevření náhledu. Je nutná odpovídající pracovní pozice; ochrana pracovních kontextů zůstává zachována.

## Automatické ověření

| Běh | Výsledek | Důkaz |
|---|---:|---|
| Hlavní sada rolí, docházky, rodiny a přejímky | 92 testů / 2 309 assertions PASS | `final-phpunit.log` |
| Doplnění soupisek a opravené přejímkové navigace | 19 testů / 173 assertions PASS | `final-registrar-tests.log` |
| DailyCoachRegistrar HTTP | 32 kontrol PASS | `DailyCoachRegistrar-repaired.log` |
| PersonLifeDocuments HTTP | 30 kontrol PASS | `PersonLifeDocuments-repaired.log` |
| TrainingClosure HTTP | 25 kontrol PASS | `TrainingClosure-repaired.log` |
| MakeupJourney HTTP | 23 kontrol PASS | `MakeupJourney-repaired.log` |
| MemberAdmission HTTP | 30 kontrol PASS | `MemberAdmission-repaired.log` |
| MedicalClearance HTTP | 22 kontrol PASS | `MedicalClearance-repaired.log` |
| SportsActivity HTTP | 23 kontrol PASS | `SportsActivity-repaired.log` |
| Konečné invarianty browserových dat a konfigurace | 13 kontrol PASS | `browser-results-final.json` |
| Migrace, tabulky a izolace po restartu | PASS, 327 tabulek, žádné čekající migrace | `readiness-after-restart.json`, `migrations-after-restart.json`, `restart-final.log` |

Celkem **111 PHPUnit testů / 2 482 assertions**, **185 HTTP kontrol** a **13 závěrečných čtecích kontrol**. PHP lint všech změněných PHP souborů a `git diff --check` prošly. HTTP sady běžely proti skutečné místní MariaDB; nejde pouze o SQLite jednotkové testy. Zahrnují také odmítnutí cizích profilů/rolí, CSRF, zastaralých revizí, ztráty historických uzávěrek a ochranu uzavřených odměnových období.

Při přípravě byly CLI kopie nástrojů nejprve pod webrootem a test inventury cest je správně označil za nezařazené. Byly přesunuty mimo webroot; následný běh prošel bez oslabení kontroly. Diagnostický soubor `browser-invariants.tsv` s chybně interpretovaným Unicode dotazem byl odstraněn; autoritou jsou JSON výstupy PDO s UTF-8. Log prvního neúspěšného restartu zůstává jako diagnostika, konečný stav dokládá `restart-final.log`.

## UX a zbývající hranice

- Pro další UX balík doporučuji vyhledávání osob umístit před zakládací formulář, zpřehlednit prázdný výběr soupisek a doplnit v přejímkovém přehledu jasné pokyny pro změnu pracovní pozice. Přehled přejímky patří správci, odkazované agendy jiným pozicím; otevření odkazu bez změny kontextu je správně blokováno, ale cesta je pro testujícího nepohodlná.
- Výsledky platí pro tuto izolovanou kopii na Windows / MariaDB 10.4.32. V tomto běhu nebyla opakována celá Linux matice ani vizuální přejímka skutečného mobilního viewportu.
- Nejde o převod skutečných dat, schválení vazeb rodičů na produkci, celkovou přejímku všech 40 scénářů, nový pilot ani souhlas s nasazením.
- Syntetická data jsou ponechána jen v izolovaném prostředí pro opakovanou prohlídku. Soukromé přístupové údaje nejsou součástí tohoto protokolu. Produkční testovací zápisy se v tomto pokračování neprováděly, proto nevznikl produkční úklid.

Obrazové důkazy: `coach-backlog.png`, `family-after-rsvp.png`, `family-anchor-fixed.png`, `registrar-history-final.png`.
