# P11-SCALE — větší zdravotní přehled a plánovač

7. 11. 2026, Europe/Prague. HOTOVO v rozsahu této studie; celé K0.2 otevřené.
Vlastník vývoj podle přímého zadání Marka. Produkci neměnit, zachovat WIP.
Větev `codex/medical-planner-scale-20261107`, worktree
`C:/Users/Marek/Documents/Codex/evidencePavel-medical-planner-scale-20261107`.
Sdílení a finální CI/začlenění vede [PR #375](https://github.com/KovoPraha/evidenceTreninku/pull/375).

## Původní plán, změny a akceptace

Plán uložený před implementací v `d2182268`; nový vlastní localhost, migrovaný
syntetický základ, větší objemy při zachování úplných výsledků, oddělené časy
a SQL diagnostika, nezměněná doménová data při GET a vlastní úklid.
Varianta měření stejné aplikace místo další implementace; jiný chat mezitím
řešil opravu #374. Rozhodnutí D2026-11-07-13, deník E2026-11-07-44.

Primární aplikace `69f49033`, 16 LF otisků; 10k/50k/100k zdravotních sledování,
tři záznamy na sledování, historie 52/260/520 týdnů (728/3640/7280 plánů),
20/200/1000 členství při 1/10/50 hranách. Zdravotní objemy a historie oddělené,
členství a šířka hierarchie rostou společně. Všech 72 kladných ×30 =2160 časů,
28 záporných, doména/DOM/HTML/READ ONLY a 21 checksumů PASS v každé sadě.
[Kompletní primární důkazy](verification/medical-planner-scale-20261107/RESULTS.md),
51 hashovaných artefaktů. Přípravy a neúplná 100k fáze jsou v kalibraci.

Při uzavření přijatý main `e36c9ba6` / #374. Doplněné aktuální planner520
a roster1000: 18 kladných ×30 =540 časů, 8 záporných. Přímá přesná shoda
všech domén se starou sadou, aktuální source17/DOM/SQL/21checksumů PASS.
[Samostatný aktuální protokol](verification/medical-planner-scale-20261107/current-planner/RESULTS.md).
Jde o pozdější nový seed a restart, žádný kontrolovaný časový pár.
Skutečný párový Linux/Chrome přínos #374 zahrnuje 20728 plánů a má vlastní důkazy.

## Výsledek pro uživatele

Zdravotní Platné při 100k zkoumá 1300558 řádků a vrací přes300k metadatových řádků;
stránka přitom zůstává kolem50kB a PHP kolem2,16MiB. Místní HTTP medián4,50s,
běžný seznam0,56s; časy na nevyhrazené Windows stanici jsou orientační.
Novější plánovač #374 omezil přenos historie při stejných výsledcích;
starý nález14554 řádků v desetileté sadě není současná neopravená implementace.
Aktuální přesná čísla vede navazující protokol. Žádná úprava produktové aplikace touto relací.

## Bezpečnost, ověření a meze

Windows / PHP8.2.12 / MariaDB10.4.32, 350 tabulek /161 migrací, buffer pool128MiB.
Minimální legacy schéma s celým migračním katalogem, nikoli produkční klon indexů.
Skutečné Rows_examined ze slow logu, fetched rows helperů oddělené od celého HTTP.
Web účty po standardním loginu SELECT only. Žádná SQL DML/DDL z diagnostických GET.
Fáze100k se po chybě sdíleného TEMP zopakovala celá, 21 součtů před/po restartu shodných.
Pouze vlastní my.ini dostal vlastní tmpdir; žádný zásah do společného XAMPP/TEMP.
Obě fáze mají bezpečný whitelist, kontrolu secret hodnot, úklid schématu/účtu/configu/relací,
zastavené vlastní procesy a nezávisle odmítnuté sockety33479/18115. Runtime se nemaže rekurzivně.
Původní `kis_rosters_admin.php` SHA256 stále
`7a7afd75bfa34f736f158096ab46a671b7fee6c6faedc3701f989c0cf64733e5`.

Primární regrese13/759 PASS; aktuální testy/styl/parsers/compile a finální PR CI
mají konkrétní doklady v protokolech a PR. Aktuální stav produkce vlastní pouze
[CURRENT](CURRENT_STATE.md); cizí schválený deploy #374 tato relace nespouští.

## Další úkoly podle priority

1. K OVĚŘENÍ: navrhnout úspornější R13 stavové čtení a COUNT/picker dotazy podle
   skutečných čítačů. Zachovat řetězce nahrazení, hranice dat, odvolání, přesné počty a stránkování.
   Před změnou ověřit skutečné relevantní indexy; syntetický minimální model není produkční indexová mapa.
2. R13 100k zopakovat na řízeném Linux/MariaDB10.3/11.4 a složitější historii;
   metadata před časováním, jednotný tmpdir a opakované pořadí běhů. Současné časové poměry nejsou kauzální.
3. R05 #374 je již implementované a ověřené i zde; neopakovat jeho opravu ani deploy.
   U1000 členství celkový Rows_examined17382→18895 i přes úsporu helperu1499→479:
   samostatně analyzovat čtení členství/RSVP a režii sond, bez tvrzení plošného zrychlení.
   Proměnlivá topologie a fallback nad limity mají zvláštní podmínky měření.
4. Fyzický mobil, řízená síť/CPU, souběžní uživatelé a ostatní R01–R16/K0.2 zůstávají otevřené.
   Přihlášené produkční UAT pickeru z #377 zůstává blokované dostupností ověřitelného browseru.
   P05/P07, exportP06 a heslo/T07 mají své dřívější závislosti v jediném registru.

[Dated handoff](SESSION_HANDOFF_MEDICAL_PLANNER_SCALE_2026-11-07.md),
[registr](WORK_PLAN_CURRENT.md), [katalog](PLAN_CATALOG.md).
