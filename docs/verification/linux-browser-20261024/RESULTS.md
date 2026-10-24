# Balík E — doplnění Linux CI a přejímky v prohlížeči

24. 10. 2026. Navazuje na [společnou technickou přejímku](../final-system-20261024/RESULTS.md).
Výsledek: Linuxová mezera uzavřena, **19 kontrol v prohlížeči prošlo, 1 stažení
odloženo kvůli blokaci Chrome**. Opravena jedna skutečná vada zobrazení.
Nejde o úplné lidské převzetí oddílem ani účetní, produkční pilot nebo schválení metodiky.

## Rozsah a izolace

- Pracovní kopie `C:\xampp\htdocs\evidencePavel-finance-b1-20261022`, větev
  `codex/linux-browser-acceptance-20261024`; základ balíku E `80d583ad`.
- Prohlížeč Chrome, pouze `http://127.0.0.1:18086`, syntetická databáze
  `evidence_b1` na `127.0.0.1:33426`. Soukromé soubory mimo webroot,
  vnější transporty vypnuté. Závěrečný čtecí probe v 22:18:23 CEST: PASS,
  327 vlastních tabulek, žádná chybějící povinná tabulka. Volitelné `ucto_gs_*` nepoužíváme.
- Použity existující dva typy přihlášení: kontrolní pracovní superadmin a
  již přihlášený syntetický rodinný účet se dvěma schválenými dětmi.
  Přepnutí pracovní pozice superadmina není důkaz omezení běžné role;
  negativní autorizační scénáře pokrývají automatické testy.
- Produkce nebyla kontaktována ani změněna, nebyly spuštěny produkční úlohy,
  migrace, platby nebo zprávy. Změny byly odeslány pouze na vlastní testovací
  větev pro Linux CI. Nebyl vytvořen ani sloučen PR, nebylo spuštěno nasazení.

## Linux — dříve přeskočené testy skutečně provedeny

Na tomto Windows počítači není funkční WSL/Docker/Podman. Místo zásahu do
instalace počítače byl použit existující izolovaný GitHub Actions workflow
`tests.yml` na Ubuntu s PHP 8.2 a samostatnými kontejnery MariaDB.
Workflow má oprávnění `contents: read`; nepoužívá produkční prostředí ani tajné údaje.

