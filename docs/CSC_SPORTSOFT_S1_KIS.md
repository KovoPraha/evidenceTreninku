# P15 S1 — KIS úložiště, příjem a párování

7. 11. 2026, vlastník Marek / vývoj KIS. Stav: IMPLEMENTOVÁNO A PUSH,
S1 synteticky ověřené, všech 11 CI kontrol PASS na 2fe8aa93;
[draft PR #376](https://github.com/KovoPraha/evidenceTreninku/pull/376).
[Hlavní plán](CSC_SPORTSOFT_READ_SYNC_PLAN_2026-11-07.md),
[kontrakt v1](CSC_SPORTSOFT_S0_CONTRACT_V1.md),
[výsledky](verification/csc-sportsoft-s1-20261107/RESULTS.md).
Větev `codex/csc-sportsoft-s1-ingest-20261107`, worktree
`C:/Users/Marek/Documents/Codex/evidencePavel-csc-sportsoft-s1-20261107`.
Základ S0 #371 + main 69f49033. S1 má samostatné předání; merge, deploy,
aktivace a provozní přejímka jsou NOT RUN. Povolena pouze syntetická data.

## Rozsah a úložiště

Nová idempotentní [migrace](../migrations/20261107210000_external_read_sync.php)
vytváří osm tabulek, nevkládá zařízení ani spojení a nic nezapíná:

| Tabulka | Účel |
| --- | --- |
| external_sync_connections | Neměnná konfigurace provider/scope/kolekce/event; sekvence a ukazatel posledního přijatého snímku |
| external_sync_devices | Jedno zařízení/token pro jedno spojení, pouze SHA-256 otisk, enabled výchozí 0 |
| external_sync_runs | Běh, zařízení, lease, monotonní sekvence, stav a otisk manifestu |
| external_sync_parts | Díl, SHA-256 přesných přijatých bajtů, validovaný allowlist JSON |
| external_sync_records | Zachované zdrojové řádky, normalizované hodnoty a historický výsledek párování |
| external_identity_links | Vazba zdrojového klíče na UCI/osobu; revize, konflikt nebo not_seen |
| external_sync_reviews | Důvod ruční kontroly a kandidátní interní ID |
| external_sync_audit | Jen ID, typ aktéra, akce a čas; bez těl požadavků, jmen, tokenů a cookies |

Run-local identita řádku je unikátní `(run_id, part_no, row_index)`;
`row_index` je od 0 uvnitř původního dílu. Uchovává se i celkové pořadí a
interní DB ID. Není zaměňována za stabilní externí identitu.

Tabulky patří do Evidence backup ownership. Webový požadavek nikdy neprovádí
DDL/migrace. Kanonické sportovce, členství, platby, licence ani přihlášky
synchronizace nezapisuje. Databázové oprávnění aplikace samo o sobě není
důkazem tohoto omezení; kontrakt je v implementaci a syntetických regresích.

Spojení reprezentuje právě jednu kolekci jednoho ověřeného klubu/týmu;
pro přihlášky také jeden závod. Při změně scope založit jiné spojení,
nepřepisovat konfiguraci existujícího. S1 nemá provisioning/rotaci/UI,
aktivaci ani plánovač. Ty vyžadují následující provozní krok. Náhodný token
má mít 32 bajtů / 64 lowercase hex znaků; testovací deterministické tokeny
nejsou použitelným provisioningem. Device token nesmí číst sportovce,
schvalovat baseline ani měnit párování.

## Přenosový protokol pro druhé pracoviště

Endpoint [api_external_sync.php](../api_external_sync.php) je machine-only.
Výchozí stav při chybějícím nebo nepravdivém `KIS_EXTERNAL_SYNC_ENABLED` je
OFF. Tato změna flag nikam nezapisuje. Po budoucím schváleném zapnutí:
HTTPS, POST, `Content-Type: application/json`, hlavička
`Authorization: Bearer <device-token>`, URL `api_external_sync.php?action=...`.
Nepoužívá přihlašovací session ani cookies člověka; nevěří forwarded HTTPS
hlavičkám. Konkrétní TLS/Authorization forwarding musí ověřit release smoke.

| action | JSON tělo | Výsledek |
| --- | --- | --- |
| claim | `{}` | run_id, connection_id, collection, scope_id, event_id, lease_generation, lease_until (UTC epoch), schema_version |
| heartbeat | run_id, lease_generation | Nový lease_until |
| upload_part | Přesný batch v1 z S0 | part_no, sha256, duplicate |
| complete | Manifest níže | status complete/review_required, duplicate |
| fail | run_id, lease_generation, failure_code | status failed; poslední přijatý snímek zůstane |

`failure_code` je pouze `auth_required`, `layout_changed`, `incomplete`,
`network_error` nebo `not_created` (poslední jen pro přihlášky). Žádné chybové
zprávy z portálu nebo HTML se neodesílají. Unknown/missing klíče se odmítají.

Manifest má přesně tyto klíče (ilustrační hodnoty):

```json
{
  "run_id": "00000000-0000-4000-8000-000000000001",
  "lease_generation": 1,
  "parts": [{"part_no": 1, "sha256": "<64 lowercase hex z upload receipt>"}],
  "page_count": 1,
  "row_count": 1,
  "end_reached": true,
  "filters_clear": true
}
```

Hash je nad původním UTF-8 JSON tělem uploadu, nikoli nad znovu serializovaným
objektem. Worker uchová přesné bajty do potvrzení; retry používá stejné tělo.
Manifest řadí všechny díly od 1 bez mezer a přebírá jejich receipt hash.
`page.index` je číslo zdrojové stránky od 1; `row_offset` je offset uvnitř
této stránky. Velkou stránku lze rozdělit do více dílů; offset navazuje.
`reported_total` se týká celé kolekce a během běhu se nesmí změnit, ani mezi
null a číslem. Stejně se nesmí změnit adapter_version. Prázdná kolekce má
jeden prázdný díl, page_count=1, row_count=0. Není to licence k publikaci nuly.

Server kontroluje souvislost dílů/stránek/offsetů, počty a manifest hash.
Pokud portál neukazuje celkový počet, server nemůže dokázat pravdivost
`end_reached` samotného: worker S2/S3 musí doložit stránkování a vyčištění
filtrů DOM testy. S1 tento důkaz portálu nenahrazuje.

Limity: 1 MiB tělo, 250 řádků/díl, 1000 dílů/stránek, 100000 řádků,
64 MiB součet těl/běh. Lease 600 s, heartbeat nejvýše do 2 hodin od startu.
Zaniklá lease se neoživuje; nové claim dostane jiné UUID a vyšší sekvenci.
Generace v1 je 1, ověřuje se vždy; UUID + sekvence brání návratu starého běhu.
Nejvýše 10 nových běhů za 60 s/spojení, opakované claim stejného aktivního
zařízení vrací stejný job. Jiné zařízení dostane busy. Čas observed_at je
UTC Z, platné datum a v intervalu start−300 s až serverový nynější čas+300 s.

Chyby jsou jen `{"error":"reason_code"}`. 401 neplatné/odvolané zařízení;
403 scope/HTTPS; 409 lease/souběh/odlišný retry; 422 kontrakt/neúplnost;
413 limit; 429 nové claim příliš často; 503 vypnuto/úložiště nedostupné.
Worker při 401/403/409 zastaví daný běh, při 429 použije backoff. Výpadek
odpovědi complete lze opakovat se stejným manifestem i po dokončení; starý
retry nesmí přepsat ukazatel novějšího snímku. Odvolaný token nesmí ani retry.

## Publikace a párování

Uploady se ukládají do stagingu. Teprve validní complete v jedné transakci
uloží řádky, vyhodnotí identity a přesune ukazatel posledního snímku. Selhání
vrátí celou publikaci zpět; díly zůstávají pro bezpečný retry.
První baseline, nula nebo pokles více než 20 % skončí `review_required`,
předchozí snímek zůstane čitelný. Zařízení jej nemůže schválit.
`externalSyncApproveSnapshot()` je pouze interní servisní bod pro budoucí
registrářské UI S4: ověří aktuální aktivní pracovní pozici, zařízení,
spojení a poslední sekvenci. Není vystavený přes machine API. Volající musí
získat trainer ID z ověřeného přihlášení, nikdy z libovolného parametru klienta.
`externalSyncReadReview()` vrací registráři navržené řádky včetně aktuálních
konfliktů a příznaku superseded bez přesunu ukazatele. U čekající baseline
se review počítá v náhledu; trvalé důvody identity vzniknou při publikaci.

[Resolver](../includes/external_identity_match.php) zachovává úvodní nuly,
odstraňuje jen ASCII mezery/NBSP a vyžaduje 11 ASCII číslic. Chybějící,
neplatné, nenalezené nebo duplicitní UCI nevytváří vazbu. Jméno/datum jsou
jen kontrola rozporu, nikdy fallback. Jméno toleruje pořadí tokenů a českou
diakritiku. Rozpor u stejného UCI mezi zdrojovými řádky zablokuje celou skupinu.
Více různých ČSC licencí stejné osoby je v pořádku; opakované license_id
odmítne snímek. Duplicitní kompozitní klíče Sportsoftu uchová jako oddělené
řádky do review. Neověřená ID zůstávají null podle S0.

Existující vazbu nelze tiše převést na jiného člověka. Změna UCI v KIS nebo
ve zdroji vrátí konflikt. [Čtecí projekce](../includes/external_sync_read.php)
vyžaduje registráře a vždy znovu ověří aktuální kanonická data; historický
person pointer se nevrací jako současná vazba. Historické řádky review/auditu
se tím nepřepisují. Budoucí konzumenti musí použít tuto projekci, nikoli
přímo věřit uloženému match_status. Snímek starší 24 hodin nebo vypnuté
spojení je stale; to neznamená změnu licence/platby.

NFC normalizaci poskytne ext-intl (nebo načtená kompatibilní Normalizer).
Bez ní se kombinující Unicode značky odmítají, neukládá se neověřená NFC.
Neznámé stavy licence/platby zůstávají unknown; rok licence neimplikuje
platnost, Sportsoft `ne` nemá bez dalšího průzkumu platební význam.

## Hranice dokončení a další práce

S1 dodává persistence, ingest a bezpečné serverové párování, nikoli pilot.
Zbývá cílová S0 inventura/scope, worker S2/S3, registrářské UI S4,
provisioning/rotace/revokace s pořadím zámků connection → device → run,
provozní retence/úklid a schválený pilot S5/S6. Do té doby nepovolovat
produkční příjem: staging obsahuje osobní údaje a není implementovaný
automatický retenční úklid. Zálohy vyžadují stejnou ochranu jako zdrojová DB.
Žádný proces této etapy nečte portály ani soukromou infrastrukturu/data Rikiho.

Pro další pracoviště je připravený [prompt S2/S3 pro stanici](CSC_SPORTSOFT_S2_S3_STATION_PROMPT.md).
Zadává implementaci a přejímku pouze na syntetických datech; není provozním
povolením a žádné provedení na cílové stanici se tím nedokládá.
