# Rodné číslo — bezpečnostní a provozní kontrakt

Stav: lokální implementace R2, produkční aktivace nepovolena

Kontrakt: `person-sensitive-v1`

Datum: 16. 8. 2026

## Kde údaj žije

Nové rodné číslo se ukládá výhradně do `osoba_citlive_udaje` jako ciphertext
XChaCha20-Poly1305, samostatný 24b nonce, verze klíče a 32b HMAC slepý index.
AAD váže ciphertext na `record_token` a ID registrační žádosti. Klíče ani
plaintext nejsou v databázi, auditu, historii sportovce nebo zdrojovém kódu.

Legacy sloupec `sportovci.rc` zůstává dočasně fyzicky přítomný, ale nový KIS
sync jej nečte ani nezapisuje. Produkční read-only preflight 16. 8. 2026 zjistil
1 241 neprázdných řádků. Tyto hodnoty se nesmějí automaticky backfillovat nebo
mazat. Převod vyžaduje samostatný schválený dry-run, validaci, kontrolu
unikátního slepého indexu a paritu před vyprázdněním legacy hodnot.

## Klíče a fail-closed chování

Mimo Git musí být nastaveny:

- `PERSON_RC_KEYRING_JSON`: JSON objekt `verze -> base64(32 bajtů)`;
- `PERSON_RC_ACTIVE_KEY_VERSION`: aktivní klíč z keyringu;
- `PERSON_RC_INDEX_KEY`: samostatný `base64(32 bajtů)` klíč pro HMAC.

Alternativou v ignorovaném `config.php` jsou konstanty
`PERSON_RC_KEYRING` (pole), `PERSON_RC_ACTIVE_KEY_VERSION` a
`PERSON_RC_INDEX_KEY`. Šifrovací klíč a indexový klíč nesmějí být stejné.
Chybějící nebo neplatná konfigurace uzavře pouze uložení/zobrazení RČ;
necitelné části aplikace mohou dál fungovat.

Keyring musí zůstat dostupný pro všechny dosud používané verze. Rotace je
auditovaná operace decrypt → encrypt s explicitním důvodem. Key escrow a jeho
záloha jsou oddělené od SQL zálohy a repozitáře.

## Přístup a audit

Plné i maskované čtení vyžaduje natvrdo session roli `admin`; nepoužívá
konfigurovatelnou tabulku `opravneni`. Trenér, hlavní trenér, veřejný účet,
sportovní účet, worker ani exportní skript nemají decrypt cestu.

Maskované čtení, odhalení, změna, výmaz, rotace klíče a zobrazení interní
fotografie zapisují do `osoba_citlive_pristupy` pouze ID kontextu, administrátora,
akci, důvod, IP a čas. Audit nikdy neobsahuje RČ, ciphertext, slepý index nebo
storage key.

HTTP odpovědi citlivých endpointů mají `no-store`, zákaz referreru a
`nosniff`. Odhalení je pouze POST + CSRF + důvod alespoň 10 znaků.

## Validace a cizinec

Přijímá se 9/10 číslic s volitelným lomítkem. Kontroluje se kalendářní datum,
offset měsíce `+50`, `+20` a `+70`, délka podle roku, dělitelnost 11 u
desetimístného čísla a přesná shoda s vyplněným datem narození. Nesoulad se
nikdy neopravuje ani nehádá.

Cizinec, kterému české RČ nebylo přiděleno, má explicitně
`has_czech_birth_number=false`; citlivý řádek nevznikne a náhradní číslo se
nevyrábí.

## Fotografie

Interní fotografie se po MIME a rozměrové kontrole dekóduje a znovu uloží jako
JPEG bez EXIF do `private://athlete-photos/` mimo webroot. V databázi jsou jen
náhodný storage key, SHA-256, MIME, velikost a rozměry. Plný soubor doručuje
`private_download.php` pouze administrátorovi a každé zobrazení audituje.
Veřejný souhlas nevytváří veřejnou kopii ani URL.

## Retence a výmaz

Vlastník 20. 10. 2026 potvrdil, že TJ KOVO žádá o státní podporu a předává
rodná čísla evidovaných sportovců NSA do Rejstříku sportu podle § 3e odst. 2
písm. c) zákona č. 115/2001 Sb. Rodné číslo klub uchovává po dobu členství.
Předání rodného čísla ČSC se eviduje odděleným, volitelným souhlasem. Bez
zaznamenaného souhlasu k tomuto účelu jej klub ČSC nepředá.

- Při zamítnutí nebo zrušení žádosti systém nastaví `retention_pending` a
  `retention_until` na 30 dní od rozhodnutí. Administrátor s pozicí registrátor
  provede po uplynutí lhůty chráněnou akci `erase_due` v
  `athlete_sensitive_admin.php` (POST, CSRF, `confirm=VYMAZAT-PO-LHUTE`).
  Jedna transakce vymaže nejvýše 100 splatných záznamů; akci je nutné opakovat,
  dokud vrací `erased_count=0`. Provoz musí tento krok pravidelně vykonávat;
  pouhé dosažení data výmaz samo nespustí.
- Po ukončení členství administrátor zkontroluje zákonné výjimky a provede
  chráněnou akci `erase` se `record_id`, důvodem alespoň 10 znaků a
  `confirm=VYMAZAT-RODNE-CISLO`. Výmaz je auditovaný. Při konkrétní povinnosti
  či nároku vyžadujícím delší uchování zaznamená důvod a omezenou lhůtu;
  samotná registrace dobu po skončení členství automaticky neprodlužuje.
- Fotografie a ostatní údaje žádosti mají samostatný retenční režim; výmaz
  rodného čísla je sám neodstraní.

Výmaz přepíše ciphertext, nonce a slepý index náhodnými bajty a nastaví stav
`erased`; zůstává ne-PII audit. Před ostrou registrací musí být odpovědnost za
pravidelné spuštění výmazu a kontrolu ukončených členství provozně přidělena.
