# Předání P11-RHA — profil sportovce

8. 11. 2026: ROZPRACOVÁNO, implementace a ověřování v PR #404.
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

PR [#404](https://github.com/KovoPraha/evidenceTreninku/pull/404) sdílí implementaci.
První HTTP fixture zastavena před porovnáním403: trenér neměl explicitní grant;
účast na tréninku správně přístup neuděluje. Doplněn pouze syntetický team2 grant,
bez zásahu do aplikace/autorizace. Lokální plný PHPUnit bez DB není důkaz release;
rozhodující je plný CI. Native/HTTP/browser zatím čeká, merge/deploy NOT RUN.

První nativní iterace42518606301: obě verze PASS datové parity, historie10k
614→173ms (10.3),689→270ms (11.4), ale Měření vše473→486ms /574→708ms:
časová regrese, nelze uvolnit. Úzkých90909typů se stále přenášelo do PHP a
široké třídění detailů probíhalo před LIMIT. Návrat k původně plánované variantě:
binární ROW_NUMBER/GROUP BY se souhrnem prvního výskytu; úzká stránka ID před
širokými měřeními. Původní výsledek zachovat; nová varianta vyžaduje novou paritu
všech stránek a nativní/HTTP měření. Nasazení nadále NOT RUN.
