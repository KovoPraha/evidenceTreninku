# P11-RHC — parita a přínos

8. 11. 2026: NOT RUN, plán před implementací.
[Plán](../../COACH_HISTORY_PAGE_PERFORMANCE_2026-11-08.md).
Reference obou původních route souborů: ab60bfdb827d7daee143541baaec222d99cf8ba3.

## Lokální prototyp

2testy/829assertions PASS, PSR-12/styl7souborů/0chyb. Samostatný
[SQLite profil](sqlite-prototype.json.gz): stejné vlastní datové sady100/1k/10k,
4zahřátí +17střídavých párů/6případů, query_only=ON,8checksumů shodných.
Celé čtení včetně původních5vazeb a kanonických štítků. 10k vše930→35ms,
26→8SQL,220000→10550vrácených řádků; skupina1170→53ms,
historický kořen597→87ms, měsíc bez roku289→22ms.
Jediný výsledek0,16→0,19ms a prázdný1,78→1,83ms; přínos všech malých případů
se netvrdí. Profil před posledním čistě stylistickým zalomením atributů.
MariaDB/HTTP/Chrome a aktuální sourcehashy ještě NOT RUN; nejde o produkční SLA.

První CI124695b0: obě nativní MariaDB parita829 a profily PASS, původní artefakty
uchované v iteration1 výsledků. HTTP před našlo chybný testový předpoklad:
registrář je již bootstrapem403, nikoli prázdný200; test opravuje očekávání,
produkční autorizace beze změny. PHPStan potvrzuje pokles staré pdo výjimky9→8,
baseline snížená, žádná nová potlačení. Rozšířené nativní fixture nyní používá
stejný EXISTS vlastnictví jako aktivní trenér a duplicitu přes hranici25/26;
lokálně2/819 PASS. Nové měření nutné, první profil se netvrdí jako finální.
HTTP matice má nyní14kladných (včetně nulového cizího výsledku),2explicitně
zamítnuté role a samostatnou čerstvou revokaci, v obou fázích přesně stejné.

PR [#400](https://github.com/KovoPraha/evidenceTreninku/pull/400), další CI6ed85c8a:
obě nativní MariaDB2/819 a profily PASS, PHPStan/style PASS. Skutečné původní
HTTP i Chrome prošly, v nové HTTP fázi byl jiný otisk karty. Porovnání zatím
nenormalizovalo hidden csrf_token formuláře Vytvořit Story; explicitní revokace
na konci fáze otáčí session a token. Doplněný pouze marker hodnoty tokenu,
stejný název/přítomnost/neprázdnost pole a akce se dál kontrolují, všechny
ostatní části karty zůstávají striktní. Diagnostika případné další neshody
vrátí jen jména/otisky syntetických sekcí, nikoli HTML či session.
Nový celý HTTP/Chrome běh nutný před release, spolu s převzatým main #395.
