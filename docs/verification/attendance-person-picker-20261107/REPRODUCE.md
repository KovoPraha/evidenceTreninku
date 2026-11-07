> HISTORICKÝ PŘEDBĚŽNÝ PÁR: dvě oddělené formy měly mezeru přenosu sousedního draftu.
> [Finální společný formulář a nové celé měření](final/RESULTS.md) jsou aktuální důkaz.
> Zdejší surové artefakty zůstávají beze změny; níže je původní dobový výsledek.

# Opakování P11 R06 výběru osob

Pouze nový vlastní checkout bez configu a nový localhost MariaDB runtime.
Žádný produkční dump, transport, cizí datadir ani existující rozpracovaná kopie.
Základní inicializaci a standardní přihlášení popisuje
[původní HTTP postup](../full-http-performance-20261107/REPRODUCE.md).
Nové nástroje přebírají jeho kontroly schématu, datadiru, portu a hash configu.

1. Composer dle locku, nový datadir se `mysql_install_db`, vlastní server na
   127.0.0.1, volné porty. Uložit vlastní `runtime.json`, sessions/metrics.
   Env `EVIDENCE_PERFORMANCE_CONFIRM=SYNTHETIC-ONLY`,
   `EVIDENCE_MARIADB_SMOKE_PORT`, `EVIDENCE_PERFORMANCE_DATADIR`.
2. Na base `d958fd6e` spustit `FullHttpReadFixture.php --setup OUTPUT`.
   Standardní HTTP login `FullHttpReadProfile.php --login OUTPUT`.
   Prostý PHP development server se stejnými sessions a bez prepend/general logu.
3. `AttendancePickerHttpProfile.php OUTPUT before`: 21 případů, pozitivní
   5 warmups + 30 měření. `FullHttpReadVerify.php OUTPUT`, uložit DOM před.
   Matice se automaticky omezuje na R06 ze stejného aktuálního HTTP harnessu.
4. Standardním formulářem v Playwright CLI přihlásit tři účty samostatně.
   `FullHttpBrowserProfile.js` s bezpečným configem role/base_url/jednoho případu:
   coach `closure-coach-training`, sports_lead `closure-lead-training`,
   other_coach `closure-other-own`. 3 cold / 10 warm po prime, stejné skutečné
   okno a DPR, zdroje měřidel jsou uložené jako ROLE-VARIANT-measure.js.txt.
5. Zastavit vlastní prostý HTTP proces, stejný root/port spustit s
   `FullHttpReadInstrument.php`, env `P11_HTTP_METRICS=OWN_RUNTIME/metrics`.
   Zkopírovat aktuální variantu HTTP reportu do `http-timings.json` a spustit
   `FullHttpReadDiagnostics.php OUTPUT`; uložit `diagnostics-before.json`.
6. Vrátit prostý server, zapojit pouze nový picker do stejné aplikace/databáze.
   Doménové checksums musí zůstat stejné; hesla/data/role se nepřeseedují.
   `AttendancePickerHttpProfile.php OUTPUT after`, DOM po a stejný Chrome profil.
   `--verify-existing` je jen ověření kompletního stejného měřeného výstupu podle
   nových GET/hashů, neslouží k přenášení vzorků mezi změněnými variantami.
7. Zopakovat oddělenou diagnostiku jako `diagnostics-after.json`. Celé HTML
   každé varianty musí být shodné s jejími prostými GET a oba páry checksums
   shodné, SQL zápisy žádné. Mezi variantami se HTML mění záměrně.
8. Na prostém serveru browser UAT JS/noJS (funkční šablona s credential markerem),
   pak `AttendancePickerHttpVerify.php OUTPUT`, `FullHttpReadRevocation.php OUTPUT`
   a `AttendancePickerFixtureCoverage.php OUTPUT`. Zápisové UAT až po všech
   měřeních: revize/host a skutečné tlačítko osobní omluvy. Šablony jsou datované;
   pro nový termín upravit datum podle právě vytvořeného fixture.
9. `AttendancePickerReadProfile.php` se spouští odděleně na vlastním serveru:
   nový náhodný test se 1200 osobami, read-only srovnání obou rolí, kolace,
   zástupné znaky, čerstvá role, nakonec drop pouze vlastního testovacího schématu.
   Stejný nástroj je v CI MariaDB 10.3/11.4.
10. Zavřít vlastní browsery, `FullHttpReadCleanup.php OUTPUT`, zastavit přesně
    vlastní HTTP/MariaDB. Ověřit TCP sockety a doplnit cleanup.json. Žádné plošné
    mazání. Důkazy archivuje `AttendancePickerReport.py INPUT PROOF`; vyžaduje
    dokončený úklid a přesně shodné otisky naměřených aplikačních souborů.

Příkazy PHP jsou v `tests/Support/`. Podklady obsahují bezpečné komprimované
JSON, JUnit, snímky a zdroje měřidel, nikdy přihlašovací hesla nebo cookies.
Pro produkční server, fyzický mobil či souběh je potřeba samostatný návrh
a ověření; tento postup žádnou takovou produkční akci neprovádí.
