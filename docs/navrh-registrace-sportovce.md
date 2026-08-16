# Návrh samoobslužné registrace sportovce

Stav: **návrh Fáze 1 ke schválení vlastníkem; neimplementováno**

Datum: 16. 8. 2026

Kontrakt návrhu: `athlete-registration-v1`

Schválení vlastníka z 16. 8. 2026 otevírá lokální implementační řezy R1–R6.
R7 zůstává uzavřený a produkční aktivace není povolena. Body 2 a 3 jsou
schválené v doporučeném znění. Pro bod 8 vlastník určil jako právní titul
zákonnou povinnost klubu; výjimkou je pouze cizinec, kterému české RČ nebylo
přiděleno (`has_czech_birth_number=false`, bez náhradního čísla). Konkrétní
právní zdroj a konečné retenční lhůty budou doplněny před produkční aktivací;
do té doby jsou lhůty v části 5.7 výslovně předběžné. Bod 9 se rozhodne až u
řezu R3.

Výchozí lokální HEAD: `9e4ae69c674d83c713d7a1392f2e85a767a4d1e6`

Do schválení tohoto dokumentu se nesmí měnit PHP, databázové schéma ani
produkce. Doporučený produktový směr je **B2 — objednávka s čekajícím
příjemcem**, ale implementace B2 smí začít až po výslovném potvrzení vlastníka
a až jako poslední řez R7.

## 1. Ověřený výchozí stav a drift zadání

Následující fakta byla ověřena proti lokálnímu Gitu, schématu a aktuálnímu kódu,
nikoli převzata ze starší dokumentace:

- lokální `main` je po čerstvém `git fetch --prune origin` na
  `9e4ae69c674d83c713d7a1392f2e85a767a4d1e6`, tedy 14 commitů před
  `origin/main` (`6bf27eaa6a8119da768e58cada3efa425c75760b`) a 0 commitů za ním;
- F4, F6 a F7 Promptu A jsou už v lokálním `main`. Řez R4 proto musí přímo volat
  existující `personMatchV1()` z `includes/person_match.php` a navázat na
  transakční schválení v `eshop_identity_admin.php`;
- lokální DB je `evidence`, MariaDB 10.4.32, 150 tabulek a migrační kontrola
  hlásí `current`; produkční DB ani produkční obsah nebyly v tomto auditu
  čteny ani měněny;
- v `sportovci` už existuje plaintextový sloupec `rc VARCHAR(20) NULL` a
  `sync_evidence.php` i `includes/kis_sync_lib.php` s ním pracují. Lokálně je
  počet neprázdných hodnot `0`; produkční počet je **neověřený**. Tvrzení
  vstupního promptu, že dnes údaj této citlivosti nikde není, proto není pravda;
- `sportovci` už obsahuje adresní sloupce `adresa_ulice`, `adresa_cp`,
  `adresa_co`, `adresa_obec`, `adresa_psc`; není důvod vytvářet druhý adresní
  model pro první verzi;
- K3 má neměnnou tabulku `club_event_term_versions`, ale ta je pevně svázaná s
  `club_events.event_id` a obsahuje i povinné storno podmínky. Není to dnes
  obecný registr souhlasů;
- `member_charges_admin.php` je jen read-only přehled. R6 musí přidat
  explicitní auditovaný writer nad `member-charge-v1`; nestačí pouze odemknout
  existující tlačítko;
- soukromé úložiště je opravdu mimo webroot, ale povoluje jen kategorie
  `receipts` a `stress-tests`. Fotografie sportovce vyžaduje novou povolenou
  kategorii a novou hard-coded admin-only větev v `private_download.php`;
- aktuální ownership kontrakt zálohy není historické „`.9`“, ale
  `2026-08-09.1` v `bin/db-backup.php`. Každá nová trvalá tabulka níže do něj
  patří ve stejném commitu jako migrace.

Nesledované dokumenty, lokální nástroje, `.agents/` a `var/` mají nejasného nebo
uživatelského vlastníka a návrh se jich nedotýká.

## 2. Rozhodnutí návrhu

1. Nevznikne druhá fronta. `account_person_claim_requests` zůstane jedinou
   administrátorskou frontou pro propojení i registraci.
2. Rozšířená data registrace budou v 1:1 tabulce, aby jednoduchá žádost o
   propojení nenesla desítky prázdných sloupců.
3. RČ nebude nově ukládáno do `sportovci.rc`, requestu, auditu ani souboru.
   Čekající i schválené RČ bude v jednom odděleném šifrovaném záznamu.
4. Adresa se po schválení uloží do již existujících sloupců `sportovci`.
   Historie stěhování není v první verzi požadována.
5. Fotografie bude mimo webroot, bez veřejné URL a bez bearer tokenu. Úplný
   obrázek uvidí jen administrátor přes auditovaný endpoint.
6. Skupina, podskupina, sezonní soupiska a členský předpis vzniknou jen po
   samostatném explicitním rozhodnutí administrátora.
