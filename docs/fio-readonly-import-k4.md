# Fio read-only import a ruční potvrzení přesné shody K4

Synchronizace je záměrně **read-only vůči bankovnímu účtu** a sama nepotvrzuje
platby. Stahuje pohyby, ukládá jejich neměnný otisk a navrhuje shodu s
objednávkou nebo členským předpisem. Přesnou shodu pak správce výslovně potvrdí
v `eshop_fio_admin.php`; teprve tento auditovaný krok změní stav platby.

## Bezpečnostní kontrakt

- Ve Fio se vytvoří token typu **Sledování účtu**, nikoli token pro zadávání
  plateb. Jeden token patří právě jednomu účtu.
- Token je pouze v environment proměnné `FIO_API_TOKEN`; nepatří do databáze,
  `config.php`, Gitu, CRON příkazu uloženého ve verzovaném souboru ani do výpisu.
- Import používá výhradně HTTPS GET endpoint `/v1/rest/periods/...`. Nepoužívá
  `/last`, protože ten posouvá serverový kurzor Fio. Překryvné období je bezpečné
  díky deduplikaci podle unikátního ID pohybu.
- Odpověď se přijme jen tehdy, pokud její IBAN přesně odpovídá aktuálnímu účtu
  v administraci `eshop_bank_admin.php`. Přesměrování HTTP je zakázané, host je pevně
  `fioapi.fio.cz`, odpověď má limit 5 MB a nejvýše 2 000 pohybů.
- Ukládají se pouze údaje nutné pro párování: ID, datum, částka, měna, VS, typ a
  SHA-256 otisk. Jméno protistrany, účet, zpráva a další bankovní osobní údaje se
  do Evidence nekopírují.
- Datum pohybu přijímá oficiální Fio tvar `RRRR-MM-DD+02:00` (obecně validní
  offset do ±14:00), prosté `RRRR-MM-DD` a kvůli zpětné kompatibilitě také epoch
  seconds/milliseconds. Neplatné kalendářní datum nebo offset zastaví celý běh.
- Stejné Fio ID se stejným otiskem je bezpečná duplicita. Stejné ID s jiným
  obsahem zastaví celý běh a původní záznam se nepřepíše.
- Dosud nepotvrzené duplicity se při dalším běhu znovu vyhodnotí. Převod, který
  dorazil před založením předpisu, se tak může později změnit z kontroly na
  přesnou shodu. Potvrzený pohyb už synchronizace nemění.

## Pravidla návrhu

`proposed_exact` vznikne pouze pro příchozí kladnou platbu, pokud současně:

1. normalizovaný variabilní symbol odpovídá právě jedné platbě objednávky nebo
   členského předpisu,
2. částka v haléřích přesně odpovídá cenovému snapshotu,
3. měna přesně odpovídá,
4. metoda je převod a platba i její objednávka/předpis stále čekají.

Chybějící/neznámý VS, jiná částka, jiná měna nebo již změněný stav dostanou
`review_*`. Odchozí a nulové pohyby dostanou `ignored_non_credit`. Výsledek je
vidět administrátorovi na `eshop_fio_admin.php`.

Při kliknutí na **Potvrdit úhradu** se všechna pravidla znovu zkontrolují v
jedné databázové transakci. Bankovní pohyb se smí svázat jen s jedinou platbou,
uloží se datum zaúčtování, správce a jeho poznámka a spustí se běžný životní
cyklus platby (včetně auditu a navazujících aktivací objednávky). Chybné nebo
mezitím změněné návrhy se nepotvrdí.

## Nastavení a CRON

Nejdříve aplikujte migrace `20260804070000_fio_readonly_import` a
`20260909170000_fio_bank_reconciliation`. Potom na
hostingu nastavte mimo Git:

```text
FIO_IMPORT_ENABLED=1
FIO_API_TOKEN=<read-only token Sledování účtu>
FIO_IMPORT_LOOKBACK_DAYS=3
```

Účet uložený v `eshop_bank_admin.php` musí být stejný jako účet tokenu.
Doporučený CRON je jednou za 10 minut; Fio doporučuje mezi dotazy se stejným
tokenem nejméně 30 sekund.
Obecný příkaz (konkrétní absolutní cestu doplní hosting) je:

```text
APP_HOST=data.kovopraha.cz php /absolutni/cesta/evidence/bin/fio-import.php
```

Environment proměnné musí CRON zdědit z bezpečné konfigurace hostingu. Token
nevkládejte přímo do příkazu, protože jej mohou zobrazovat procesní a provozní
logy. Import vypíše jen počty; při chybě nevypíše token ani bankovní data.

## Automatické potvrzení

Není zapnuté. Každá shoda vyžaduje kliknutí správce, jeho důvod a potvrzovací
checkbox. Případné budoucí automatické potvrzování musí být samostatná změna až
po vyhodnocení reálných dat, zejména duplicit, přeplatků, opakovaných VS a plateb
po stornu.
