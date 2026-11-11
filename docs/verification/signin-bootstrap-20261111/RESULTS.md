# Bootstrap na přihlášení do KIS

Výchozí c629a789. Párová přejímka i 15 CI PASS. #453 sloučen jako 18bd541b a deploy 43104809135 spuštěn na shodném testovaném stromu.

## Výsledek a vydání

PR #453 sloučený jako 18bd541b, 15 CI PASS. Git tree f0894988467e39265d28c81e1356739cfab71c0e
je shodný s testovaným 87fe5f59f422c292ece0889e02b0f44379d758d0.
[Párový běh](https://github.com/KovoPraha/evidenceTreninku/actions/runs/43102336480).

Na MariaDB 10.3 i 11.4 prošlo 17 párů měření formuláře a čtyři kontrolní
scénáře. Shoduje se celé HTML po odstranění jedné známé značky a normalizaci
nonce a CSRF. Ověřeno odmítnutí chybného CSRF (400), prázdný formulář (200),
syntetické přihlášení (303) a přesměrování přihlášeného účtu (302).
[HTTP 10.3](mariadb-10.3/http.json.gz), [HTTP 11.4](mariadb-11.4/http.json.gz).

Chrome provedl 88 měřených navigací po 24 zahřívacích. Desktop a mobilní
rozlišení mají shodné pixely před a po. Prázdný formulář zobrazil stejné
hlášení. Studený komprimovaný Bootstrap CSS klesl z 64 454 na 31 248 B
(o 33 206 B, 51,52 %). Teplý přenos CSS byl 0 B v obou verzích.
[Mediány a velikosti](mariadb-11.4/browser-summary.json).

[Deploy 43104809135](https://github.com/KovoPraha/evidenceTreninku/actions/runs/43104809135) SUCCESS. 2456 testů a 56288 assertions.
Záloha 389 tabulek a 2 triggerů ověřena.
Aktivace, konfigurace, migrace, index, variabilní symboly a HTTP smoke 200 PASS.
Přímý runner HTTP 200. UAT a import false. Produkční čtecí kontrola
přihlášení s návratovou cestou i bez ní potvrdila shodu HTML, hlaviček
a místního CSS. Přihlášená produkční přejímka NOT RUN.
[Doklad vydání](release.json), [produkce před](production-before.json), [po](production-after.json).
