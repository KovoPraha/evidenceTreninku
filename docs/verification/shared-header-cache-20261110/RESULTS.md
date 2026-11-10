# P11-HJS3 — průběžné ověření společného pracovního skriptu

ROZPRACOVÁNO. [Plán](../../SHARED_HEADER_SCRIPT_PERFORMANCE_2026-11-10.md#p11-hjs3--společný-skript-po-odstranění-duplicitních-stylů-10-11-2026).
Čerstvý main5d6434dc: původní hlavička byte-shodná s referencí HJS2, žádné top-level JS deklarace.

## Lokální kontrola implementace

- PASS: prefix/suffix hlavičky, původní JS mimo vyjmenovaný validační prefix,
  same-origin manifest a 9 interpolací; všech 6 PHP výrazů tokenově shodných.
- Veřejný soubor 15 271 B, SHA256
  `222b64217870ab726e3ac50483b00fd54e4e0d79251917dfb5162b5de275c8ee`;
  obsahový název a SHA384 SRI kontrolované mechanicky. Původní blok 14 812 B.
- PASS: 8 Node VM porovnání (push subscribe/unsubscribe, fresh CSRF, chybné
  cesty/context, hledání a escaping, fallback confirm). Žádná reálná oznámení.
- PASS: porovnávací PHPUnit 1 test / 16 assertions, včetně odmítnutí jiné URL,
  hashe, SRI, nonce, dalšího skriptu/CSS/whitespace a nesouhlasného CSRF formuláře.
- PASS: PHP style 4 soubory / 0 oprav / 0 chyb; Python syntax, JS syntax,
  aktuální dokumentace 80 řádků / 5 317 odkazů / 0 chyb.
- Native/browser/úspora celého gzip přenosu/aktuální CI/merge/deploy: NOT RUN.
  Dokud neprojdou, žádné nasazení. Původní WIP a staré prototypy zachované.

Porovnání celých stránek používá pouze nonce z CSP a ověřený session CSRF;
neodstraňuje CSS, další skripty, verze ani whitespace. Obě větve fixture mají
fyzicky stejný aktuální PHP kontext mimo hlavičku; celé syntetické schéma se
kontroluje checksumy. Apache před variantou používá původní assets/.htaccess;
nových 7 dní platí jen pro obsahově adresovaný staff soubor. Ostatní JS 30 s.
