# Platební režimy aktivních produktů

Čtecí produkční ověření 42006624742 zjistilo 223 aktivních produktů, všechny
bank_transfer. Globální zapnutí SumUp tedy samo nezpřístupní kartu pro nové
objednávky. Vlastník následně výslovně zvolil **povolit kartu všem 223 aktivním
produktům pro nové objednávky**. Dosavadní uložené platební režimy se nepřepisují.

Připravený chráněný nástroj umí nejprve jen čtecí preview. Vyžaduje existující
jednoznačnou aktivní superadmin identitu, nic nezakládá ani nepovyšuje.
Po případném schválení použije přesný počet a SHA-256 otisk cílových produktů.
Cena, zveřejnění, neaktivní produkty a uložené platební režimy dosavadních plateb
se nemění. Jednotlivé změny používají stávající kanonickou službu a její audit.

Celá dávka není jedna transakce: každý produkt se mění a audituje atomicky.
Při přerušení lze se stejným otiskem bezpečně pokračovat; již nastavené produkty
se znovu nemění a audit se neduplikuje. Změna cílového seznamu nebo oprávnění
pokračování odmítne. Při potřebě okamžitého zastavení karty slouží existující
disable SumUp; uložené starší objednávky tím neztrácejí svůj bankovní režim.

Offline testy ověřují vymezený rozsah, nezměněné ceny a staré platby,
idempotenci, odmítnutí změněného otisku/počtu/oprávnění a skutečné přerušení
uprostřed dávky s následným pokračováním bez duplicitního auditu.
