# Doručení do PPL Parcelboxu

Stav: implementováno lokálně, výchozí nastavení **vypnuto**. Nejde o automatické vytváření zásilek ani štítků. Nasazení a aktivace vyžadují samostatné provozní ověření.

## Co zákazník uvidí

- U zboží označeného `shipping_eligible` se v pokladně objeví osobní odběr zdarma a PPL Parcelbox za klubem nastavenou cenu. U přihlášeného nákupu musí být celý košík tvořen zasílatelným zbožím; akce a lekce do stejné PPL objednávky nepatří.
- Mapový widget PPL vrací `KM` kód. Server jej při objednání znovu ověří v PPL CPL API jako český `ParcelBox` a uloží název/adresu jako neměnný snapshot. Telefon je povinný jen pro PPL.
- Cena dopravy vstupuje do celkové částky objednávky a platby; klientský údaj o ceně je jen kontrolou proti zastaralému formuláři, nikoli zdrojem ceny.
- Detail objednávky ukazuje vybraný box, cenu dopravy a po předání také číslo zásilky. U PPL se nezobrazuje výzva k osobnímu odběru.

## Zprovoznění

1. Získat od PPL přístup pro CPL API sandbox/produkci a veřejný API klíč Widgetu 2.0 svázaný s doménou. Sandbox podle PPL řeší `CISteam@ppl.cz`.
2. Na **testovacím** serveru nastavit `PPL_API_MODE=test`, `PPL_CLIENT_ID`, `PPL_CLIENT_SECRET`. Testovací režim se na veřejné produkční doméně nenabízí. Na produkci nastavit `PPL_API_MODE=production` a produkční přístupy. Secret nikdy nezapisovat do databáze, Git ani formuláře.
3. Spustit migrace a ověřit jejich výsledek. V administraci objednávek otevřít **Doprava PPL**, zadat cenu, veřejný klíč widgetu a aktivovat nabídku po dokončeném sandboxovém testu. Změna je auditována. Produkty povolit pro zaslání v administraci produktu.
4. V sandboxu ověřit mapu, výběr `ParcelBox`, zrušení mapy, falešný nebo uzavřený kód, chybějící telefon, změnu ceny mezi načtením a potvrzením, osobní odběr, hosta i člena, celkovou částku, platební e-mail, předání a storno.

Pokud PPL API/mapa neodpovídají, objednávka do boxu se nevytvoří. Osobní odběr zůstává možný. Pokud chybí kterýkoli přístup nebo cena, nabídka PPL se zákazníkům nezobrazí.

## Provoz zásilky

Po zaplacení administrátor připraví objednávku, v **Klient PPL** vytvoří štítek na uložený `ppl_access_point_code` a teprve po fyzickém předání dopravci zadá číslo zásilky do administrace. Tím se objednávka označí jako předaná PPL, zákazníkovi se zařadí informační e-mail a číslo zásilky uvidí v detailu. Automatické vytvoření štítku, volání `/shipment/batch` a automatický tracking jsou záměrně mimo tuto první integraci; bez produkčních přístupů a smluvních parametrů je nelze bezpečně ověřit. Pokyny k vyzvednutí odesílá PPL.

## Technické reference

- PPL začátek integrace: https://developer.ppl.cz/web/guest/jak-zacit
- Widget 2.0: https://ppl-widget2.apidog.io/3-statick%C3%A1-html-integrace-2029154m0
- CPL `GET /accessPoint`: https://ppl-cpl-api.apidog.io/seznam-v%C3%BDdejn%C3%ADch-m%C3%ADst-13465887e0
- CPL token: https://ppl-cpl-api.apidog.io/z%C3%ADsk%C3%A1n%C3%AD-bearer-tokenu-13560034e0
