# Finalizace a lokální akceptační scénáře M2

Stránka `testovaci_scenare.php` soustřeďuje scénáře A01–A10 na jednom místě.
Je určena vlastníkovi produktu pro ruční kontrolu propojení Evidence, e-shopu a KIS.

Horní panel **Závěrečná brána M2** odděluje automaticky ověřenou technickou
připravenost od výsledku vlastníkovy prohlídky. Při každém načtení kontroluje
dostupnost všech cest A01–A10, shodu migračního katalogu s databází a přítomnost
základních localhostových identit a demo nabídky. M2 označí jako uzavíratelné
teprve při zelených technických kontrolách, PASS 10/10 a nulovém počtu
blokujících výsledků. Panel nic neopravuje, nenasazuje ani neodesílá.

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

## Výsledky prohlídky

Každá karta A01–A10 má localhost-only formulář s výsledkem `PASS`, `PARTIAL`,
`FAIL`, `BLOCKED` nebo `Netestováno`, důležitostí a poli pozorované/očekávané
chování. Zápis vyžaduje administrátora a CSRF token. Hodnoty mají pevné enumy,
poznámky nejvýše 4000 znaků a ukládají se se zámkem do
`var/acceptance-feedback.json`. Soubor je v `.gitignore`, takže se do GitHubu
nemůže dostat omylem.

Tlačítko **Stáhnout výsledky pro GitHub / Cowork** vytvoří Markdown tabulku bez
hesel nebo automaticky načtených osobních dat. Ručně zadané poznámky musí vlastník
před commitem zkontrolovat. Reset demo databáze výsledky nemaže, takže lze
postupně projít role v oddělených relacích.
