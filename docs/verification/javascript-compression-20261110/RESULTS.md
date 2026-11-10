# P11-JSZ — ověření

10. 11. 2026. [Plán](../../JAVASCRIPT_COMPRESSION_PERFORMANCE_2026-11-10.md).

Výchozí skutečné produkční GET: 89282 B identity, max-age=30,
[bezpečné otisky a metadata](../shared-header-script-20261110/production-js-transport.json).
Nové Apache/browser měření, implementace, CI, merge, deploy: NOT RUN.

## Průběžná kontrola Apache a upřesnění cache kontraktu

Implementováno pouze assets/.htaccess (7 řádků); původní root .htaccess,
13 JS i všechny PHP/SQL/CSS beze změny. Test má oddělené fyzické docrooty,
načítá skutečnou root bezpečnostní konfiguraci a nový soubor pouze v after.
Izolovaný Windows Apache 2.4.58 ověřil gzip negotiation i přesné bytes prvního JS.

První přísná kontrola gzip If-None-Match správně odhalila standardní Apache
AddSuffix: gzip ETag se liší a opakovaný podmíněný gzip GET vrací plný 200,
identity nadále 304. Totéž platí už před změnou pro gzip CSS.
Nejde o změnu TTL; u JS po vypršení 30 s může místo identity304 přijít nový
komprimovaný200. Nelze slíbit úsporu pro tento konkrétní revalidační scénář.
Neoslabovat ETag pomocí NoChange ani prodlužovat cache jen kvůli výsledku.
[Primární dokumentace](https://httpd.apache.org/docs/2.4/mod/mod_deflate.html#deflatealteretag).
Kontrakt proto přijme bezpečný200 nebo304, kontroluje skutečný status i hash;
identity304, Vary, TTL a negotiation zůstávají povinné. Původní očekávání všech
komprimovaných304 se neopíralo o Apache chování; nyní je opravené před release.
Nativní úplná sada a Chrome stále běží; výkon/CI/merge/deploy zatím NOT RUN.

## Windows Apache — PASS

[Strojový protokol](native-windows.json): Apache 2.4.58, 13 veřejných JS,
18 kontrolovaných souborů, gzip/identity/q=0, HEAD, identity304/range, gzip
conditional200/304 s přesným obsahem a 10 zákazů přístupu. CSS/PNG/JSON/rootSW
nezměněné. Hlavní JS 89282 → 26618 B (−70,19 %); jde o skutečný HTTP přenos,
nikoli offline gzip odhad. Cache zůstala30s; gzipETag revalidace200 je přiznaná.
Chrome běží; PR/CI/merge/deploy zatím NOT RUN.
