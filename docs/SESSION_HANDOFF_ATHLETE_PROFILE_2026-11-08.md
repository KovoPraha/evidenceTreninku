# Předání P11-RHA — profil sportovce

8. 11. 2026: ROZPRACOVÁNO, plán před implementací.
[Plán](ATHLETE_PROFILE_READ_PERFORMANCE_2026-11-08.md),
[výsledky](verification/athlete-profile-read-performance-20261108/RESULTS.md).
Větev codex/athlete-profile-history-performance-20261108, base69b69fa4,
vlastní medical-state worktree; původní checkout/WIP a hash zachované.
Výchozí produkceda152282/deploy42494231010 SUCCESS, RHC již uzavřené.

Historie a měření osobního profilu: stejná autorizovaná osoba a oprávnění,
celé počty/roky/hodiny/typy zachovat; detaily po25, Měření lazy po otevření.
Ověřit UNION členství a window souhrny proti původnímu čtení, celé stránky,
přesné typové filtry, poznámky/odměny a čerstvou revokaci. Ostatní záložky/writery
beze změny, žádné DDL/cache oprávnění. Nativní obě DB i skutečné HTTP/Chrome před/po.

Další krok implementace a ověření, vše měření/PR/merge/deploy zatím NOT RUN.
Vlastník autorizoval celý ověřený release; neptat se na stejné oprávnění znovu.
Soukromá skutečná profilová data/toky/credentials nikdy nepatří do Gitu.

Průběžně: implementována UNION projekce historie po25, souhrny beze změny,
poznámky/měření jen pro vybraná ID. Měření lazy,25řádků a globální přesný filtr.
Zvolen úzký seřazený sloupec typů + původní PHP array_count_values místo
ROW_NUMBER/GROUP BY: zachovává první výskyt, PHP klíče i case/mezery bez kolace;
na počátečním profilu jen COUNT. Souhrn typů je stále lineární čtení, nikoli cache.
SQLite parita všech stránek:2testy/605assertions PASS. Nativní a HTTP/Chrome NOT RUN;
žádný release zatím. Další krok: vlastní CI fixture a původní skutečné route.
