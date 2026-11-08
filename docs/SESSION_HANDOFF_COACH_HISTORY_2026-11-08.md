# Předání P11-RHC — Moje tréninky

8. 11. 2026: ROZPRACOVÁNO, plán uložený před implementací.
[Plán](COACH_HISTORY_PAGE_PERFORMANCE_2026-11-08.md),
[výsledky](verification/coach-history-page-performance-20261108/RESULTS.md).
Větev codex/coach-history-page-performance-20261108, baseab60bfdb,
vlastní medical-state worktree. Původní WIP a jeho hash zachované.

Další neomezená cesta priority4 analýzy: ajax_treninky.php/Moje tréninky.
Úzké kandidáty, kanonická hierarchie před stránkou25, plné detaily/vazby pouze
stránky, úplný počet a celá historie dál dosažitelné. Všechny filtry/práva,
CSRF i poznámkové writery zachovat. Osobní profil a další P11 samostatné.

Další krok: zmrazená reference, implementace a úplná parita všech stránek,
nativní profily a skutečné HTTP/Chrome. Před nasazením aktuální zelené CI.
Vlastník již povolil celý ověřený release; nezjišťovat stejné oprávnění znovu.
Měření/PR/merge/deploy zatím NOT RUN. Skutečná výchozí produkce9681a56a,
deploy42488777710 SUCCESS, vlastní RH již zahrnuté; staré vydání neopakovat.

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

První CI124695b0: obě nativní MariaDB parita829 a profily PASS, původní artefakty
uchované v iteration1 výsledků. HTTP před našlo chybný testový předpoklad:
registrář je již bootstrapem403, nikoli prázdný200; test opravuje očekávání,
produkční autorizace beze změny. PHPStan potvrzuje pokles staré pdo výjimky9→8,
baseline snížená, žádná nová potlačení. Rozšířené nativní fixture nyní používá
stejný EXISTS vlastnictví jako aktivní trenér a duplicitu přes hranici25/26;
lokálně2/819 PASS. Nové měření nutné, první profil se netvrdí jako finální.
HTTP matice má nyní14kladných (včetně nulového cizího výsledku),2explicitně
zamítnuté role a samostatnou čerstvou revokaci, v obou fázích přesně stejné.
