# 01 – Audit připravenosti

Datum: 1. 8. 2026
Režim: read-only audit kódu, lokální DB a GitHub/produkčního deploy důkazu

> Tento dokument zachovává výchozí auditní snapshot. Následné opravy jsou vedené
> v `06-program-board.md` a `SESSION_HANDOFF.md`; dokud nejsou sloučené a
> nasazené, nemění zde popsaný stav `origin/main` ani produkce.

## Výsledek

| Oblast | Stav | Verdikt |
|---|---|---|
| GitHub a produkční deploy | zelená | reálně funguje |
| DB migrace a záloha | žlutá | běží, ale je nutný restore drill a sjednocení větví |
| KIS importní základ | žlutá | implementován, lokálně bez provozních runů |
| Členská evidence | žlutá | dobrý admin základ, chybí self-service identity a rodiče |
| E-shop | červená | neexistuje |
| Platby | červená | chybí společný platební model a integrační vrstva |
| Wallet | červená | existuje pouze starší návrh, ne ledger |
| Klubové události | červená | účetní události nejsou registrace členů |
| Automatické testy | červená | projekt nemá vlastní testovací sadu |
| Staging a observabilita | červená | není prokázáno oddělené prostředí ani integrační dohled |

Celkově: **připraveno pro Fázi 0, zatím nepřipraveno pro souběžnou finanční a
členskou implementaci.**

## Co je prokazatelně připravené

### Produkční deploy

GitHub workflow `Nasadit produkci` je aktivní. Běh
`30668559417` pro commit `58ec8ec985d447dfe901481ac8bb24b944b03d08`
skončil úspěšně a doložil:

- kontrolu PHP syntaxe,
- SSH spojení,
- existenci produkčního `config.php`,
- vytvoření DB zálohy,
- přenos souborů přes rsync,
- HTTP 200 z produkčního webu,
- `verze_db = 2.20.2`, `verze_kod = 2.20.2`,
- PHP `8.2.32`.

Produkční URL: `https://data.kovopraha.cz/evidence/`.

### KIS synchronizační základ

Projekt obsahuje:

- parser tří XLSX vstupů v `includes/kis_sync_lib.php`,
- importní runy a řádky v `includes/kis_import_run_lib.php`,
- deterministické párování a konflikty v `includes/kis_match_lib.php`,
- tabulky `kis_import_runs`, `kis_import_rows`, `kis_import_matches`,
- historii člena `sportovec_history`,
- mapování soupisek `soupiska_mapping`,
- preview a zásadu, že chybějící člověk není automaticky archivován.

Relevantní kód: `includes/auto_migrace.php:707-930`,
`includes/kis_import_run_lib.php:19-122`, `includes/sportovec_status_lib.php:4-64`.

To je vhodný migrační most. Není to ještě náhrada KIS, protože chybí
kanonické sezónní soupisky, stabilní externí ID, exportní kontrakty a cutover
proces.

### Členské a provozní moduly

Existují sportovci, skupiny/podskupiny, tréninky, KIS stavové signály,
admin karta člena, historie, hromadné akce, veřejný booking, sportoviště,
lekce a web push. V repozitáři existuje také starší volitelný SSO bridge pro
Velocotu (`auth/sso_bridge.php`), ale není součástí cílové architektury a musí
zůstat vypnutý. Evidence je samostatná aplikace; případné budoucí sdílení
uživatelské identity vyžaduje samostatný kontrakt a rozhodnutí.

## Co lokální DB skutečně ukazuje

Read-only kontrola lokální MariaDB dne 1. 8. 2026:

| Metrika | Hodnota |
|---|---:|
| schema version | `2.20.2` |
| tabulky | 72 |
| sportovci | 253 |
| sportovci s e-mailem | 0 |
| veřejné účty | 0 |
| KIS import runy | 0 |
| mapování soupisek | 0 |
| kreditní období | 0 |
| historie členů | 0 |
| veřejné rezervace | 0 |

Tabulky `kredit_pohyby` a `sso_tokeny` ani plánované sloupce
`verejni_uzivatele.sportovec_id` a `kredit_zustatek` lokálně neexistují.

