# Aktuální plánovač po #374 — dvě největší sady

Měření a místní ověření HOTOVO. Zdrojový pin `e36c9ba6e461b7da842c53939c2fe20eed821f38`,
17 ověřených LF otisků. Stejné logické rozměry a doménové výsledky jako primární studie,
nové náhodné schéma a standardní login. Jde o samostatný pozdější běh na stejném Windows PC.
Časové poměry nejsou řízený párový experiment; párový Linux přínos vlastní [#374](https://github.com/KovoPraha/evidenceTreninku/pull/374).

## Praktický výsledek

- Při 520 týdnech / 7280 plánech historický souhrn vedoucího s filtrem kořene načetl **14554 → 3706 řádků**.
- Špička PHP celého požadavku **10.33 → 3.38 MiB**; aktuálně 47 SQL a 58767 Rows_examined.
- Při 1000 členstvích / 50 hranách historický helper načetl 1499 → 479 řádků; skutečná účast a odpovědi shodné.
- U1000 členství se celkový Rows_examined přesto zvýšil 17382 →18895. Oprava má konkrétní rozsah historického souhrnu; neprokazuje plošnou úsporu každého požadavku. Čtení členství/RSVP a cena nových sond jsou další samostatní kandidáti.
- Všech 18 doménových výsledků se přesně rovná uložené původní sadě i kanonickému výpočtu. Konkrétní DOM ID a počty PASS.

## Metoda a úplné výsledky

18 kladných případů × pět zahřátí + 30 vzorků = **540 HTTP časů**, 8 záporných kontrol.
Nových 12 standardních loginů, potom web účet SELECT only. Celý HTTP bootstrap a navigace,
oddělený prepend/general/slow log, skutečné Rows_examined, 21 shodných součtů v každé sadě.
CLI projekce READ ONLY; fetched rows patří jen historickému helperu, PHP peak celému requestu.
P95 je 29. z 30 hodnot. Žádné vzorky finálních úplných běhů vyřazené.

| Sada / případ | HTTP medián / p95 ms | SQL | Rows_examined / sent | PHP MiB | Helper fetched starý → aktuální |
| --- | ---: | ---: | ---: | ---: | ---: |
| planner520 / planner-coach-own | 1068.2 / 1720.5 | 37 | 29507 / 222 | 3.24 | 1 → 1 |
| planner520 / planner-coach-root | 1620.0 / 2853.9 | 47 | 55134 / 3951 | 3.36 | 7278 → 3706 |
| planner520 / planner-coach-child | 1253.5 / 1967.0 | 47 | 55134 / 3951 | 3.36 | 7278 → 3706 |
| planner520 / planner-coach-trainer | 992.4 / 1564.8 | 37 | 29507 / 221 | 3.24 | 1 → 1 |
| planner520 / planner-coach-group | 850.9 / 1608.3 | 37 | 29556 / 355 | 3.25 | 1 → 1 |
| planner520 / planner-coach-empty | 1485.4 / 3683.3 | 36 | 36650 / 3797 | 3.35 | 7278 → 3706 |
| planner520 / planner-lead-all | 1240.2 / 1966.2 | 37 | 29556 / 355 | 3.25 | 1 → 1 |
| planner520 / planner-lead-root | 1445.3 / 2498.8 | 47 | 58767 / 4105 | 3.38 | 14554 → 3706 |
| planner520 / planner-lead-own | 1014.4 / 1413.2 | 25 | 29198 / 60 | 3.22 | 1 → 1 |
| roster1000 / planner-coach-own | 1059.0 / 1557.9 | 37 | 15696 / 6445 | 5.91 | 1 → 1 |
| roster1000 / planner-coach-root | 2429.8 / 7572.9 | 47 | 18538 / 7045 | 5.91 | 775 → 479 |
| roster1000 / planner-coach-child | 1772.1 / 7960.6 | 47 | 18538 / 7045 | 5.91 | 775 → 479 |
| roster1000 / planner-coach-trainer | 880.7 / 2535.4 | 37 | 15696 / 6444 | 5.91 | 1 → 1 |
| roster1000 / planner-coach-group | 763.0 / 1761.2 | 37 | 15745 / 11723 | 8.59 | 1 → 1 |
| roster1000 / planner-coach-empty | 503.4 / 1673.8 | 36 | 4135 / 717 | 3.22 | 775 → 479 |
| roster1000 / planner-lead-all | 546.7 / 944.3 | 37 | 15745 / 11723 | 8.59 | 1 → 1 |
| roster1000 / planner-lead-root | 567.3 / 1212.5 | 47 | 18895 / 12344 | 8.59 | 1499 → 479 |
| roster1000 / planner-lead-own | 328.4 / 720.3 | 25 | 3088 / 109 | 3.22 | 1 → 1 |

## Výhrada k porovnání časů

Vedoucí/kořen520 měl v primárním Windows běhu200ms a v pozdějším aktuálním1445ms.
Podmínky zátěže pracovní stanice nebyly řízené a tento doplněk časové zrychlení neprokazuje.
Nižší přenos dat a PHP paměť jsou přímo naměřené; párové Linux časy nové opravy
jsou samostatně v #374. Tyto dvě situace se nesmějí sloučit do jednoho rychlostního závěru.

## Reprodukce a kalibrace

Použít aktuální aplikaci připnutou na výše uvedený git objekt a verzované měřicí nástroje.
Stejný vlastní runtime/porty 33479 a 18115 s izolovaným tmpdir, ale nové schéma, DB účet,
config a prázdné klientské cookie jars. Stock FullHttpReadFixture --setup OWN_OUTPUT;
ověřit environment.json s 17 LF otisky aplikace před spuštěním MedicalPlannerCurrentRun.ps1.
Ten vybere pouze planner520 a roster1000, provede login/restrikci/paritu/projekce/časy/diagnostiku.
Potom stejný hashově chráněný FullHttpReadCleanup, StopDatabase, kontrola socketů a původního WIP;
MedicalPlannerCurrentReport.py vytvoří tento samostatný archiv a ověří shodu s primárními doménami.

První export otisků se zastavil na timeoutu mnoha git podprocesů; nahrazen jedním git archive
s LF normalizací (Windows archive respektuje CRLF). Před časováním všech 17 zdrojů ověřených.
První server ještě neposlouchal při loginu: žádný měřený request nevznikl, vlastní web se zastavil.
Přidána TCP readiness před loginy/diagnostikou, potom všechny skutečné loginy PASS.
Plány/odkazy a RSVP se při založení dávkují mimo měření; kompletní doménová shoda se starou sadou prokázaná.
Vlastní lokální formatter nakonec dokončil s 0 opravami; opožděný pokus ukončit již skončený PID nic nezastavil.
Metadata a stylové přípravy ani opakované časování na této pracovní stanici neposkytují kontrolovaný odhad časového zrychlení.

Model má statickou hierarchii, plány míří na kořen, členství roste současně s její šířkou.
Nad limity čtecího helperu #374 zůstává kanonický fallback; nejde o konstantní cenu libovolné historie.
Prohlížeč, fyzický mobil, řízená síť/CPU a souběh nejsou tímto doplněním měřené.
Žádný deploy nebo produkční zápis touto relací. Vlastní prostředí znovu uklizené a porty odmítají spojení.

[Souhrn](summary.json), [prostředí](environment.json), [manifest](artifact-sha256.json),
[úklid](cleanup.json), [kontroly](checks.json), [JUnit](targeted-tests.xml).
[Primární studie](../RESULTS.md), [plán](../../../MEDICAL_PLANNER_SCALE_2026-11-07.md).
