# Ověřovací a obnovovací e-maily

## AUTH-MAIL-01 — oprava automatického zpracování rodičovské fronty (21. 11. 2026)

**Stav: K OVĚŘENÍ — kód připraven, produkční diagnostika otevřená.** Vlastník: vývoj KIS;
větev `codex/auth-mail-worker-repair-20261121`, oddělený worktree z `origin/main`
`11505aed`, převzaté souběžné `main` `5acd225f` v PR #506. Zadání vlastníka: opravit automatické zpracování ověření účtu a obnovy
hesla rodičů, bezpečně převzít existující frontu, neřešit výpadek ručním retry.
Podklad hlášení: tři `pending`, nula `processing` a `failed`, šest `sent`; dva
starší příklady mají nula pokusů a prošlý čas `available_at`. Konkrétní adresy
ani tokeny se do Gitu nezapisují. Tyto počty jsou hlášení, ne nezávislé produkční
měření.

**Předběžná příčina v kódu:** `bin/club-event-notifications.php` zpracovává obě
fronty, ale v repozitáři není jeho produkční schedule ani instalátor hostitelského
CRONu. `trainer-access-operations.php` filtruje pouze `target_type=trainer` a
`kind=reset`. Proto rodičovský `pending` s nula pokusy odpovídá neaktivnímu
plánovači. Produkční příčinu je nutno potvrdit čtecím stavem: poslední běh
`transactional_notifications`, skutečná přítomnost CRON souboru, počty `due`,
připravenost schématu/klíčů, místní transport a bezpečný souhrn logů. Konfigurace
odesílání používá PHP `mail()`; projekt nemá vlastní SMTP klient ani SMTP
přihlašovací údaje.

**Volba a rozsah:** před zapnutím výdeje doplnit read-only diagnostiku přes
chráněné GitHub Actions. Poté zavést samostatný vlastněný hostitelský plán pro
rodičovské `verify` a `reset`, který nevyzvedne trenérské resety ani klubové
notifikace. Využije stávající claim/token validaci a omezení pokusů; nebude
vytvářet účty ani nové odkazy. Alternativa spustit nyní společný worker by
zpracovala i nezkontrolovanou klubovou frontu. Alternativa ruční retry jednotlivých
řádků neřeší automatiku a může vytvořit nejisté duplicitní doručení.

Postup a akceptace:

1. Čtecím během zjistit skutečnou konfiguraci, stav due/failed a CRONu bez
   příjemců, tokenů a těla zpráv. Rozlišit chybějící plán od chyby bootstrapu.
2. Na syntetické DB ověřit oddělený výběr veřejných `verify`/`reset`, expiraci,
   souběh, odmítnutý transport a stárnoucí claim. `failed` má mít viditelný důvod;
   známé odmítnutí dodržuje pět pokusů, nejisté doručení se neopakuje automaticky.
3. Vydat kód a provozní instalátor; před aktivací znovu číst produkční frontu,
   zejména platnost starých tokenů a konfiguraci klíčů. Instalovat pouze vlastní
   CRON soubor atomicky, s kontrolou identity, zámkem a heartbeat. Cizí úlohy
   neměnit.
4. Ověřit přirozený tick, `pending → sent/cancelled/failed`, počet pokusů a
   skutečné doručení obou typů na řízené TEST schránce. `sent` dokládá přijetí
   transportem, nikoli doručení do schránky. Produkční test a doručení zatím
   **NOT RUN**. Přetrvávající skutečné nedoručení vyžaduje samostatné prověření
   hostitelského mail transportu a jeho logů.

