# Zapnutí pošty členství — 5. 11. 2026

Vlastník výslovně schválil zapnout e-maily k dokladům a upomínkám. Denní
zpracování už běží; požadavek nyní zahrnuje i skutečný poštovní transport.

1. Čtecím chráněným workflow ověřit stav a frontu zpráv před změnou.
2. Rozšířit existující konfigurační workflow o samostatné mail-enable/mail-disable.
   Samostatný spravovaný blok upraví pouze MONTHLY_MEMBERSHIP_MAIL_ENABLED;
   automatizace a ostatní integrace zůstanou beze změny. Před zapnutím ověřit
   dostupnou PHP mail funkci a aktivní automatizaci, zachovat zálohu i atomický zápis.
3. Ověřit nezávislé přepínání obou příznaků, opakovatelnost a původní chování;
   sloučit po kontrolách. Na produkci zapnout mailový příznak a workflow proměnnou.
4. Spustit skutečný apply --send a ověřit heartbeat, stav fronty a přepínačů.
   Prázdný běh nedokazuje doručení do schránky. Nezakládat falešné doklady ani
   neposílat zkušební zprávy na odhadnuté adresy.
5. Zapsat skutečný výsledek do CURRENT_STATE, handoffu a runbooku. Pro pozastavení
   pošty stačí workflow MAIL=false a mail-disable; denní účtování může pokračovat.

Předprodejní příprava Závodního týmu (vystavovatel, tarif, publikace) zůstává
samostatná. Tímto se neaktivují ostatní komunikační fronty aplikace.

Stav: plán před změnou. Živé výsledky budou doplněny po ověření.
