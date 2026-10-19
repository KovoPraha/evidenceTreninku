# Ověřovací a obnovovací e-maily

Lokální implementace z 19. 10. 2026. Produkční doručení zatím nebylo ověřeno. Před nasazením je nutná migrace `20261019100000_auth_mail_outbox` a funkční plán `bin/club-event-notifications.php`; samotný úspěch HTTP požadavku ani `mail()` doručení nedokazuje.

## Datový tok

Registrace uloží účet a hash ověřovacího tokenu a ve stejné DB transakci zařadí e-mail do `auth_mail_outbox`. Obnova hesla podobně uloží hash do `password_reset_tokens` a zašifrovanou zprávu do fronty. Původní dosud platný resetovací odkaz nová žádost neodvolá; spotřebování jednoho odkazu odvolá všechny aktivní tokeny daného účtu. Veřejná odpověď neprozrazuje existenci adresy. `booking/znovu_overit_email.php` vydá nový ověřovací token a zruší čekající staré zprávy.

Obsah zprávy s odkazem je v databázi šifrovaný XChaCha20-Poly1305. Pro podklíč se používá existující `PERSON_RC_KEYRING` a aktivní verze; při rotaci je nutné ponechat staré verze, dokud neodejdou nebo nevyprší všechny zprávy v dané verzi. Indexovací klíč `PERSON_RC_INDEX_KEY` zůstává samostatný. Token v URL je ve fragmentu, aby se nedostal do serverových access logů cílové stránky; prohlížeč jej předá až POST formulářem. Žádný admin náhled plaintextu/tokenů neexistuje.

CLI worker střídá `auth_mail_outbox` a `club_event_notifications` do společného limitu. Před odesláním znovu kontroluje stav tokenu, cílového účtu, e-mailu a u dětského resetu i platnou vazbu rodiče. Po pěti neúspěšných pokusech zůstane zpráva `failed`. Staré/neplatné tokeny se zruší bez odeslání. Pád po předání poště může způsobit duplicitní doručení (at-least-once), nikoli opakované použití tokenu.

Správce v `auth_mail_admin.php` vidí stavy, příjemce, typ, pokusy a chybu. Pouze stále platnou selhanou zprávu smí s důvodem a potvrzením auditovaně vrátit do fronty. Obecný provozní přehled signalizuje `failed`. Uživateli vypršelého odkazu nabídněte nový odkaz, nikoli ruční znovuodeslání starého.

## Předprodukční a produkční ověření

1. Nasadit migraci a ověřit `bin/migrate.php` v režimu `check`; bez migrace registrace bezpečně selže bez částečného účtu.
2. Zkontrolovat dostupnost všech potřebných verzí `PERSON_RC_KEYRING`, šifrovacího rozšíření Sodium a aktivního CLI CRON bootstrapu z `docs/thinline-deploy-runbook.md`.
3. Na vyhrazené TEST adrese vytvořit registraci, ověřit `pending → sent`, použít odkaz jednou, opakované použití odmítnout. Ověřit resend a reset, včetně selhání transportu a obnovy.
4. Zkontrolovat `auth_mail_admin.php?status=failed` a `eshop_notifications_admin.php?status=failed`, čas nejstarší čekající zprávy a skutečné doručení v testovací schránce. Při opakovaném selhání transportu neoznačovat uživateli zprávu za doručenou.

Nezměněné synchronní zprávy z jiných rezervačních cest (`booking/rezervovat.php`, `booking/waiting_list.php`, hromadné `odeslat_emaily.php`) zůstávají samostatným technickým dluhem; jejich šablony a idempotenci je třeba před přesunem inventarizovat. Viz datový audit F10.
