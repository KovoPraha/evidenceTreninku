# P11-RH — parita a výkon

8.11.2026: NOT RUN, plán před implementací.
[Plán](../../TRAINING_HISTORY_PAGE_PERFORMANCE_2026-11-08.md).

## První lokální měření / SQLite

3 testy / 922 assertions PASS před i po PSR-12 úpravě, styl 7 souborů /0 chyb.
[Bezpečný profil](sqlite-prototype.json.gz) je před stylistickou úpravou zdrojů;
logika stejná, finální zdrojové SHA ověří následný nativní profil. PHP8.2.12/Windows,
SQLite memory,4zahřátí a17střídavých párů na každém ze6případů/3objemů100/1k/10k.
Čas celého čtení včetně štítků a účastníků, diagnostika zvlášť, query_only=ON,
pět datových checksumů před/po stejných. Stránka25 zachovává přesnou podmnožinu
původních řádků/štítků/účastníků; všechny stránky a celé souhrny prověřené v testech.

| 10k tréninků | Před ms | Po ms | SQL před/po | Načtené řádky před/po |
| --- | ---: | ---: | --- | --- |
| Vše | 797.92 | 83.34 | 22 /4 | 140000 /10350 |
| Trenér | 1578.67 | 161.47 | 22 /4 | 140000 /10350 |
| Kategorie | 395.88 | 75.12 | 12 /4 | 70000 /5350 |
| Historická kořenová skupina | 411.84 | 114.31 | 34 /26 | 85004 /20354 |
| Jediný záznam | 0.15 | 0.23 | 3 /4 | 14 /15 |
| Prázdný | 0.06 | 0.04 | 1 /1 | 0 /0 |

Tenké kandidáty a historické vazby se stále čtou pro celkový výsledek; plná data
pouze25tréninků, nejvýše300účastnických řádků tohoto fixture. Režie dalšího dotazu
u jediného záznamu otevřeně měřená. Nejde o produkční latenci ani HTTP/browser důkaz.
Nativní10.3/11.4 a skutečné HTTP zatím NOT RUN, release zatím NOT RUN.
