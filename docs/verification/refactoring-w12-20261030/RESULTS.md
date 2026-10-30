# W12 — parser, preview a obnova, 30. 10. 2026

Výchozí W11 zdroj `73f30692298c12f0d84ef581aa8118fd3b057b87`.
Syntetické náhodné localhost DB, celý migrační katalog, FK zapnuté;
produkce ani skutečné zdrojové soubory se k měření nepoužily.

## Změny a důkazy

1. `kis_read_rows` uvolní workbook přes `finally/disconnectWorksheets`
   při úspěchu i výjimce. Zamražený původní parser vrací stejné úplné výsledky
   a metadata (hash), včetně více listů, vzorců, formátovaných dat a prázdných
   řádků. 10 000 řádků: zadržená paměť po uvolnění výsledku 29 443 736 →
   3 179 512 B, chybějící hlavička 34 464 B. Vynucený limit 8 MiB.
   Špička při načítání se nesnížila (33,8/36,9 MB), čas 2,08/2,10 s;
   jeden vzorek, žádný slib rychlejšího parsování nebo streamovaného XLSX.
2. Preview s legacy indexy mělo při 10 000 vstupních řádcích 204,65 s.
   Běžící `SHOW EXPLAIN` doložil lookup `im` jen přes `run_id` a procházení
   stejného importu pro každý řádek; druhý diagnostický běh byl po několika
   minutách ukončen pouze ve vlastní DB, `finally` ji odstranilo.
   Nová migrace nahrazuje přesně ověřený legacy neunikátní `(run_id)`
   neunikátním `(run_id,row_id)`, zachovává duplicity,
   je opakovatelná a odmítne kolidující nesprávnou definici indexu.
   První přidání indexu dalo 15,38 s včetně 20 023 SQL (z toho 20 000 INSERT),
   obě části klíče v EXPLAIN, cílový řádek místo celého importu.
   Fingerprint `ac4151f6…46e1b` je stejný, rollback a opakované finalize PASS.
   SQL/řádky i přírůstek paměti 68 775 336 B se nezměnily. Časy jsou jednotlivé
   lokální instrumentované běhy při sdílené zátěži, nikoli mediány ani SLA.
   První fixture bez legacy indexů je zachována zvlášť a není vydávána za
   produkční baseline. Párování identit a jeho snapshot se nemění;
   tento profil má 21 existujících osob, ne 10 000 × 10 000 kandidátů.
3. Skutečný exportní ZIP: 10 000 pohybů a 100 klubových předpisů, XLSX,
   PDF i JSON, 59,46 s a 186 609 592 B přírůstku paměti (limit procesu 512M).
   PDF obsahuje také výjimky nepřiřazených pohybů; nejde o 100řádkový PDF test
   ani důkaz libovolně velkého Dompdf exportu. Nová nezávislá DB obnoví
   skutečnou gzip zálohu, 290 tabulek/1 trigger, všechny celé tabulky mají
   stejný digest; FK definice/aktivace a hash soukromého archivu stejné.
   Záloha 3,61 s, obnova 7,85 s, gzip 639 721 B. Ověřen skutečný download
   obnoveného archivu; nejde o obnovu produkční DB s jiným legacy katalogem.
4. Exportní souběh: přenosný test se dvěma spojeními a skutečným commitem,
   konzistentní snapshot PASS; negativní READ COMMITTED kontrola rozdíl vidí.
   Vlastní schéma/soubory se odstraní, sdílený B1 ani hosting se nepoužívají.

## Ověření a nasazení

Cílené testy parser/import/export 51/1232 PASS; migrace má test duplicit,
idempotence a chybných indexů. První plný běh odhalil chybějící legacy tabulku
v AuthSecurityMigrationTest; fixture a explicitní seznam migrací doplněny,
celý migrační upgrade následně 1/91 PASS. Plná regrese a CI jsou povinné.
PHPStan bez nových nálezů; styl a AST/literal důkaz parseru zachován.
CI opakuje parser, preview, snapshot a úplnou obnovu na MariaDB 10.3/11.4.

První CI 11.4 odmítlo konkrétní očekávaný název/plnou délku klíče v EXPLAIN.
To samo neprokazuje pomalé čtení. Fixture nyní zakládá legacy indexy před
číslovanými migracemi (stejné pořadí jako upgrade) a měří skutečné session
čítače Handler_read_next + Handler_read_rnd_next kolem celého preview.
Brána pro 10 000 řádků dovoluje nejvýše 310 000 postupů po řádcích,
nikoli kvadratické skenování. EXPLAIN zůstává v důkazu; rozhoduje práce
enginu, nikoli vynucování názvu plánu odlišných verzí optimizeru.
Oba SHOW STATUS jsou mimo instrumentovaný počet aplikačních SQL.
Následná reprodukce i na místní MariaDB ukázala, že při studených statistikách
může ponechaný legacy `(run_id)` skutečně stále vést ke kvadratickému plánu.
Finální migrace jej proto odstraní až po ověření nového složeného indexu,
jehož levý prefix všechny původní run lookupy pokrývá. Unikátní nebo jinak
definovaný vlastní `idx_run_id` se nemaže. Duplicity, idempotence, nesprávná
definice i zachování vlastního indexu: 4 testy / 101 assertions PASS včetně
celého migračního upgrade. Finální lokální profil 10k: 6,00 s, 40 194 postupů
po řádcích (rozpočet 310 000), stejný fingerprint a paměť. Tím se měří práce
enginu nezávisle na konkrétním názvu plánu a rychlosti stroje.

DDL konsoliduje dva indexy stagingové tabulky; žádný historický migrační soubor
se nemění. Před release záloha a ověření produkce, po release očekávaný
přírůstek migračního ledgeru o 1. Návrat aplikační změny musí být dopředné
opravné vydání, které zachová nový migrační soubor i ledger; starý katalog
by nový záznam správně odmítl jako neznámý. Neunikátní index ponechat,
neprovádět automatický DROP ani mazání stagingu. Integrace a import
zůstávají vypnuté. Nedávný provozní disk-full incident se eviduje odděleně.
