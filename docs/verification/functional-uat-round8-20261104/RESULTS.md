# Osmý autonomní funkční průchod — 4. 11. 2026

**25 kontrol přípravy období PASS, 21 cílených testů / 190 assertions PASS.**
Skutečný místní browser ověřil přehled, stránkování, filtr a věková upozornění.
Nová chyba aplikace se v tomto rozsahu nepotvrdila; tato vlna nepřidává runtime změnu.
Proběhla souběžně s autorizovaným vydáním PR #297 a nutným bezpečnostním odblokováním PR #298.
Heslo, použitý obnovovací odkaz a T07 zůstávají SKIPPED.

## Prostředí a fixture

Nové worktree vydání z čerstvého main, následně zdroj
`0171ba5ad2c05c26caf4322db1837bb81fd396b7` včetně opravené vývojové závislosti.
Vlastní MariaDB 10.4.32 / functional_round4 / 127.0.0.1:3322 a samostatný
Apache/PHP 8.2.12 na 127.0.0.1:18422. Jde o zachovanou syntetickou DB dřívějších vln,
nikoli produkční dump/config. Nová session a soukromé adresáře patří této vlně;
webové mail/cURL transporty blokované. Testovací klient hlídá jméno DB, host, port,
datadir, vlastníka checkoutu a syntetický descriptor. Ostatní instance a WIP nedotčené.

Fixture **R8-b8d1d6**: rodičovská skupina #21, soupiska #22, později připojená
soupiska #23 a končící podskupina #24, celkem 32 syntetických osob. Den kontroly
1. 12. a 20. 12. 2026. Plány jsou neveřejné. Věkové pravidlo 10–13 let obsahuje
způsobilé osoby, jednu nad hranicí, jednu pod hranicí a jednu bez data narození.
[Identifikátory](fixture.json), [generátor](fixture.php.txt), [HTTP prefix](http-prefix.php.txt).
Zápisy sloužily pouze k přípravě vlastních místních dat.

## Provedené kontroly

| Oblast | Výsledek |
|---|---|
| Změny zařazení | K 1. 12. pokračuje 29 osob, jedna končí a jedna přibývá. K 20. 12. jsou počty 28/2/2 po zohlednění připojení a ukončení podskupin. Poslední den členství platí včetně. |
| Plánované lekce | Každá lekce vyhodnotí hierarchii ke svému dni. Později připojená podskupina se objeví až u odpovídající lekce; lekce po konci jiné podskupiny a zrušené lekce jsou vyloučené. Jedna lekce navázaná na dvě platné podskupiny se zobrazí jen jednou. |
| Stránkování a hledání | 31 osob na dvou stránkách 25 + 6. Extrémní stránka se omezí na poslední. Navigace uchová skupinu, den, hledání i filtr. Víceslovné hledání nerozlišuje velikost písmen; prázdný výsledek je výslovný. |
| Svěření a věk | Oprávnění končící přesně o půlnoci už k začátku dne neplatí, odvolané se nezobrazí. Věkové stavy jsou 26 způsobilých / 1 nad / 1 pod / 1 neznámé datum. Návrh navazující soupisky nic nezapisuje. |
| Role a odmítnutí | Registrář i vedoucí sportu přehled načtou; odkazy na osobu respektují jejich pracovní kontext. Trenér a anonymní návštěvník jsou odmítnuti. Neplatné datum, filtr a neexistující skupina vracejí 422; historická sezonní obnova 422 a zápisový POST 405. |
| Persistence | Shodné počty i hashe všech 12 sledovaných doménových tabulek před a po kontrolách, znovu i před a po browser průchodu. Žádné automatické členství, placené přijetí, platba ani úprava soupisek. |

[Strojový výsledek](season-http.json): **10 modelových, 14 HTTP a jedna souhrnná
kontrola persistence**, celkem 25. HTTP část provedla **15 skutečných požadavků**;
nejde o 25 browser scénářů. [Uložený pomocník](season-http.php.txt).

První kontrola HTML pomocníku omylem počítala tabulku klávesových zkratek v hlavičce;
byla opravena na tabulku v `main` a normalizovaný viditelný text. Nešlo o chybu aplikace.
Při přípravě fixture chyběly povinné snapshot údaje vazby lekce; transakce se vrátila zpět
a generátor byl opraven před úplným průchodem.

## Skutečný browser a hranice

ChatGPT in-app browser se přihlásil existujícím místním účtem **round4-registrar**.
Otevřel skupinu k 1. 12., přešel na druhou stránku a viděl přicházející i končící osobu.
Na soupisce #22 ověřil tři různé věkové výjimky a text, že návrh nemění členství ani
placený nárok. Skutečný výběr filtru končících zařazení ukázal jedinou správnou osobu.
Po aktualizaci dev závislosti byla stránka znovu načtená. Nic se formulářem nezapisovalo.
[Browser souhrn](browser.json), [druhá stránka](preparation-page-two.png), [věková kontrola](age-review.png).
Snímky vizuálně zkontrolované a obsahují pouze vlastní syntetická data.

Produkční přihlášení v dostupném browseru chybělo; přihlášené produkční UAT se netvrdí.
Jeho omezení a samostatné technické ověření nasazení popisuje
[protokol vydání](../functional-round7-release-20261104/RESULTS.md).
Pomocné karty zavřené, vlastní Apache vypnutý a MariaDB doložila Normal shutdown / Shutdown complete.
Syntetická DB a důkazy zachované; příprava není provedení E8, poslední synchronizace
ani lidská/účetní přejímka. Tyto body zůstávají otevřené.
