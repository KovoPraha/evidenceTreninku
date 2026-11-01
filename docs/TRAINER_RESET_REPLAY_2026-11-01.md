# Ověření a oprava opakovaného otevření obnovovacího odkazu

1. 11. 2026, Europe/Prague. Pracovní větev `codex/trainer-reset-replay-20261101`,
samostatné worktree `evidencePavel-trainer-reset-replay-20261101`, základ
`origin/main` = `97a0ff213a871639f7708d99613282f73652302e`.

## Oprávnění a hranice

Vlastník požádal prověřit kód, opravit případnou chybu a otestovat bez nasazení
do produkce. Žádné produkční resety, hesla, odkazy, účty nebo data se nepoužijí.
Původní UAT artefakty v jiném worktree zůstávají zachované. Testy používají
výhradně syntetická data. Neprovádět merge ani deploy.

## Zjištění a plán

- Zápisová služba trenéra vybírá nevyužitý platný token pod zámkem, kontroluje
  aktuální účet a verzi relace a v jedné transakci mění heslo, verzi relace
  a spotřebuje odkazy. Stávající test již odmítá druhé použití.
- Stránka `booking/nove_heslo.php` na GET vždy zobrazí heslový formulář,
  i pro použitý nebo neplatný odkaz; kontroluje platnost až při změně hesla.
  To je potvrzený nedostatek UI. Skutečný druhý zápis v produkci není doložen.
- Při chybě validace klient navíc naplní token pouze z fragmentu, který už byl
  odstraněn z adresy; oprava musí zachovat možnost opravit chybně vyplněná hesla.

Postup:

1. Reprodukovat a posílit testy jednorázovosti a neměnnosti hesla/verze relace.
2. Sdílet bezpečné čtení platnosti mezi předběžnou kontrolou a zápisovou službou;
   zápis si zachová transakci a zamykající kontrolu.
3. Před zadáním hesla předat fragment přes CSRF chráněný POST, bez tokenu v URL.
   Použitý/neplatný odkaz zobrazí odmítnutí bez heslového formuláře.
4. Otestovat validní/spotřebovaný/expirovaný odkaz, chybná hesla, zachování tokenu,
   opakovaný POST a zachování původního hesla/verze relace. Prověřit trenérský
   i sdílený veřejný/sportovní režim.
5. Spustit přiměřené kontroly, celý PHPUnit, syntaxi a diff-check; připravit
   změnu k review a doložit omezení. Produkční UAT ani deploy tím nevzniknou.

Stav: vyšetřování, implementace a lokální ověření dokončeny. Cílená sada
20/215 a JavaScript 2/2 PASS, syntetické srovnání původního a nového vykreslení
potvrzeno. Původní služba nezávisle 1/14 PASS. Celý PHPUnit 1900/38141 se třemi
Windows skipy, PHPStan bez chyb, style, syntaxe, dokumentační kontrakt,
anonymní inventory a Composer konfigurace PASS. Změna připravena k review
a Linux CI; bez merge a deploye. [Protokol a omezení](verification/trainer-reset-replay-20261101/RESULTS.md).
