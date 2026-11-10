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

## Reálný Chrome + Linux Apache — PASS

[PR #427](https://github.com/KovoPraha/evidenceTreninku/pull/427), první Apache CI
[42772021506](https://github.com/KovoPraha/evidenceTreninku/actions/runs/42772021506) SUCCESS.
Linux Apache2.4.66: [native](native-linux.json), [Chrome mediány](browser-summary-linux.json),
[168 navigací včetně zahřívání](browser-samples-linux.json.gz).
Windows Apache2.4.58: [Chrome mediány](browser-summary-windows.json),
[168 navigací včetně zahřívání](browser-samples-windows.json.gz).
Na každé platformě 136 měřených +32 zahřívacích navigací, 2 viewporty,
cold/warm, 17 střídajících párů po4 warmup. Žádné síťové mocky nebo latence.
Všechny 13 veřejné JS dohromady 147587 →45775 B (−68,98 %); hlavní dva
89282 →26618 B (−70,19 %). Browser vidí stejné 2 requesty/89282 dekódovaných B,
cold transfer včetně ResourceTiming režie 89882 →27218 B; warm medián0 obě.
DOM/computedstyle shodné, 2 pixelové páry/platformu totožné; Bootstrap collapse,
Toast, přístupné label a odemčení zrušeného form PASS; JS/síťové chyby0.

Linux cold desktop FCP24→24 ms / load22,7→23,2 ms; mobil FCP24→24 / load22,7→23,1.
Warm load desktop14,9→15,1 / mobil15,6→15,9 ms. Windows je výrazně hlučnější:
mobile cold load136,5→177,7 ms, uvnitř předem daného gate20%+20ms; FCP152→144.
Desktopcoldload105→105,3. **Zrychlení času stránky neprokázané**; prokázaná je
nižší velikost skutečného přenosu. Žádný odhad mobilního SLA bez skutečné sítě.
Nejde o přihlášené produkční UAT; PHP/SQL a JS zdroj nejsou měněné.
Ostatní aktuální CI dosud běží, merge/deploy NOT RUN.
