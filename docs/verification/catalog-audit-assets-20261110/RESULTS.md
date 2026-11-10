# P11-ASSET4 — měření a produkční ověření

Plán zapsán před implementací, base 50ce79b2. Čtecí audit, nové nativní měření, 19 CI a produkční release PASS. Historické tři hashové rozdíly mají níže uvedenou důkazní mez a nejsou označené za vysvětlené.


### ASSET4 — diagnóza a výběr před implementací

Čerstvý veřejný Chrome: katalogdesktop resourcepayload1417429B, z toho obrázky
1208278B; mobile283580B/74429B. Pět společnýchCSS/JS tvoří78508B (CSS 51890B,
JS26618B), samostatný ikonovýfont130396B. Kalendář má navíc duplicitníCDN CSS22709B.
Čtyři největší fotografie: losslessWebP zachoval pixely, ale1132857→2004806B;
zamítnuto, do aplikace nejde. Změna rozměrů/kvality má vlastní vizuální přejímku.
Vybrán obsahový manifest pěti společných assetů místo filemtime: bez změny
payloadů a TTL ušetří znovunačtení CSS 51890B při dalších deployích bez změnyobsahu;
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


## Přijaté měření ASSET4

Testovaný commit `d439f5dbdfa54705873fec0207e41771843e8cae`,
[nativní běh 42833762133](https://github.com/KovoPraha/evidenceTreninku/actions/runs/42833762133)
SUCCESS na MariaDB 10.3 i 11.4. [10.3](native-10.3/native-summary.json),
[11.4](native-11.4/native-summary.json), [celé HTTP](native-11.4/whole-http.json.gz),
[Chrome souhrn](native-11.4/browser-summary.json),
[kontrolní scénáře](native-11.4/browser-catalog-desktop-controls.json.gz).

- Celé HTML 9 pohledů, 17 střídavých párů po 4 warmupech: každá DB 306 měřených
  +72 zahřívacích GET. Lišit se smějí pouze konkrétní nonce/CSRF, pět verzí,
  dvě známé fixture origin adresy a přesná oprava whitespace nonce značky.
  Cena, pořadí, dostupnost, texty ani skripty se neodstraňují. 345 doménových
  tabulek shodných; autentizační metadata nejsou součástí business checksumů.
- Skutečný Chrome: 408 měřených +96 zahřívacích navigací, dalších 336 primerů.
  Dva pohledy × dvě šířky × cold/warm/metadata-only release. Čtyři páry
  screenshotů mají stejné dekódované pixely, computed styles/geometrie shodné,
  všech pět skutečně stažených payloadů má původní canonical Git SHA-256.
- Po vydání beze změny obsahu 78508→0 B pro pět souborů v rámci JS TTL.
  Po skutečném 31s čekání 78508→26618 B: CSS úspora 51890 B, JS se podle
  stávajícího pravidla načetly znovu. Cache TTL zůstávají CSS 604800 s / JS 30 s.
  První načtení má stejných 78508 B; gzip HTML je kvůli delším verzím přibližně
  o 240 B větší. Běžný warm cache má 0→0 B. Nejde o zrychlení všech návštěv.
- Skutečná změna komentáře v CSS, spuštění stejného offline generátoru v privátní
  kopii a skutečné HTTP načtení: novou URL dostal jen app-ui.css, hash staženého
  obsahu přesně odpovídá nové verzi, ostatní čtyři URL a vzhled zůstaly shodné.
  Nový manifest se projevil při druhém GET během běžné revalidace PHP. Omezené
  pozorování do 6 s je mimo benchmark. Pole observed_refresh_ms v surovém
  kontrolním záznamu zahrnuje i následující fallback průchod; není to měření TTL PHP.
- Vadná syntetická fotografie: před změnou 12 vadných obrázků, po změně 0;
  původní nonce guard je zachovaný. Žádné neočekávané JS/network chyby.
  [Úklid 10.3](native-10.3/cleanup.json) a [11.4](native-11.4/cleanup.json) PASS.

Mediány v izolovaném loopback prostředí; žádné síťové mocky ani umělá latence.
Nejde o produkční SLA. Gate FCP/ready: nejvýše 20 % +20 ms; všechny buňky PASS.

| Pohled | Šířka | Cache | Pět souborů B před → po | FCP ms před → po | Ready ms před → po |
|---|---|---|---:|---:|---:|
| login | desktop | cold | 78508 → 78508 | 52.0 → 52.0 | 83.9 → 82.6 |
| login | desktop | warm | 0 → 0 | 44.0 → 44.0 | 62.3 → 58.6 |
| login | desktop | release | 78508 → 0 | 56.0 → 44.0 | 73.1 → 66.2 |
| login | mobile | cold | 78508 → 78508 | 56.0 → 52.0 | 86.3 → 86.3 |
| login | mobile | warm | 0 → 0 | 44.0 → 44.0 | 69.2 → 67.5 |
| login | mobile | release | 78508 → 0 | 52.0 → 44.0 | 79.3 → 69.3 |
| catalog | desktop | cold | 78508 → 78508 | 84.0 → 88.0 | 111.1 → 113.9 |
| catalog | desktop | warm | 0 → 0 | 72.0 → 72.0 | 96.0 → 80.3 |
| catalog | desktop | release | 78508 → 0 | 88.0 → 72.0 | 107.6 → 91.8 |
| catalog | mobile | cold | 78508 → 78508 | 84.0 → 84.0 | 123.0 → 119.8 |
| catalog | mobile | warm | 0 → 0 | 84.0 → 72.0 | 103.8 → 95.2 |
| catalog | mobile | release | 78508 → 0 | 84.0 → 76.0 | 102.8 → 101.1 |


## Produkční vydání a skutečné meze

[PR #435](https://github.com/KovoPraha/evidenceTreninku/pull/435) sloučen jako
`94c10708d44972fb3a942584a099c28a6a7afebe`, celý tree přesně shodný s testovaným
d439f5db. [19 CI](release/app-checks.json), [tree identity](release/tree-identity.json).
[Deploy 42837011604](https://github.com/KovoPraha/evidenceTreninku/actions/runs/42837011604)
SUCCESS, znovu 2432 testů /55977 kontrol. Záloha 389 tabulek/2 triggerů ověřená;
SHA-256 ab496f748e59e9902a00a4d5faa1fba74dc68aeae93697206404847b81073e68.
Konfigurace zachovaná bez doplnění, archivní klíč/záloha/webcopy PASS, pending
migrace [], dosavadní VS a definice/inventory zdravotního indexu shodné.
Přímý HTTP smoke runneru 200, oba úklidy SUCCESS; import přeskočený a UAT false.
[Receipt](release/release-controls.json), [celý workflow stav](release/deploy-summary.json),
[indexové důkazy](release/index-preflight/medical-index-before.json).

Živý [bezprostřední before](release/catalog-before.json), [after](release/catalog-after.json)
a [opakování](release/catalog-after-repeat.json): **5/5 celých HTML i všech karet
shodných**, včetně ceny, skutečného strana=2, hledání i prázdného výsledku.
Nová kontrola ponechává inline skripty a produktové hodnoty, jednotkové mutace
ceny/jména/dostupnosti/obrázku/pořadí/skriptu shodu poruší. Historické tři otisky
z předchozího řezu tím zpětně nevysvětlujeme; jejich důkazní mez zůstává výše.

Chrome 158 na Windows: katalog/přihlášení × desktop/mobile, všechny GET200,
[4 páry skutečných screenshotů mají stejné dekódované pixely](release/live-chrome/pixels.json).
Po změně žádné JS/network chyby; původní nonce guard zachovaný. Všech pět
[živých souborů](release/assets-after.json) odpovídá canonical Git bytům i
[původním cache/encoding headerům](release/assets-before.json). První cold
načtení stále 78508 B; produkční jednorázový průchod není časový benchmark.

Místní PHP libcurl po vydání hlásil HTTP/2 PROTOCOL_ERROR a při vynuceném
HTTP/1.1 prázdnou odpověď. Stejné HTTPS GET vracejí systémový curl i skutečný
Chrome 200; proto explicitní --system-curl v čtecím probe. HTTPS ověření,
statusy a **úplné obsahové porovnání se nemění**. Výsledky uvádějí transport.
Jde o doplnění testovacího nástroje; aplikace se kvůli němu znovu nenasazuje.

Přihlášené lidské produkční UAT, fyzický telefon a obnova této konkrétní zálohy
NOT RUN. Původní fio větev a WIP kis_rosters_admin.php s otiskem 7a7afd75
zachované; žádný stash/clean/reset ani převzetí HJS/RIX prototypů.
Finální doklady: větev codex/stable-ui-assets-release-docs-20261110, PR bude
doplněn při otevření. Po sloučení dokumentačního PR neopakovat deploy.