7. Veřejný formulář nikdy nespustí matching proti `sportovci` a nikdy
   neprozradí existenci osoby ani shodu RČ.
8. Zdravotní omezení se v R1–R7 **nesbírají**. Jejich zavedení by vyžadovalo
   samostatný účel, právní titul, retenční pravidlo a přísnější přístupový model.

## 3. Datový model

### 3.1 Rozšíření jediné fronty

Do `account_person_claim_requests` přidat:

| Sloupec | Typ / pravidlo | Účel | Záloha |
|---|---|---|---|
| `request_kind` | `VARCHAR(32) NOT NULL DEFAULT 'person_link'` | rozliší `person_link` a `athlete_registration` | tabulka už v kontraktu je |
| `contract_version` | `VARCHAR(64) NULL` | u registrace vždy `athlete-registration-v1`; staré žádosti zůstanou `NULL` | tabulka už v kontraktu je |

Stav zůstane jediný: `pending`, `approved`, `rejected`, `cancelled`. Rozhodnutí,
aktér a důvod dál zapisuje `account_person_claim_events`. Nevznikne paralelní
audit ani paralelní seznam čekajících žádostí.

### 3.2 `athlete_registration_request_details`

Jedna řádka na registrační žádost:

| Sloupec | Pravidlo |
|---|---|
| `request_id` | PK + FK na `account_person_claim_requests.id`, `ON DELETE RESTRICT` |
| `submitted_related_sportovec_id` | nullable FK; smí ukázat jen na osobu s aktivní schválenou `self`/`guardian` vazbou daného účtu |
| `contact_email_snapshot`, `contact_phone` | kontakt žadatele v okamžiku podání; e-mail se bere z ověřeného účtu |
| `citizenship_country_code` | nullable ISO 3166-1 alpha-2; podklad pro rozhodnutí o cizinci bez českého RČ |
| `address_street`, `address_house_number`, `address_orientation_number`, `address_city`, `address_postcode` | pending adresa; po schválení se kopíruje do existujících `sportovci.adresa_*` |
| `created_at`, `updated_at` | časová razítka |

Jméno, příjmení, datum narození, účet, vztah a stav se neduplikují; už jsou v
kanonické žádosti. Tabulka je trvalá a **musí být v ownership kontraktu
zálohy**.

### 3.3 `osoba_citlive_udaje`

Jeden šifrovaný záznam pokryje pending žádost i později schválenou osobu. Při
schválení se pouze doplní `sportovec_id`; RČ se nekopíruje mezi dvěma tabulkami.

| Sloupec | Pravidlo |
|---|---|
| `id` | PK |
| `record_token` | 32 náhodných hex znaků, unique; stabilní součást AAD |
| `request_id` | unique FK na žádost, `ON DELETE RESTRICT` |
| `sportovec_id` | nullable unique FK na `sportovci`; doplní se až po schválení |
| `rc_ciphertext` | binární ciphertext včetně autentizačního tagu; nikdy plaintext |
| `rc_nonce` | přesně 24 bajtů pro XChaCha20-Poly1305 |
| `rc_key_version` | identifikátor aktivního klíče, ne klíč |
| `rc_blind_index` | 32b HMAC-SHA-256 z normalizovaného RČ, unique; jiný tajný klíč než pro šifrování |
| `contract_version` | `person-sensitive-v1` |
| `status` | `pending`, `active`, `retention_hold`, `erased` |
| `retention_reason`, `retention_until`, `erased_at` | řízená retence a doložitelný výmaz |
| `created_at`, `updated_at` | časová razítka |

Tabulka je trvalá a **musí být v ownership kontraktu zálohy**. Plaintextový
prefix pro masku se neukládá; maska vznikne až po oprávněném dešifrování.

### 3.4 `osoba_citlive_pristupy`

Append-only audit citlivých operací:

| Sloupec | Pravidlo |
|---|---|
| `id`, `sensitive_record_id` | PK a FK na `osoba_citlive_udaje` |
| `sportovec_id`, `request_id` | nullable reference pro dohledání kontextu |
| `actor_trainer_id` | povinný administrátor |
| `action` | `masked_view`, `reveal`, `replace`, `erase`, `key_rotate`, `photo_view` |
| `reason` | povinný u odhalení, změny, výmazu a rotace |
| `ip_address` | IP požadovaná vlastníkem; nikdy RČ ani jiné vstupní hodnoty |
| `created_at` | čas operace |

Tabulka je trvalá a **musí být v ownership kontraktu zálohy**. Audit nikdy
neobsahuje ciphertext, blind index, celé RČ ani cestu k souboru.

### 3.5 `athlete_private_files`

Metadata fotografie; samotný soubor je v nové kategorii
`private://athlete-photos/<random>.jpg|png` mimo webroot.

