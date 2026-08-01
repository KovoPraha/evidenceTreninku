# KIS parity v1 – syntetický dry run

`bin/kis-parity-dry-run.php` je čistě lokální CLI kontrola klasifikovaného,
syntetického KIS snapshotu. Nemá připojení k databázi, konfiguraci, session ani
síti. Neumí nic aplikovat a KIS ani Evidenci nemění.

Tento první kontrakt ověřuje tvar a úplnost budoucího paritního reportu. Zatím
není napojený na reálné KIS exporty ani produkční data.

## Spuštění

```powershell
php bin/kis-parity-dry-run.php --input tests/fixtures/kis/parity-valid.json
php bin/kis-parity-dry-run.php --input tests/fixtures/kis/parity-valid.json --json
```

CLI pouze čte jeden lokální běžný soubor. Odmítá stream/network URL, symbolické
odkazy, soubor větší než 5 MiB a kontrakt s více než 10 000 řádky.

Exit kódy:

| Kód | Význam |
|---:|---|
| `0` | strukturálně platný report bez blokujících řádků |
| `2` | report obsahuje blokující kategorii nebo neplatný obsah/JSON kontrakt |
| `64` | chyba použití CLI, argumentů nebo vstupní cesty |

## Vstupní kontrakt

Kořenový JSON objekt smí obsahovat pouze:

```json
{
  "contract": "kis-parity-v1",
  "run_ref": "synthetic:run-001",
  "missing_in_run_count": 2,
  "rows": []
}
```

- `run_ref`, `source_ref` a `target_ref` jsou neprůhledné syntetické nebo externí
  identifikátory, nejvýše 128 znaků, bez mezer a znaku `@`.
- Do payloadu nepatří jména, e-maily, telefony, rodná čísla, adresy ani volný text.
- Každý `source_ref` musí být právě jednou.
- Každý řádek smí obsahovat jen `source_ref`, `category`, `reason` a u párovaných
  kategorií také `target_ref`.

Povolené kategorie a pevné důvody:

| Kategorie | Povinný `reason` | Blokuje |
|---|---|---|
| `matched_same` | `signals_equal` | ne |
| `matched_different` | `signals_differ` | ano |
| `new` | `no_candidate` | ano |
| `ambiguous` | `multiple_candidates` | ano |
| `conflict` | `strong_signal_conflict` | ano |
| `invalid` | `invalid_input` | ano |
| `ignored` | `explicitly_ignored` | ne |
| `unexplained` | `missing_match_result` | ano |

`target_ref` je povinný pouze pro `matched_same` a `matched_different`. Pokud dva
řádky tvrdí stejný cíl, oba se v reportu změní na `conflict` s důvodem
`duplicate_target`. `target_ref` zůstává ve výstupu jen jako důkaz, který
konfliktní cíl oba řádky deklarovaly; není to schválené propojení.

## Výstup a bezpečnostní význam

Řádky se vždy seřadí podle `source_ref`, proto stejný vstup dává stejný výstup.
Souhrn obsahuje počet všech osmi kategorií, počet blokátorů a všech vstupních
řádků. Každý platný vstupní řádek je ve výstupu právě jednou a vždy má pevný
důvod.

`missing_in_run_count` je pouze informační údaj. Výstup jej označuje:

```json
{
  "informational_only": true,
  "archive_action": "never"
}
```

Chybění v jednom KIS běhu tedy nikdy neznamená archivaci, deaktivaci ani jiný
zápis. CLI nemá příkaz `apply`, nepoužívá produkční identitu a není rozhodnutím
o KIS cutoveru.
