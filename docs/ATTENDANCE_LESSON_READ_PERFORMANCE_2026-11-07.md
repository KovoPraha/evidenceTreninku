# P11-R06 — dávkové čtení nabídky lekcí, 7. 11. 2026

ID P11-R06-LESSONS; vlastník zadání Marek, realizace Codex. HOTOVO v rozsahu dávkového čtení lekcí.
Vlastník zadal commitnout a nasadit vhodné ověřené změny a pokračovat dalšími
optimalizacemi. Nejprve se samostatně dokončuje katalog #364; tato etapa je
oddělená implementace s paritou a měřením před/po, bez změny writeru docházky.

## Důkaz, varianta a přijetí

[HTTP analýza](verification/full-http-performance-20261107/RESULTS.md) zachytila
203 SQL při širokém výběru 50 lekcí. `trainingDayChoices` u každé položky znovu
ověřuje aktuální pracovní pozici a existenci plánu. Preferenci vlastních plánů,
SQL kolaci, doslovné LIKE hledání, datum, limit 50 i výsledné řádky zachovat.

Zvolený postup: ponechat dosavadní výběr kandidátů, existenci plánů ověřit
jednou dávkou a aktuální pracovní oprávnění jednou před vrácením výsledku.
Žádná cache oprávnění mezi voláními. V transakci zůstane původní smyčka včetně
pořadí SQL a zamykání. `trainingDayPlan`, potvrzení a zápis docházky beze změn.
Alternativa výběru SQL LIMIT až po změně oprávnění nebo dlouhodobá cache by
měnila sémantiku; zde není potřeba. Výběr 10k osob je další samostatná etapa,
vyžaduje UI a nativní browser, není směšovaný s touto čtecí optimalizací.

1. Zmrazit původní helper jako nezávislou referenci.
2. Implementovat dávkový operační reader, transakční fallback.
3. Striktně porovnat celé řádky/pořadí a výjimky: coach/sports_lead/superadmin,
   jiný/neaktivní účet, odebrané role, chybějící plány, vlastní i zastupované
   lekce, datum, Unicode/mezery/wildcards hledání a prázdný výsledek.
4. Stejný syntetický fixture, 5 zahřátí a 30 střídavých párů; samostatně
   SQL/řádky/EXPLAIN, čas a PHP špička. SQLite místně, MariaDB 10.3/11.4 v CI.
   Rozpočet běžných 50 nabídek: nejvýše 6 SQL pro běžnou pracovní pozici.
5. Přiměřená regrese a úplné CI; výsledky, commit, PR a případné nasazení
   evidovat odděleně. Žádné produkční osoby, role, lekce ani účast neměnit.

## Pracovní kopie a předání

Větev `codex/attendance-lesson-read-performance-20261107`, základ `22e1b2cb`.
Worktree `C:/Users/Marek/Documents/Codex/evidencePavel-attendance-lesson-read-performance-20261107`.
Původní WIP zachovaný, zdroje/flags ani data původní kopie se nemění.
[Protokol](verification/attendance-lesson-read-performance-20261107/RESULTS.md),
[předání](SESSION_HANDOFF_ATTENDANCE_LESSON_READ_PERFORMANCE_2026-11-07.md),
[registr P11](WORK_PLAN_CURRENT.md). Dokončení tohoto řezu je doložené níže.

## Výsledek a uzavření

- Striktní celé řádky/pořadí/výjimky a původní transakční cesta PASS;
  cíleně 19 testů / 993 assertions, po integraci katalogu 25 / 2801.
- MariaDB 10.3/11.4: 720 střídavých párů, šest otisků zdrojů shodných.
  Pro 50 nabídek trenéra 153 → 6 SQL, medián 41,47 → 1,77 ms (10.3)
  a 19,99 → 0,97 ms (11.4). Jde o helper na syntetických datech, ne celé HTTP.
- Integrovaný commit `f5bd650f`: Testy 42372966108, kvalita 42372966069,
  dokumentace 42372966050 a CodeQL 42372964140 PASS. Linux 2355 / 46183.
- [PR #367](https://github.com/KovoPraha/evidenceTreninku/pull/367) sloučený;
  provozní provedení a jeho omezení dokládá [release protokol](verification/attendance-lesson-release-20261107/RESULTS.md).
- Původní WIP v `C:/xampp/htdocs/evidencePavel` zachovaný; hash
  `kis_rosters_admin.php` je stále `7a7afd75bfa34f736f158096ab46a671b7fee6c6faedc3701f989c0cf64733e5`.

Další P11: samostatně navrhnout omezený výběr osob místo 10221 voleb;
zachovat výběr, rozsahy, zastupování a přístupnost a změřit celé HTTP/browser.
Tento větší UI řez se nyní neimplementoval. Celé K0.2 a lidská přejímka otevřené.
Výsledkové předání: větev `codex/performance-release-handoff-20261107`,
worktree `C:/Users/Marek/Documents/Codex/evidencePavel-performance-release-handoff-20261107`.
