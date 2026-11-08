# P11-RHC — stránkovaná trenérská historie

8. 11. 2026: ROZPRACOVÁNO, plán před implementací.
Vlastník: vývoj podle nového zadání Marka; commit/merge/deploy po přesné paritě,
naměřeném přínosu a úspěšných kontrolách. [Předání](SESSION_HANDOFF_COACH_HISTORY_2026-11-08.md),
[výsledky](verification/coach-history-page-performance-20261108/RESULTS.md).

## Volba a výchozí stav

[Analýza, priorita4](verification/performance-analysis-20261107/RESULTS.md):
neomezené historie tréninků. Předchozí [P11-RH](TRAINING_HISTORY_PAGE_PERFORMANCE_2026-11-08.md)
řešil oba správní seznamy, nikoli ajax_treninky.php / Moje tréninky. Tato zbývající
cesta čte všechny poznámky/obrázky/měření a účastníky, trenéry, skupiny a tagy
celého výsledku. Dávkové vazby již existují; neimplementovat jejich N+1 znovu.
Zvoleno před osobním profilem (další samostatný řez), protože společná trenérská
historie navíc přenáší účastníky všech tréninků. Absolutní pořadí dle návštěvnosti
není dostupné; časový přínos tohoto řezu zatím NOT RUN.

Base `ab60bfdb827d7daee143541baaec222d99cf8ba3`, skutečná produkce9681a56a/deploy42488777710
SUCCESS, obsahuje také předchozí vlastní RH. Větev
`codex/coach-history-page-performance-20261108`, worktree
`C:/Users/Marek/Documents/Codex/evidencePavel-medical-state-performance-20261107`.
Původní checkout na codex/fio-auto-reconciliation-recovery zachovat, včetně
kis_rosters_admin.php SHA256
`7a7afd75bfa34f736f158096ab46a671b7fee6c6faedc3701f989c0cf64733e5`.
Ostatní otevřené PR ani WIP se neslučují; přijímat pouze již začleněný main.

## Postup a akceptace

1. Zmrazit původní SQL a oba skutečné route soubory na base. Stejná trenérská
   identita a coachScopeTrainingPredicate; zachovat všechny predikáty roku,
   měsíce, původní skupiny, hledání a kanonické soupisky ke dni tréninku.
2. Nejprve úzké kandidáty id/datum, historická hierarchie před výběrem stránky25.
   Přesný počet nad celým výsledkem. Plná projekce a stávajících pět dávkových
   vazeb/štítky pouze pro stránku. Zachovat i případnou původní násobnost JOIN;
   explicitní datum DESC,id DESC upřesní pouze neurčené remízy data.
3. Přidat AJAX stránkování do Moje tréninky. Nové filtry začínají na1, navigace
   zachová aktuální filtry a celkový počet; debounce/AbortController zůstávají.
   Detaily accordion a formuláře poznámek/CSRF/zápisový endpoint beze změny.
4. Nezávislé původní čtení proti sjednocení všech stránek: přesná pole/typy,
   filtry, vazby a účastníci v rámci tréninku, pořadí, prázdná/poslední stránka,
   změny dat/práv, historické hrany/cyklus, neobvyklé hodnoty roku/měsíce/hledání.
5. SQLite a nativní MariaDB10.3/11.4, stejné vlastní syntetické fixture100/1k/10k,
   query cache OFF,4zahřátí a17střídavých párů. Celé čtení včetně vazeb měřit
   neinstrumentovaně; SQL/vrácené řádky/bindings a paměť samostatně, checksumy stejné.
6. Skutečné AJAX HTTP před/po se standardním loginem a bootstrapem, celé původní
   otisky vykreslených karet proti odpovídající podmnožině nové stránky. Negativní
   role/cizí rozsah a čerstvá revokace. Chrome na skutečném Moje tréninky:
   počáteční načtení, další/předchozí stránka, filtry a prázdné hledání, zachované
   accordion a otevření/zrušení poznámky. Měřit AJAX i čas vykreslení seznamu;
   samotný dokument load není dokončený AJAX. Žádný produkční zápis poznámek.
7. Bezpečné výsledky do repa; aktuální CI, merge přesného ověřeného stromu,
   standardní chráněný deploy import/UAT=false, záloha/migrace/VS/aktivace/smoke,
   anonymní čtecí produkční kontrola před/po. Přihlášené produkční UAT odděleně.

Bez nového DDL/indexu, cache oprávnění, změny session lifetime, assetů nebo writerů.
YEAR/MONTH sémantiku včetně měsíce bez roku zachovat; indexový řez z analýzy5
samostatně. Osobní profil, jeho měření/testy a další P11 nejsou tímto hotové.

Další krok: implementace a lokální parita; měření, PR, merge a deploy NOT RUN.

První implementace lokálně2testy/829assertions PASS, včetně původní násobnosti JOIN,
cizího trenéra, všech filtrů a celých vazeb/účastníků přes všechny stránky.
SQLite prototyp10k:930→35ms,26→8SQL,220000→10550řádků; malé výsledky neutrální
či s malou režií. Zdroj po prvním profilování jen PSR-12/zalomení atributů;
finální native/HTTP měření musí ověřit aktuální hash. PDO emulace odpovídá db.php
(default MySQL true, původní opakovaný :q zachovaný), ne předstírané serverové prepares.
Hotový vlastněný HTTP/Chrome runner:15kladných scénářů včetně prázdných omezených
rolí +anonym403, čerstvá revokace trenéra, přesné otisky karet/ID/pořadí/celkového
počtu. Chrome měří fetch→DOM a dvě renderovací smyčky skutečného AJAX,3cold+10warm
pro default/vše/prázdné; další/předchozí, filtry, hledání a otevření/zrušení poznámky.
Bezpečné syntetické screenshoty desktop/390px; žádné produkční zápisy.
Další krok: push/PR a aktuální nativní i celé HTTP/Chrome CI; release zatím NOT RUN.

Před prvním CI převzatý main069c5bc5/#396. Doložené přihlášené Q2 UAT PASS a jeho meze zachované; pracovní E22 za přijatým E21, původní plánovací commit zachovaný. Oba původní aplikační route soubory baselineab60bfdb se tím nemění.