| Sloupec | Pravidlo |
|---|---|
| `id`, `request_id`, `sportovec_id` | PK, povinná žádost, osoba nullable do schválení |
| `file_kind` | první verze pouze `profile_photo` |
| `storage_key` | náhodný private storage key; ne původní cesta |
| `sha256`, `byte_size`, `mime_type`, `width_px`, `height_px` | serverově zjištěná metadata |
| `status` | `active`, `replaced`, `withdrawn`, `erased` |
| `consent_snapshot_id` | FK na samostatný snapshot interní fotografie |
| `created_at`, `replaced_at`, `erased_at` | životní cyklus |

Původní název není pro účel potřeba a neukládá se. Rozměry, limit velikosti,
MIME přes `finfo`, dekódování obrázku a re-encoding bez EXIF proběhnou před
uložením. Tabulka je trvalá a **musí být v ownership kontraktu zálohy**;
fyzické soubory musí být zahrnuty do provozní zálohy privátního storage a
restore drillu, nikoli do SQL artefaktu.

### 3.6 `athlete_registration_consent_snapshots`

Neměnný důkaz přesného textu potvrzeného účtem:

| Sloupec | Pravidlo |
|---|---|
| `id`, `request_id` | PK a FK na žádost |
| `purpose` | `member_data_notice`, `birth_number_legal_notice`, `photo_internal`, `photo_public` |
| `term_version_id`, `terms_version` | reference na rozšířený K3 registr + čitelná verze |
| `text_snapshot` | přesný text v okamžiku přijetí |
| `accepted` | explicitní boolean; povinné účely musí být true |
| `accepted_by_account_id`, `accepted_at` | kdo a kdy |
| `withdrawn_at` | jen pro odvolatelné účely, zejména fotografie |

Unique klíč je `(request_id, purpose)`. Tabulka je trvalá a **musí být v
ownership kontraktu zálohy**.

### 3.7 Rozšíření existujícího K3 registru, ne nový registr

Skutečný `club_event_term_versions` nelze bez úpravy použít pro registraci,
protože vyžaduje `event_id`, storno text a deadline. Doporučení je řízeně
zobecnit právě tuto tabulku:

- přidat `scope_type`, `scope_key` a `consent_purpose`;
- povolit `event_id`, `cancellation_policy_plain` a
  `cancellation_deadline_at` jako nullable pro neudálostní účely;
- zachovat starý unikátní klíč událost + verze a přidat unique
  `(scope_type, scope_key, consent_purpose, terms_version)`;
- stávající K3 zápisy dál používat jako `scope_type='club_event'`; nové texty
  registrace budou `scope_type='athlete_registration'`;
- tabulku nepřejmenovávat v prvním řezu a nevytvářet vedle ní druhý registr.

Relaxace `NOT NULL` je zpětně kompatibilní expand změna, ale není to pouhé
přidání sloupce. Vlastník musí potvrdit, že takové zobecnění existujícího K3
registru přijímá. Pokud ne, je potřeba změnit zadání „nezakládej nový registr“;
syntetická klubová událost pro registrační souhlasy je zakázaný workaround.

### 3.8 Volitelné tabulky a sloupce jen pro schválenou B2

Tyto změny nepatří do R1, dokud vlastník nepotvrdí B2:

- `shop_order_items.registration_request_id` nullable FK;
- `shop_order_items.beneficiary_status` s hodnotami `resolved`,
  `pending_registration`, `rejected`;
- `club_program_registration_holds` pro rezervaci kapacity od checkoutu do
  schválení/expirace, se stavem `active`, `converted`, `released` a unique
  `source_order_item_id`;
- nový aplikační stav objednávky `awaiting_person_approval` nad stávajícím
  `VARCHAR`, bez nového finančního stavového stroje.

Nová hold tabulka je trvalá a **musí být v ownership kontraktu zálohy**.

### 3.9 Co záměrně nepřidáváme do `sportovci`

- žádné nové RČ, ciphertext, nonce, blind index ani klíč;
- žádnou cestu nebo binární obsah fotografie;
- žádný automaticky odvozený tým, kategorii nebo sezonu;
- žádná zdravotní omezení;
- žádný druhý stav registrační žádosti.

Legacy `sportovci.rc` zůstane v první aditivní migraci fyzicky přítomný kvůli
kompatibilitě, ale nový kód do něj nesmí zapisovat ani jej číst. Jeho bezpečné
vyprázdnění je samostatná řízená provozní akce popsaná v části 6.8.

## 4. Uživatelské a administrátorské toky

### 4.1 Tok A — samostatná registrace

1. Ověřený přihlášený účet otevře `booking/registrace_sportovce.php` ze stránky
   „Moje osoby“ nebo z prázdného stavu e-shopu.
2. Zvolí `guardian` nebo `self`. Server znovu ověří aktivní účet a e-mail.
3. Vyplní jméno, příjmení, datum narození, kontakt, adresu, občanství a podle
   schválených pravidel RČ. Fotografie je podle doporučení volitelná.
