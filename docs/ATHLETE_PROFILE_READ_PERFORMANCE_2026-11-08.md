# P11-RHA — historie a měření profilu sportovce

8. 11. 2026: ROZPRACOVÁNO, plán před implementací.
Vlastník: vývoj podle nového zadání Marka; commit/merge/deploy po paritě,
naměřeném přínosu a úspěšných kontrolách. [Předání](SESSION_HANDOFF_ATHLETE_PROFILE_2026-11-08.md),
[výsledky](verification/athlete-profile-read-performance-20261108/RESULTS.md).

## Výchozí stav a rozsah

[Analýza4](verification/performance-analysis-20261107/RESULTS.md), navazuje na
[RHC](COACH_HISTORY_PAGE_PERFORMANCE_2026-11-08.md), které osobní profil neřešilo.
sportovec_treninky.php nyní čte a vykresluje všechna měření i pro skrytou záložku;
ajax_sportovec_treninky.php čte všechny poznámky a plné tréninky/měření za rok/vše.
Roky a celkový počet navíc násobí vazby účasti a měření jiných osob před DISTINCT.

Base69b69fa46349b0778980f3cbf3116be91b77cea6, skutečná produkceda152282,
deploy42494231010 SUCCESS. Větev codex/athlete-profile-history-performance-20261108,
worktree C:/Users/Marek/Documents/Codex/evidencePavel-medical-state-performance-20261107.
Původní checkout codex/fio-auto-reconciliation-recovery a WIP zachovat;
kis_rosters_admin.php SHA256
`7a7afd75bfa34f736f158096ab46a671b7fee6c6faedc3701f989c0cf64733e5` shodný.
Neslučovat cizí otevřená PR, přijímat jen již začleněný main.

## Plán a akceptace

1. Zmrazit oba skutečné route soubory a původní čtení na base. Přístup přes
   public_profile_access_session_athlete_id, staff scope/verzi a krátký bearer
   token zachovat a znovu ověřovat při každém AJAX. Parametr id/sid nesmí změnit
   osobu oproti autorizované session. Hash pouze původní303 výměna na čisté URL.
2. Členství tréninku odpovídá přesně původní účast NEBO vlastní propojené měření.
   Zkusit odvozená unikátní ID z UNION obou vazeb, připojit tréninky; sirotci,
   duplicity, pouze měření bez účasti a cizí měření musí odpovídat původnímu
   LEFT JOIN/DISTINCT. Ověřit nativně před volbou. Žádné DDL/indexy.
3. Zachovat úplné dostupné roky a celkový počet profilu. Historie: úzké kandidáty
   id/datum/délka pro přesný počet a původní výpočet hodin, stránka25, teprve potom
   plné tréninky, vlastní poznámky a vlastní měření této stránky. Stejné pořadí
   datum DESC,id DESC a uvnitř tréninku poradi ASC,mereni_id ASC.
4. Měření: na počátečním GET pouze přesné souhrny, nikoli celé texty/HTML tabulky.
   Souhrn typů musí zachovat počty i pořadí prvního výskytu původního seznamu;
   ověřit úzké ROW_NUMBER/group souhrny proti prostému původnímu čtení. Přesná
   binární shoda typů (velikost písmen/mezery/neznámé typy) jako starý JS filtr.
   Po otevření záložky načíst25řádků, filtr přes celou historii a stránkování,
   všechny původní hodnoty/jednotky/poznámky dál dosažitelné. Nový režim ve stávajícím
   AJAX endpointu, stejná autorizace/hlavičky; nepřidávat veřejnou nechráněnou cestu.
5. Frontend: načítat záložku na požádání i po obnovení uložené záložky. Rok/typ
   resetuje stránku, navigace zachová filtry, abort ochrání před starou odpovědí.
   Stejná práva na poznámky/odměny; writery/CSRF/privátní soubory beze změny.
   Zátěžové testy, kalendář, závody a další záložky jsou mimo tento řez.
6. Nezávislá původní reference proti sjednocení všech stránek a souhrnům:
   účast/měření/mix, sirotci/duplicitní vazby, více osob, roky/prázdno/poslední
   stránka, remízy data/poradi, všechny i neznámé typy, jednotky/null/zaokrouhlení,
   čerstvé změny dat a odvolání přístupu. SQLite a MariaDB10.3/11.4.
7. Na stejných vlastních syntetických datech100/1k/10k tréninků a odpovídajících
   měřeních: query cache OFF,4zahřátí +17střídavých párů, čas neinstrumentovaně,
   SQL/vrácené řádky/bindings/paměť zvlášť. Skutečný celý GET profilu i historii
   a měření před/po, negativní přístupy a revokace, checksumy a cleanup.
   Chrome: první profil, historie/rok/další/předchozí, lazy Měření, globální filtr
   typu, uložená záložka, poznámky dle oprávnění a390px. Staré measurement rows
   z plného profilu porovnat s novou stránkovanou záložkou, nikoli předstírat
   existující měřicí AJAX před optimalizací. Syntetické přístupy jen standardními
   login/token toky, nikdy přímým podvržením session.
8. Bezpečné důkazy do repa, aktuální CI, přesný tested/merged tree a sourcehashy,
   chráněný deploy import/UAT=false, záloha/migrace/VS/aktivace/smoke a čtecí
   produkční kontrola. Přihlášené produkční UAT evidovat odděleně. Důkazy novým
   dokumentačním PR do main bez zbytečného druhého deploye.

Žádné změny cen/plateb, rolí, přístupových tokenů/session lifetime nebo přenosu
skutečných soukromých profilových dat do Gitu. Živý katalog se může měnit během
kontrol; původní neshodu nezakrývat novějším vzorkem jako v předchozím protokolu.
Další krok: implementace, datová parita a měření. PR/merge/deploy zatím NOT RUN.

Průběžně: implementována UNION projekce historie po25, souhrny beze změny,
poznámky/měření jen pro vybraná ID. Měření lazy,25řádků a globální přesný filtr.
Zvolen úzký seřazený sloupec typů + původní PHP array_count_values místo
ROW_NUMBER/GROUP BY: zachovává první výskyt, PHP klíče i case/mezery bez kolace;
na počátečním profilu jen COUNT. Souhrn typů je stále lineární čtení, nikoli cache.
SQLite parita všech stránek:2testy/605assertions PASS. Nativní a HTTP/Chrome NOT RUN;
žádný release zatím. Další krok: vlastní CI fixture a původní skutečné route.
