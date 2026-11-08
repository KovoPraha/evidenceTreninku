# P11-RHC — parita a přínos

8. 11. 2026: finální parita a přínos PASS; #400 sloučené, vydání42494231010 SUCCESS.
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


## Finální ověření b4022c2a

[CI42493362405](https://github.com/KovoPraha/evidenceTreninku/actions/runs/42493362405)
všechny tři úlohy SUCCESS. HEAD `b4022c2a3b306d98ff0afdbba64f5ec342bb1e66`,
reference obou původních skutečných route souborů `ab60bfdb827d7daee143541baaec222d99cf8ba3`.
Společné ostatní zdroje stejné v obou fázích. Převzaté již sloučené #396/#395;
oba referenční route soubory jsou identické i se skutečnou předchozí produkcí1c828fbf.
SHA256 čtyř měřených aplikačních souborů ověřené proti Git blobům HEAD i merge.

MariaDB10.3.39 a11.4.13: každá **2 testy /819 assertions**, errors/failures/skipped0.
Všechny stránky rekonstruují přesné hodnoty a typy původních řádků, násobnost
vazeb včetně opakovaného ID na hranici25/26, související skupiny/podskupiny,
trenéry/účastníky/tagy/štítky a úplný počet. Rok, měsíc bez roku, kombinace,
LIKE včetně wildcard, cizí trenér, změna poznámky/rozsahu, historické hrany/cyklus
PASS. Jediná upřesněná neurčená remíza: datum DESC,id DESC.
PDO MySQL emulované prepares odpovídají defaultu db.php; opakovaný :q zachovaný.
Testuje se skutečný databázový server, nikoli tvrzení o serverových prepares.

### Celé čtení na 10 000 trénincích — medián ms

| Případ | MariaDB10.3 před | Po | MariaDB11.4 před | Po |
| --- | ---: | ---: | ---: | ---: |
| Vše | 1055.89 | 43.43 | 564.60 | 23.65 |
| Původní skupina | 1089.01 | 70.76 | 578.72 | 38.04 |
| Měsíc bez roku | 396.09 | 32.30 | 199.86 | 17.36 |
| Jediný výsledek | 1.79 | 2.06 | 0.81 | 0.94 |
| Prázdný | 23.82 | 10.99 | 2.52 | 2.29 |
| Historická kořenová soupiska | 638.34 | 114.17 | 377.10 | 64.02 |

PHP8.2, stejné vlastní fixture100/1000/10000, šest případů,4zahřátí +17střídavých
párů. Query cache OFF, čtecí režim. Čas celého čtení včetně kanonických štítků a
všech pěti původních vazeb je neinstrumentovaný; diagnostika SQL/řádků zvlášť.
Nativní čtení používá stejný EXISTS vlastnictví aktivního trenéra; samotný
bootstrap/session se měří následným skutečným HTTP, není v tomto CLI intervalu.
Lookup mapy se porovnávají podle klíče mimo časový a paměťový interval,
pořadí všech hodnot uvnitř jednoho tréninku zůstává striktní.

10k vše: **26→8 SQL,220000→10550 vrácených řádků**, nejvíce bindings10000→26.
Dodatečná špička používané PHP paměti106560584→4917968B; nejde o celý RSS.
Tenké kandidáty a případná historická hierarchie se stále čtou pro úplný výsledek.
Nejde o EXPLAIN rows examined. Jediný výsledek má malou režii dalšího dotazu;
přínos každého malého výsledku se netvrdí. Osm doménových checksumů stejných.
[Profil10.3](final/10.3-coach-history-comparison.json.gz),
[profil11.4](final/11.4-coach-history-comparison.json.gz),
[JUnit10.3](final/10.3-coach-history-tests.xml.gz),
[JUnit11.4](final/11.4-coach-history-tests.xml.gz).

### Skutečný AJAX HTTP na 1001 dostupných trénincích

Plně migrovaný vlastněný fixture na MariaDB11.4, PHP8.2, stejné databáze a data
před/po. Standardní login, skutečné db.php/session/autorizace/HTML, žádné síťové
náhrady. Čtrnáct kladných případů po5zahřátích a17časech v každé fázi =476časů.
Registrář/anonym 403 v obou fázích (4GET), čerstvá revokace stejné přihlášené
session v každé fázi (2GET) shodně odmítnutá. Cizí ID přes podvržený parametr
trenéra nezpřístupněné, druhý oprávněný trenér svůj záznam vidí.

| Případ | Před ms | Po ms | HTML před B | Po B |
| --- | ---: | ---: | ---: | ---: |
| all | 148.14 | 10.27 | 12155924 | 307864 |
| group | 149.09 | 13.09 | 12143088 | 307171 |
| team | 155.35 | 14.96 | 12143088 | 307171 |
| year | 50.88 | 9.52 | 3789296 | 307866 |
| month_without_year | 13.92 | 8.58 | 826461 | 307879 |
| year_month | 6.60 | 6.55 | 97876 | 99102 |
| search | 53.09 | 43.50 | 1360074 | 307209 |
| wildcard | 158.14 | 11.42 | 12155924 | 307865 |
| rare | 44.78 | 45.01 | 12194 | 12510 |
| empty | 22.79 | 22.45 | 77 | 263 |
| page2 | 156.12 | 10.09 | 12155924 | 307318 |
| last | 149.68 | 6.79 | 12155924 | 12660 |
| foreign | 18.38 | 18.09 | 77 | 263 |
| other_coach | 20.08 | 20.47 | 6400 | 6716 |

[Souhrn a sourcehashy](final/integrated-summary.json), [HTTP před](final/history-http-before.json.gz),
[po](final/history-http-after.json.gz). Každá nová karta má přesný původní normalizovaný
otisk obsahu a akcí, pořadí a ID odpovídají přesné stránce, celkový počet úplné
původní množině. Kontrola běží u každého kladného GET mimo interval curl.
Pouze otočené csrf_token hodnoty nahrazené markerem; přítomnost, neprázdnost,
název a akce formulářů zachované. Žádná změna autorizačního ani poznámkového writeru.
Osm checksumů stejných před/po i mezi fázemi.

### Skutečný Chrome a rozhraní

Chrome156, viewport1036×703, default/vše/prázdné,3cold+10warm pro každý případ/fázi
=78pozorování. Standardní vlastní browser login. Výsledky od skutečného fetch startu
k vložení DOM a dvěma animation frames; jde o render-ready proxy, nikoli LCP nebo
tvrzení o fyzickém prvním paintu seznamu. Networkidle čekání se do metriky nepřičítá.
Dokumentový load a TTFB se ukládají odděleně a nejsou dokončeným AJAX seznamem.

| Chrome warm medián | Před fetch→DOM ms | Po | Před fetch→2frames ms | Po |
| --- | ---: | ---: | ---: | ---: |
| default | 20.80 | 20.20 | 43.00 | 43.85 |
| all | 4531.40 | 25.95 | 4636.15 | 44.35 |
| empty | 7.85 | 8.55 | 29.20 | 32.25 |

[Před](final/browser-coach-before.json.gz), [po](final/browser-coach-after.json.gz),
[interakce](final/browser-interactions.json.gz): další/předchozí vrátí stejné ID,
25dalších různých tréninků, zachované filtry/počet, hledání resetuje stránku,
accordion a otevření/zrušení poznámky PASS. Žádný produkční POST.
390px stránkování funkční a v šířce viewportu. Vizuálně zkontrolované bezpečné
syntetické screenshoty: [před desktop](final/coach-history-before-desktop.png),
[po desktop](final/coach-history-after-desktop.png), [po390px](final/coach-history-after-mobile.png).
Nejde o fyzický mobil, zátěž/souběh ani přihlášené produkční UAT.

[Cleanup](final/runner-cleanup.json) PASS: owned fixture, server, bridge účet,
oba route soubory obnovené; soukromé logy/cookies/credentials vyloučené.
V Gitu pouze bezpečná syntetická data a agregáty. Nejde o produkční SLA.

Celá hlavní sada2400testů/54217assertions a všech14CI na aktuálním b4022c2a SUCCESS.
#400 sloučené jako da152282ad4274690595c188a78dec0c90422f38; celý tested/merged
strom a sourcehashy stejné. [Produkční protokol](../coach-history-release-20261108/RESULTS.md)
dokládá nasazení42494231010 SUCCESS; původní katalog222→223 není shodný, nový čtecí průchod5/5stabilní. Přihlášené produkční UAT samostatné.
