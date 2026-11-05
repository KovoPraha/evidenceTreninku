# Desátý autonomní funkční průchod — 5. 11. 2026

Výchozí main `402f8afe0dd2aa6ad208a78fb26511ecd6877abb`, vlastní větev/worktree
`codex/functional-uat-round10-20261105`. Vlastník povolil testování, opravy a testy;
nový merge/deploy není součástí této vlny. Heslo, použitý odkaz a T07 vynechat.

1. Ověřit vlastní syntetickou MariaDB na 3322, datadir, session a blokované transporty.
   Podle potřeby aplikovat pouze chybějící migrace na tomto sandboxu.
2. Prověřit položkové výdeje a vratky: role, CSRF, opakování, částečné kusy,
   stav skladu, výměnu a návaznost z konkrétní objednávky do vlastního případu.
3. Vybrané uživatelské cesty provést skutečným browserem. Nový nález nejprve
   reprodukovat selhávajícím regresním testem, potom opravit a znovu ověřit.
4. Uložit protokol/důkazy, aktualizovat dokumentaci a připravit PR se zelenými kontrolami.
   Vypnout pouze vlastní pomocné procesy, zachovat cizí WIP a syntetická data.

Zápisy pouze do vlastních místních fixture LOCALHOST/NEPLATIT. Žádná skutečná
platba, rozesílka, import nebo změna integrace. Produkční UAT nelze odvodit z místních výsledků.
Stav: F10 potvrzena v HTTP i Chrome a opravena místně. [Protokol](RESULTS.md) obsahuje
červené/zelené důkazy, původní 33 kontroly před/po, nových 38 kontrol, souběhy 19+6
a závěrečnou cílenou sadu 47/617. Browser došel přes všech šest stran k nejstaršímu
případu a zachoval 40 tabulek. [Úklid](cleanup.json) potvrzuje vypnutí pouze vlastních
služeb a zavření pomocné karty; data zachovaná. Další krok je samostatný review a
případné rozhodnutí vlastníka o merge/deploy. Heslo a T07 se touto vlnou neřeší.
