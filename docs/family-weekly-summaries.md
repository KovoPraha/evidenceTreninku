# Týdenní rodinné souhrny

Stav k 5. 8. 2026: funkční a ověřené pouze na localhostu. Produkční e-mailový
transport ani CRON nejsou zapnuté.

## Co uživatel umí

- v přihlášeném sportovním přehledu si jedním krokem zapnout nebo vypnout odběr,
- před zapnutím si prohlédnout stejný sedmidenní obsah, který bude uložen do zprávy,
- při vypnutí okamžitě zrušit všechny neodeslané zprávy,
- dál používat aplikaci i bez odběru; výchozí stav je vypnuto.

Souhrn používá kanonickou rodinnou agendu a oprávnění účtu. Nepřijímá ID dítěte
z URL a nevytváří druhou kopii kalendářové logiky.

## Fronta a opakovatelnost

Migrace `20260805040000_family_weekly_summaries` přidává preference, frontu a
auditní události. Pro jeden účet a začátek týdne může existovat právě jeden
snapshot. Opakované spuštění proto nevytváří duplicity. Worker před převzetím
znovu ověří aktivní a ověřený účet i zapnutý odběr.

## Bezpečný localhost test

Administrátor otevře:

`http://localhost/evidencePavel/family_weekly_summaries_admin.php`

Tlačítko „Připravit aktuální týden“ vytvoří chybějící snapshoty. Tlačítko
„Uložit 1 zprávu do outboxu“ uloží jednu zprávu jako JSON do ignorovaného adresáře
`var/family-weekly-summary-outbox/`. Adresář `var/` je webově zakázaný a tento
transport neotevírá síťové spojení.

Stejný test lze spustit z příkazové řádky:

```powershell
$env:APP_HOST='localhost'
php bin/family-weekly-summaries.php --generate --force
php bin/family-weekly-summaries.php --send-local --limit=1
```

Produkční CRON spouští jednou týdně vytvoření fronty a následně její omezené
zpracování skutečným e-mailovým transportem:

```bash
APP_HOST=kis.kovopraha.cz php bin/family-weekly-summaries.php --generate --send --limit=50
```

Volba `--send` je záměrně explicitní. Opakované spuštění je bezpečné: pro jeden
účet a počátek týdne vznikne nejvýše jeden snapshot a worker používá převzetí,
počet pokusů a odložené opakování. `--send-local` nadále odmítne jiný host než
localhost.

`--force` obchází pouze pondělní plán na localhostu; unikátní klíč fronty stále
brání duplicitám. `--send-local` se na jiném hostu než `localhost` nebo `127.0.0.1`
ukončí chybou.

## Produkční brána

Před produkčním zapnutím je nutné samostatně schválit text, odesílací adresu,
transport, frekvenci a provozní dohled. Aktuální kód neposkytuje přepínač pro
skutečný e-mail a produkční nasazení tohoto řezu samo nic neodešle.