4. Každý souhlas/informační text má samostatný checkbox a verzi. Veřejný
   formulář neobsahuje žádné výsledky matchingu.
5. Server validuje celý vstup, RČ a datum, uloží request, detail, šifrované RČ,
   snapshoty souhlasů a soubor v jedné aplikační transakci s kompenzačním
   odstraněním nedokončeného uploadu.
6. Výsledek je vždy neutrální: „Žádost byla odeslána ke kontrole
   administrátorovi.“ Stejný text se použije i při interně nalezeném konfliktu;
   veřejná větev však `personMatchV1()` vůbec nevolá.
7. Opakované stejné podání vrátí existující pending žádost. Účet může svou
   pending žádost zrušit; citlivá data přejdou do retenčního režimu, nemažou se
   nekontrolovaně v HTTP requestu.

Chybové stavy před zápisem: neověřený účet, neplatné datum/RČ, nesoulad RČ s
datem, chybějící povinný text, nepovolený soubor, překročený limit a chybějící
šifrovací klíč. Chybová odpověď nesmí obsahovat vložené RČ ani cestu k souboru.

U už existujícího veřejného `self` profilu se může žádost bezpečně vztáhnout k
této vlastní schválené osobě přes `submitted_related_sportovec_id`; server ID
znovu ověří proti aktivní vazbě. Veřejný profil sám o sobě nadále neznamená
schválené klubové členství.

### 4.2 Tok B — nákup bez propojené osoby

#### B1 — registrace napřed

E-shop nabídne „Přihlásit nového sportovce“, uloží návratovou URL a nákup
programu dovolí až po schválení a ručním zařazení. Nevyžaduje změnu finančního
lifecycle ani kapacitní hold, ale uživatel nákup nedokončí v jednom průchodu.

#### B2 — objednávka s čekajícím příjemcem (doporučeno)

První bezpečná verze B2 má tyto omezení:

- objednávka obsahuje právě jednu programovou položku, množství 1; nemíchá se
  s běžným zbožím ani s druhou čekající registrací;
- použije veřejnou cenu. Budoucí členství ani klubová sleva se nepřiznávají
  zpětně;
- při checkoutu vznikne registrační žádost a kapacitní hold. Order item má
  `beneficiary_sportovec_id=NULL`, ale povinné `registration_request_id`;
- platbu lze přijmout standardní bankovní/Stripe cestou. Potvrzení platby se
  nesmí vrátit zpět jen proto, že osoba čeká; objednávka přejde na
  `awaiting_person_approval` a účast ještě nevznikne;
- administrátor po schválení osoby výslovně potvrdí cílové zařazení. Teprve v
  této transakci se doplní beneficiary, hold převede na účast a zavolá se
  kanonický programový lifecycle;
- neuhrazená zamítnutá žádost zruší objednávku a uvolní hold. Uhrazená
  zamítnutá žádost zruší celou jedno-položkovou objednávku přes existující
  storno lifecycle a vytvoří `refund_required`; žádná automatická externí
  vratka se nespouští;
- expirace platby, storno zákazníkem i zamítnutí uvolní hold právě jednou;
- timeout administrátorského rozhodnutí a délka holdu musí být schválené
  vlastníkem. Zaplacený hold nesmí tiše expirovat bez provozního signálu.

Toto je nejrizikovější část. R7 musí mít testy souběhu posledního místa,
duplicitního webhooku, zamítnutí před/po platbě, expirace a právě-jednou
uvolněné kapacity.

### 4.3 Tok C — jedna schvalovací obrazovka

`eshop_identity_admin.php` zůstane jedinou frontou. Detail registrační žádosti:

1. hard-coded `roleAtLeast('admin')`, `no-store`, žádné konfigurovatelné
   oprávnění;
2. zobrazí běžné údaje, stav souhlasů a tlačítko soukromé fotografie;
3. maskované RČ se načte pouze pro admina a operace se zapíše jako
   `masked_view`; celé RČ odhalí jen samostatný POST + CSRF + důvod, s auditem
   `reveal`;
4. zavolá jedinou `personMatchV1()` a zobrazí všechny kandidáty SHODA/P1–P4.
   Samostatně přidá administrátorský signál přesné shody blind indexu RČ;
5. admin zvolí „Připojit k této osobě“, „Založit novou osobu“ nebo zamítnout.
   SHODA se chová přesně podle `person-match-v1`, včetně všech kandidátů a
   výjimky s důvodem alespoň 10 znaků;
6. založení/připojení, schválení `self`/`guardian` vazby, doplnění adresy,
   přiřazení citlivého záznamu a audit proběhnou v jedné transakci. Ručně
   založená osoba má `kis_external_id=NULL`;
7. konflikt blind indexu s jinou osobou nebo rozdílné RČ u osoby, která už RČ
   má, je fail-closed a vyžaduje samostatnou opravu dat; nikdy se nepřepisuje.

