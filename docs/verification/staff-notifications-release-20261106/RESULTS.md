# Pracovní upozornění – produkční vydání 6. 11. 2026

Vlastník výslovně schválil sloučení, nasazení, pravidelné zpracování a ověření zvonečku i odesílání e-mailů. Použity existující čekající žádosti; žádná žádost nebyla kvůli testu schválena ani zamítnuta.

## Vydání a kontroly

- [PR #330](https://github.com/KovoPraha/evidenceTreninku/pull/330) sloučen 06:06:51 UTC; živý commit `9c543ff9ef7870d3503ec7f38fc05f2b376d0a82`.
- Všech 11 kontrol na konečném headu `01bafd053b5e18f1945b6fe006b93fc91bbabf67` PASS; strom squash merge shodný. Linux 2280 testů / 42176 assertions, samostatné POSIX kontroly 3/45, MariaDB 10.3 i 11.4, PHPStan, styl a CodeQL PASS.
- Cílené místní testy 28/206 PASS. Opakovaný úplný místní test a PHPStan překročily Composer timeout 300 s; nejsou vykazovány jako místní PASS. Úplné ověření doloženo Linux CI.
- [Chráněné nasazení 42172409868](https://github.com/KovoPraha/evidenceTreninku/actions/runs/42172409868) SUCCESS. Aktivace 06:11 UTC, HTTP smoke PASS, schéma current / pending `[]`.
- Před aktivací vytvořena ověřená DB záloha `evidence_2026-11-06_060929_014837d4.sql.gz`: 385 tabulek, 2 triggery, 6286195 komprimovaných bajtů; SHA-256 `1606ab85f014777665f78789ec652afaa0146b28598087c454acc260d5684b32`. Záloha zůstává soukromá mimo webroot. Samostatná izolovaná obnova této nové zálohy nebyla v této přejímce spuštěna.
- Otisky všech 8 dosavadních plateb a VS před/po shodné (`unchanged: true`). Import kroužků a uživatelské UAT se deployem nezapínaly.

## Skutečný minutový provoz

| Kontrola | Výsledek |
| --- | --- |
| [Výchozí status 42172975579](https://github.com/KovoPraha/evidenceTreninku/actions/runs/42172975579) | 3 čekající žádosti, 6 oprávněných pracovních účtů, 0 událostí / upozornění / odeslaných zpráv; plánovač ještě neinstalovaný |
| [Instalace 42173094848](https://github.com/KovoPraha/evidenceTreninku/actions/runs/42173094848) | `installed: true`, `changed: true`, 06:13:54 UTC |
| [Nezávislý status 42173255204](https://github.com/KovoPraha/evidenceTreninku/actions/runs/42173255204) | 3 události, 18 osobních upozornění, 18 e-mailů přijatých transportem, 0 chyb; hostingový heartbeat 06:15:04 UTC, další běh již 0 zpráv |
| [Konečný status 42173897241](https://github.com/KovoPraha/evidenceTreninku/actions/runs/42173897241) | V 06:21:36 UTC stále 18 odeslaných, pending/processing/failed = 0; čerstvý hostingový běh 06:21:04 UTC bez dalších kopií, 3 původní žádosti stále čekají |

Surové agregované výsledky jsou v adresářích `status-before`, `install`, `status-first` a `status-final`. Neobsahují adresy ani údaje sportovců. První skutečný hostingový běh připravil 3 × 6 zpráv a poslední přijetí transportem nastalo v 07:14:30 databázového času (Europe/Prague).

Cron `CRON.1minuta/evidence-staff-notifications.php` spouští PHP každých 60 sekund. `executable: false` ve statusu je příznak souboru, nikoli neúspěch PHP spouštění: opakované skutečné hostingové heartbeat a dokončené zprávy běh prokazují. Ruční `process` nebyl použit. Samostatný worker vybírá pouze `staff_action`; starší nesouvisející klubová fronta ani obnova účtů se jeho aktivací neodesílají.

## Přihlášený produkční prohlížeč

Skutečný Chrome nad `https://kis.kovopraha.cz/staff_notifications.php`:

1. Zvoneček i přehled ukázaly 3 nepřečtené položky pro žádosti #5, #4 a #1.
2. Otevření odkazu `?notice=13` přes GET ponechalo počet 3.
3. „Otevřít k vyřízení“ přepnulo již oprávněný registrářský kontext a otevřelo přesnou agendu `eshop_identity_admin.php?request_id=5`; počet poklesl na 2.
4. Detail správně zobrazil „Přečteno · Čeká na vyřízení“. Přečtení nevyřídilo zdrojovou žádost.
5. „Označit jako nepřečtené“ obnovilo počet 3. Původní kontext Správce systému byl obnoven; zvoneček i osobní přehled zůstaly dostupné.

Změnil se pouze osobní stav přečtení a byl vrácen. Nebyla změněna oprávnění, hesla ani rozhodnutí o žádostech. Produkční schválení a zamítnutí nebylo prováděno kvůli testu; tyto přechody pokrývají automatické testy. Mobilní zobrazení bylo ověřeno v předchozí syntetické přejímce, tato produkční kontrola byla desktopová.

## Doručení a další obsluha

Odesílání je ověřeno skutečnými 18 přijetími poštovním transportem a prázdnou pracovní frontou bez chyb. Doručení do koncové schránky zatím příjemce nepotvrdil; stav `sent` se za toto potvrzení nevydává.

Pro kontrolu použít `staff-notifications-production.yml` na `main`, `action=status`, potvrzení `OVERIT-UPOZORNENI`. Čtecí status nic nevytváří ani neposílá. Instalaci není potřeba opakovat; automatický běh je aktivní. Dohled používá klíč `staff_notifications`. Výpis příjemců vychází z aktuálních aktivních registrářů, při jejich absenci ze superadministrátorů; samotná notifikace neuděluje další přístup.