Tato data jsou **lokální vývojový vzorek, nikoliv důkaz stavu produkčních
dat**. Přesto dokazují, že nelze testovat propojení identity nebo migraci KIS
jen nad současnými lokálními daty. Fáze 0 musí vytvořit anonymizované realistické
fixtures nebo staging kopii.

## Potvrzené mezery

### 0. KIS matcher má kritickou chybu závislou na pořadí

`includes/kis_match_lib.php:43-92` drží kandidáty ve statické cache, ale při
hledání jedné osoby z ní odstraňuje všechny neodpovídající řádky. Každá další
osoba se tak porovnává už jen se zmenšenou množinou. Výsledkem mohou být falešně
„noví“ sportovci.

Dopad: před opravou a regresním testem s více lidmi se nesmí provést ostrý KIS
import. Oprava je součástí Foundation gate.

### 1. Lokální Git stav není sjednocený s produkčním main

Lokální `main` je na `c461733` a po fetchi je o dva commity za
`origin/main = 58ec8ec`. Pracovní strom obsahuje další rozpracované deploy
změny. Některé lokální soubory popisují jiný kontrakt Secrets než starší
checkout.

Dopad: žádné implementační vlákno nesmí začít úpravami sdílených souborů,
dokud se tyto změny bezpečně nesjednotí. Existující práci nelze zahodit ani
přepsat.

### 2. Neexistují projektové automatické testy

`composer.json` má pouze runtime balíčky. Projekt nemá PHPUnit konfiguraci ani
repo test suite; nalezený `test.php` není dostatečná regresní infrastruktura.

Dopad: finanční ledger, kapacity, sklad, webhooky a KIS cutover bez testů
nesmějí do produkce.

### 2a. Závislosti obsahují známé bezpečnostní chyby

`composer audit --locked` dne 1. 8. 2026 hlásí 12 advisories: tři HIGH pro
PhpSpreadsheet `5.8.0` a devět MEDIUM pro Guzzle/PSR-7. PhpSpreadsheet zpracovává
uživatelské XLS/XLSX soubory v KIS importu, takže nejde jen o nepoužitou knihovnu.

Dopad: bezpečná kompatibilní aktualizace a zelený dependency audit jsou podmínkou
dalšího produkčního importu a nové implementace.

### 3. Migrace jsou centralizované v jednom request-time runneru

`db.php` načítá `includes/auto_migrace.php` při DB requestu. Tento model je
pro současnou aplikaci funkční, ale nový program přinese mnoho navazujících
finančních a datových migrací.

Dopad: Fáze 0 má zavést explicitní, číslované a testovatelné migrace nebo
alespoň jejich kompatibilní runner s transakčním/provozním reportem.

### 4. Identita je rozštěpená

- `treneri` používají interní přihlášení; legacy Velocota bridge je vypnutý,
- `sportovci` jsou evidované osoby bez loginu,
- `verejni_uzivatele` mají vlastní heslo pro booking,
- lokální sportovci nemají e-mail a veřejné účty jsou prázdné.

Automatické propojení pouze podle e-mailu je nevhodné pro rodiče, více dětí a
sdílené rodinné adresy. Nejdřív musí vzniknout explicitní vazba účet ↔ osoba
s rolí a platností.

Navíc `login.php:41-51` podporuje legacy plaintext hesla a zahashuje je až po
úspěšném loginu. Read-only kontrola lokální DB našla 40 ze 42 trenérských účtů
mimo bcrypt/argon2. Produkční hesla nebyla kontrolována. Před členským účtem je
nutná řízená migrace/reset, rate limiting a aplikačně vynucené bezpečné session.

### 5. Současný kredit není wallet

`sportovec_obdobi` počítá odměnu za tréninky a příznak `vyplaceno`.
`docs/roadmapa-rozsireni.md:119-184` navrhuje budoucí wallet, ale skutečné
tabulky ještě neexistují.

Peněžně dobíjený zůstatek se nesmí bez rozhodnutí smíchat s klubovou odměnou.
ČNB výslovně řeší dobíjení benefitních prostředků v omezené síti; před
implementací je nutné účetní/právní potvrzení konkrétního modelu.

### 6. `ucto_udalosti` je jiná doména

