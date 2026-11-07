# P11-R06 — dávkové čtení nabídky lekcí, 7. 11. 2026

ID P11-R06-LESSONS; vlastník zadání Marek, realizace Codex. ROZPRACOVÁNO.
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
[registr P11](WORK_PLAN_CURRENT.md). Další krok: reference, reader a lokální parita.

Implementace hotová k nativnímu ověření: 19 testů / 993 assertions PASS;
50 nabídek 153 → 6 SQL pro trenéra, 254 → 9 pro superadmina. Celé řádky,
výjimky, čerstvost i transakční SQL mají paritu. Nativní CI je další krok.

Nativní parita a 720 párů na 10.3/11.4 PASS, 6 otisků zdrojů shodných.
50 nabídek trenéra: 41,47/19,99 → 1,77/0,97 ms, 153 → 6 SQL.
[PR #367](https://github.com/KovoPraha/evidenceTreninku/pull/367); celkové CI dobíhá.

První runtime `b20715ec` má všech 11 CI kontrol PASS včetně 10.3/11.4.
Doplněný lokální okrajový test pro NULL/neaktivního/chybějícího vlastníka
a legacy ID 0 prošel; transakční kontrola a writer zůstávají původní.
