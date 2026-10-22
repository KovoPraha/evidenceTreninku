# Produkční kontrola Packety bez objednávky

Ruční workflow `diagnose-packeta-production.yml` na `main`, prostředí
`production`, potvrzení `OVERIT-PACKETU-VYPNUTOU`. Sdílí produkční zámek
a ověřené SSH spojení s ostatními provozními workflow. Aplikaci nenasazuje.
Diagnostika a bootstrap se dočasně kopírují pouze do soukromého
`data/.kis-deploy`, úklid probíhá i po selhání.

Používá již nasazený konfigurační soubor a implementaci checkoutu.
Databázové spojení má režim `READ ONLY` pro celou session. Před síťovými
voláními vyžaduje vypnutou dopravu, cenu 150 Kč, platný veřejný klíč
a vypnutou simulaci. Klíč ani přihlašovací údaje nevypisuje.

Ověřuje odmítnutí dopravy v checkoutu, osobní odběr bez dopravného,
skutečnou serverovou validaci českého Z-BOXu 22312 a odmítnutí běžné
pobočky 39209 i syntetického ID 999000001. Volání Packety pouze ověřují
výdejní místo; nevytvářejí zásilku. Výpadek sítě není úspěšný negativní test.
Před/po porovnává nastavení, počty objednávek a metadata e-mailové fronty
bez adresátů nebo obsahu zpráv. Souběžná cizí změna znamená neúspěšnou
kontrolu shody, nikoli důkaz, že diagnostika sama zapisovala.

Výsledek není dokončenou objednávkou, UAT zapnuté dopravy ani důkazem
obchodní aktivace účtu Packety. Příslušné výstupy jsou explicitně `false`.
Účet a ruční podání se kontrolují samostatně v přihlášeném Klientu Packety,
bez odeslání podacího formuláře a bez vytvoření štítku/skutečné zásilky.