### 4.4 Tok D — ruční zařazení

Po schválení se na stejném detailu zobrazí krok „Zařadit sportovce“:

- admin vybere jednu skupinu, podskupinu náležející této skupině a aktivní tým
  v konkrétní sezoně;
- datum narození může zobrazit varování, ale nic nevybere automaticky;
- zápis do `sportovec_skupina`, `sportovec_podskupina` a
  `club_roster_members` proběhne transakčně;
- `club_roster_events` zachytí sezonní soupisku a `ucto_audit_log` s typem/ID
  aktéra a důvodem zachytí legacy skupinu a podskupinu;
- duplicita je idempotentní no-op, konflikt podskupiny a skupiny blokuje celý
  zápis.

Stav kroku se odvozuje z kanonických vazeb; nevzniká druhá onboarding tabulka.

### 4.5 Tok E — členský předpis až po zařazení

Tlačítko „Vystavit členský předpis“ je dostupné jen pokud:

1. registrační žádost je `approved` a má `matched_sportovec_id`;
2. osoba má skupinu;
3. osoba má podskupinu patřící do této skupiny;
4. osoba má aktivní `club_roster_members` v explicitně zvolené aktuální sezoně.

R6 přidá writer nad existující `club_member_charges` a
`club_member_charge_events`, ne nový finanční model. Administrátor zvolí titul,
období, splatnost, částku v haléřích, měnu a plátce z aktivních `self`/`guardian`
vazeb. Stabilní idempotency reference bude obsahovat request, sezonu a typ
předpisu. Zdroj je `membership`. Vystavení je vždy POST + CSRF + potvrzení +
důvod; nikdy se nespouští automaticky po schválení ani po zařazení.

## 5. Rodné číslo

### 5.1 Právní a produktová brána

Vlastník potvrdil, že právním titulem je zákonná povinnost klubu. Implementace
R1–R6 proto používá povinné potvrzení informačního textu
`birth_number_legal_notice`, nikoli souhlas jako právní titul. Přesný právní
zdroj, účel a konečné retenční lhůty musí vlastník dodat před produkční
aktivací; jejich doplnění neblokuje lokální implementaci ani testování.

ÚOOÚ současně uvádí, že běžné fungování spolku nemá být založeno na obecném
souhlasu, ale členové musí být přiměřeně informováni o účelu a způsobu
zpracování. Proto se `member_data_notice` i `birth_number_legal_notice`
evidují jako potvrzení informace, nikoli jako univerzální „souhlas s GDPR“.

### 5.2 Šifrování a klíče

- algoritmus: `sodium_crypto_aead_xchacha20poly1305_ietf_encrypt/decrypt`;
- 256bitové klíče jsou mimo Git v `config.php`/prostředí jako verzovaný keyring,
  například `PERSON_RC_KEYS_V1`; aktivní verze je samostatná konfigurace;
- blind index používá **jiný** 256bitový klíč `PERSON_RC_INDEX_KEY_V1`;
- AAD je `person-sensitive-v1|record_token|request_id`; záměna ciphertextu mezi
  řádky proto selže autentizací;
- bez sodium, aktivního klíče nebo index key zápis i čtení RČ selže uzavřeně.
  Ostatní necitlivé stránky aplikace mohou dál fungovat;
- klíče se nikdy nezapisují do DB, logu, test fixture, GitHub artefaktu ani
  chybové odpovědi;
- rotace je explicitní CLI/admin operace se starým i novým klíčem, ověřením
  decrypt→encrypt a auditní událostí bez plaintextu.

### 5.3 Normalizace a validace

1. přijmout číslice s volitelným `/`, interně normalizovat pouze na číslice;
2. povolit 9 číslic pro osoby narozené před 1. 1. 1954, jinak 10;
3. dekódovat rok, měsíc a den včetně ženského offsetu `+50` a dodatečných
   sestav `+20`/`+70`;
4. pro desetimístná RČ od roku 1954 vyžadovat dělitelnost 11 přes integer/string
   algoritmus, nikoli float;
5. odvozené datum musí být kalendářně platné a přesně stejné jako zadané datum
   narození;
6. nesoulad je chyba. Aplikace RČ nikdy neopravuje, nedopočítává ani neháda;
7. cizinec bez českého RČ použije jen vlastníkem schválenou explicitní větev;
   náhradní číslo se nevyrábí.

### 5.4 Přístup a zobrazení

- jediná autorizační podmínka je přihlášený trenér s
  `roleAtLeast('admin')`; `canAccess()` se nepoužije;
- trenér, hlavní trenér, veřejný účet, sportovní účet, worker i exportní CLI
  nemají decrypt API;
- admin detail zobrazí masku například `900101/****`; celé číslo jen na
  samostatný POST + CSRF + povinný důvod;
- každé maskované čtení, odhalení, změna, výmaz a rotace má actor ID, čas, IP a
  předmět v `osoba_citlive_pristupy`;
