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

## M2.3d – stabilní identita exportu

Import nyní ukládá také non-PII kontrakt `kis-import-field-v1`. Ve všech třech
exportech vyžaduje stabilní interní identifikátor osoby v jednom z normalizovaných
sloupců `kisid`, `iduzivatele`, `uzivatelid`, `idclena` nebo `clenid`. KIS ID se
ukládá do samostatného `sportovci.kis_external_id`; UCI licence zůstává nezávislá.

Kontrakt kontroluje povinné hlavičky, chybějící a neplatná ID, duplicity, rozpor
identity a nejednoznačné platební vazby. Report používá jen pořadové `source:N`,
pevné důvody, počty a fingerprint; neobsahuje jména ani hodnoty KIS ID. Starý
export bez tohoto kontraktu lze prohlížet, ale nelze jej aplikovat do sandboxu ani
spustit jako kanonickou synchronizaci. Již aplikovaný sandbox lze vždy vrátit.

Localhost běh #8 nad třemi syntetickými artefakty prošel 2/2 a byl v prohlížeči
aplikován do sandboxu 2/2 a vrácen na 0/2. Skutečný cutover zůstává blokovaný,
dokud reprezentativní anonymizovaný finální export nepotvrdí konkrétní aliasy polí
a úplný paritní report osob, členství, soupisek a plateb.

## M2.3e – uložený cutover paritní report

Každý nový importní běh nyní atomicky ukládá také `kis-import-parity-v1`. Report
porovnává zdrojový snapshot s aktuální cílovou Evidencí v oblastech osoby, aktivní
členství, textový snapshot soupisek a agregované platební signály. Obsahuje pouze
`source:N`, případné `sportovec:N`, pevné kategorie, souhrnné počty a fingerprinty
preview/field kontraktu; jména, KIS ID ani peněžní hodnoty nevypisuje.

Kategorie `new`, rozdílné signály, konflikty a nejednoznačnosti zůstávají
blokátory. Osoba s KIS ID chybějící v jednom běhu je pouze informační údaj a nikdy
se automaticky nearchivuje. Report navíc odděluje pokrytí domén: agregovaný počet
uhrazených/otevřených platebních řádků je zachycen a M2.3f přidává cílový kontrakt
`member-charge-v1`. Jednotlivé předpisy se stabilním zdrojovým ID, částkou, stavem,
měnou a daty se ukládají do `kis_import_payment_rows`; report zveřejní jen počty.
Dokud nejsou bezpečně přeneseny do `club_member_charges`, vznikne blokátor
`payment_prescriptions_not_promoted`. Rozdílný již existující cíl používá
`payment_prescriptions_different`.

Localhost run #12 proto pravdivě hlásí tři blokátory: dvě nové syntetické osoby a
dosud neprovedený přenos dvou předpisů. Browser ověřil zobrazení 2 staging / 2 čeká,
stažení non-PII JSON a nezávislý sandbox lifecycle byl před finálním během ověřen
2/2 → 0/2. Žádný z těchto kroků nezapisuje do produkce ani do kanonických předpisů.

## M2.3f – cílový model a staging členských předpisů

`club_member_charges` drží předpis pro konkrétního sportovce a volitelný účet
plátce. Peněžní transakce zůstává samostatná a váže se přes polymorfní
`payments.payable_type=member_charge`; import proto nikdy nevydává předpis za
bankovní platbu. Unikátní dvojice `source_system + source_external_id` je hranice
idempotence a `club_member_charge_events` je připravený audit změn.

Preview ukládá jednotlivé zdrojové předpisy atomicky. Duplicitní ID, nekonzistentní
částky, neplatná měna nebo stav zruší celý běh; prázdná či záporná částka je field
blokátor a nevytvoří stagingový předpis. Paritní JSON neobsahuje ID předpisů ani
peněžní hodnoty. M2.3f ještě neimplementuje zápis do `club_member_charges`.

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
