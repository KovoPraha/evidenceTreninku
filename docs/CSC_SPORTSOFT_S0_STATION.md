# P15 / S0 — nonstop stanice oddělená od Rikiho

Datum: 7. 11. 2026. Vlastník: Marek / vývoj KIS.
[Hlavní plán](CSC_SPORTSOFT_READ_SYNC_PLAN_2026-11-07.md),
[datový kontrakt](CSC_SPORTSOFT_S0_CONTRACT_V1.md),
[prompt pro místní Codex](verification/csc-sportsoft-s0-20261107/STATION_PROMPT.md).
Stav: požadavky a předání připravené; živá inventura cílové stanice NOT RUN.
Dokumentace je v otevřeném [PR #371](https://github.com/KovoPraha/evidenceTreninku/pull/371).

## Co skutečně víme

Vlastník potvrdil jiný Windows počítač s osobním asistentem Rikim, Ubuntu
prostředí a přístupem místního Codexu do repozitáře Evidence. Požaduje
oddělit KIS od ryze soukromých dat Rikiho. Spojení z nynějšího počítače
nebylo poskytnuto; předání proběhne promptem v repozitáři. Místní Node ani
aktuální průzkumný Chrome nejsou inventurou cílového stroje.

Čtecí kontrola soukromého repozitáře riki-nanoclaw ověřila **zdrojové
podklady, nikoli živé nasazení**:

| Podklad | Konkrétní zjištění / hranice důkazu |
| --- | --- |
| [NEW-STATION](https://github.com/mmmixamarek-source/riki-nanoclaw/blob/main/docs/riki-recovery/NEW-STATION.md), blob `369dc47b6405eb7e8613567c0a2813bd5b8f93a9` | Baseline 27. 9. 2026: Windows, WSL2 Ubuntu, Docker Desktop s WSL integrací. Staré verze Node/pnpm/Docker nejsou dnešní inventura. |
| [SOURCE-INVENTORY](https://github.com/mmmixamarek-source/riki-nanoclaw/blob/main/docs/riki-recovery/SOURCE-INVENTORY.md), blob `bd2513f55bfbfebf31819e7502104fc78a2d9305` | Soukromé runtime DB, sessions, profily a zálohy jsou jiné autority než Git; pro KIS se nepřebírají ani neinventarizují. |
| [Windows autostart](https://github.com/mmmixamarek-source/riki-nanoclaw/blob/main/scripts/install-windows-autostart.ps1), blob `8ab6149405cb78e3795beae878ac1429e718a6f0` | Úloha `Riki-Start-WSL-NanoClaw` používá Interactive logon, start při přihlášení a pětiminutový trigger; keeper drží WSL. KIS ji nesmí upravit ani převzít. Není to důkaz bootu bez přihlášení. |

Žádný Riki runtime, DB, `.env`, vault, pošta, deník, osobní profil, log nebo
záloha se v S0 nečetly ani nezměnily. Zdrojový repozitář se neměnil.

## Doporučená varianta pro první implementaci

**Samostatný nativní Windows worker KIS**, vlastní neadministrátorský OS
účet, vlastní adresáře a Windows Task Scheduler. Bez závislosti na
NanoClaw/Riki, jejich WSL distribuci, Docker daemonu, OneCLI či LLM.
Zdroj workeru patří do Evidence `workers/external-sync/`; serverová část
zůstává na KIS hostingu. Instalace, účet, plánovač a tokeny v tomto kroku
nevznikají. Konkrétní návrh musí ověřit místní inventura.

| Varianta | Závěr S0 |
| --- | --- |
| Nativní Windows, samostatný účet | Doporučeno: jednoduché místní ruční přihlášení, samostatný životní cyklus, nepotřebuje přístup k Riki infrastruktuře. |
| Kontejner vedle Rikiho ve stejném Dockeru / stejném WSL účtu | Nyní nezvoleno: sdílí správu runtime, mounty a účet; dosažitelná izolace není doložená. Samotný nový název kontejneru neřeší soukromí. |
| Samostatná VM nebo vyhrazený stroj | Náhradní varianta, pokud nelze oddělit účty/práva nebo zajistit boot/headless. Vlastní profil a přístup obsluhy; nepoužívat sdílené disky/clipboard s Rikim. |

ACL jsou izolace mezi běžnými účty, ne bariéra proti administrátorovi hostu.
Pokud Riki běží s neomezeným hostovým/admin přístupem, nelze tvrdit úplné
oddělení jen novou složkou. Nezjišťovat to čtením jeho soukromé konfigurace:
nechat správce potvrdit oprávnění, jinak zaznamenat UNKNOWN a navrhnout
samostatnou správní hranici. Stejný fyzický host vždy sdílí výpadek a výkon.

## Přesný seznam potřeb stanice

| Oblast | Požadavek a akceptace | Dnešní stav |
| --- | --- | --- |
| OS | Podporovaný Windows 11+ / Server 2019+ podle Playwright; zaznamenat edici, build, architekturu a podporu vybrané verze | Windows potvrzen vlastníkem; přesná verze UNKNOWN |
| Runtime | Samostatná připnutá Node 24 LTS, přesná kompatibilní verze Playwright a Chromium v lockfile/release manifestu; bez `@latest` při startu | Na cílové stanici UNKNOWN |
| Kapacita | Navržená rezerva pro sekvenční browser: 2 logické CPU, 2 GiB volné RAM a 5 GiB volného šifrovaného disku; není to minimum výrobce ani garantované měření | Změřit bez čtení cizích procesů/dat; peak ověřit v pilotu |
| Účet | `kis-sync` jako navržený vlastní neadmin účet; žádné členství docker-users, žádný přístup k Riki složkám či mountům | Není založen / existence neověřena |
| Kód | Navržené `C:\ProgramData\KISSync\releases\<version>` read-only pro runtime; vydání spravuje operátor, worker se sám neaktualizuje | Neinstalováno |
| Soukromý stav | `C:\ProgramData\KISSync\private\` pouze účet workeru a pověřený správce; podadresáře `profiles\csc`, `profiles\sportsoft`, `spool`, `logs`; bez OneDrive/Dropbox/Gitu | Připravit a doložit ACL až při instalaci |
| Přihlašování | Obsluha pod účtem workeru ručně otevře headed profil, poté zavře a uvolní zámek. Headless čtení používá tentýž dedikovaný profil, nikdy osobní Chrome | Přenos průzkumné session zakázán; nové ruční login ještě neprovedeno |
| Scheduler | Vlastní úloha `KIS-ExternalSync`, start při bootu nezávisle na přihlášení, IgnoreNew, omezené restartování; kontext účtu s batch právem a potřebným síťovým přístupem | Konfigurace ani restart test NOT RUN |
| Napájení | Po zapojení do sítě neuspávat během požadovaného provozu; ověřit chování po Windows Update/restartu a obnovení napájení | Jen inventura; power plán se v S0 nemění |
| Síť | Odchozí HTTPS na ČSC, Sportsoft a explicitně nakonfigurovaný KIS host; žádný inbound port. DNS, ověřené TLS, proxy pravidla a čas musí fungovat pod účtem workeru | Cílová dostupnost UNKNOWN |
| Tajemství | Vlastní úzce omezený KIS device token, chráněný pod účtem workeru; pouze Authorization header. Cookies zůstávají v profilových adresářích | Token není vydaný; endpoint ještě neexistuje |
| Logy / obnova | Pouze metadata běhů/chyb, žádné DOM/PII/cookies. Spool max72h, navržená kvóta512MiB; při plné kvótě zastavit nový sběr. Relace se po ztrátě znovu ručně přihlásí | Implementace NOT RUN |

Node24 je nyní podporovaná LTS řada; patch je třeba ověřit při sestavení.
[Node releases](https://nodejs.org/en/about/previous-releases).
OS/runtime podpora se řídí verzí Playwright,
[systémové požadavky](https://playwright.dev/docs/intro#system-requirements).
Auth soubory umožňují přístup k účtu, proto nepatří do repozitáře:
[Playwright authentication](https://playwright.dev/docs/auth).
Batch a interactive logon jsou odlišné režimy:
[Microsoft Task Scheduler](https://learn.microsoft.com/en-us/windows/win32/taskschd/security-contexts-for-running-tasks).

Navržený batch účet může vyžadovat lokální správu hesla při registraci úlohy;
heslo se neposílá do chatu/skriptu/PR. Nepoužít autologin Windows, nevypínat
zámek obrazovky ani 2FA. Pokud profil nefunguje v headless/batch kontextu,
označit nepřijatý provoz a zvolit ověřenou variantu, neslibovat nonstop.

## Zakázané propojení s osobními daty

- Žádné čtení Riki adresářů, DB, mailu, deníku, záloh, logů, `.env`,
  OneCLI, privátních klíčů, Docker volumes a browser profilů, ani „jen pro inventuru“.
- Žádný `docker inspect`, `docker logs`, `docker exec`, `wsl --exec`,
  registry secrets, výpis prostředí/procesových argumentů či plošný diskový scan.
- Žádné mounty ani sdílený browser/debug port, úložiště session, token,
  automatizační fronta, AI paměť nebo databáze mezi Rikim a KIS.
- Worker nepotřebuje LLM klíč, Codex session ani poštovní přístup. Riki se
  v první verzi nepoužije ani pro dohled; stav zobrazuje KIS.
- Do Gitu smí jít jen sanitizovaný obsahově omezený výsledek inventury,
  syntetické fixtures a zdroj workeru; žádné reálné exporty členů ani profily.

## Předání a podmínka dokončení S0

Místní Codex má připravený [přesný prompt](verification/csc-sportsoft-s0-20261107/STATION_PROMPT.md)
a [čtecí inventurní skript](verification/csc-sportsoft-s0-20261107/station-inventory.ps1).
Skript vrací pouze OS/kapacitu/dostupnost nástrojů a KIS cest; nepřistupuje
k Riki runtime, nespouští WSL/Docker ani browser a nic neinstaluje.

S0 požadavky, kontrakt a předání jsou hotové. **S0 celek zůstává částečný**:
chybí výsledek inventury cílového stroje, potvrzené oddělení účtů a ověřené
Sportsoft scope/platební popisky. Následuje návrat cílového protokolu přes
vlastní PR; S1 lze vyvíjet nad syntetickým kontraktem bez přístupu ke stanici.
Žádná změna portálů, nasazení, produkční ingest ani aktivace tímto předáním nevzniká.
