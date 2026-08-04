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
php bin/kis-parity-dry-run.php --input tests/fixtures/kis/parity-realistic.json --json
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

## Navazující uložený preview kontrakt M2.3b

Importní UI používá samostatný `kis-import-preview-v2`. Nový běh po matchingu
atomicky uloží úplný report, počty blokátorů a fingerprint nezávislý na databázovém
ID běhu. Report obsahuje pouze odkazy `source:N`, případně `sportovec:N`, pevnou
akci a pevný důvod; neobsahuje jména, e-maily ani zdrojové identitní hodnoty.

Detail je dostupný oprávněnému trenérovi v `kis_sync_center.php`. JSON export má
`Cache-Control: no-store`. Stav `ready_for_test_review` znamená pouze připravenost
k lidské kontrole; sandbox promote stále vyžaduje samostatné potvrzení administrátora
a produkční import není povolen.

M2.3c přidává pouze localhost sandbox promotion. Je dostupná administrátorovi po
CSRF kontrole, explicitním potvrzení, důvodu a shodě fingerprintu. Výsledek se
ukládá výhradně do `kis_import_sandbox_*`; tabulky osob a členství se nemění.
Rollback deaktivuje sandbox položky a zůstává dostupný i při pozdějším driftu
preview. Produkční promote ani cutover tím nejsou povoleny.

## Realistická syntetická fixture W0-G

`tests/fixtures/kis/parity-realistic.json` skládá deset neprůhledných řádků do
jednoho reprezentativního blokujícího běhu. Pokrývá rodinný collision scénář,
rozpory silných identifikátorů, duplicate target, nový a nevysvětlený řádek,
provozní rozdíl a čistou shodu. Očekávaný výsledek je devět blocker řádků a jedna
čistá shoda; tři členové nezahrnutí v běhu zůstávají pouze informační.

Fixture neobsahuje skutečné hodnoty identitních signálů. Výsledky matcheru jsou
předklasifikované a kontrolují kontrakt paritního reportu, nikoliv formát KIS
XLSX exportu. Podrobná mapa scénářů a privacy pravidla jsou v
`tests/fixtures/kis/README.md`.
