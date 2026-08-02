# Syntetické KIS parity fixtures

Všechny soubory v tomto adresáři obsahují pouze neprůhledné syntetické reference
a pevné klasifikační kódy. Nesmí obsahovat jména, e-maily, telefony, adresy,
rodná čísla, data narození ani skutečná KIS/UCI ID.

## Přehled

- `parity-valid.json` – minimální neblokující kontrakt.
- `parity-invalid.json` – malý blokující kontrakt a duplicate-target kontrola.
- `parity-schema-invalid.json` – záměrně nepovolené pole pro ověření fail-closed
  validace a redakce vstupní hodnoty.
- `parity-realistic.json` – reprezentativní kombinace deseti anonymních výsledků
  pro W0-G.

## Scénáře v `parity-realistic.json`

| Reference | Význam testu |
|---|---|
| `kis:family-a:child-01` a `child-02` | dva členové se sdíleným kontaktním signálem zůstávají nejednoznační |
| `kis:strong-id:uci-conflict` | rozpor neprázdných silných externích identifikátorů |
| `kis:strong-id:birth-conflict` | shodný externí identifikátor s rozporným ověřovacím signálem |
| `kis:duplicate:a` a `duplicate:b` | dva zdrojové řádky deklarují stejný kanonický cíl |
| `kis:missing:result-01` | řádek bez vysvětleného výsledku matcheru |
| `kis:new:member-01` | nový záznam bez kandidáta |
| `kis:matched:stable-01` | jediná čistá neblokující shoda |
| `kis:matched:signals-changed-01` | spárovaný záznam s provozním rozdílem |

Označení `family` je pouze anonymní collision scénář. Nezavádí účet rodiče,
vazbu rodič–dítě ani oprávnění k osobě. `missing_in_run_count` je vždy pouze
informační a nesmí vyvolat archivaci.
