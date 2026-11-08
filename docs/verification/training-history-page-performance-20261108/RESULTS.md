# P11-RH — parita a výkon

8. 11. 2026: finální parita a přínos PASS; všech14CI PASS, #394 sloučené jakoab49947d.
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
Historický stav prototypu: nativní/HTTP a release tehdy NOT RUN. Aktuální důkazy níže.


## Finální varianta 2a02e96f — nativní PDO a celé HTTP

[CI 42485394262](https://github.com/KovoPraha/evidenceTreninku/actions/runs/42485394262)
PASS ve všech třech úlohách. Přesný commit `2a02e96fb00101866e8d0d05fe460c57b305ab53`;
SHA256 všech tří aplikačních souborů v profilech ověřeny proti Git blobům tohoto HEAD.
Reference původních obou route souborů `cc80556131f855cb29ec75c335873574930577af`.
Nové zdroje z main #390/#393 převzaté a společné oběma fázím.

Nativní parita na MariaDB 10.3.39 i 11.4.13: **3 testy / 5006 assertions**, chyby,
failures a skipped 0. Všechny stránky rekonstruují úplnou původní množinu pro oba
seznamy a filtry, včetně samostatné i společné legacy skupiny/podskupiny,
trenérů, kategorie, hledání, prázdného výsledku a historické hierarchie/cyklu.
Striktní hodnoty a typy detailů, štítky a pořadí jmen v každém tréninku shodné.
Jediná úmyslná změna pořadí: při shodném datu explicitně id DESC místo neurčené remízy.
Celkové počty, hodiny a kategorie jsou nad úplným filtrovaným výsledkem.

### Celé čtení na 10 000 trénincích, medián ms

| Případ | MariaDB 10.3 před | Po | MariaDB 11.4 před | Po |
| --- | ---: | ---: | ---: | ---: |
| Vše | 2733.31 | 37.79 | 1849.65 | 38.75 |
| Trenér | 1403.39 | 27.84 | 974.11 | 34.27 |
| Kategorie | 990.16 | 23.24 | 643.78 | 22.33 |
| Jediný záznam | 1.25 | 1.59 | 1.27 | 1.65 |
| Prázdný | 0.70 | 0.41 | 0.52 | 0.35 |
| Historická kořenová skupina | 2501.62 | 100.75 | 1624.61 | 98.99 |

4 zahřátí +17 střídavých párů na 100/1000/10000 trénincích, šest případů,
SQL query cache OFF a čtecí transakce. Celé čtení zahrnuje štítky a účastníky;
čas není profilovaný SQL loggerem. Diagnostika samostatně: u 10k všech výsledků
**22 →4 SQL, 140000 →10350 načtených řádků**, max bindings 10000 →25.
MariaDB 11.4 špičková přidaná PHP paměť **84 612 200 →5 787 928 B**.
Jde o vrácené řádky, nikoli EXPLAIN rows examined. Úplné tenké kandidáty
pro přesné souhrny a případné historické vazby se stále načítají.
Jediný záznam má režii jednoho dalšího SQL (přibližně +0,4 ms nativně);
není tvrzen přínos každého jednotlivého malého výsledku.

[Profil 10.3](final/native-10.3.json.gz), [profil 11.4](final/native-11.4.json.gz),
[JUnit 10.3](final/tests-10.3.xml.gz), [JUnit 11.4](final/tests-11.4.xml.gz).
Pět checksumů beze změny. Předchozí úspěšná iterace je uchovaná v `iteration1/`;
odstranění nepoužitých LEFT JOIN aliasů bylo zapsané a commitnuté před variantou 2.

### Skutečné HTTP na 1002 trénincích

Stejný vlastněný plně migrovaný syntetický fixture, PHP 8.2 / MariaDB 11.4.
Původní a nové skutečné route soubory, standardní login, žádné podvržení session.
12 kladných případů, po pěti zahřátích 17 časovaných GET na případ/fázi =408 časů;
šest zamítnutých role/cesta případů v každé fázi =12 negativních GET.

| GET | Před ms | Po ms | HTML před B | Po B |
| --- | ---: | ---: | ---: | ---: |
| sprava_treninku.php-all | 330.22 | 15.44 | 4804979 | 162462 |
| sprava_treninku.php-group | 111.64 | 18.13 | 4794464 | 161451 |
| sprava_treninku.php-team | 334.21 | 20.52 | 4794465 | 161452 |
| sprava_treninku.php-trainer | 216.49 | 14.28 | 4775005 | 164589 |
| sprava_treninku.php-page2 | 331.21 | 13.52 | 4804979 | 161583 |
| sprava_vsech_treninku.php-all | 397.75 | 15.72 | 8414159 | 253205 |
| sprava_vsech_treninku.php-group | 144.09 | 18.63 | 8400918 | 256667 |
| sprava_vsech_treninku.php-team | 402.47 | 20.80 | 8400919 | 256668 |
| sprava_vsech_treninku.php-trainer | 260.04 | 14.50 | 8355033 | 257111 |
| sprava_vsech_treninku.php-page2 | 396.25 | 15.59 | 8414159 | 257221 |
| rich-rare | 51.17 | 50.29 | 56657 | 55941 |
| rich-empty | 29.86 | 29.34 | 47141 | 46425 |

[Souhrn a zdrojové SHA](final/integrated-summary.json), [HTTP před](final/history-http-before.json.gz),
[po](final/history-http-after.json.gz). Každý zobrazený řádek má přesně původní
normalizovaný otisk obsahu i akcí, statistiky jsou přesné mapy štítek/hodnota.
Úplné HTML záměrně jiné stránkováním; počet viditelných tréninků je25.
Přístup trenéra/registráře/hosta odmítnut stejně před i po. Pět datových checksumů
stejných před/po i mezi fázemi. Úplná parita všech stránek je navíc nativní test.

Skutečný Chrome 156, viewport1036×703: tři scénáře ×(3cold+10warm) ×2fáze =78
pozorování. [Před](final/browser-sports_lead-before.json.gz),
[po](final/browser-sports_lead-after.json.gz), [interakce](final/browser-interactions.json.gz):
klik Další,25 dalších různých tréninků, zachované filtry a Enter nového hledání
s resetem stránky PASS. Nejde o fyzický mobil ani přihlášené produkční UAT.
[Úklid](final/runner-cleanup.json) PASS: fixture, PHP server, bridge účet a oba route
soubory obnovené; soukromé výstupy vyloučené. V Gitu jen syntetické bezpečné JSON.
Měření nejsou SLA skutečné produkční historie; provozní release se doloží zvlášť.

Chrome warm medián bohatého přehledu: TTFB416,70→18,90ms, DOMContentLoaded1044,25→77,35ms a load1167,65→85,20ms. Jde o stejný syntetický desktop scénář se skutečnými prostředky CDN, nikoli produkční měření.

Celá regresní sada na2a02e96f:2392testů/53289assertions PASS, obě nativní smoke/profile matice, kvalita, dokumentace a CodeQL; všech14kontrol SUCCESS. [Snapshot a release](../training-history-release-20261108/RESULTS.md).
