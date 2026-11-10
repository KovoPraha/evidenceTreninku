# P11-ASSET4 — průběžné ověření

Plán zapsán před implementací, base50ce79b2. Čtecí katalogová diagnóza a výběr kandidáta probíhají. Implementace/nové CI/release NOT RUN.


### ASSET4 — diagnóza a výběr před implementací

Čerstvý veřejný Chrome: katalogdesktop resourcepayload1417429B, z toho obrázky
1208278B; mobile283580B/74429B. Pět společnýchCSS/JS tvoří78508B (CSS51890B,
JS26618B), samostatný ikonovýfont130396B. Kalendář má navíc duplicitníCDN CSS22709B.
Čtyři největší fotografie: losslessWebP zachoval pixely, ale1132857→2004806B;
zamítnuto, do aplikace nejde. Změna rozměrů/kvality má vlastní vizuální přejímku.
Vybrán obsahový manifest pěti společných assetů místo filemtime: bez změny
payloadů a TTL ušetří znovunačtení CSS51890B při dalších deployích bez změnyobsahu;
v rámci JS30sTTL potenciálněcelých78508B. Nejprve skutečná dvourelease fixture
v jednom originu, žádné prodmtime zápisy a žádné prodnasazení jen kvůli benchmarku.

Katalog: starý page2 parametr stranka je ignorován, skutečný je strana; původní
tři rozdíly jsou pouze dva odlišné pohledy. Default/page2hash se lišil už před
vydáním,09:29→11:38, price zůstal stejný. Aktuální hashy přesně odpovídají after
11:56 a12:03. Samotná náhrada libovolné podmnožiny12fotografií známým placeholderem
staré hashy nevysvětlí (boundedreplay bez zásahu do dat). Aktuální veřejné obrázky
se načetly, ale12důvěryhodných inlinefallback handlerů je blokovaných: zdrojová
nonce značka obsahuje emisní whitespace, a proto nesplní exactnonce guard.
Opravit pouze zdrojovou značku, bezpečnostní kontrolu ponechat.

Přihlášený browser inventory timeout20s, žádné UIzměny/role/formuláře.
Proto samostatný úzký CLI audit pevného okna10.11., START TRANSACTION READ ONLY
+ROLLBACK, jen veřejná auditní pole a agregace skladové aktivity. Bez důvodů,
aktérů, zákazníků, cen nákladů či tokenů. Nejprve CI/PR/main; potom stávající
chráněné SSH/prodprostředí zkopíruje pouze dočasný čtecí nástroj mimo webroot a
uklidí ho. Jde o diagnózu, nikoliv nasazení nové webové aplikace.