- response je `Cache-Control: no-store`, `Pragma: no-cache`, bez referreru a
  plaintext není v URL, HTML hidden inputu, flash session ani JavaScript logu.

### 5.5 Blind index a duplicity

Blind index je `HMAC-SHA-256(index_key, normalized_digits)`. Je unikátní a slouží
jen k přesnému serverovému porovnání. Veřejná větev jej nikdy nedotazuje.
Administrátor u kandidáta uvidí pouze „shodný bezpečný otisk RČ“, ne hash.
Blind index nenahrazuje `person-match-v1`; je další fail-closed signál.

### 5.6 Zákaz úniku

R2 musí zavést centrální citlivou službu a automatické guardy:

- žádná exportní služba neimportuje decrypt modul;
- runtime test vloží syntetické RČ pouze přes šifrovací službu a ověří, že
  sentinel ani názvy citlivých sloupců nejsou ve výstupu `export_csv.php`,
  `export_xls.php`, `export_seznam.php`, `export_uci.php`, `export_draha.php`,
  `club_event_participants_export.php`, obou ICS feedů, story generátoru, KIS
  preview/parity JSONu, auditlogu, e-mailového outboxu ani chybové odpovědi;
- statický test zakáže `sportovci.rc` a `rc_ciphertext` ve všech exportních,
  kalendářových, story, auditních a message kompozitorech;
- audit a aplikační logy přijímají pouze record/request/person ID a pevný důvod;
- KIS raw archivy a případné historické importní soubory jsou samostatný
  citlivý zdroj: musí být inventarizovány, přístupově omezeny a zahrnuty do
  schválené retence. Non-PII preview/parita se nemění.

### 5.7 Retence — doporučený výchozí kontrakt ke schválení

Technický návrh:

| Stav | Doporučená retence citlivých dat |
|---|---|
| pending žádost | do rozhodnutí; provozní alarm po 30 dnech bez rozhodnutí |
| rejected/cancelled | 30 dní pro opravu omylu/odvolání, potom výmaz RČ a fotografie; zůstane ne-PII audit rozhodnutí |
| approved + aktivní členství | jen po dobu trvání doloženého účelu evidence |
| ukončené členství | 90 dní od ukončení, potom výmaz, pokud vlastník nedoloží konkrétní delší zákonnou lhůtu a `retention_reason` |
| access audit | nejméně 2 roky; doporučeno 3 roky, protože neobsahuje RČ |
| SQL/file backup | do expirace provozního backup cyklu; restore musí znovu aplikovat tombstones/výmazy, aby smazaná data neožila |

Třicet a devadesát dní jsou předběžné produktové výchozí lhůty, nikoli tvrzení
o zákonné lhůtě. Vlastník před produkční aktivací doplní přesný právní zdroj,
konečné lhůty, odpovědnou osobu a pravidlo pro probíhající spor/kontrolu. Do té
doby lze R1–R6 implementovat a lokálně testovat, ale produkční aktivace zůstává
uzavřená.

### 5.8 Legacy `sportovci.rc`

Před produkční aktivací R2:

1. read-only preflight spočítá neprázdné `sportovci.rc` a citlivé raw archivy,
   nikdy nevypíše hodnoty;
2. nové KIS synchronizační cesty přestanou raw RČ zapisovat do `sportovci.rc`;
3. pokud produkční count není nula, samostatný vlastník-em schválený CLI převod
   provede dry-run, zašifruje každý validní záznam do
   `osoba_citlive_udaje`, ověří blind index a teprve po paritní kontrole
   vyprázdní legacy hodnotu. Nejde o automatický backfill migračního runneru;
4. neplatný, duplicitní nebo s datem nesouhlasící záznam zůstane blokátorem k
   ručnímu rozhodnutí bez výpisu hodnoty;
5. fyzické odstranění sloupce `sportovci.rc` je pozdější contract krok až po
   jednom releasu bez čtení/zápisů.

Lokální count `0` není důkazem produkčního stavu.

## 6. Fotografie nezletilého

- interní evidenční fotografie a veřejné zveřejnění jsou dva oddělené účely a
  dva oddělené snapshoty;
- interní fotografie je doporučeně volitelná. Její absence nesmí blokovat
  registraci, pokud vlastník výslovně nerozhodne jinak;
- fotografie není šifrovaná podle zadání, ale je mimo webroot, bez EXIF,
  admin-only a každé úplné zobrazení je auditované;
- veřejný souhlas nikdy nezpřístupní tuto interní kopii přes
  `private_download.php`; případná publikace musí vytvořit oddělenou schválenou
  public asset kopii v budoucím samostatném toku;
- odvolání `photo_public` okamžitě zakáže další publikaci. Odvolání
  `photo_internal` přepne soubor do řízeného výmazu podle retence;
- žádné rozpoznávání obličeje, biometrické šablony nebo automatické porovnání
  fotografie se neimplementuje.

## 7. Ochrana proti duplicitám

