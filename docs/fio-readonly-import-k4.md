# Fio synchronizace a párování plateb

Stav 20. 10. 2026: token odpověděl pro účet 2302794191/2010
(`CZ6820100000002302794191`) a kód pro doplnění historie i bezpečné potvrzení
jednoznačných shod je nasazený. Po neúspěšném prvním importu byl produkční
`FIO_IMPORT_ENABLED` ručně vypnut; oprava formátu data Fio je nasazená, další
import čeká na opětovné povolení. `FIO_AUTO_CONFIRM_ENABLED` zůstává vypnutý.
Desetiminutový plán je připravený, ale job se přeskočí, dokud repozitářová
proměnná `KIS_FIO_SCHEDULE_ENABLED` není `true`.

## Kam uložit token

1. Ve Fio Internetbankingu v Nastavení → API vytvořte samostatný token pro
   klubový účet s oprávněním **Sledování účtu**, bez zadávání plateb.
2. V repozitáři [KovoPraha/evidenceTreninku](https://github.com/KovoPraha/evidenceTreninku)
   otevřete **Settings → Environments → production → Environment secrets →
   Add environment secret**.
3. Jméno: **`KIS_FIO_API_TOKEN`**. Hodnota: vytvořený token.
   Jde o Secret, nikoli Variable.
4. Uložení secretu samo nespouští workflow ani import. Pro změnu režimu slouží
   workflow „Nastavit Fio synchronizaci na produkci“.

Token nepatří do chatu, Gitu, dokumentace, příkazové řádky, URL v prohlížeči ani
logu. Při pozdějším schváleném spuštění jej chráněný workflow předá přes
omezeně přístupný dočasný soubor a uloží do ignorovaného produkčního `config.php`
s právy 0600. Pro místní testování lze použít prostředí `FIO_API_TOKEN`.
Automatické testy používají syntetická data a živý token nepotřebují.

## Bankovní komunikace a ochrana dat

Používáme pouze HTTPS GET na
`https://fioapi.fio.cz/v1/rest/periods/{token}/{od}/{do}/transactions.json`.
Nepoužíváme `/last`, které posouvá bankovní kurzor. Host je pevný, přesměrování
zakázané, timeout 20 sekund, odpověď nejvýše 5 MB a 2 000 pohybů. Překročení
limitu zastaví postup; velmi vytížený účet vyžaduje před aktivací menší okna.

IBAN odpovědi musí odpovídat účtu v `eshop_bank_admin.php`. GET ověří přístup
a účet, nikoli rozsah oprávnění tokenu; „Sledování účtu“ volí vlastník v bance.
Ukládáme ID, datum, částku, měnu, VS, typ a SHA-256 otisk. Jméno protistrany,
její účet ani zprávu nekopírujeme. Změna obsahu stejného ID, chybějící seznam
transakcí, neplatné datum nebo pohyb mimo období zastaví import bez částečného zápisu.

## Automatické potvrzení

`fioImportJson` nadále pouze importuje pohyby a návrhy. CLI navíc spustí
potvrzování, pouze pokud je `FIO_AUTO_CONFIRM_ENABLED=1` a historie je dotažená
až do dneška. Automatika vyžaduje:

- přesnou shodu VS, částky a měny s čekající bankovní platbou;
- právě jednu evidovanou platbu a právě jeden příchozí pohyb se stejným VS;
- IBAN uložený u platby shodný s účtem bankovního pohybu;
- čekající objednávku / předpis s odpovídající částkou a měnou;
- žádnou předchozí přidělenou úhradu ani použití pohybu v hromadné platbě.

Opakovaný VS trvalého příkazu s více obdobími, více převodů se stejným VS,
částečné úhrady, přeplatky, odlišná měna, chybějící VS a stornované cíle
vyžadují kontrolu. Automatika neodhaduje nejstarší měsíc.

Potvrzení zamkne pohyb, kandidátní platby a cíle, znovu ověří stav a použije
stávající životní cyklus v jedné transakci. Zachovává audit, aktivace a případné
zařazení potvrzení platby do běžné fronty oznámení. Auditor je `system`, ID
správce `NULL`; ukládá se bankovní ID, datum zaúčtování a důvod potvrzení.
Opakování nevytváří další úhradu ani oznámení. Obchodní konflikt dostane
`review_auto_conflict`; technická chyba provede rollback a umožní opakování.
Ruční potvrzení nadále vyžaduje správce, CSRF, důvod a checkbox.

## Doplnění historie po výpadku

Migrace `20261020170000_fio_sync_state` přidává zálohovanou tabulku
`fio_sync_state`. Postup patří IBAN, výměna tokenu jej nemaže.

- První běh trvale uloží počátek z `FIO_IMPORT_START_DATE`, jinak posledních
  30 dní včetně dneška. Existující starší importy stejného účtu rozšíří počátek
  na nejstarší známé období, aby se znovu pokryly i mezery.
- Jedno spuštění provede jeden HTTP dotaz, běžně nejvýše za sedm dní. Následující
  běh navazuje s překryvem `FIO_IMPORT_LOOKBACK_DAYS` (výchozí 3). Při větším
  překryvu se okno zvětší, aby vždy postupovalo.
- `covered_through` se posune až po úspěšném uložení celé odpovědi. Pád mezi
  uložením a posunem způsobí pouze bezpečné opakování. Síťová chyba, špatný IBAN,
  neplatná odpověď nebo zamčená historie nevynechají období. Počátek se zachová
  i při chybě úplně prvního běhu.
- Databázový zámek brání souběžným CLI běhům; `last_attempt_at` omezuje dotazy
  na jeden za 30 sekund i po chybě sítě.
- `last_success_at`, `last_error`, `covered_through` slouží dohledu. CLI vrací
  `caught_up` a počty automaticky potvrzených / předaných ke kontrole.

Delší výpadek se dožene více běhy. Automatika čeká na dokončení historie,
aby viděla případné konkurenční převody v pozdějších oknech. Pro historii starší
90 dní může být nutné odemčení v bankovnictví (HTTP 422); datum se automaticky
nepřeskočí. Po vypršení odemčení je případně nutné jej zopakovat.
Změna `FIO_IMPORT_START_DATE` po založení postupu jej nepřepisuje. Rozšíření
již importované historie vyžaduje samostatně připravený zpětný import.

## Provozní zapnutí

1. Nasadit standardním release postupem a aplikovat migrace včetně
   `20260804070000_fio_readonly_import`, `20260909170000_fio_bank_reconciliation`
   a nové migrace postupu synchronizace.
2. Ověřit účet v administraci, secret a požadovaný začátek historie.
3. Nejprve povolit samotný import a zkontrolovat reálné návrhy.
4. Potom povolit `automaticke_potvrzeni` ve workflow konfigurace, které vyžaduje
   `ZAPNOUT-FIO-AUTOMAT`. Čtecí režim používá `ZAPNOUT-FIO-READONLY`.
   Nouzové vypnutí vypne oba příznaky.
5. Po kontrole importu zapnout repozitářovou proměnnou
   `KIS_FIO_SCHEDULE_ENABLED=true`. Plán se pak spouští každých 10 minut;
   sledovat chyby a stáří posledního úspěchu v administraci Fio.

CLI pro schválené prostředí:

```text
APP_HOST=kis.kovopraha.cz php /absolutni/cesta/evidence/bin/fio-import.php
```

Token nemá parametr příkazové řádky. Dvě integrace se stejným tokenem musí
sdílet dodržování limitu Fio; doporučen je samostatný token pro EvidencePavel.
Oficiální dokumentace: https://www.fio.cz/docs/cz/API_Bankovnictvi.pdf
