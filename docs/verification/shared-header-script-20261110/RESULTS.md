# P11-HJS — průběžná přejímka

NEVYBRÁNO; prototyp zachovaný bez merge/deploy. [Plán](../../SHARED_HEADER_SCRIPT_PERFORMANCE_2026-11-10.md).
Statický audit canonical hlavicka.php:68443bytes, staff script14812bytes,
tělo14717bytes,9PHP interpolací/6odlišných hodnot. Není to měření HTTP zisku.
Nové runtime/HTTP/Chrome/CI/merge/deploy NOT RUN. WIP7a7afd75 zachovaný.


## Uzavření výběru — NEVYBRÁNO, prototyp zachovaný

Nové skutečné HTTPS GET obou existujících JS s Accept-Encoding:gzip prokázaly
identity přenos, nikoli gzip: Bootstrap80721 + app-ui8561 =89282bytes. Oba
mají Cache-Control:max-age=30. CSS má gzip/604800, tato politika se na JS
nevztahuje. [Bezpečná měření](production-js-transport.json).
Offline gzip6 odhad obou JS je26809bytes; není to ještě změřený nový HTTP zisk.

Přesun14.7kB inline JS do nového nekomprimovaného assetu může zvýšit cold
přenos, protože HTML už gzip má; krátká30s cache omezuje zisk navigací.
Proto se kandidát HJS nepřijímá do runtime. Mechanická shoda prefix/suffix,
JS a všech PHP hodnot PASS, srovnávací JS7případů a normalizátor1/8 PASS.
Native/browser výkon prototypu nebyl přijat ani nasazen. Vlastní prototyp
zůstane commitnutý pouze na codex/shared-header-assets-performance-20261110.

Přednost má menší a šířeji působící zásah: povolit gzip pro existující veřejné
JS pod assets, beze změny jejich dekódovaných bytes, HTML nebo počtu požadavků.
Zachovat30s cache, rootSW i ostatní typy a soukromé cesty. Nový řez P11-JSZ
bude mít samostatný plán, skutečný Apache/nativeHTTP/Chrome a release gate.
Zadání merge/deploy platí až pro plně přijatý JSZ; HJS se do produkce neslučuje.