Jedinou autoritou je `docs/pravidla-shody-osob.md` a jedinou implementací
`personMatchV1()`:

- SHODA blokuje nové založení a nabídne všechny existující kandidáty;
- P1–P4 varují a vyžadují výslovné rozhodnutí;
- více shod se nikdy nesmrskne na prvního kandidáta;
- „Přesto založit“ vyžaduje důvod alespoň 10 znaků a audit;
- blind index RČ může kandidáta zvýraznit nebo celý krok blokovat, ale nesmí
  měnit pravidla SHODA/PODOBNOST;
- admin může připojit žádost k existující osobě nebo založit novou přes
  existující transakční F7 cestu;
- veřejná odpověď, časování a chybová větev jsou neutrální. Test T12 z
  `person-match-v1` se znovu použije i pro registrační formulář.

`includes/kis_match_lib.php` zůstává pro KIS importní klasifikaci. Registrace
nesmí vytvářet třetí matcher ani kopírovat pravidla.

## 8. Souhlasy a informační texty

| Účel | Povinnost / doporučení | Snapshot |
|---|---|---|
| `member_data_notice` | povinné potvrzení informace o správci, účelu, příjemcích, právech a retenci; není univerzální „souhlas s GDPR“ | vždy |
| `birth_number_legal_notice` | povinné potvrzení informace o zpracování na základě vlastníkem deklarované zákonné povinnosti; nejde o souhlas jako právní titul | vždy, přesný text a vztah self/guardian |
| `photo_internal` | doporučeně samostatný volitelný souhlas pro interní identifikační fotografii | pokud je fotografie nahrána |
| `photo_public` | samostatný volitelný souhlas; nesmí být předzaškrtnutý ani podmínkou členství | vždy explicitní ano/ne |
| `health_restrictions` | v R1–R7 se nesbírají | žádný |

Změna textu vytvoří novou verzi, nikdy nepřepíše starou. Request nese snapshot,
takže pozdější změna registry nezmění historický důkaz.

## 9. Bezpečnost, transakce a provoz

- všechny veřejné a administrátorské POSTy používají CSRF a ochranu proti
  dvojímu odeslání;
- účet, request, citlivý záznam, kandidáti a cílové vazby se před změnou znovu
  načtou a podle potřeby zamknou; stale formulář selže;
- aktér je vždy `account`, `trainer` nebo `system` s typem i ID; system nemá
  falešné ID trenéra;
- chyby jsou obecné a neobsahují PII. Detail jde jen do bezpečného auditního
  kódu/důvodu bez hodnot;
- migrace jsou idempotentní a kompatibilní s MariaDB 10.3; žádné `RETURNING`,
  `JSON_TABLE`, `SKIP LOCKED`, `LATERAL` ani databázové šifrovací funkce;
- R1 doplní všechny nové tabulky do `EVIDENCE_TABLES` a zvýší aktuální
  ownership verzi. Generický test musí zůstat zelený;
- restore drill musí kromě SQL ověřit i privátní fotografie a dostupnost
  správné verze šifrovacího klíče. Záloha bez klíče je záměrně nečitelná, ale
  provoz musí mít odděleně zálohovaný key escrow mimo repozitář;
- produkční klíče, deploy a DB změny vyžadují samostatný výslovný pokyn.

## 10. Implementační řezy po schválení

| Řez | Obsah | Hlavní brána |
|---|---|---|
| R1 | aditivní/expand migrace fronty, detailu, citlivých dat, auditů, fotografií a snapshotů; ownership kontrakt | MariaDB 10.3/11.4, SQLite, backup catalog; bez UI a bez backfillu |
| R2 | crypto služba, RC validátor, hard admin read/reveal, private photo storage, legacy preflight a export guards | chybějící klíč fail-closed; sentinel nikde neunikne |
| R3 | veřejný Tok A v `booking/`, neutrální odpověď a správa vlastních pending žádostí | ověřený účet, CSRF, idempotence, T12 |
| R4 | rozšíření jediné admin fronty, `personMatchV1()`, create/link/reject v jedné transakci | SHODA/P1–P4, blind-index konflikt, audit reveal/override |
| R5 | explicitní skupina + podskupina + sezonní soupiska na detailu | žádné auto-zařazení, stale/konflikt rollback |
| R6 | explicitní writer `member-charge-v1` až po readiness predicate | žádný automatický předpis, money integer, idempotence |
| R7 | pouze schválená B1/B2; u B2 pending beneficiary, hold, refund_required | souběh kapacity, platba před schválením, reject/refund, právě-jednou |

Každý řez má vlastní implementační commit, následný datovaný handoff commit,
plnou PHPUnit sadu, first-party lint, Composer brány, migration
`check → apply → check` a živý localhost průchod. Produkce se v tomto vlákně
nenasazuje.

## 11. Povinná testovací matice

