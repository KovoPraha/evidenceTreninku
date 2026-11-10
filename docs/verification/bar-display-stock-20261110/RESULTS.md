# BAR-DISPLAY-STOCK — průběžný protokol 10. 11. 2026

[Rozsah, akceptace a rozhodnutí vlastníka](../../BAR_DISPLAY_STOCK_PARITY_2026-11-10.md).

## Implementace

- Převzaté původní TV šablony `public/obrazovka.php` a `public/obrazovka2.php`, oddělený textový dvousloupcový ceník a střídající tři fotografické karty. Přizpůsobení výšce, původní barvy, typografie, velikosti, přechody a mobilní chování zachované. Připojená pouze data KIS a jeho bezpečné obrázky.
- [Bajtová shoda původních CSS](original-css-parity.json), ceny nadále z celých haléřů, escapované HTML a bezpečný JSON. Tiskový PDF tok je oddělený.
- Samostatný převod obou skladů podle `source_id`: konkrétní soukromý snímek, uzamčený zdroj, transakce cíle, audit obou skladů, ochrana proti nové zásobě/historii/rezervacím a opakování bez přičtení. Původní katalogový import se neopakuje. Převod zároveň přebírá pouze pět veřejných voleb vzhledu původní obrazovky, nikoli platební konfiguraci.
- Chráněné `bar-stock-production.yml`: inspect → čerstvá úplná záloha a skutečná obnova → apply konkrétního otisku → nezávislý status. Nejistý zápis se automaticky neopakuje. Souhrny neobsahují hesla ani dumpy.
- Původní dvě TV URL získávají odlišné pevné cíle `?view=text` / `?view=photos`. Následné přepnutí URL může použít aktuální status zásob místo již neplatného požadavku nulové provozní historie; úplná souborová záloha a její obnova zdroje zůstávají povinné.

## Dosavadní ověření

- 28 cílených PHP testů / 538 assertions PASS, včetně bezpečného vykreslení, tiskové větve, starých URL a transakčního převodu/replay/konfliktu/rolí.
- Node test původního chování PASS: právě tři různé karty, výměna po 10 sekundách a 720 ms přechod, bez okamžitého opakování předchozích tří.
- Dokumentační kontrakt, katalog plánu a nezměněné archivní bloky PASS.
- Přímý PHPStan celého projektu po obejití pouze časového limitu Composeru PASS (bez chyb). Lokální plný Composer test a první PHPStan běh narazily na časový limit 300 s; úplná testovací sada zůstává na Linux CI. Časový limit není vykázán jako PASS.
- Syntetické HTML obou obrazovek s 45 produkty vzniklo bez DB a bez produkčních zápisů. Vizuální Computer Use se opakovaně nepodařilo inicializovat, včetně Chrome; živá vizuální přejímka zatím NOT RUN.

## Dosud neprovedené

První úplný Linux běh odhalil dvě integrační opomenutí: nonce u externích skriptů a registraci obou záměrně izolovaných TV šablon v inventuře UI. Opraveno bez oslabení CSP. Kompresní regresní profil nadále porovnává totožné vstupní bajty před/po gzip a HTTP/cache/browser kontrakty, ale používá aktuální commit; historický snímek lze reprodukovat přes `JS_COMPRESSION_SOURCE_REF`. Původní globální zákaz všech změn aplikace proti historickému commitu nebyl regresním kontraktem dalších vydání.

Úplná produkční záloha a izolovaná skutečná obnova [42851495227](https://github.com/KovoPraha/evidenceTreninku/actions/runs/42851495227) SUCCESS: 389 tabulek a 4595 souborů. Žádný aplikační nebo skladový zápis tím nebyl proveden.

Sloučení a nasazení této změny, skutečné čtení/změna skladových zásob a nové přepnutí starých TV URL jsou zatím NOT RUN. Níže budou připojené skutečné běhy a výsledky; testovací úspěch není doklad produkčního převodu.

Předání implementace: [PR #440](https://github.com/KovoPraha/evidenceTreninku/pull/440). Souběžná změna #438 je zachovaná; její vydání má samostatné schválení podle svého protokolu. Žádné prod zásoby zatím nebyly měněné.
