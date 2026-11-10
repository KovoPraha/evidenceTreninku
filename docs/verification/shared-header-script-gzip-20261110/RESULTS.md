# P11-HJS2 — přejímka po zavedení gzip

ROZPRACOVÁNO. [Plán před implementací](../../SHARED_HEADER_SCRIPT_PERFORMANCE_2026-11-10.md#p11-hjs2--nové-posouzení-po-nasazení-gzip-10-11-2026).
Basefaed76c0; runtimef682cc46. Staré HJS odloženo před kompresí;
nové nativní/browser/release výsledky NOT RUN. WIP zachovaný.

## Mechanická a srovnávací přejímka — PASS

Prefix/suffix hlavičky nezměněný; JS tělo doslovně stejné po9 explicitních
kontextových substitucích, všech6 PHP hodnot tokenově shodných. Modul14602B,
SHA2564d430c9482dd4f25ad9b5546dc1bbf00fc4b2fb5e6c4d26cc4173ff9585ec080.
NodeVM7/7 (push dostupnost/subscribe/unsubscribe/CSRF/searchescape/confirm),
PHP normalizátor1test/8assertions PASS. Nejde o výkonové měření.

Přidán izolovaný Apache front s fyzickými assets, existující root bezpečností
před proxy na vlastní4PHP workers/variantu. Skutečný HTML/JSgzip a JSmaxage30;
stejná proxy/DB topologie oběma. Nový JSZ kontrakt nyní komprimuje aktuální
kanonické assets oběma směry, nefixuje navždy nesouvisející PHP na6dee3253.
Gzip/identity/q0/decodedhash/cache/range/security prahy se neoslabují.
Chrome bude sledovat skutečné novépayloadbytes i celéHTML, bez vynucených
warmhits po30s; cold/warm a pixelový gate stále čekají. Native/CI/release NOT RUN.
