# P15 / S0 — prompt pro Codex na cílové Windows stanici

Tento text předej Codexu běžícímu na nonstop stanici. Je to zadání čtecí
inventury, nikoli instalační skript nebo povolení spuštění synchronizace.
Kanonické podklady: [stanice](../../CSC_SPORTSOFT_S0_STATION.md),
[kontrakt](../../CSC_SPORTSOFT_S0_CONTRACT_V1.md), [plán P15](../../CSC_SPORTSOFT_READ_SYNC_PLAN_2026-11-07.md).

---

Pracuješ na druhém pracovním místě projektu KIS / Evidence,
repozitář `KovoPraha/evidenceTreninku`. Dokonči cílovou inventuru S0 pro
čtecí synchronizaci ČSC a Sportsoftu. Tento Windows počítač současně
provozuje osobního asistenta Rikiho. **Jeho data jsou ryze soukromá a zcela
mimo tento úkol — nesmí dojít k jejich čtení, zápisu, kopírování, indexování
ani zpřístupnění workeru KIS.** Oprávnění OS nebo fakt, že je soubor dostupný,
neznamená oprávnění jej číst.

## 1. Převzetí zdrojů

- Použij pouze již dostupný repozitář Evidence; nehledej ho plošným scanem
  disku/profilů. Načti jeho AGENTS.md, CLAUDE.md, CURRENT, HANDOFF,
  WORK_DOCUMENTATION_POLICY a P15 soubory odkazované výše.
- Zkontroluj WIP, fetch origin a skutečný stav PR #371. Pokud není sloučený,
  použij jako zdroj jeho aktuální větev
  `origin/codex/csc-sportsoft-read-sync-plan-20261107`; samotný starý main
  S0 podklady nemusí obsahovat. Pokud je sloučený, vyjdi z aktuálního main.
- Vytvoř vlastní worktree a větev `codex/csc-sportsoft-station-s0-20261107`
  v běžné pracovní oblasti Evidence. Existující checkout, cizí WIP ani
  jinou stejnou větev nepřepisuj. Zapiš přesný base SHA a použitou cestu.
- Nepřipojuj repozitář Rikiho jako workspace. Nepoužívej jeho AGENTS,
  runtime, paměť, skills, pracovní frontu ani konfigurační soubory.

## 2. Povolené čtení a striktní hranice

Povolené jsou zdroje Evidence a obsahově omezená metadata Windows:
edice/build/architektura, CPU počet, RAM/disk kapacita, dostupnost scheduleru,
existence Node/npm/WSL/Docker příkazů bez jejich privátní konfigurace.
Použij přiložený `station-inventory.ps1`; přečti ho před spuštěním.
Výstup drž nejprve lokálně mimo Git a do protokolu přenes jen whitelist polí.
Nejprve ověř, že jsi opravdu na cílové stanici, potvrzené vlastníkem.

Výslovně zakázané:

- Osobní soubory, pošta, deník, konverzace, runtime DB, `.env`, secrets,
  cookies, browser profily, logy, zálohy a data Rikiho i jiných aplikací.
- Plošné `rg`, recursive dir/find přes home, AppData, disk nebo WSL.
- `docker inspect/logs/exec`, čtení volumes/socketu, `wsl --exec`,
  `systemctl` nad Rikim, dumpy procesových argumentů, prostředí, registry
  přihlašování, SSH klíčů a správce hesel. Neotvírat Riki UI ani jeho API.
- Čtení existujících naplánovaných úloh včetně akcí/argumentů Rikiho.
  Pro S0 stačí stav Windows služby Schedule; start Rikiho je již popsán
  zdrojově v podkladech. Žádný restart nebo změna jeho infrastruktury.
- Připojení produkční DB KIS, ingest skutečných sportovců nebo změna portálů.
  Nevyplňuj ani neodesílej formuláře, nezaškrtávej boxy, nekopíruj session.

Pokud nástroj vypíše nečekaná soukromá data, dále je nečti, neukládej do
protokolu ani neopakuj výpis; zaznamenej pouze neobsahovou chybu a uprav
rozsah kontroly. Nedostupné údaje označ UNKNOWN, nerozšiřuj oprávnění.

