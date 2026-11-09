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


## První příprava f9 — FAIL testovací role, bez nasazení

42718340757 oběDB zastavené před timingem: trenér dostal na sprava_treninku
správnou302/login, tato legacy stránka vyžaduje hlavni. Zvolena existující
syntetická sports_lead identita pro třístránkový profil; role ani guardy
aplikace se nemění. [Důkazy](first-f9/native-10.3/runner-cleanup.json): cleanup
PASS oběDB, native model3/562 a normalizátor1/6 PASS. Přenosová úspora,
source edit a CSS identity ověřené; FCP/HTML kompletního běhu zatím NOT RUN.
Browser měření rozdělené do34vzorkových bloků kvůli omezení CLI;17párů a
4warmupy každého cold/warm viewport/route zůstávají. WebCrypto v reálném
browseru místo závislosti na Node require; žádné request mocky.


Druhý běh42718778899/3f také před timingem FAIL testovacího kontextu: sports_lead
správně403 na moje_treninky, kterou vlastní coach. Měření nyní používá přesné
per-route existující identity: coach profil+Moje, sports_lead Správa, veřejný
bearer samostatně. Doplněny negativní302/403 kontroly hranic pracovních pozic.
Žádná změna/grant rolí ani route guardů; oba cleanup PASS,3/562 a1/6 PASS.


Třetí42719230914/20 došel celým parent timingem, ale FAIL srovnání Moje:
shared header má stejný per-session CSRF také ve dvou push JSON objektech.
Původní normalizace formuláře/const je neobsáhla. Nová úzká normalizace vezme
jediný ověřený64hexCSRF z explicitního pole/const a nahradí jeho výskyty,
při více různýchCSRF v jednom parentu selže. Není to změna aplikace ani
obecné ignorováníJS. Další případný rozdíl uloží jen bezpečný redigovaný
snippet. OběDB model3/562/normalizátor1/6/cleanup PASS, app deploy NOT RUN.


Čtvrtý42719770933/b8 dokončil shodu celéhoHTML všech3stránek na obouDB,
ale FAIL na příliš konkrétním očekávání302/403 negativní workspace kontroly.
Stávající kontrakt přijímá302/303/401/403 podle již inicializovaného kontextu;
nový test přijme jen odmítnutí a vyžaduje naprosto stejný status i Location
před/po pro oba zakázané kontexty. Nikdy200. Staré cookiejary konkrétního
parent testu se před loginem znovu založí, stejně jako v ostatních harness.
Aplikace/role nezměněné; native model3/562, normalizátor1/6 a cleanup PASS.
