# Sjednocení oprav, produkční vydání a úplná automatizovaná přejímka

Datum: 25. 10. 2026. Vlastník výslovně povolil sjednotit a nasadit dokončené
změny. Zápisové testy proběhly pouze v izolaci; produkční ověření bylo čtecí.

## Výsledek a rozsah

- [PR #194](https://github.com/KovoPraha/evidenceTreninku/pull/194) je sloučený.
  Produkce běží na `a23e393f64eeffafd75df834e6e5ba8c78213b72`.
- [Nasazení 40198413027](https://github.com/KovoPraha/evidenceTreninku/actions/runs/40198413027)
  skončilo SUCCESS: ověřená záloha před aktivací, pending migrations `[]`, zachované
  historické variabilní symboly, ochrana soukromých souborů a HTTP smoke PASS.
- Sjednoceny jsou dokončené klubové balíky A–E i e-shop; nové opravy doplňují
  starší nezapsané tréninky bez 14denního omezení, správný cílový týden, viditelné
  rodinné kotvy, pravdivý popis výsledků přejímky a vstup do hromadných akcí.
- Nová verze nepřidává migrace. Nastavení integrací ani konfigurace se nezměnily,
  migrační ledger zůstal 139; Fio, automatické párování, SumUp, Stripe a Velocota
  jsou vypnuté. Packeta flag není definovaný. Viz [porovnání](release-comparison.json).
- Nedokončené WIP `kis_rosters_admin.php` v původním checkoutu zůstalo nedotčené.
  Historické větve vyhodnotila [inventura](../integration-20261025/BRANCH_INVENTORY.md);
  stará Fio migrace ani zavřená diagnostická větev se do release znovu nepřidávaly.

## Automatizované důkazy

| Oblast | Výsledek |
| --- | --- |
| Linux PHP 8.2 | 1 718 testů / 34 748 assertions PASS |
| POSIX/symlink hranice soukromého úložiště | 3 testy / 45 assertions PASS bez skipu; podmnožina plné sady |
| MariaDB 10.3 a 11.4 | integrační smoke sady PASS |
| CodeQL a závislosti | PASS |
| 26 izolovaných HTTP sad | 805 kontrol PASS |
| 6 sad reálných souběhů a konzistentních exportních snímků | 78 kontrol PASS |
| Syntetická migrace a rollback | 17 kontrol PASS |
| Produkční databázové invarianty | 16 kontrol PASS |
| Produkční účetní a skladové vazby | 14 + 20 kontrol PASS |
| Obnova právě vytvořené zálohy do izolované MariaDB | 330 tabulek + 2 triggery, manifest a checksum PASS |

CI: [40198097961](https://github.com/KovoPraha/evidenceTreninku/actions/runs/40198097961),
CodeQL: [40198096615](https://github.com/KovoPraha/evidenceTreninku/actions/runs/40198096615).
Také main CI 40198281452, main CodeQL 40198282844 a testy v deployi prošly.
PR head `08ed50d7` a nasazený squash `a23e393f` mají totožný strom.

[HTTP seznam](http-summary.json), [souběhy](concurrency-summary.json),
[nezkrácené syntetické výsledky](isolated/), [produkční běhy](production-drills.json),
[invarianty](overit-databazove-invarianty-0.json) a
[účetnictví/sklady](overit-ucetni-vazby-0.json).

HTTP sady pokrývají trenéra, registráře, život osoby, zdravotní podklady, přihlášky,
tréninky/omluvy/náhrady/uzavření, sezonu, výdaje/zálohy/odměny, finanční uzávěry,
objednávky, sklady, výdeje/vratky, doklady, PDF/XLSX/ZIP export, měsíční provoz,
soukromí týdenního přehledu a cutover. Nejde o tvrzení, že byla otestována každá
možná kombinace skutečných dat nebo schválena účetní metodika.

Izolace: samostatný checkout a privátní runtime `browser-acceptance-20261025`,
MariaDB 33436 a HTTP 18096; syntetické LOCALHOST/NEPLATIT záznamy, vypnuté externí
transporty. Kopie testovacích harnessů mění jen cestu a identitu této izolace.
Migrační knihovna první spuštění na 33436 správně odmítla svým guardem. Guard se
neoslaboval; finální originální test prošel v kanonickém samostatném runtime
33426/18086. Migrační komponenty tam mají shodný obsah s nasazenou verzí.

## Čtecí kontrola produkce

- [Dopad vydání 40198749487](https://github.com/KovoPraha/evidenceTreninku/actions/runs/40198749487):
  live SHA/run souhlasí; config, integrační flags a agregované počty proti preflightu
  40198129096 beze změny. `domain_mutations=false`.
- [Invarianty 40198783643](https://github.com/KovoPraha/evidenceTreninku/actions/runs/40198783643)
  a [účetní vazby 40198840148](https://github.com/KovoPraha/evidenceTreninku/actions/runs/40198840148): PASS.
- [HTTP](public-http.json): veřejné stránky dostupné, interní stránky bez session
  přesměrují na přihlášení. Rodinný ICS bez tokenu správně vrací 404.
- Přihlášený Chrome: trenér vidí 1 nezapsanou lekci a odkaz na 30. 5. 2026 skutečně
  otevře týden 25.–31. 5. se zapnutým filtrem Moje. Pracovní kontext byl vrácen na
  registráře. Žádné osoby, tréninky, objednávky ani platby se testem nezapisovaly.
- Veřejný CSS soubor odpovídá bajtově Git blobu release; [SHA-256 důkaz](css-integrity.json).
  Odlišný hash místního pracovního souboru byl pouze Windows CRLF, nikoli drift produkce.

Přímý curl z GitHub runneru neměl síťové spojení na hosting. Existující bezpečný
fallback ověřil HTTP ze serveru, nezávisle prošel také místní klient a přihlášený
Chrome. Nešlo o chybu aplikace. Dva původně odhadnuté neexistující URL byly odstraněny
z testovacího seznamu; očekávání rodinného ICS bylo opraveno podle jeho tokenového kontraktu.

Diagnostika dopadu používá historický manifest 54 souborů pro starší klubovou
integraci. Očekávaná neshoda nového CSS není chyba nasazení a tento historický
manifest není důkazem otisků celého nového release. Nový release potvrzují SHA
nasazení, čtecí live metadata, CSS Git blob a živé chování opraveného trenérského přehledu.
Pole `deployment_ready=false` v této inventuře záměrně není vydáním oprávnění
k nasazení ani potvrzením kompletní provozní připravenosti.

## Záloha a návrat

Před aktivací vznikla záloha `evidence_2026-10-25_133822_b4a9be32.sql.gz`,
SHA-256 `b8a242deb8bf23e055504d5340fb039399f8a13701f7cbce447ee35a3aef239f`.
[Manifest vytvoření](backup-created.json), [důkaz obnovy](backup-restore.json),
[drill 40198896312](https://github.com/KovoPraha/evidenceTreninku/actions/runs/40198896312).
Obnova proběhla v jednorázovém CI kontejneru MariaDB 11.4; shoda počtů řádků všech
330 tabulek a obou triggerů byla ověřena vůči manifestu. Kontejner a kopie zálohy
byly uklizeny. Produkční databáze nebyla obnovována.

Připravená a odeslaná větev `codex/acceptance-rollback-ready-20261025`, commit
`f4a085f31714ad112170086b3576eec995f6a035`, vrací aplikační a testovací soubory
na předchozí produkci `115a617d`. Mimo dokumentaci je její strom s předchozí
produkcí totožný. Při potřebě návratu ji sloučit řízeným PR do main a spustit
chráněný deploy; konfiguraci a současná data zachovat. Tento release nemá nové
migrace, proto k návratu kódu není potřeba ztrátová obnova databáze. Starší rollback
před klubovou integraci se pro toto vydání nepoužívá. Samotný rollback nasazen nebyl.

## Co zbývá před skutečným provozním převzetím

1. Ověřit a schválit rozsah trenérských přístupů. Čtecí inventura uvádí 36 účtů
   s trenérskou pozicí a 36 bez aktuálního rozsahového grantu. Není to automaticky
   zákaz veškeré starší vlastní evidence; nové delegované agendy ale nelze prohlásit
   za personálně připravené. Hromadné přidělení odhadem neproběhlo.
2. Ověřit konkrétní rodičovské vazby a pilotní rodiny. Poslední přihlášená UI
   kontrola uváděla 0 schválených vazeb a 3 žádosti. Izolovaná přejímka omezených
   rodičů/sportovce prošla; nevytváří skutečná oprávnění.
3. Doplnit skutečnou inventuru a ocenění: 799 zveřejněných aktivních variant
   (855 variant celkem) nemá řízený počáteční sklad. To nejsou počty kusů.
4. Doplnit platné dohody a rozhodnutí o částkách k jedinému aktivnímu příspěvkovému
   plánu v říjnu; aktuálně nemá ani jednu měsíční dohodu/rozhodnutí.
5. Dohledat dvě již před vydáním chybějící přílohy účtenek. Nenasazovalo se za ně
   náhradní potvrzení ani prázdný soubor.
6. Potvrdit s účetní metodiku a vzorové předání; doplnit jména a termín pilotu
   D12–D14. D15 pro toto vydání kódu je schválené, další aktivace/importy samostatně.

Produkce obsahuje 8 objednávek, ale účetní a řízené skladové tabulky jsou zatím
prázdné. Produkční kontroly tedy potvrzují konzistenci současných dat, nikoli
reálnou transakci od prodeje až k účetnímu ZIP. Tu prokazují syntetické end-to-end
sady; lidské a účetní převzetí zůstává otevřené. UAT flag zůstává pravdivě false.

## Další automatizované ověřování

Plná Linux/MariaDB/CodeQL CI zůstává zapnutá pro další změny. HTTP sady a souběhy
mají zůstat v izolaci; při relevantní změně opakovat dotčené sady a před dalším
release celou společnou přejímku. Produkční diagnostiky spouštět čtecím workflow,
obnovu jen do izolace. Bez zvláštního provozního rozhodnutí nepřidávat periodické
zápisy do produkce, aktivaci integrací ani rozesílání zpráv.

Tento protokol je dokumentační následník deploye, nikoli další aplikační vydání.
Dokumentační commit v main může být novější než produkční SHA, aplikační soubory
musí být vůči `a23e393f` beze změny.
