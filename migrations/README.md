# Cislovane databazove migrace

Legacy schema je zmrazene na verzi `2.20.2`. Kazda budouci zmena databaze musi
byt novy nemenny soubor pojmenovany `YYYYMMDDHHMMSS_popis.php`.

Soubor vraci pole s identickym `id` a funkci `up`:

```php
<?php
return [
    'id' => '20260801120000_priklad',
    'up' => static function (PDO $pdo): void {
        $pdo->exec('/* idempotentni zmena schematu */');
    },
    'verify' => static function (PDO $pdo): bool {
        // Read-only postcondition: vratit true pouze pokud je zmena hotova.
        return true;
    },
];
```

Kazdy `up` krok musi byt bezpecne opakovatelny (idempotentni), protoze MySQL DDL
nelze s vlozenim zaznamu do ledgeru uzavrit do jedine atomicke transakce.
Runner proto ulozi zaznam az po uspesnem `up` a uspesne read-only `verify`.
Jiz aplikovany soubor se nikdy neupravuje. Runner kontroluje SHA-256 checksum a
pri zmene nebo neznamem ID v `evidence_schema_migrations` selze bez zapisu.

Format timestamp ID je pevne 14 cislic (`YYYYMMDDHHMMSS`) a podtrzitkem
oddeleny popis. Vyhrazene ID `0000_legacy_2_20_2` runner zapisuje automaticky s
checksumem `sha256('legacy-schema:2.20.2')` jako dukaz baseline.

Pred pouzitim CLI musi byt explicitne nastavene `APP_HOST`, napriklad
`APP_HOST=localhost php bin/migrate.php --check`.
