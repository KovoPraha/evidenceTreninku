# P15 / S0 — výsledky a hranice předání

Datum: 7. 11. 2026, Europe/Prague. Vlastník: Marek / vývoj KIS.
Větev/worktree pokračují z [P15](../../CSC_SPORTSOFT_READ_SYNC_PLAN_2026-11-07.md),
[PR #371](https://github.com/KovoPraha/evidenceTreninku/pull/371).
Převzatý main `faae75e8`, před předáním doplněn `56f5ce08` (#370);
cizí WIP původního checkoutu zachované.

## Hotové výstupy

- [Přesný kontrakt](../../CSC_SPORTSOFT_S0_CONTRACT_V1.md): 9 polí licence,
  7 polí člena, 5 polí závodu, 6 polí existující přihlášky; společná obálka,
  typy/limity/null, původ každého údaje, normalizace a vyloučená pole.
- [JSON Schema](contract.schema.json), [syntetické fixtures](fixtures.json)
  a [statický HTML podklad](source-fixtures.html). Žádný skutečný sportovec,
  cookie, export, fotografie nebo údaj Rikiho se do fixtures nekopíroval.
- [Požadavky stanice](../../CSC_SPORTSOFT_S0_STATION.md),
  [předávací prompt](STATION_PROMPT.md) a [čtecí inventura](station-inventory.ps1).
  Jen metadata OS/kapacity/nástrojů; bez přístupu k Rikimu a bez instalace.
- Vlastník výslovně zvolil předání místnímu Codexu, nikoli vzdálený přístup
  k počítači. Stanice je Windows a jiná než současné pracoviště.

## Zpřesnění z důkazů

První přihlášený průzkum je v [původním protokolu](../csc-sportsoft-discovery-20261107/RESULTS.md).
V S0 byl čten dochovaný lokální DOM z téhož průzkumu, nikoli nové živé stránky:

| Podklad (UTC čas názvu snímku 7. 11.) | Nově zapsané zjištění |
| --- | --- |
| ČSC 13:02:37 | Licence obsahuje jméno jako text odkazu a oddělený tooltip `Platná licence` / `Neplatná licence`; Hostování je disabled checkbox. První stránka má25řádků z hlášených254, není to celý export. |
| Sportsoft přihláška 12:47:44 | Zaplaceno má v doloženém vzorku14řádků text `ano`. Negativní varianta není doložená; výběrový checkbox není údaj o platbě. |
| Sportsoft závody 13:01:34 | Hlavičky Datum/Název/Místo, akce pro existující a novou přihlášku; ID závodu se nesmí odvozovat z pořadí. |

Pokus CLI `-s=csc-sportsoft-readonly tab-list` skončil „browser is not open“.
Žádný nový browser ani profil nebyl otevřen; neproběhla žádná nová portálová
navigace, editace, přihláška, platba nebo změna licence.
Úplnost adresářů ani délka přihlášení se tím znovu neověřily.

Riki: přes GitHub konektor čteny zdrojové README/AGENTS, zdrojové provozní
podklady a instalační skript; konkrétní relevantní bloby jsou v dokumentu
stanice. WSL2/Docker je doložená architektura; Interactive Windows task
není garance bootu bez loginu. Živý host a soukromá data Rikiho se nečetly.
Nový vlastnický pokyn vylučuje Rikiho i z dohledu a směřuje na samostatný
Windows účet/worker. Cílový Codex nesmí kvůli inventuře procházet Riki runtime.

## Kontroly a otevřené body

- JSON Schema Draft2020-12: PASS přes jsonschema4.25.1, 4 platné kolekce
  přijaté, 6 chybných dávek odmítnutých (zakázané pole, číselné UCI,
  chybějící licence, vymyšlené member_id, chybějící/chybné event scope).
  Počty fixture řádků souhlasí. Další doménové případy jsou specifikace,
  nikoli test již existujícího matcheru. Validator byl pouze v lokálním
  pomocném adresáři mimo Git; první pokus zjistil chybějící knihovnu.
- PowerShell AST/parser a allowlist příkazů inventury: PASS. Jen čtecí
  Get-CimInstance/Measure-Object/Get-PSDrive/Get-Command/Get-Service/Test-Path
  a serializace ConvertTo-Json; script ani cílová inventura nebyly spuštěné.
- `php tools/check-current-docs.php` a `git diff --check`: PASS; finální
  opakování před commitem zaznamenané v předání PR. Archivní bloky zachované.
- Syntetický browser parser/worker, PHP ingest, DB migrace: NOT RUN / nejsou implementované.
- Cílová kapacita, OS build, oddělení účtů, šifrování a headless/batch/boot: UNKNOWN/NOT RUN.
- Sportsoft team scope, stabilní row IDs, bezpečný event allowlist a negativní
  platební varianta: OPEN; nulové hodnoty/unknown nejsou odhadnuté souhlasy.

S0 je **částečně hotové**: konkrétní příprava a předání dokončené, fyzická
inventura a zbývající zdrojová ověření otevřená. Další pracoviště vrátí
STATION_RESULT.md přes vlastní PR. Nasazení/produkční ingest/aktivace NOT RUN.
