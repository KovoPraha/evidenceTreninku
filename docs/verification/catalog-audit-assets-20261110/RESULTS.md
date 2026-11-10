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


### Dokončený čtecí audit a meze historického důkazu

PR [#434](https://github.com/KovoPraha/evidenceTreninku/pull/434) je v main
`fb593ad49f230b319b770bce763954017007db42`, po 11 úspěšných CI kontrolách.
[Čtecí běh 42826637716](https://github.com/KovoPraha/evidenceTreninku/actions/runs/42826637716)
SUCCESS včetně úklidu. [Veřejný výsledek](production-readonly-audit.json):
49 událostí fotografií (471–521), read-only transakce, rollback a konfigurace
beze změny. Šest sledovaných PHP zdrojů odpovídá kanonickým blobům main.
Nejde o nasazení webové aplikace; runtime zůstává beead425.

**Historické tři rozdíly nejsou uzavřené jako PASS.** Chybí původní HTML,
uchovaný SHA-256 nelze obrátit. Chybný parametr `stranka=2` opakoval první stránku;
správný je `strana=2`. Default se změnil již před vydáním ASSET3. Časová shoda
s editací fotografií sama nevysvětluje oba různé pohledy: náhrada všech podmnožin
viditelných fotografií placeholderem původní hash nereprodukovala. Neoznačovat
fotografie za prokázanou úplnou příčinu ani HTTP 200 za shodu obsahu.

### Implementace ASSET4 — průběžný stav

Větev `codex/stable-ui-assets-performance-20261110`, vlastní worktree
`C:/Users/Marek/Documents/Codex/evidencePavel-ui-cache-performance-20261110`.
Offline SHA-256 manifest pěti společných souborů, žádné hashování při GET.
Obsah souborů a cache TTL zachované. Přesná kontrola PHP tokenů a emisního HTML
proti 50ce79b2 PASS: pouze pět verzí, povinné závorky guardu a oprava whitespace
nonce značky obrázku. CSP guard se nemění. Manifest má kontrolu skutečných bytů
v PHPUnit a tím i v existujících deploy testech. Generátor: `php tools/build-ui-asset-versions.php --write`.

Nový izolovaný profil: MariaDB 10.3/11.4, fyzické kopie dvou vydání na stejném
originu, skutečný Apache a Chrome. Celé HTTP včetně správného stránkování,
17 střídavých párů po 4 warmupech; cold/warm/metadata-only release, kontrola
expirace 30s JS, skutečné změny CSS a CSP fallbacku. Bez síťových mocků či
umělého zpomalování. Native/browser výsledky zatím NOT RUN; podmíněné nasazení
zatím nepřijato. Původní WIP kis_rosters_admin.php 7a7afd75 zachovaný.
