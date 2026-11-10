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

## První nativní běh — částečně PASS, nepřijatý release

Run42782790667 /3d93f8ad:10.3 celýHTTP1k/10k/30k, role/security/checksum/cleanup
PASS. Staff HTML o14059B menší, offlinegzip snapshot o3,25–3,56kB menší;
nejde ještě o skutečnou browserovou úsporu.11.4 skončil na Bootstrap browser
kontraktu, doplněny přesné URL/CSS/API údaje do chyby; samotný gate se neoslabuje.
Nový samostatný JSZ Apache/Chrome (aktuálních14JS) PASS, PHPStan/style a docs PASS.

CodeQL správně označil novou hranici DOMJSON→manifest href jako nedůvěryhodná
data (alert4 js/xss-through-dom). Bez potlačení: přidaná fail-closed validace4
root URL cest a tvaru6hodnot; manifest je explicitně rooted na location.origin.
Pro platné hodnoty zPHP zůstane skutečná URL totožná. Mechanický kontrakt nově
povoluje právě tuto1URL úpravu a pojmenovaný validační prefix, ostatní JSbody
zůstává doslovně stejné. NodeVM href getter doplněn o skutečné browserové
rozlišení relativní URL. Nová přejímka/CodeQL čekají; merge/deploy NOT RUN.
