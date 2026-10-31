# Příprava interního testování trenéry — 31. 10. 2026

Výchozí audit: obecná samoobslužná obnova hesla trenéra nebyla připravena.
Kontrolovaný zdroj: `8bf61e3d0837b8d475dd5fe0b6b75ad6177452e7`.
Následující audit zachycuje stav před implementací. Provozní stav nasazení
zůstává v [CURRENT_STATE](CURRENT_STATE.md).

## Implementace po schválení vlastníkem

**Navazující provozní oprava:** intervaly skutečných běhů existujícího GitHub
plánovače byly několik hodin (31. 10. 00:39 a 06:18 UTC), což nelze použít
jako spolehlivý transport hodinových resetovacích odkazů. Nasazen je proto
minutový cron hostingu, výhradně pro trenérské obnovy. Instalátor smí vytvořit
jen vlastní nový soubor v existujícím zapisovatelném `CRON.1minuta`; cizí obsah
nepřepisuje. Stav se dokládá aktuálním heartbeat skutečného hostitelského běhu,
nikoli jen konfigurací. Worker má společný zámek pro cron i ruční běh.
Spouštěcí soubor má práva `0700` podle [dokumentace hostingu](https://www.cesky-hosting.cz/napoveda/skripty-na-serveru/cron/);
samotné čtení bez práva spuštění by hostingový cron neaktivovalo.
GitHub workflow zůstává pro chráněnou instalaci, diagnostiku a ruční zpracování.
Dva úspěšné hostingové běhy 31. 10. v 09:23:03 a 09:24:03 UTC jsou
[doložené](verification/trainer-recovery-20261031/scheduler/RESULTS.md).
To potvrzuje plánovač trenérské fronty; příjemce ještě nepotvrdil skutečné doručení testu.

- Odkaz „Zapomenuté heslo“ na trenérském přihlášení vede na samostatný režim
  `booking/zapomenute_heslo.php?pro=trener`; spotřebování odkazu a návrat na
  `login.php` zachovávají trenérský kontext. Veřejná obnova zůstává oddělená.
- Používá se jednoznačný e-mail aktivního trenéra, evidovaný správcem klubu.
  Držení této schránky se prokazuje tokenem. Obnova nevytváří veřejný účet
  a nepropojuje účty podle shodného e-mailu.
- Již výslovně propojený veřejný účet musí být aktivní, ověřený a mít shodný
  e-mail. Obě hesla a revokační verze se aktualizují atomicky. Nejednoznačné
  nebo neplatné propojení vyžaduje opravu správcem, nikoli automatické přiřazení.
- Token platí 60 minut, v DB je pouze jeho účelově oddělený hash. Zašifrovaný
  e-mail se ukládá do stávající fronty ve stejné transakci. Před odesláním
  i použitím se znovu kontroluje účet, adresa, session verze a explicitní vazba.
  Změna hesla odvolá ostatní trenérské odkazy i veřejné odkazy propojeného účtu.
- Nová migrace přidává `trainer_password_reset_tokens`; její vlastnictví je
  součástí zálohování. SQLite rozšíření CHECK fronty zachovává zprávy i auditní
  vazby a je ověřeno opakováním. Existující MySQL frontu migrace nepřestavuje.
- `trainer-access-production.yml` původně plánovalo worker po pěti minutách;
  navazující oprava výše přesouvá plánování na hosting.
  Zpracovává jen `kind=reset,target_type=trainer`, nejvýše 20 zpráv za běh.
  Veřejné žádosti ani historická klubová oznámení tím nejsou aktivována.
  Skutečný běh je nutné doložit heartbeat, ruční spuštění jej nenahrazuje.
- Ruční `action=status` provádí pouze čtení: souhrn způsobilých účtů,
  trenérů bez aktuálního rozsahu a stav front. Výstup neobsahuje osobní údaje.
  Počty rozsahů nenahrazují lidské potvrzení správných skupin a členů.
- Vlastník schválil jednu transportní zprávu na `info@kovopraha.cz`.
  `action=probe` má pevného příjemce a jednorázový soukromý marker; nejistý
  výsledek se automaticky neopakuje. Přijetí transportem není doručení do schránky.

Lokální HTTP průchod na izolované SQLite ověřil skutečné formuláře, odmítnutí
neplatného CSRF, zařazení žádosti, změnu hesla, odmítnutí opakovaného odkazu,
návrat na trenérské přihlášení a úspěšné přihlášení novým heslem (HTTP 303).
PHP `mail()` bylo v tomto testovacím serveru zakázáno. Produkční účty se neměnily.

Před nasazením: cílené testy, úplné Linux CI včetně MariaDB 10.3/11.4,
PHPStan, styl, anonymní sweep, záloha a chráněný deploy. Po nasazení:
read-only stav účtů/front, GET formulářů, jeden schválený transportní test,
ověření workeru a potvrzení doručení vlastníkem. Skutečné trenérské rozsahy
se bez konkrétního zadání nepřidělují.

## Oddělení aplikací

EvidencePavel a Velocota mají samostatné repozitáře, nasazení a hostingové
účty/kořeny. EvidencePavel není modulem Velocoty. Doklady posledního nasazení
potvrzují nezměněnou konfiguraci EvidencePavel a vypnutou integraci Velocoty:
[srovnání vydání](verification/db-temp-recovery-20261030/release-comparison.json).
Obnova kapacity zasáhla pouze odvozený index `stats_search_postings` Velocoty;
nepřesouvala aplikační data mezi projekty. Nebyl zjištěn doklad smíchání aplikací.
Tato kontrola není úplným auditem všech produkčních řádků ani databázových grantů.

Obě aplikace ale sdílejí server replikant3544 a jeho systémovou kapacitu.
Samostatné domény a adresáře proto neizolují důsledky zaplnění databázového
disku. Úplné provozní oddělení vyžaduje samostatnou databázovou/serverovou
infrastrukturu; pouhé přesunutí webových souborů tento problém neřeší.

## Velocota — globální hledání ponechat vypnuté

Read-only měření 31. 10. 2026 v 04:05:04 UTC: systémový oddíl
3 811 250 176 B volných z 21 661 515 776 B. Předchozí měření po nasazení
30. 10. v 21:19 UTC: 5 598 212 096 B. Rezerva tedy poklesla přibližně
o 1,79 GB i při pozastaveném Search. Příčina tohoto poklesu není zjištěna.

Index postings zůstává prázdný, aktivní generace NULL, tokenized generací 0,
pozastavení cronu beze změny. Dvě skutečné diskové temporary tables vznikly
úspěšně. Původní index spotřebovával přibližně 3,35 GB; jeho návrat by nyní
spotřeboval většinu zbývající rezervy. Výsledek `ok` staré monitorovací sondy
není souhlasem s aktivací hledání.

Samostatný další úkol Velocoty: zjistit příčinu růstu a zajistit trvalou
kapacitu nebo změřit úspornější index. Teprve potom vytvořit čerstvou generaci,
ověřit její úplnost, běžné podmínky připravenosti a bezpečnou rezervu a obnovit
provoz. Zálohy a původní cron zůstávají zachovány.

## EvidencePavel — obnova hesla

- Živý `login.php` vrací HTTP 200, obsahuje trenérské přihlášení, ale nemá
  odkaz na zapomenuté heslo. Ověřeno neautentizovaným GET bez odeslání formuláře.
- `booking/zapomenute_heslo.php` i `booking/nove_heslo.php` vracejí HTTP 200
  a `no-store`. Text obnovy je určen rodičům a sportovcům; po změně hesla vede
  zpět na veřejné přihlášení.
- `passwordResetResolveTarget()` rozpoznává aktivní veřejný účet s ověřeným
  e-mailem nebo sportovce s oprávněným vlastníkem. Samostatného trenéra nevyhledává.
- Je-li veřejný účet již explicitně propojen přes `trener_id`, spotřebování
  tokenu aktualizuje také heslo aktivního trenéra a odvolá jeho starší relace.
  Shodný e-mail sám o sobě toto propojení nezaručuje.
- Syntetická SQLite kontrola skutečné funkce `passwordResetQueueRequest()`:
  samostatný trenér s e-mailem nedostal token; po explicitním propojení ověřeného
  veřejného účtu se cíl našel. Žádný skutečný účet ani heslo nebyly změněny.

Znovu spuštěné testy `PasswordResetTest`, `PasswordResetWiringTest`,
`PasswordSecurityTest` a `TransactionalNotificationWorkerTest`:
**21 testů / 125 assertions PASS**, PHP 8.2.12. Pokrývají mimo jiné propojeného
trenéra, jednorázovost, expiraci, odvolání relací a lokální práci fronty.
Neprokazují doručení do produkční schránky.

Produkční formulář používá šifrovanou frontu `auth_mail_outbox` a worker
`bin/club-event-notifications.php`. Aktuální [provozní dokumentace](identity-email-queue.md)
výslovně nemá doložený běh plánovače ani skutečné doručení. Tato kontrola
neprovedla produkční diagnostiku fronty, neodeslala e-mail a neurčila počet
trenérů s ověřeným propojeným účtem.

## Doporučený následující rozsah

1. Před další obecnou optimalizací dokončit přístup trenérů. Read-only inventura
   aktivních trenérů má zjistit pouze souhrnné počty dostupných/ověřených e-mailů,
   propojených účtů a kolizí, bez zveřejnění osobních údajů.
2. Doplnit odkaz a celý trenérský postup obnovy včetně správného návratu na
   trenérské přihlášení. Samostatný trenér musí mít bezpečně ověřený kanál obnovy;
   účty nepropojovat automaticky podle shody e-mailu a nepřebírat identity Velocoty.
3. Ověřit produkční frontu režimem `--status`, existující plán spouštění a následně
   na dohodnuté testovací schránce doručení, jednorázové použití odkazu, odmítnutí
   starého hesla a odvolání starších relací. Odeslání skutečné zprávy je samostatný
   krok; v tomto ověření nebylo provedeno.
4. Pro interní pilot ověřit přihlášení a oprávnění trenérů k jejich skupinám,
   sportovcům, tréninkům a docházce. Zachovat zbývající lidskou přejímku D12–D14.
5. Teprve podle výsledků pilotu otevírat další konkrétní optimalizace.
   W6–W15 jsou podle [uzavření refaktoringu](REFACTORING_CLOSURE_2026-10-30.md)
   technicky dokončeny a nasazeny; to není potvrzení všech uživatelských scénářů.

V této kontrole se neměnil produkční kód, konfigurace ani stav hledání.
