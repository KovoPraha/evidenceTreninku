# P15 — prompt pro S2/S3 na nonstop stanici

Připraveno 7. 11. 2026 podle zadání vlastníka. **Předání zadání HOTOVO;
vývoj a testy na cílové stanici tímto dokumentem nejsou provedené.**
Tento celý dokument lze předat Codexu v již dostupném repozitáři Evidence.
Navazuje na [S1](CSC_SPORTSOFT_S1_KIS.md), [P15 plán](CSC_SPORTSOFT_READ_SYNC_PLAN_2026-11-07.md)
a [oddělení stanice](CSC_SPORTSOFT_S0_STATION.md). Není povolením pilotu.

---

Pracuješ na nonstop Windows stanici jako druhé vývojové pracoviště KIS / Evidence,
repozitář `KovoPraha/evidenceTreninku`. Implementuj **syntetickou část S2/S3**:
samostatný čtecí worker ČSC/Sportsoftu a jeho transport proti hotovému S1.
Nezůstávej u návrhu: připrav kód, reprodukovatelné testy, dokumentaci a vlastní
draft PR. **Žádný skutečný provoz, portálové přihlášení ani nasazení.**

## 1. Nejdřív hranice soukromí

Na stejném počítači běží osobní asistent Riki. Jeho data jsou ryze soukromá.
Ani dostupnost souboru, společný účet nebo administrátorské oprávnění
neopravňují k jejich čtení. KIS nesmí Rikiho využívat jako závislost ani dohled.

- Pracuj pouze ve známém checkoutu Evidence a nových vlastních adresářích
  tohoto úkolu. Nehledej repo plošným scanem disku, home, AppData či WSL.
- Neotvírej Riki repo, UI/API, soubory, `.env`, DB, poštu, konverzace, paměť,
  skills, logy, zálohy, klíče, session, prohlížečové profily ani pracovní frontu.
  Ani odkazy na Riki zdroje ve staré dokumentaci nejsou povolením je otevřít.
- Nepoužívej jeho WSL/Docker/OneCLI ani globální balíky/cache/profily.
  Žádné `docker inspect/logs/exec`, čtení volumes/socketu, `wsl --exec`,
  výpis všech procesových argumentů, prostředí nebo akcí naplánovaných úloh.
- Nepřipojuj se k existujícímu Chrome/CDP ani k libovolnému localhost portu.
  Nevytvářej mounty, symlinky nebo junctions do cizích adresářů. Ověř vlastní
  cílové cesty bez rekurzivního průzkumu; přes existující přesměrování neprocházej.
- Dětské procesy workeru nesmějí dědit celé prostředí přes `...process.env`.
  Použij minimální explicitní prostředí, vlastní TEMP/cache/profil a ověřené
  cesty nástrojů; nikdy nečti hodnoty cizích tajemství kvůli jejich filtrování.
- Nespouštěj binárku nalezenou v Riki adresáři. Použij už dostupný nezávislý
  nástroj nebo vlastní vývojovou závislost. Pro Git použij existující oprávněný
  přístup k Evidence; nečti credential store ani klíče.
- Při nečekaném soukromém výstupu jej dál nečti, neopakuj a nekopíruj do logu
  ani Gitu. Zaznamenej pouze neobsahový problém a zúž rozsah.

Pro syntetický vývoj stačí vlastní pracovní adresář; **není to důkaz izolace
budoucího provozu od administrátora hostu**. Produkční účet/ACL/VM zůstávají
otevřené. Chybějící inventuru S0 označ UNKNOWN, nedoplňuj ji průzkumem Rikiho.
Pokud nelze bezpečně vymezit vlastní pracovní prostor, zastav závislé lokální
spouštění, popiš konkrétní blokátor; pokračuj jen bezpečně dostupnou prací v Evidence.

## 2. Převzetí hotového S1 a vlastní větev

1. V již známém Evidence repo zkontroluj WIP a načti AGENTS.md, CLAUDE.md,
   docs/CURRENT_STATE.md, docs/HANDOFF_CURRENT.md, docs/WORK_PLAN_CURRENT.md
   a docs/WORK_DOCUMENTATION_POLICY.md. Zachovej cizí změny.