Finální testovaný kód: **`ad4ba012aa4cc6c7b59f6b0c02343db16a9f86ec`**, včetně CSS opravy.
[Finální běh 40129065090](https://github.com/KovoPraha/evidenceTreninku/actions/runs/40129065090)
má všechny tři joby úspěšné. První kontrolní běh `40127939621` nad `2b67cce9`
také prošel; finální běh jej doplňuje o opravu z prohlížeče.

| Kontrola | Výsledek |
|---|---|
| Celá PHPUnit sada na Linuxu | **1 709 testů / 34 526 assertions, bez selhání a přeskočení** |
| Samostatná povinná unixová kontrola | **3 testy / 45 assertions, bez přeskočení** |
| Integrační smoke na MariaDB 10.3 | PASS |
| Integrační smoke na MariaDB 11.4 | PASS |
| Composer strict validate a audit | PASS |

Do CI byl doplněn samostatný krok s `--fail-on-skipped` a kontrolou Linuxu:

1. `testCanonicalPublicSymlinkFailsBeforeWriting` — odmítnutí veřejné symlink cesty před zápisem.
2. `testCleanupDoesNotFollowOrDeleteReplacementSymlink` — cleanup nesleduje ani nemaže podvržený symlink.
3. `testRefusesSymlinkedDataBeforeChanges` — příprava izolace odmítne symlinkovaná data před změnami.

Tyto tři testy jsou podmnožinou plné sady; počty se nesčítají.
[Metadata všech kroků](linux-ci.json), [stručný výpis skutečných výsledků](linux-test-excerpt.txt).

## Přejímka v prohlížeči

Úplný strojově čitelný záznam: [browser-checks.json](browser-checks.json).
Zápisy níže proběhly přes viditelné formuláře, nikoli přímou změnou DB.

| ID | Scénář a ověřený výsledek | Stav |
|---|---|---|
| B01 | Přihlášení existujícího kontrolního účtu, pracovní pozice registráře | PASS |
| B02 | Kontrola možných shod před vytvořením, následně právě jedna čekající osoba #370 | PASS |
| B03 | Karta osoby: nevznikl účet, přístup, soupiska, účast ani finanční předpis | PASS |
| B04 | Čitelná tmavá hlavička tabulky osob po CSS opravě | PASS |
| B05 | Interní poznámka osoby uložená s autorem a datem | PASS |
| B06 | Nový trénink #55, účastnice Anna #91, kategorie MTB, 75 minut | PASS |
| B07 | Uzavřená docházka, revize 1, skutečně 16:00–17:15, účastnice a trenér zachováni | PASS |
| B08 | Odkaz na zdravotní podklady zachovává osobu a den; neurčený požadavek je výslovně přiznaný | PASS |
| B09 | Nový účetní export #36, revize 8 za říjen; starší revize zachovány, výjimky a neúplnost viditelné | PASS |
| B10 | Kliknutí na stažení této revize; server HTTP 200, Chrome ERR_BLOCKED_BY_CLIENT | ODLOŽENO |
| B11 | Náhled vydané faktury #18: 250 Kč, původní VS, neplátce DPH, odběratel a položky; oprava #19 samostatně | PASS |
| B12 | Vyhledání finančního případu vrací jeden odpovídající uzavřený případ #25 | PASS |
| B13 | Případ #25: schválená v2, zachovaná nahrazená v1, nulová záloha; odkaz vede na akci #25 s plánem a skutečností | PASS |
| B14 | Odměny: výkaz #20 schváleno 500 Kč, vyplaceno 500 Kč, zbývá 0 Kč; stavy oddělené | PASS |
| B15 | Rodinná evidence a kurz nabízejí právě schválené děti Annu a Báru; nová nepropojená osoba #370 není nabídnuta | PASS |
| B16 | Dříve připravená objednávka sourozenců zobrazuje stejný kurz zvlášť pro dvě děti, 150 + 150 = 300 Kč | PASS |
| B17 | Obě účasti mají vazbu na tuto objednávku a platnost; budoucí listopadové období je odlišeno od současného | PASS |
| B18 | Rodič vidí nově uzavřený trénink #55, 1,25 h, potvrzenou účast; interní poznámku nevidí; roční součet 3 tréninky / 210 minut | PASS |
| B19 | Příprava přechodu #10: revize registru 3, návrh mapy docházky a chybějící rozhodnutí vlastníka; žádné tvrzení o ostrém přechodu | PASS |
| B20 | Stav komunikace přiznává blokované PHP odesílání a odlišuje zpracování fronty od doručení; staré záznamy upozorněny bez opakování | PASS |

Nové syntetické zápisy: osoba #370 `LOCALHOST Browser 2410 Přejímka`, její poznámka,
trénink #55 a revize uzavření 1, účetní export #36 (revize 8). Navíc změny pracovního
kontextu kontrolního účtu a běžné auditní záznamy. Nevznikla nová objednávka ani úhrada.
Objednávka sourozenců `KP2610238684A82F7F` byla v této přejímce pouze čtena.

## Oprava a vizuální důkaz

V tabulce osob globální styl hlavičky přepsal tmavé pozadí Bootstrapu světlou
barvou, ale ponechal bílý text. Před opravou: `rgb(248, 249, 250)` / bílá;
po opravě: `rgb(33, 37, 41)` / bílá. `assets/app-ui.css` nyní pro `thead.table-dark`
zachová odpovídající dvojici proměnných Bootstrapu. Ověřeno reloadem,
vypočtenými styly a snímkem skutečné stránky; finální Linux sada také prošla.

- [Před opravou](person-table-before.png), [po opravě](person-table-fixed.png).
- [Poznámka osoby](person-note.png), [uzavřený trénink](training-closed.png).
- [Rodinná objednávka dvou dětí](siblings-order.png), [nová potvrzená docházka u rodiče](family-attendance.png).
- [Klubová akce](event-work.png), [odměny](rewards.png), [komunikace](communication.png).

## Jedna odložená technická kontrola a meze důkazu

Chrome po kliknutí na ZIP zobrazil `ERR_BLOCKED_BY_CLIENT`
([snímek](download-browser-block.png)). Místní server přitom zaznamenal
`200 GET /accounting_export_download.php?id=36`. Nebyly měněny ochrany prohlížeče
ani instalovány doplňky. Úspěšné stažení přes tento profil proto **není potvrzené**.
Zobrazení blokace samo neurčuje, která ochrana nebo rozšíření ji vyvolala.

Nezávislá kontrola již vytvořeného souboru v soukromém serverovém úložišti:
ZIP 764 431 B, 45 souborů, 1 XLSX a 33 PDF, CRC bez chyb;
[SHA-256 a přesný rozsah](export-archive-integrity.json). Jde o integritu archivu,
nikoli náhradu důkazu stažení v Chrome nebo nové účetní posouzení obsahu.

Některá automatická kliknutí/DOM pozorování vracela timeout nebo nezměněný stav.
Před dalším krokem byl ověřen snímek; formuláře nebyly naslepo odesílány opakovaně.
Tyto timeouty nejsou označené za prokázané vady PHP aplikace.

Tento průchod pokrývá reprezentativní provázané scénáře v desktopovém Chrome;
není úplnou maticí mobilních rozlišení, všech prohlížečů a všech pracovních rolí.
Nákup dvou dětí od začátku, omezené role, souběhy a ochrany finančních změn mají
samostatné automatické důkazy v balíku E a Linux CI; zde se netvrdí nové ruční
provedení každé jejich varianty. Drobné technické názvy stavů (`issued`, `closed`,
`paid`) v části přehledů zůstávají tématem sjednocení českých popisků.

[Rozhodnutí D01–D20](../../CLUB_COMPLETION_DECISIONS.md) se nemění. Metodika účetní,
skutečné sazby, jmenovaní schvalovatelé, historické identity a salda, inventura,
aktivace integrací a skutečný přechod nejsou touto technickou přejímkou schváleny.
