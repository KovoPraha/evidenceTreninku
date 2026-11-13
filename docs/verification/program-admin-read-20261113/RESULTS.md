# P11-PADMIN — čtení správy programů

13. 11. 2026. ROZPRACOVÁNO. [Plán](../../club-programs.md#p11-padmin--dávkové-čtení-správy-programů-13-11-2026).
Base c5a4ca3b, runtime b2ca9f4b. WIP zachovaný.
Implementace/parita/native/HTTP/Chrome/CI/merge/deploy: NOT RUN.
Přihlášené produkční UAT: NOT RUN.


Místní strict parita: 5 testů / 75 assertions PASS, rozšířená sada kontextu,
podmínek a sdílené/placené kapacity: 27/196 PASS. [Výběrové SQLite SQL počty](sqlite-selection.json)
0:1→1, 2:43→14, 12:253→34, 100:2101→212, 401:8422→823; žádná nativní
časová interpretace. Syntaxe PHP/JS/Python, PSR-12/200/braces, composer validate
a check-current-docs PASS. PHPStan běží. Celý nativní/HTTP/Chrome pár, CI,
merge a deploy: NOT RUN. Další krok nové CI a přijetí měřeného nativního zisku.


První native checkpoint PR #477/670a88d2, běh 43472823698:
strict nativní řádky/typy/pořadí, 0/2/12/100/401 a checksumy/obnova PASS
na obou MariaDB. 100 nabídek: 10.3 643,741→87,283 ms,
11.4 894,890→108,677 ms, SQL 2228→262. 401 nabídek:
2610,006→333,034 / 3615,241→425,548 ms, SQL 8922→1023.
Peak PHP při401 3038080→3810408 B (+772328 B); úspora je v SQL/čase,
nikoli v paměti. Malý 2-row případ má více načtených metadat, ale méně
SQL a nižší naměřený čas. Jde o syntetický průběžný výsledek, ne SLA.

Celý profil nebyl přijat: samostatný HTTP fixture neměl přímý require
definice CLUB_PROGRAM_TERM_PURPOSES. Doplněna explicitní závislost fixture;
produkční reader beze změny. Vlastní schéma/účty/config/porty uklizené.
Nové celé CI/HTTP/Chrome měření nutné před merge. Místní composer analyse
ukončil časový limit300s bez dokončení; Linux PHPStan/styl běhu43472823347
PASS. Lokální 27/196 a ostatní předchozí kontroly zůstávají PASS.
