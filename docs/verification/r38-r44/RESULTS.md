# R38–R44 — závěrečné místní ověření

23. 10. 2026. Izolovaný web `127.0.0.1:18086`, MariaDB
`127.0.0.1:33426/evidence_b1` a soukromé úložiště mimo webroot.
Pouze syntetická data, žádný push, merge, deploy nebo produkční zápis.

| Kontrola posledního společného kódu | Výsledek |
|---|---|
| Celá PHPUnit sada | **1 526 testů / 31 327 assertions PASS**, 0 chyb, selhání a přeskočení |
| Syntaxe všech nových a změněných PHP souborů | **38 souborů PASS** |
| Výdaje a bankovní návraty — skutečné HTTP | **47 kontrol PASS** |
| Odměny a bankovní návraty — skutečné HTTP | **25 kontrol PASS** |
| Účetní export — skutečné HTTP | **34 kontrol PASS**, 18 souborů v ověřeném ZIP, 10 listů XLSX |
| Příprava přechodu — skutečné HTTP | **26 kontrol PASS** |
| Migrační CLI a sportovní převod v MariaDB | **17 kontrol PASS** |
| Finanční převodníky v MariaDB | **21 kontrol PASS** |
| Výdaje a odměny — samostatné MariaDB souběhy | **29 + 12 kontrol PASS** |
| Registry přechodu — MariaDB souběh | **3 kontroly PASS** |
| Nezávislé opakování nalezených migračních chyb | **8 kontrol PASS** |
| Souvislý syntetický provozní měsíc na finálním kódu | **13 kontrol PASS**, konečný zůstatek 4 800 Kč |
| Dva účty pro místní prohlídku | **2 skutečná přihlášení PASS**, opakování zachová hesla a kontroluje role |
| Migrační katalog | **136 migrací, current, žádná pending**, pouze izolovaná DB |

Celkem 132 HTTP kontrol. CLI/MariaDB počty zahrnují přípravu, běžné kontroly
i skutečné konkurenční scénáře; neznamenají 82 různých závodů. Souběžné
scénáře samostatně prokazují skutečný `INNODB_TRX LOCK WAIT`, výsledky workerů
a finální data. Není použito pouze předstírání souběhu v SQLite.

## Důkazy

- `phpunit.json`, `php-lint.json` — celková regrese a syntaxe.
- `expense-return-http.json`, `reward-return-http.json`,
  `expense-return-race.json`, `reward-return-race.json` — finance.
- `r36-return-export-http.json` — XLSX, PDF, archiv a vazby návratů.
- `migration-cli-mariadb.json`, `finance-mariadb.json`,
  `r38-independent-review.json` — převody a nezávislá kontrola oprav.
- `r40-r44-http.json`, `r40-r44-race.json` — příprava přechodu.
- `operating-month.json`, `backup-restore.json` — souvislá zkouška a obnova.

Nezávislá kontrola vedla zejména k opravě archivace otevřeného finančního
případu, nesprávného rodinného cíle, souběžné změny schválené mapy, vyhrazeného
příznaku výmazu a ověřování původního zdrojového souboru. Finanční revize
doplnila návrat bankovní výplaty bez změny nároku, správné příděly objednávek
a pozdě zadané historické podklady. Opravy mají samostatné regresní scénáře.

## Poslední obnova

Záloha `evidence_2026-10-22_233032_da6e3883.sql.gz` v místní složce
`C:\Users\Marek\AppData\Local\EvidencePavelB1\finance-b1-20261022\backups-r38-r44`.
Název používá UTC; datum místního testu je 23. 10. 2026.

SHA-256 SQL: `2083e3e22a940e9dc950280339cafe7a35604a23b019891e1e51ed99ade38fe9`.
Obnoveno do nového schématu `evidence_b7_restore_1fe340a9`, s novým náhodným
uživatelem oprávněným pouze k tomuto cíli. Původní databáze se nepřepisuje.

- 326 tabulek a 2 triggery, stejné počty řádků.
- 157 soukromých souborů obnovených do nového adresáře; kontrola všech bajtových otisků.
- 65 kanonických souborových odkazů, 24 skladových variant včetně pohybů a šarží.
- 17 vazeb uzavřených tréninků, 2 sportovní výkazy, 2 přijetí s plátcem bez účtu.
- 17 výplat odměn a 8 návratových vazeb na původní kanonický příděl a peníze.
- 23 migračních map, 16 otisků podkladů přechodu, 15 úplných zdrojových manifestů a 15 původních souborových archivů.

V DB zůstává také 11 syntetických vývojových dávek vytvořených před zavedením
úplného archivního kontraktu. Obnova je zachovává a samostatně počítá;
neoznačuje je za úplný převod. Nový migrační zapisovač bez tohoto kontraktu
pokračování odmítne. Historické testovací podklady nejsou potichu doplněny
domnělým originálem.

První běh obnovovací kontroly odhalil nesprávné použití resolveru běžných
příloh pro migrační JSON archiv. Kontrolní skript nyní používá přesný
archivní formát a kontrolu skutečné cesty uvnitř obnoveného úložiště;
opakovaná kompletní obnova výše prošla. Nešlo o chybějící bajty v záloze.

Obnova testuje místní DB a soubory, nikoli produkční klíče, externí banku,
odeslané zprávy nebo skutečný návrat ostrého provozu.

## Nepřevzaté provozní části

R38/R39 nejsou potvrzením reálných zdrojů a fyzického skladu. Chybějící
aktivní obchodní případy mají karanténu nebo výslovný postup dokončení ve
starém systému; další konkrétní adaptéry vyžadují ověřené zdrojové exporty.
R43/R44 nejsou živý pilot ani přepnutí. Účetní nepotvrdila skutečný měsíc.
Nasazení, reálný import, zapnutí integrací a odstavení starých služeb se
neprovedly. [D01–D16](../../CLUB_COMPLETION_DECISIONS.md) uvádí odložené body.
