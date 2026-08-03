# Lokální rozcestník akceptačních scénářů M1

Stránka `testovaci_scenare.php` soustřeďuje scénáře A01–A10 na jednom místě.
Je určena vlastníkovi produktu pro ruční kontrolu propojení Evidence, e-shopu a KIS.

## Bezpečnostní omezení

- stránka odpoví jako nenalezená, pokud HTTP host, adresa serveru a adresa klienta
  nejsou přesně `localhost`, `127.0.0.1` nebo `::1`,
- pokud je nastaven `APP_HOST`, musí být také loopback,
- po lokální kontrole je nutné přihlášení administrátora,
- stránka nezobrazuje ani neukládá hesla a nenabízí obecný reset databáze,
- odkazy jsou rozdělené na zákaznickou část a administraci; pro souběžné role je
  vhodné použít oddělený profil prohlížeče nebo anonymní okno.

Reset demo dat je dostupný pouze administrátorovi přes CSRF chráněný a výslovně
potvrzený POST. Před spuštěním se znovu kontrolují všechny tři loopback hranice.
Existující `bin/seed-local-demo.php` se spouští přímo přes pole argumentů bez
shellu, s `APP_HOST=localhost` a časovým limitem. Jeho standardní i chybový výstup
se záměrně zahodí, protože obsah může zahrnovat testovací přihlašovací údaje.
Pokud `proc_open`, PHP CLI, seed nebo lokální `config.php` nejsou dostupné, stránka
reset pravdivě označí jako nedostupný.

## Význam stavů

- **Připraveno** — potřebné obrazovky existují a scénář lze ručně projít.
- **Částečně připraveno** — bezpečný dílčí tok existuje, ale v kartě je výslovně
  popsána zbývající produktová mezera.
- **Nedostupné** — alespoň jeden soubor cílové obrazovky chybí; stav se dopočítá
  při každém načtení a odkaz se nepovažuje za hotový jen podle dokumentace.

Rozcestník sám kromě výslovně potvrzené obnovy localhost seedu nic nemění. Ostatní
mutace se provedou až na cílových obrazovkách a pouze po jejich běžném potvrzení,
CSRF kontrole a autorizaci.
