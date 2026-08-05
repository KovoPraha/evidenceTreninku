# Sjednocení bezpečnostní infrastruktury – 5. 8. 2026

Výchozí audit našel dva HIGH a několik MEDIUM problémů v legacy vrstvě. Opravy jsou
v commitu `6655a39`; produkce se při implementaci nezměnila.

## Uzavřené nálezy

- **HIGH – veřejné soukromé přílohy:** nové účtenky a soubory zátěžových testů se
  ukládají přes `includes/private_storage.php` mimo webroot. Výdej zajišťuje
  `private_download.php` po kontrole session, oprávnění nebo platného veřejného
  hashe sportovce. Legacy adresáře jsou navíc blokované v `.htaccess`.
- **HIGH – Host poisoning:** reset hesla, registrace, rezervace a další absolutní
  odkazy používají pouze konfigurovanou `APP_BASE_URL` přes `includes/app_url.php`.
  Hodnota `HTTP_HOST` se do bezpečnostních odkazů nepřenáší.
- **MEDIUM – uložené XSS:** známé `innerHTML` sinky byly nahrazeny DOM API,
  vložený JSON používá `JSON_HEX_*` a HTML e-mailových šablon prochází úzkým
  serverovým allowlistem.
- **MEDIUM – CSRF:** zápis další činnosti i náhled ukládané e-mailové šablony
  vyžadují CSRF token; nepodporované metody jsou odmítnuty.

Lokální převod existujících souborů proběhl po ověřené záloze příkazem
`php bin/migrate-private-files.php --apply`. Dvě používané účtenky byly přesunuty
do `C:\xampp\private\evidencePavel`; opakovaný dry-run hlásí dva již soukromé
záznamy a žádnou chybu. Neodkazované legacy soubory zůstaly na místě, ale jejich
přímé HTTP adresy vracejí 403.

## Ověření

- PHPUnit: 452 testů, 3 951 kontrol;
- syntaxe: 450 vlastních PHP/PHP3 souborů bez chyby;
- migrace: legacy `2.20.2`, katalog 48/48, nic nečeká;
- Composer audit: bez známého bezpečnostního nálezu;
- HTTP: legacy účtenka 403, interní zátěžový obrázek 403, nepřihlášený download
  účtenky 403.

## Otevřené navazující řezy

Tyto body nejsou vydávány za opravené a před produkčním nasazením zůstávají v plánu:

1. přesunout zbývající DDL z webových requestů do číslovaných migrací a ověřit
   aplikaci pod DB účtem bez DDL oprávnění;
2. převést registrační, resetovací a rezervační e-maily na společnou frontu se
   stavem doručení, retry a bezpečným opakováním;
3. doplnit `bin/verify.php` jako jedinou reprodukovatelnou přednasazovací bránu;
4. před ostrým deployem ověřit produkční Apache, `APP_BASE_URL`, soukromý adresář,
   zálohu a návratový postup.

## Produkční konfigurace souborů

`APP_PRIVATE_STORAGE_ROOT` musí být absolutní zapisovatelný adresář mimo webroot.
Nasazení nejprve spustí dry-run, potom zálohu, převod `--apply` a až poté zapne
nový kód. Bez nastaveného soukromého adresáře produkční upload záměrně selže.
