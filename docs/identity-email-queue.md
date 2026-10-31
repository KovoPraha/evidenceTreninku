# Ověřovací a obnovovací e-maily

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
