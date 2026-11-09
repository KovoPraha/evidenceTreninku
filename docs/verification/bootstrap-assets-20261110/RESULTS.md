# P11-ASSET — průběžné ověření

ROZPRACOVÁNO. [Plán](../../BOOTSTRAP_ASSET_PERFORMANCE_2026-11-10.md).
Baselineff182971/runtime17110a7f. RIX SQL/index varianty jsou nevybrané,
reader obnoven přesně. Canonical Bootstrap232803bytes/SHA384 odpovídá SRI
na duplicitních CDN odkazech; živý payload/HTML/Chrome následují.
Nový app merge/deploy NOT RUN, WIP7a7afd75 zachovaný.


## Příprava — PASS

Pouze3 přesné odstraněné tagy; canonical LF všechny3zdroje proti ff182971
jinak identické, celý reader byte-shodný. Živý [lokální/CDN CSS payload](public-css-identity.json)
oba232803decoded bytes a stejný SHA384/SRI; lokální gzip31248bytes,CDN30829bytes.
Bez cookies, TLSověřené,2GET/200. Odstranění CDNkópie tedy šetří jeden
externí render-blocking request a jeho30829gzip bytes při cold načtení.
To je deterministický přenosový zisk, zatím ne naměřená úspora FCP.
Úzký normalizátor1test/6assertions PASS, odmítá jinou verzi/SRI i chybějící/
duplicitní/externí lokální kopii. Žádný app source edit nad rámec3tagů.
Nativní HTTP/Chrome a finální aktuální CI následují.