Výsledky a předání: [protokol](verification/auth-mail-worker-20261121/RESULTS.md),
[registr](WORK_PLAN_CURRENT.md#auth-mail-01--rodičovské-ověřovací-a-obnovovací-e-maily-21-11-2026).

Implementace v této větvi: `parent-auth-mail-operations.php` má `status`, `install`
a `process`. `status` čte souhrny bez příjemců a tokenů, poslední běh původního
společného workeru a vlastní heartbeat. Chráněný workflow
`parent-auth-mail-production.yml` kontroluje hash nasazeného kódu; po vydání
nejprve spustit `status` s potvrzením `OVERIT-RODICOVSKOU-POSTU`. Teprve po
vyhodnocení výsledku spustit `install` s `ZAPNOUT-RODICOVSKOU-POSTU`.
Hostitelský minutový CRON používá neblokující zámek a limit 20 zpráv na běh;
`process` vybírá pouze `public` ověření/obnovu a `child` obnovu. Instalátor
odmítne cizí soubor a nemění jiné crony. Výchozí vydání samotného kódu nic
neodesílá. Vypršelé nebo nahrazené odkazy worker zruší; uživatel si pak vyžádá
nový odkaz. Syntetické testy neprokazují produkční doručení.

Trenérská obnova připravená 31. 10. 2026 používá stejnou šifrovanou frontu,
ale vlastní tokeny a `target_type=trainer`. Její samostatný chráněný worker
`trainer-access-production.yml` vybírá výhradně trenérské obnovy; nezapíná
historickou veřejnou nebo klubovou frontu. Plánovaný interval je pět minut,
skutečný provoz a doručení je třeba ověřit. Podmínky a přejímka jsou v
[přípravě trenérů](TRAINER_READINESS_2026-10-31.md); nasazení v CURRENT_STATE.

Fronta je součástí kódu `main` od 19. 10. 2026; lokální doplnění diagnostiky a testů z 20. 10. 2026 samo o sobě není nasazením. Produkční doručení zatím nebylo ověřeno. Worker vyžaduje migraci `20261019100000_auth_mail_outbox` a provozně ověřený plán `bin/club-event-notifications.php`; samotný úspěch HTTP požadavku ani `mail()` doručení nedokazuje.

## Datový tok

Registrace uloží účet a hash ověřovacího tokenu a ve stejné DB transakci zařadí e-mail do `auth_mail_outbox`. Obnova hesla podobně uloží hash do `password_reset_tokens` a zašifrovanou zprávu do fronty. Původní dosud platný resetovací odkaz nová žádost neodvolá; spotřebování jednoho odkazu odvolá všechny aktivní tokeny daného účtu. Veřejná odpověď neprozrazuje existenci adresy. `booking/znovu_overit_email.php` vydá nový ověřovací token a zruší čekající staré zprávy.

Obsah zprávy s odkazem je v databázi šifrovaný XChaCha20-Poly1305. Pro podklíč se používá existující `PERSON_RC_KEYRING` a aktivní verze; při rotaci je nutné ponechat staré verze, dokud neodejdou nebo nevyprší všechny zprávy v dané verzi. Indexovací klíč `PERSON_RC_INDEX_KEY` zůstává samostatný. Token v URL je ve fragmentu, aby se nedostal do serverových access logů cílové stránky; prohlížeč jej předá až POST formulářem. Žádný admin náhled plaintextu/tokenů neexistuje.

CLI worker střídá `auth_mail_outbox` a `club_event_notifications` do společného limitu. Před odesláním znovu kontroluje stav tokenu, cílového účtu, e-mailu a u dětského resetu i platnou vazbu rodiče. Po pěti známých odmítnutích transportu zůstane zpráva `failed`; mezi pokusy platí prodleva. Staré/neplatné tokeny se zruší bez odeslání. Výjimka transportu, selhání uložení výsledku nebo převzetí starší 15 minut znamenají nejasné doručení: úloha zůstane k ruční kontrole a automaticky se neopakuje. Nejde o garanci doručení právě jednou; před ručním opakováním musí správce ověřit původní výsledek.

Před převzetím první zprávy se ověří struktura obou front; chybějící fronta tedy nezpůsobí částečné odeslání z druhé. Parametry `--limit=1..99`, `--transport=mail|local-outbox` a výstup běžného zpracování zůstávají zachovány.

Správce v `auth_mail_admin.php` vidí stavy, příjemce, typ, pokusy a chybu. Pouze stále platnou selhanou zprávu smí s důvodem a potvrzením auditovaně vrátit do fronty. Obecný provozní přehled signalizuje `failed`. Uživateli vypršelého odkazu nabídněte nový odkaz, nikoli ruční znovuodeslání starého.

## Kontrola bez odesílání

S explicitním `APP_HOST` a nakonfigurovanou databází lze spustit:

```text
php bin/club-event-notifications.php --status
```

Tento režim provádí pouze čtení. Nevyzvedává zprávy, nemění jejich stav, neposílá
poštu a nevytváří lokální adresář ani soubory. JSON obsahuje počty podle stavu,
počet zpráv připravených k odeslání, stáří nejstarší čekající zprávy v sekundách,
počet převzetí starších 15 minut a nedostupných verzí šifrovacích klíčů.
Neobsahuje příjemce, obsah zpráv, tokeny, hashe ani texty chyb transportu.

`ready=true` znamená dostupnost ověřovaných struktur front, konfigurace šifrování
a lokálních předpokladů zvoleného transportu. Neověřuje všechny registrační
tabulky ani platnost každého odkazu; tu worker kontroluje při zpracování.
`attention_required=true` signalizuje chybějící předpoklad, selhanou zprávu,
zastaralé převzetí nebo čekající zprávu starší 15 minut. Návratový kód je `0`
pouze pro připravenou frontu bez těchto upozornění; jinak `1` (neplatné CLI
argumenty `64`). Chyba připojení/konfigurace skončí `1` bez citlivého detailu.

`scheduler_verified=false` a `mailbox_delivery_verified=false` jsou výslovné
meze této diagnostiky. Existující plán zpráv soupiskám není důkazem běhu tohoto
workeru. U `mail` se neprovádí síťový test SMTP. U `local-outbox` se kontroluje
localhost a zapisovatelný existující nadřazený adresář bez zkušebního zápisu.

Test `TransactionalNotificationWorkerTest` používá výhradně SQLite v paměti,
náhodné testovací klíče a adresy `example.test`. Ověřuje skutečné uložení
rozšifrovaného ověřovacího e-mailu a klubové zprávy do dočasného lokálního
outboxu, společný limit obou front, selhání a opakování po prodlevě, zrušení
neplatného tokenu i read-only diagnostiku se zapnutým SQLite `query_only`.
Nezapojuje aplikační databázi, SMTP ani produkční plán.

## Předprodukční a produkční ověření

1. Nasadit migraci a ověřit `bin/migrate.php` v režimu `check`; bez migrace registrace bezpečně selže bez částečného účtu.
2. Zkontrolovat dostupnost všech potřebných verzí `PERSON_RC_KEYRING`, šifrovacího rozšíření Sodium a aktivního CLI CRON bootstrapu z `docs/thinline-deploy-runbook.md`.
3. Na vyhrazené TEST adrese vytvořit registraci, ověřit `pending → sent`, použít odkaz jednou, opakované použití odmítnout. Ověřit resend a reset, včetně selhání transportu a obnovy.
4. Zkontrolovat `auth_mail_admin.php?status=failed` a `eshop_notifications_admin.php?status=failed`, čas nejstarší čekající zprávy a skutečné doručení v testovací schránce. Při opakovaném selhání transportu neoznačovat uživateli zprávu za doručenou.

Rezervační cesty již používají producenty fronty; starší audit F10 zachycuje
historický stav. Aktuální transportní adaptéry `mail()` jsou v klubových
oznámeních, soupiskových zprávách, rodinných souhrnech a upomínkách.
Soupiskový worker a jeho plán jsou samostatné. WebPush v rezervaci zůstává
oddělený best-effort efekt. Inventuru a syntetické kontrakty uvádí
[W11](REFACTORING_W11_2026-10-30.md); nejsou důkazem skutečného doručení.