2. Fetchni origin a ověř skutečný stav [PR #376](https://github.com/KovoPraha/evidenceTreninku/pull/376).
   Zdroj S1 je `origin/codex/csc-sportsoft-s1-ingest-20261107`, ne automaticky main.
   Referenční celek **2fe8aa93** prošel 11 CI kontrolami; serverový kód je
   f61c365d, c539387e doplnilo výsledky. Tento prompt přichází následnou dokumentací.
   S0 je v [#371](https://github.com/KovoPraha/evidenceTreninku/pull/371).
3. Dokud S1 není sloučené, založ vlastní větev/worktree z uvedené S1 větve,
   např. `codex/csc-sportsoft-s2-s3-station-20261107`, v pracovní oblasti Evidence.
   Existující stejnojmennou větev nepřepisuj; zvol jedinečný suffix.
   Zaznamenej přesný base SHA a případné novější změny S1. Pokud už je S1
   v main, ověř přítomnost jeho kontraktu/kódu/testů a použij aktuální main.
4. Necommituj do cizí S1 větve, neprováděj force push, merge PR ani deploy.
   Před kódem zapiš zahájení do P15, registru a handoffu.

Načti hlavně:

- [S1 protokol a limity](CSC_SPORTSOFT_S1_KIS.md) a [ověření S1](verification/csc-sportsoft-s1-20261107/RESULTS.md).
- [Kontrakt v1](CSC_SPORTSOFT_S0_CONTRACT_V1.md), [JSON Schema](verification/csc-sportsoft-s0-20261107/contract.schema.json),
  [syntetické JSON fixtures](verification/csc-sportsoft-s0-20261107/fixtures.json)
  a [statický HTML podklad](verification/csc-sportsoft-s0-20261107/source-fixtures.html).
- [Průzkum bez osobních hodnot](verification/csc-sportsoft-discovery-20261107/RESULTS.md)
  a [S0 požadavky](CSC_SPORTSOFT_S0_STATION.md); neotvírej odkazované reálné portály.
- `api_external_sync.php`, `includes/external_sync*.php`,
  `includes/external_identity_match.php`, migraci `20261107210000_external_read_sync.php`
  a testy `ExternalReadSyncTest`, `ExternalSyncHttpTest`, `ExternalIdentityMatchTest`.

Kontrakt je autorita. Neměň PHP matcher, autentizaci nebo schéma jen pro
usnadnění workeru. Skutečný rozpor dolož malým syntetickým případem a předej
hlavnímu KIS pracovišti; implementuj ostatní nezávislé části.

## 3. Povolený vývojový režim

Kód patří do `workers/external-sync/`: TypeScript, Playwright, přesně připnuté
verze a lockfile, deterministické adaptéry bez LLM/Riki/Codex runtime závislosti.
Plánovanou Node řadu a kompatibilitu ověř proti S0 a dokumentaci výrobce;
nevyměňuj systémový Node ani jiné sdílené nástroje.

Povoleny jsou projektové vývojové závislosti, vlastní npm config/cache a
Playwright browser cache uvnitř nového vyhrazeného prostoru. Žádná globální
instalace, čtení osobního `.npmrc`, sdílený browser profil nebo změna systémové
konfigurace. Stažení připnutých závislostí z jejich oficiálních distribučních
zdrojů je oddělené od testovacího provozu; nepřidávej přístup k Riki infrastruktuře.

„Nic nezapínat“ znamená žádnou trvalou službu, scheduler, autostart,
produkční flag, firewall/TLS výjimku, OS účet/ACL změnu, restart/odhlášení
Windows, power změnu nebo přihlašování na portály. **Dočasné ručně spuštěné
testovací procesy nad syntetickými daty jsou povolené** a na konci se uklidí.

Test harness smí poslouchat jen na loopbacku na vlastních nově přidělených
portech. Worker má explicitní režim `synthetic`, výchozí disabled a allowlist
přesných testovacích originů/portů vytvořených tímto harness; žádný scan portů.
Odmítni jiné URL, redirect mimo allowlist, externí subresource a websocket.
To platí pro browser i transport. Reálné domény ČSC, Sportsoftu a produkčního
KIS se v tomto kroku **ani nenavštěvují**, i kdyby byly už přihlášené.
Nežádej hesla, tokeny ani skutečné exporty sportovců.

## 4. S2 — adaptéry ověřené na lokálních stránkách

Implementuj čtyři přesné kolekce: `csc_licenses`, `sportsoft_members`,
`sportsoft_events`, `sportsoft_registrations`. Poslední pouze pro existující
přihlášku konkrétního syntetického eventu. Scope ověřuj na každé stránce.

- Rozšiř vlastní syntetické stránky o asynchronní překreslení, změny DOM ID,
  stránkování, dělení velké stránky, ASP.NET podobný čtecí postback, chybějící
  záhlaví, změnu počtu, opakovanou stránku, nesprávný scope, login/captcha
  a ztrátu relace uprostřed běhu. Původní S0 fixture nevydávej za celý portál.
- Čti jen povolené buňky/pole. Licence: tooltip stavu a disabled Hostování;
  Sportsoft: popisky a povolené údaje. Neověřená ID zůstávají null, neodvozuj
  je z pořadí, jména ani neprůhledného parametru `m`.
- Zdrojové řetězce a úvodní nuly zachovej. Server vlastní normalizaci UCI,
  typované stavy a párování. Žádný `sportovec_id` ani jmenný fallback z workeru.
- Parser musí odhalit konec seznamu a filtry; `end_reached`/`filters_clear`
  nikdy nenastavuj automaticky bez důkazu z fixture. Změna schématu/scopu
  nebo stránkování znamená selhání, nikoli publikaci částečného výsledku.
- Přidej funkční pasti s počitadly pro save/create/delete/payment/export,
  editaci a změnu checkboxů. Test musí prokázat **nulu mutačních handlerů**.
  Nestačí blokovat všechny POST: povolené syntetické stránkování může POST
  potřebovat. Allowlist musí rozlišovat konkrétní čtecí akci a její parametry.
- `not_created` znamená hlášení stavu bez vytvoření přihlášky. Licence,
  přihlášky, kontakty, platby ani formulářové hodnoty se neupravují.

## 5. S3 — transport, obnovitelnost a soukromý stav

Implementuj `claim → upload_part → complete`, heartbeat a fail podle S1.
Testy musí používat skutečný S1 validator/ingest, nejen napodobeninu odpovědí.
Vytvoř vlastní izolovanou syntetickou DB z migračního/testovacího kódu,
bez načtení existujícího aplikačního config.php, dumpu nebo produkční DB.

- Jedno spojení = provider/scope/kolekce/event; token náleží jednomu spojení.
  Job ID, scope a lease přebírej ze serveru. Do testovací DB lze připravit
  pouze syntetické zařízení a token; žádný skutečný provisioning.
- Ulož přesné UTF-8 bajty dílu, hashuj tyto bajty, retry je neposerializuje
  znovu. Manifest obsahuje všechny receipt hashe v pořadí od 1. Zachovej
  stránkové indexy, offsety, počty a limity S1 (250 řádků / 1 MiB na díl).
- Jeden proces na spojení + zámek profilu. Deduplikaci, restart procesu,
  síťový výpadek a clock/lease scénáře simuluj bez restartu OS nebo Rikiho.
  Expirovaný běh se neoživuje; nová lease nesmí potvrdit starý job.
- 401/403/409 a změnu layoutu řeš zastavením příslušného běhu, 429 řízeným
  backoffem. Rozlišuj syntetickou ztrátu odpovědi po skutečně provedeném
  uploadu/complete a konflikt obsahu. Opakování musí být idempotentní.
- `review_required` je výsledek pro registráře. Worker nemá možnost baseline
  nebo pokles schválit. Testovací řídicí harness může samostatně použít
  servisní funkci S1 se syntetickým registrářem pro ověření druhého běhu;
  toto oprávnění nesmí být součástí workeru nebo device API.
- Privátní spool/profil/logy mají vlastní kořen mimo Git a synchronizované
  cloudové složky, žádné odkazy na Riki. Navrhni Windows ochranu klíče pod
  vlastním účtem (např. DPAPI CurrentUser), autentizované šifrování spoolu,
  atomický zápis, omezenou retenci/kvótu a obnovu po poškození. Ověř na
  syntetických datech; nepoužívej Riki vault ani tajemství. Konkrétní volbu
  zapiš, nevydávej prosté zakódování nebo ACL za šifrování.
- Retence spoolu: návrh S0 max. 72 h / 512 MiB, při plné kvótě zastavit nový
  sběr. Pro testy použij malé limity a řízené hodiny. Mazat lze pouze vlastní
  doložené soubory v testovacím kořeni; žádné systémové nebo sdílené čištění.
- Logy jen metadata, chyby z pevného slovníku; žádné HTML/DOM, jména, UCI,
  data narození, tokeny, cookies, request bodies nebo reálné cesty jiných účtů.

Testovací transport může použít lokální TLS s vlastní pouze testovací CA
nebo jasně označený loopback bridge volající skutečný `externalSyncDispatch()`.
Při bridge variantě je reálný HTTPS průchod endpointem NOT RUN. Neoslabuj
`api_external_sync.php`, nevypínej ověřování certifikátů ani nepřidávej
produkční HTTP bypass. Test harness není provozní služba.

## 6. Akceptace a předání

Požadované důkazy:

1. Čtyři kolekce projdou v1 schématem a skutečným S1; kanonické osoby/platby
   před a po shodné. Příjem není kopie serverové logiky v TypeScriptu.
2. Více stránek, dělená stránka, duplicita/missing UCI, počty a změna scope;
   žádné doplňování neznámých polí a nulové mutační handlery.
3. Chybějící díl, špatný hash, přerušený přenos, ztráta odpovědi, stejný retry,
   změněný retry, starý běh, dvě instance, expirovaná lease a revoked token.
4. Baseline/nula/pokles zůstávají review; poslední přijatý snímek se zachová.
5. Restart pouze vlastního testovacího procesu, šifrovaný spool, limity,
   poškození souboru a redakce logů; syntetický token nesmí uniknout do Gitu.
6. Pokus o jiný origin/port, redirect či cizí stavový adresář se odmítne.
   Testuj pomocí nových umělých cest/sentinelů, nikdy skutečného Riki adresáře.

Připrav README s přesnými příkazy pro čistou reprodukci, lockfile, verze,
strukturu workeru, vzorovou konfiguraci se synthetic/disabled defaultem,
závislosti a runbook budoucího odděleného Windows účtu. Instalační/scheduler
skript smí být jen explicitně nespouštěný návrh, nesmí ho spouštět npm lifecycle.
Pokud něco na stanici nelze provést bezpečně, uveď konkrétně NOT RUN a důvod;
náhrada mockem nebo Linux CI není důkazem Windows provozu.

Výsledek zapiš do `docs/verification/csc-sportsoft-s2-s3-station-20261107/RESULTS.md`:
base/worker SHA, verze, příkazy, PASS/FAIL/NOT RUN, limity, důkaz nulových
mutací a uklizených vlastních procesů. Nevkládej hostname, cizí účty/cesty,
osobní data ani tajemství. Aktualizuj P15, CURRENT/HANDOFF, registr,
EVENT/DECISION_LOG a katalog podle pravidel; vlastní ID `P15-S2S3-STATION`.
Předej commit/push a **vlastní draft PR** s vazbou na #376; neslučuj jej.
Spusť nové worker testy, příslušné kontraktové testy S1, dokumentační kontrolu
`php tools/check-current-docs.php` a `git diff --check`; chybějící nástroj
neřeš použitím Riki runtime. Uveď skutečný stav CI.

Konečný výsledek označ **„S2/S3 syntetická implementace“**, nikoli hotová
integrace skutečných portálů nebo nonstop provoz. Živé selektory, klubový scope,
nové ruční přihlášení, cílový účet/ACL, boot/lock testy a pilot zůstávají
NOT RUN pro další výslovně povolenou etapu. Nyní nic z toho neaktivuj.
