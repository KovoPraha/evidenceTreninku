# W11 — fronty, producenti a transporty

Navazuje po W10. Před změnou znovu přečíst aktuální kód: starší dokumentace
o synchronních rezervačních e-mailech již neodpovídá aktuálnímu main.
Příznaky platebních integrací nejsou důkaz vypnuté pošty ani workerů.

## Rozsah a první inventura

- `auth_mail_outbox`: registrace/resend/reset, šifrované tělo a čerstvá validace
  tokenu; společný worker `bin/club-event-notifications.php`.
- `club_event_notifications`: rezervace, čekací listina, programové změny,
  objednávky/výdej; producenti booking/parent_journey/shop_payment_notification.
- `roster_message_recipients`: soupiskové zprávy, samostatný worker a existující
  production workflow. Před doručením znovu ověřuje oprávnění příjemce.
- `family_weekly_summaries` a `member_charge_reminders`: vlastní generování,
  preference/identity, čerstvá autorizace, samostatné CLI.
- Čtyři současné adaptéry `mail()` jsou v club_event_notification,
  family_weekly_delivery, member_charge_reminder a roster_message. Local-outbox
  je pouze pro localhost. WebPush v rezervaci zůstává samostatný best-effort efekt.

## Měření a gate

0/100/10000 syntetických úloh: status, claim a omezený drain; skutečné SQL,
řádky, čas, paměť. Celý migrovaný katalog na obou MariaDB, pouze testovací
adaptér/local-outbox a adresy example.test/invalid. Nikdy spustit produkční send.
Zpřenosnit dosavadní `OperationalDeliveryMariaDbRace.php` do vlastní náhodné
lokální DB; současně má starou závislost na pracovním adresáři B1.

Ověřit dvě soupeřící převzetí, rollback producenta, odmítnutý transport/retry,
revokaci, pád před/po předání transportu a stale claim. Neznámé doručení se má
držet k ruční kontrole, ne automaticky opakovat. Není to obecná exactly-once
garance poštovní služby. Diagnostika nesmí obsahovat adresy, tokeny či těla.

Sdílet jen prokázaně totožný mechanismus s měřeným přínosem; doménové
podmínky claimů a čerstvá autorizace nesmějí být nahrazené obecnou cache.
Pokud měření splní rozpočty, zapsat rozhodnutí ponechat ověřený kód.
Opravit zastaralé provozní návody a přesně oddělit kód/CI od skutečného
scheduleru a doručení do schránky. Merge/deploy po standardních kontrolách,
bez změny frekvence rozesílek nebo aktivace transportu. Poté W12–W15.
