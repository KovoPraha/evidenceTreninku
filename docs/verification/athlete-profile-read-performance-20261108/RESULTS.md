# P11-RHA — parita a výkon

8. 11. 2026: NOT RUN, plán před implementací.
[Plán](../../ATHLETE_PROFILE_READ_PERFORMANCE_2026-11-08.md).
Frozen reference obou skutečných routes:69b69fa46349b0778980f3cbf3116be91b77cea6.

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