## 3. Výsledek inventury a návrh instalace

Prověř navrženou **nativní Windows variantu KIS** bez WSL/Dockeru Rikiho:

1. OS podporuje připnutou verzi Node/Playwright; dostupná kapacita odpovídá
   navržené rezervě 2 CPU / 2 GiB volné RAM / 5 GiB disku. Nejde o měření
   reálného browserového výkonu, ten patří do pozdějšího pilotu.
2. Navrhni vyhrazený neadmin účet `kis-sync`, vlastní instalační a privátní
   adresáře. Jestli jména/cesty již existují, nečti jejich obsah; zaznamenej
   kolizi a navrhni jinou cestu. Nezalož účet ani neměň ACL v tomto kroku.
3. Navrhni start při bootu bez přihlášeného uživatele pod vlastním batch
   účtem. Vlastní `KIS-ExternalSync`, IgnoreNew, deduplikace a omezené retry;
   žádný autologin Windows, změna zámku obrazovky nebo zásah do Riki tasku.
4. Sepiš postup nového ručního přihlášení do obou portálů pod účtem workeru,
   uvolnění profilu a pozdějšího ověření headless/batch/lock/restart.
   Neprováděj restart ani nevynucuj odhlášení uživatele.
5. Popiš oddělení přístupů: runtime může číst jen svůj kód a zapisovat svůj
   stav; nemá Riki mounty, osobní profily, OneCLI, LLM ani jeho device tokeny.
   Nic se neposílá Rikimu. Opačný přístup Rikiho řeš potvrzením správce
   o účtech/právech, nikoli průzkumem soukromých dat. Hostový administrátor
   není izolován běžnou ACL; nejasnost přiznej, případně navrhni vlastní VM.
6. Síťové spojení zatím jen navrhni: outbound443 ČSC/Sportsoft/KIS,
   samostatný device token až po implementaci serveru. Žádné nové porty,
   firewallové výjimky, přesměrování ani vlastní TLS bypass.

## 4. Rozdělení následného vývoje

Tento prompt provádí S0. Připrav konkrétní backlog stanice; zatím
neinstaluj balíky, službu, profil, token ani plánovač. Nespouštěj
produkční automatiku a neposílej zprávy přes Rikiho.

Po navazujícím zadání může tato stanice vyvíjet `workers/external-sync/`
(adaptéry, lokální parsery, běhový zámek, privátní spool, instalace/runbook)
nad syntetickými fixtures. Hlavní KIS pracoviště vlastní PHP API, DB,
serverovou normalizaci UCI, matcher a registrářské UI. Kontrakt v1 je společný;
jeho změnu nejdřív zapiš/reviduj, nevytvářej vlastní kompatibilní „odhad“.
Žádné nové závislosti v NanoClaw ani úpravy jeho repozitáře.

## 5. Povinné předání

Vytvoř `docs/verification/csc-sportsoft-s0-20261107/STATION_RESULT.md`:
čas, base SHA, metoda, obsahově omezená inventura, PASS/UNKNOWN/NOT RUN,
návrh oddělení účtů a přesné chybějící kroky. Zpráva nesmí obsahovat osobní
hostname/IP, uživatelská jména jiných účtů, credentials nebo výpisy soukromých cest.
Odkazuj na již existující S0 specifikaci, nepiš konkurenční plán.

Aktualizuj P15, registr, CURRENT/HANDOFF (do80řádků), log a katalog podle
projektových pravidel. Zachovej souběžnou práci; pro nové rozhodnutí/událost
použij jmenné ID s `P15-STATION`, ať nevznikne kolize čísel mezi pracovišti.
Spusť `php tools/check-current-docs.php`, `git diff --check`, kontrolu
sanitizace; commit a push jen vlastní změny, předej vlastní PR proti main.
Pokud #371 ještě není sloučené, popiš závislost a neslučuj duplicity.

S0 neoznač jako celé HOTOVO, pokud chybí inventura/oddělení účtů nebo
otevřené mapování zdrojů. Provozní headless/restart a ostrý ingest jsou
samostatné další etapy. Závěrem napiš konkrétní připravenost a další krok,
nikoli tvrzení, že synchronizace již běží.