Současné `udalosti/` slouží účetní evidenci a vyúčtování. Formulář obsahuje
typ, zálohu a stav, ale nikoliv cílové skupiny, přihlášky osob, kapacitu,
čekací listinu, souhlasy nebo storno podmínky (`udalosti/formular.php:13-130`).

Klubové výjezdy a soustředění proto potřebují nové tabulky a služby.

### 7. Chybí bezpečný integrační provoz

Nové integrace vyžadují:

- evidenci příchozích webhooků a externích transakcí s unikátními ID,
- idempotenci každé finanční operace,
- frontu chyb a ruční řešení nepřiřazených plateb,
- cron/worker pro Fio, e-maily a retry,
- korelační ID napříč objednávkou, platbou, wallet a zásilkou,
- alerty a audit administrátorských zásahů.

### 8. Deploy funguje, ale není ještě release-safe

Současný workflow získává host key živě přes `ssh-keyscan`, při selhání DB zálohy
pokračuje, synchronizuje přímo do živého adresáře bez atomického přepnutí a
rollbacku a migraci spouští přes veřejný HTTP endpoint s tokenem v URL.

Dopad: pro dnešní malý projekt je funkčnost prokázaná, pro finanční doménu musí
být backup fail-closed, host key připnutý, token mimo URL a release buď atomický,
nebo vybavený jednoznačným forward-fix/rollback postupem.

## Externí integrace – ověřená proveditelnost

- Shoptet umí export produktů v CSV, XLSX a XML a permanentní zabezpečenou
  exportní URL. Pro migraci doporučujeme jednorázový XLSX/CSV export se
  stabilním SKU a opakovatelný dry-run import.
- Stripe Checkout je vhodný pro hostovanou karetní platbu. Splnění objednávky
  musí potvrdit serverový webhook; návratová stránka nestačí. Duplicitní eventy
  se musí deduplikovat.
- Fio API poskytuje pohyby v JSON/XML/CSV a každý pohyb má unikátní ID. To
  umožňuje idempotentní bankovní párování přes VS, částku a měnu.
- Packeta poskytuje API pro zásilky a v5 JSON feed výdejních míst. Nemá běžný
  sandbox; testuje se testovacím odesílatelem bez fyzického podání zásilky.

Oficiální zdroje ověřené 1. 8. 2026:

- [Shoptet – Export produktů](https://podpora.shoptet.cz/export-produktu/),
- [Stripe – How Checkout works](https://docs.stripe.com/payments/checkout/how-checkout-works),
- [Stripe – Fulfill orders](https://docs.stripe.com/checkout/fulfillment),
- [Stripe – Webhooks](https://docs.stripe.com/webhooks),
- [Fio – API Bankovnictví](https://www.fio.cz/bankovni-sluzby/api-bankovnictvi),
- [Packeta – API](https://docs.packeta.com/docs/getting-started/packeta-api),
- [Packeta – pickup-point feed v5](https://docs.packeta.com/how-to-update-packeta-feed),
- [ČNB – dobíjení v omezené síti](https://www.cnb.cz/cs/dohled-financni-trh/legislativni-zakladna/stanoviska-k-regulaci-financniho-trhu/RS2023-31),
- [ÚOOÚ – činnosti sportovních klubů](https://uoou.gov.cz/profesional/qa-otazky-a-odpovedi/cinnosti-spolku-sportovnich-klubu-a-obdobnych-zajmovych-sdruzeni).

## Foundation gate – podmínky pro „můžeme začít“

Všechny položky musí být splněné:

- [ ] čistý a synchronizovaný `main`, bez kolize s deploy trackem,
- [ ] schválená kanonická identita a model rodič–dítě,
- [ ] schválené oddělení reward credit a cash top-up,
- [ ] anonymizované realistické KIS a Shoptet fixtures,
- [ ] PHPUnit/integrační test harness a CI před deployem,
- [ ] staging/test DB oddělená od produkce,
- [ ] prakticky ověřená obnova DB zálohy,
- [ ] rozhodnuté účetní, storno, DPH a retenční zásady,
- [ ] vlastník každé externí integrace a sandbox/test strategie,
- [ ] přijaté akceptační brány z dokumentu 04.

Po této bráně je stav **GO pro Fázi 1**, nikoliv automaticky GO pro Stripe,
wallet nebo KIS cutover.
