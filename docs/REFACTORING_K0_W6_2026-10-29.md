# K0 a W6.1 — zahájení realizace

Zahájeno 29. 10. 2026 na pokyn vlastníka: zapsat plán a autonomně pracovat.
Komplexní plán byl po úspěšném CI sloučen v PR #219. Základ tohoto balíku je
`3cc60fb6d7fad4bc6430724529295ef025d75178`, větev
`codex/refactoring-k0-w6-characterization-20261029`, izolované worktree
`C:/Users/Marek/Documents/Codex/evidencePavel-refactoring-k0-w6-20261029`.

## Hranice prvního realizačního balíku

- K0.1: registr hlavních cest celé aplikace, jejich oprávnění, služeb, tabulek,
  vedlejších účinků, testů a dosud nezměřených profilů.
- K0.2 / W6.1: opakovatelný syntetický profil kapacity včetně vrácených řádků,
  času SQL a PHP, paměti a velikosti výsledku; samostatně posoudit fan-out týmů.
- W6.1: charakterizace časových hranic, deduplikace, quantity, legacy fallbacku,
  více fyzických týmů a čerstvého čtení. Zachovat existující race testy.
- Rozhodnutí o následujícím W6.2 opřít o výsledky; nepředstírat, že měření jedné
  domény dokončuje výkonovou analýzu všech přihlášených agend.

Tento balík mění testy, měřicí nástroje a dokumentaci. Aplikační změna bude
samostatný ověřitelný řez. Samotný testovací balík nevyžaduje produkční deploy.
Předchozí oprávnění ke sloučení a nasazení aplikačních změn po ověření platí;
nezahrnuje zapnutí integrací, ostré importy ani prohlášení úplného UAT.

## Brány

Charakterizace musí projít na nezměněném aplikačním kódu. Nové testy patří do
PHPUnit a MariaDB 10.3/11.4 CI. Žádná nová baseline výjimka, migrace, produkční
zápis, odesílání ani zásah do původního WIP. Měřicí data jsou výhradně syntetická.
Zpětný návrat tohoto balíku je revert testů/dokumentace, bez změny dat.

Implementován první řez: [registr 16 cest](REFACTORING_ROUTE_REGISTER_2026-10-29.md),
13 charakterizačních scénářů, doplnění dávkových hranic a měřicí PDO/profil.
[Výsledky a vzorky](verification/refactoring-k0-w6-20261029/RESULTS.md).
K0.1 je dokončeno; K0.2 celé aplikace zůstává průběžné. První W6.1 profil je
nad redukovaným schématem. W6.2 bude samostatná čistá extrakce, dávkové W6.3
vyžaduje další měření na migrovaném schématu. Před merge probíhá finální CI.
