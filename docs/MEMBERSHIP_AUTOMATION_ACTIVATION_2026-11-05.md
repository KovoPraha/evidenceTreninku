# Aktivace pravidelného zpracování členství

Uživatel 5. 11. 2026 výslovně požádal zapnout pravidelnou práci systému,
i když dosud nemá žádná členství ke zpracování. Pošta zůstává vypnutá.
Produkt Závodní tým zůstává konceptem; tento krok neschvaluje jeho prodej.

## Postup

1. Doplnit chráněný konfigurační workflow (main, production, společný zámek
   produkce-kis) a úzce vymezený CLI nástroj pro jediný příznak automatizace.
   Před zápisem ověřit produkci, dostupný worker a databázové schéma.
   Zálohovat soukromý config, změnit atomicky, ověřit v novém PHP procesu.
   Poštovní ani jiné platební přepínače se nemění. Výstup neobsahuje osobní
   údaje, klíče ani bankovní údaje.
2. Otestovat opakování, přepnutí, zachování ostatní konfigurace, zálohu,
   odmítnutí poškozeného PHP a bezpečnostní podmínky workflow; projít CI a sloučit.
3. Chráněným workflow zapnout automatizaci. Nastavit repozitářové proměnné
   SCHEDULE/APPLY=true, MAIL=false a ručně spustit existující worker v apply.
4. Ověřit výsledek a uložený úspěšný heartbeat. Aktualizovat provozní dokumentaci
   skutečnými běhy a stavem. Prázdný běh není důkazem skutečného doručení pošty
   ani vystavení či zaplacení budoucího dokladu.

## Pozastavení

Vypnout plán a zápis v proměnných workflow; chráněným konfiguračním workflow
vypnout příznak automatizace. Stávající doklady, platby a členství se nemažou.
Záloha configu je soukromá; nepřepisovat z ní pozdější změny jiných integrací.

## Stav

Dokončeno: [PR #318, produkční aktivace a první ostrý běh](verification/membership-automation-20261105/RESULTS.md)
ověřeny. Denní plán a zápis zapnuté; první apply 0 členství, 0 předpisů,
0 zpráv, žádná chyba. Následně ověřen úspěšný uložený heartbeat. Pošta vypnutá.
