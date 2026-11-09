# Předání P11-ASSET — 10. 11. 2026

ROZPRACOVÁNO. [Plán](BOOTSTRAP_ASSET_PERFORMANCE_2026-11-10.md),
[důkazy](verification/bootstrap-assets-20261110/RESULTS.md).

RIX SQL/index varianty nebyly přijaty; původní reader je přesně ff182971.
Produkce nadále17110a7f, nový deploy NOT RUN. Draft425 dosud b9 a regresuje;
neslučovat jej, dokud nebude nahrazen frontendovým řešením a celou přejímkou.

Další analýzou doložený zásah: odstranit duplicitní Bootstrap CDN CSS z3stránek.
Lokální canonical232803bytes/SRI identické. WIP7a7afd75 zachovaný, vlastní
probe větev mimo htdocs. Přesné zdroje po odečtení tagů, HTTP/business/roles,
17paired Chrome cold/warm desktop390/public, CSS2→1, bez časové regrese.
Další krok implementace a nativní profil; žádné SQL/DB nebo provozní přepínače.