- RČ: 9/10 číslic, slash/no slash, +50/+20/+70, dělitelnost 11, neplatné datum,
  nesoulad s datem, cizinec bez RČ, duplicitní blind index;
- crypto: roundtrip, změněný ciphertext/nonce/AAD, neznámá verze klíče,
  chybějící keyring/index key, rotace;
- role: admin ano; hlavní trenér, trenér, account, child a anonymní uživatel ne;
- audit: maska, reveal, změna, výmaz a fotografie přesně jednou, bez PII;
- request: self/guardian, neověřený účet, opakovaný submit, limit pending,
  cancel, stale approve/reject, vnější transakce;
- matching: povinné T1–T12 z `person-match-v1` bez druhé implementace;
- upload: MIME spoof, rozměry, velikost, EXIF odstranění, traversal, cizí ID,
  soft/řízený výmaz;
- zařazení: nesouvisející podskupina, neaktivní sezona/tým, duplicita, rollback;
- předpis: všechny čtyři readiness podmínky, cizí plátce, duplicita reference,
  záporná/float částka, neplatná měna;
- export guard: všechny jmenované exporty/feedy/story/KIS/audit/e-mail/log;
- B2: poslední kapacita ve dvou procesech, unpaid reject, paid reject,
  duplicate payment event, expiry, mixed cart fail-closed a restore holdů.

## 12. Otevřená rozhodnutí vlastníka

Lokální implementace R1–R6 byla vlastníkem otevřena 16. 8. 2026. R7 zůstává
uzavřený a bod 9 je odložen k rozhodnutí před zahájením R3.

| # | Rozhodnutí | Doporučení návrhu |
|---:|---|---|
| 1 | B1 nebo B2? | **B2** s jednou programovou položkou, kapacitním holdem a `refund_required` při zamítnutí po platbě |
| 2 | Povinná data | **schváleno:** jméno, příjmení, datum narození, kontakt, úplná adresa; RČ povinné jen pro osobu, které bylo přiděleno; občanství pro cizince |
| 3 | Cizinec bez českého RČ | **schváleno:** explicitní `has_czech_birth_number=false`; nevyrábět náhradní číslo; admin ověří jiným schváleným postupem |
| 4 | Dospělý od 18 let | self registrace ano; guardian vazbu v den 18. narozenin automaticky nerušit, ale označit k pravidelné administrátorské revizi |
| 5 | Retence RČ | pending do rozhodnutí; reject/cancel 30 dní; po členství 90 dní, pokud právní posouzení neurčí konkrétní delší lhůtu |
| 6 | Retence fotografie | stejný základ jako registrace; po odvolání interního souhlasu řízený výmaz, veřejná publikace okamžitě zastavit |
| 7 | Fotografie interní vs. veřejná | **oddělit**; obě volitelné, veřejná nikdy nepodmiňuje členství |
| 8 | Právní titul RČ | **pro R1–R6 schváleno:** vlastníkem deklarovaná zákonná povinnost klubu; konkrétní zdroj a konečná retence jsou povinná brána před produkční aktivací |
| 9 | Zobecnění K3 registru | **odloženo k R3:** přijmout rozšíření `club_event_term_versions`; nevytvářet syntetickou událost ani paralelní registr |
| 10 | Fotografie povinná? | doporučení: ne |
| 11 | SLA admin rozhodnutí / B2 hold | doporučení: upozornění po 2 pracovních dnech; zaplacený hold bez tiché expirace |
| 12 | Parametry prvního členského předpisu | kdo určí titul, částku, měnu, období a splatnost; návrh je explicitní admin formulář bez automatického výpočtu |

## 13. Zdroje pro právní a retenční část

- [Nařízení GDPR, zejména čl. 5, 6 a 9 (EUR-Lex)](https://eur-lex.europa.eu/legal-content/EN/TXT/?uri=CELEX%3A32016R0679)
- [Zákon č. 133/2000 Sb., § 13 a § 13c — rodná čísla](https://www.zakonyprolidi.cz/cs/2000-133)
- [ÚOOÚ: Rodná čísla](https://uoou.gov.cz/profesional/qa-otazky-a-odpovedi/rodna-cisla)
- [ÚOOÚ: Činnosti spolků, sportovních klubů a obdobných sdružení](https://uoou.gov.cz/profesional/qa-otazky-a-odpovedi/cinnosti-spolku-sportovnich-klubu-a-obdobnych-zajmovych-sdruzeni)

Tento dokument je technický návrh, nikoli právní stanovisko. Konkrétní právní
zdroj a konečné retenční lhůty musí odpovědná osoba klubu potvrdit před
produkční aktivací.

## 14. Schvalovací výrok

Fáze 2 je otevřená pouze pro lokální řezy R1–R6. R7, produkční migrace, deploy a
produkční aktivace zůstávají uzavřené. Před R3 je nutné uzavřít bod 9. Před
produkční aktivací musí vlastník dodat konkrétní právní zdroj a konečné retenční
lhůty.
