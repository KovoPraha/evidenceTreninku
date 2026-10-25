# Společná integrace — 25. 10. 2026

**Dokončené změny jsou sjednocené a technicky ověřené v izolaci. Nic nebylo
nasazeno na produkci, sloučeno do main ani provozně aktivováno.**

Větev: [`codex/integration-20261025`](https://github.com/KovoPraha/evidenceTreninku/tree/codex/integration-20261025).
Pracovní kopie: `C:\xampp\htdocs\evidencePavel-integration-20261025`.
Ověřený technický commit: `075d92ac19182c8da514a0ca9f56ebbe632ffe7b`.
Následné dokumentační změny nemění aplikaci ani testy.

## Co bylo spojeno

- Aktuální `origin/main` `6bf57d6fe4c20fd9d9e182d3bde4253b1abc4a51`, včetně
  čtyř bezpečnostních oprav PR #190 a dříve začleněných oprav obchodu, financí,
  skladů, vratek, rodinných nákupů a Fio.
- Klubové balíky A–E, následná přejímka a účetní předání z větve
  `codex/linux-browser-acceptance-20261024` na `420e78a0`: každodenní práce
  trenéra/registráře, život osoby a podklady, správa akcí, sezon a přechodu,
  společná přejímka, Linux a návrh pilotu D12–D14.
- Merge commit `7fb9e003` zachovává obě historie. Dva konflikty v
  `CURRENT_STATE.md` a `HANDOFF_CURRENT.md` byly vyřešeny zachováním obou
  datovaných záznamů a přidáním aktuálního rozcestníku. Historický údaj
  „PR #190 otevřen“ není dnešním stavem; oprava už je na main.
- Nezávislá kontrola automatického spojení ověřila pořadí načtení aktuálních
  oprávnění před akcí, pracovní role, delegace trenéra a vedoucího sportu,
  komunikaci a migrační návaznost. Další sémantický konflikt nebyl nalezen.
- Commit `075d92ac` doplňuje test opravy indexů VS do obou větví MariaDB CI.
  Aplikační chování nebylo při této integraci dodatečně měněno.

## Inventura všech vývojových větví

[Úplná inventura](BRANCH_INVENTORY.md) a [strojový seznam](branch-inventory-classified.json)
klasifikují všech **249 místních a origin odkazů** výchozího snapshotu;
žádný nezůstal nerozhodnutý. Místní a vzdálený odkaz na tutéž větev se počítají
samostatně. Nově vytvořená integrační větev není další chybějící implementací.

| Klasifikace | Počet odkazů |
|---|---:|
| Přesná shoda souhrnného patche s již začleněným squash commitem | 222 |
| Již obsažené podle historie Git | 13 |
| Starší etapy větví, jejichž dokončený patch už byl začleněn | 9 |
| Přesná shoda celého stromu s historickým main | 2 |
| Uzavřený nesloučený jednorázový návrh PR #109 | 2 |
| Překonaná varianta Fio migrace | 1 |

Do integrace úmyslně nevstoupily:

1. `d1221f35`: původní Fio větev má staré číslo migrace
   `20261020120000_fio_sync_state`. Odpovídající dokončená verze s číslem
   `20261020170000` už je v `c4f5d92f` (#102). Další merge by přidal duplicitu.
2. `2a4630f3`: diagnostický návrh identifikace bankovního účtu z uzavřeného,
   nesloučeného [PR #109](https://github.com/KovoPraha/evidenceTreninku/pull/109).
   Nejde o chybějící přijatou funkčnost. Původní větev zůstává zachovaná.
3. Necommitovaný `kis_rosters_admin.php` v původním checkoutu. Je zachovaný
   a není označen za dokončený: nová nápověda tvrdí, že návrh nekontroluje datum
   narození, ale `kisRosterRolloverAgeCheck()` věk podle cílové skupiny kontroluje.
   Případné dokončení této úpravy je samostatný malý úkol.

## Nové ověření společné verze

[Linux CI 40187386208](https://github.com/KovoPraha/evidenceTreninku/actions/runs/40187386208)
proběhlo nad přesným commitem `075d92ac`. [Metadata běhu](linux-final.json),
[výpis výsledků](linux-test-excerpt.txt), [místní souhrn](verification-summary.json).

| Kontrola | Čerstvý výsledek |
|---|---|
| Linux PHP 8.2, úplný PHPUnit | **1 717 testů / 34 744 assertions PASS** |
| Linux soukromé úložiště a symlinky | **3 testy / 45 assertions PASS**, zákaz přeskočení; podmnožina plné sady |
| MariaDB 10.3 a 11.4 | Obě celé CI větve PASS, včetně instalace závislostí a všech smoke scénářů |
| Composer v PHPUnit jobu | Validace konfigurace, instalace a audit závislostí PASS |
| Nový cílený test VS na MariaDB 10.3 a 11.4 | **23 kontrol na každé verzi PASS**: skutečný migration runner, čerstvá instalace, upgrade, opakování, historický ledger a platby |
| [HTTP přejímka](http-effective-results.json) | **26 sad / 805 kontrol PASS** v novém syntetickém prostředí; opakování jednoho timeoutu popsáno níže |
| [Souběhy a snímky](concurrency-results.json) | **6 sad / 78 kontrol PASS**: výdej, vratky, účetní export/sklad, uzávěrka tréninku a odvolání oprávnění |
| Lokální migrační test VS | **10 kontrol PASS**, původní ochrana lokálního nástroje nezměněna |
| [Syntetický převod osob/tréninků](migration-acceptance.json) | **17 kontrol PASS** |
| [Migrační katalog](migrations.json) | **138 migrací**, `current=true`, `pending=[]` |
| [Záloha a obnova](backup-restore.json) | **327 tabulek, 2 triggery, 40 soukromých souborů, 15 vazeb**; obnova do další nové DB |
| Úplnost migrační obnovy | **4 mapy, 5 úplných zdrojů a 5 původních archivů** skutečně zkontrolováno, nikoli pouze nulové počty |
| [Závěrečná izolace](isolation-final.json) | PASS; vlastní DB, soukromé cesty mimo webroot, externí transporty vypnuté |
| [PHP syntaxe](php-lint.json) | 75 změněných aplikačních/testovacích PHP souborů PASS; nový CI smoke navíc lintován před spuštěním |

Počty různých sad se nesčítají, jejich scénáře se překrývají. Počet HTTP kontrol
se liší od starších protokolů: jde o čerstvou databázi s menším počtem archivních
souborů a záznamů. Proběhlo všech 26 jmenovaných sad; žádný starý počet nebyl
převzat jako dnešní výsledek. Jednotlivé výstupy jsou v adresáři `http/`.

První průchod měl 25 úspěšných sad a jeden timeout klienta po 15 sekundách při
HTTP vytvoření kontrolního bodu přechodu. [Původní běh](http-initial-runs.json)
a [chyba](cutover-initial-timeout.log) jsou zachované. Stejný test se stejným
limitem prošel samostatně za **5,1 s**, bez změny kódu nebo oslabení kontrol.
Souběh s místním čtením souborů je možná příčina, nikoli prokázaná diagnóza.
Výsledek není výkonnostní zárukou pro hosting.

První obnova neměla migrační archivy, protože čisté prostředí ještě neobsahovalo
importní případy. Proto následoval skutečný syntetický migrační scénář a nová
záloha/obnova s nenulovým ověřením map a archivů. Původní výsledek je zachovaný
v `initial_restore` místního souhrnu, za úplný důkaz platí poslední obnova.

## Izolace a reprodukce

Nový runtime: `C:\Users\Marek\AppData\Local\EvidencePavelIntegration20261025\EvidencePavelB1\finance-b1-20261022`.
MariaDB 10.4.32 naslouchá pouze na `127.0.0.1:33426`, HTTP na `127.0.0.1:18086`.
DB, konfigurační soubor, sessions a soukromé úložiště vznikly nově; žádná
produkční data ani konfigurace nebyly kopírovány. Původní testovací runtime
zůstal zachovaný, pouze jeho ověřené procesy byly řádně zastaveny kvůli portům.

[Postup spuštění a ověření](VERIFICATION_PLAN.md) vyžaduje v každém testovacím
PowerShell procesu nastavit:

```powershell
$env:LOCALAPPDATA='C:/Users/Marek/AppData/Local/EvidencePavelIntegration20261025'
```

Nejde o trvalou změnu uživatelského prostředí. Původní kontroly vlastníka,
datadir a lokálních portů nebyly oslabeny. Nový CI smoke navíc odmítne běh mimo
Linux GitHub Actions před PDO připojením; používá pevný loopback a vlastní
náhodnou DB, nikdy nepřepisuje existující databázi ani nečte aplikační config.

[Otisky 735 aplikačních PHP souborů](application-source-hashes.json) potvrzují,
že aplikace během místní přejímky nebyla změněna. Rozdíl `7fb9e003..075d92ac`
obsahuje pouze nový CI test a jeho spuštění. Produkční workflow a plánovače
jsou proti výchozímu main beze změny. Na GitHub byla odeslána jen integrační
větev a spuštěn testovací workflow, nikoli produkční operace.

## Co tento výsledek neuzavírá

- [D12–D14](../../CLUB_PILOT_D12_D14.md): konkrétní obsluha, termín pilotu,
  ověření obnovy na hostingu a omezený skutečný převod dat.
- [D01–D20](../../CLUB_COMPLETION_DECISIONS.md): konkrétní účetní údaje,
  skutečná salda, pravidla a potvrzení účetní; D15 zůstává NEPOVOLENO.
- Úplnou lidskou přejímku a nové testování v desktopovém prohlížeči: nyní
  proběhly automatizované HTTP scénáře, nikoli nové ruční odklikání všech obrazovek.

Další technický krok může vycházet z této konkrétní integrační větve; není
potřeba znovu slučovat staré vývojové větve. Merge do main, nasazení, převod
skutečných dat a aktivace služeb zůstávají samostatnými následnými kroky.
