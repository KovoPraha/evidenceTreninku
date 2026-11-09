# Q2-TIME — ochrana rozepsaného tréninku

9. 11. 2026, Europe/Prague. Lokální ověření, bez produkčního zápisu.

## Příčina a rozsah

Dynamická šablona času používala v JavaScript stringu `\.`; lomítko se při
vytvoření HTML ztrácelo a tečka v patternu znamenala libovolný znak.
Například `00:13x456` mohl projít nativní kontrolou, ale server jej odmítl.
Serverové odmítnutí vracelo nový formulář bez vyplněných hodnot. Screenshot
s `13,456` dokládá chybný formát, nikoli přesnou síťovou posloupnost testerova
selhání; tu z fotografie nelze určit.

Oprava času platí pro nový i editovaný trénink. Koncepty a obnova platí pro
**nový zápis v formular.php**, včetně zápisu z plánu a duplikátu. Editace již
uloženého tréninku dostala kontrolu času, nikoli automatické koncepty.

## Ověření

- PHP syntaxe dotčených formulářů a helperu, `node --check`: PASS.
- `composer validate --strict`: PASS.
- `php vendor/bin/phpunit --filter 'TrainingSubmissionTest|SportsMeasurementInputTest|SportsMeasurementContractTest'`:
  **31 testů / 108 assertions PASS**.
- `php tests/Support/TrainingSubmissionSessionSmoke.php`: PASS, pending přes
  hranici requestů, odmítnutí opakování a původní výsledek po dokončení.
- Chromium přes Playwright CLI: **18 kontrol PASS**. Chybný čas neodeslán,
  konkrétní nápověda, obnova textu/času/osoby/vzdálenosti, otevření nové karty,
  zavření a znovuotevření, odmítnutí neplatné oddělovací tečky i nulového času,
  Zpět, správný čas odešle, nejistý výsledek uchová text bez slepého opakování
  a bez přepsání, serverové odmítnutí obnoví snapshot, jiný účet nic neuvidí,
  potvrzené dokončení odstraní koncept.

Browser běžel na syntetické lokální fixture s **aktuálním dynamickým blokem
měření extrahovaným z formular.php** a skutečným novým JS souborem. Serverové
odpovědi v posledních kontrolách byly simulované. Nejde o plnou přihlášenou
přejímku aplikace s DB ani o produkční UAT.
Reprodukce: `python tests/Support/TrainingDraftBrowserFixture.py`, PHP server
na 127.0.0.1:18519, otevřít `/output/playwright/training-form.html`, přes
Playwright CLI `run-code --filename tests/Support/TrainingDraftBrowserCheck.js`.
Výsledek je v `document.body.dataset.testResult`; úspěšný běh má `pass: 18`.

## Provozní hranice

Koncepty zůstávají v témže prohlížeči a oddělují se podle trenéra a formuláře.
Nejde o synchronizaci mezi zařízeními. Vymazání dat prohlížeče nebo soukromý
režim mohou koncept odstranit; při selhání úložiště se zobrazí upozornění.
Přílohy je nutné vybrat znovu. Při serverové chybě je navíc snapshot držen
u existujícího session submission klíče; CSRF ani klíč přihlášení se neobnovují.
Koncept se při odeslání nemaže. Dokončené zápisy se odstraní při dalším otevření
nového formuláře podle serverem potvrzených klíčů. Po vypršení session může
nejistý koncept zůstat k ručnímu ověření historie, nikdy se automaticky neodešle.
Původní dvouhodinová platnost submission klíče a kontrola duplicit zůstávají.


## Průběžná kontrola CI

První běh 42588826486 odhalil neúplnou DOM fixture staršího klávesnicového
testu: neobsahovala formulář, na který se nově připojuje obnova účastníků.
Fixture doplněna o skutečný form boundary a regresi obnovy chipů/ID bez
zdvojení; produkční kontrola nebyla oslabena. Lokální `composer analyse`
překročil výchozí limit 300 s; rozhodující dokončení statické analýzy vede CI.

Druhý CI běh 42589038957: PHPStan/styl PASS, PHPUnit 2409 testů měl jedinou
chybu v source-wiring kontrole původní konstrukce retry URL. Přechod na
existující `trainingSubmissionFormUrl` zachovává plan_id i submission_key
(ověřeno behaviorálním testem), navíc udržuje duplikát. Kontrola napojení
aktualizována na skutečné volání helperu; podmínky oprávnění a zápisu beze změny.
Lokální širší sada před převzetím #410: 2407 testů / 54863 assertions,
5 přeskočených kontrol na Windows, bez selhání. Aktuální rozsah uzavírá Linux CI.


## Předání implementace před pověřením k nasazení

Kódová hlava `9dad0dfc`: [všech 11 CI kontrol PASS](ci-summary.json),
[Linux / obě MariaDB 42589435512](https://github.com/KovoPraha/evidenceTreninku/actions/runs/42589435512)
SUCCESS: **2409 testů / 54943 assertions**, samostatná kontrola privátního
úložiště 3/45 PASS. Statická analýza/styl, CodeQL a dokumentace PASS.
Cílená rozšířená sada 34/131 PASS, Node našeptávač 6/6 PASS; finální browser
znovu 18/18 PASS. Vlastní testovací browser a PHP server 18519 ukončeny.

[PR #412](https://github.com/KovoPraha/evidenceTreninku/pull/412) obsahuje kód,
testy i souborové předání. Merge a nasazení **NOT RUN**; produkční formulář
se touto prací zatím nezměnil. Tento závěrečný zápis mění pouze dokumentaci.
Další krok: review a případně schválené nasazení, poté přihlášená produkční
přejímka chyby času a obnovení skutečného rozepsaného formuláře.

## Následné schválené vydání 9. 11.

Výše uvedené NOT RUN je historický stav při předání implementace.
#412 je nyní sloučený a nasazený; [produkční protokol a otevřená přejímka](../training-form-resilience-release-20261109/RESULTS.md)
obsahují přesný commit, CI, deploy, HTTP, shodu JS a blokaci přihlášeného UAT.
