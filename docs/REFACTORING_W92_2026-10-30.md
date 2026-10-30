# W9.2 — košík a souběžné skladové operace

Plán před změnou, 30. 10. 2026. Navazuje na W9.1 skladový přehled.

1. Změřit košík 1/10/50 položek, goods i programy, s členskou cenou
   a bez ní. Posoudit dostupný kontext čtení programů a cross-offer kapacitu.
   Podle důkazů přijmout oddělenou čtecí změnu, nebo zapsat důvod ponechání.
2. Zpřístupnit existující skutečné MariaDB race pro rezervaci posledního
   kusu, příjem, fyzickou vratku, opakovaný výdej a výdej versus storno v CI.
   Nahradit závislost na lokálním B1 prostředí izolovanou náhodnou DB.
   Zachovat skutečné pozorování LOCK WAIT a původní doménové aserce.
3. Nezměnit zápisové zámky, idempotenci, minor units, cenové zdroje,
   finanční role, VS ani aktivaci plateb. Žádné skutečné objednávky/platby.
4. Plná regrese a MariaDB 10.3/11.4; velikosti fixture a omezení měření
   uvést v protokolu. Browser a souvislý tok navazují W13/W15.

Před změnou košíku musí být konkrétní měření a shoda výsledku proti
zmrazenému baseline. Souběhové skripty samy nejsou produkční změnou.
