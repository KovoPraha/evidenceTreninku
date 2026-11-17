# P11-CATLABEL — průběžné ověření

17. 11. 2026, Europe/Prague. Lokální implementace, nativní/HTTP/Chrome a merge/deploy čekají.
Base `30386ddb29ceed99190a18ed9e792ff02111b139`, větev
`codex/catalog-category-label-performance-20261117` v odděleném worktree.
[Plán](../../CATALOG_READ_PERFORMANCE_2026-11-07.md#p11-catlabel--skládání-popisků-kategorií-17-11-2026).

Jediná funkční změna: uvnitř shopCategoryMenuLabel nahrazené array_unshift
přidáním na konec a jediným array_reverse před implode. Ostatní prefix/suffix
zdroje byteově shodný. Frozen popisek i celé menu pochází z výše uvedeného
base; before celé menu volá skutečný frozen starý popisek. 3 SQL zůstávají,
bez cache, limitů, změny signatury, cen, produktů, oprávnění či writerů.

Místní SQLite/PHP8.2.12 profil měří shopCategoryNodes + celé menu (callsite
načítá uzly dvakrát). 2 zahřívací +11 měřených střídavých trojic, shoda
všech celých menu/typů/hashů. [Vzorky](local-probe.json), [prototyp](local-probe.php.txt).

| Tvar / kategorie | Původní ms | Popisky ms | Sdílené uzly ms |
| --- | ---: | ---: | ---: |
| flat /30 | 1,49 | 1,53 | 1,04 |
| flat /1000 | 68,59 | 64,72 | 54,01 |
| tree /1000 | 69,67 | 68,59 | 47,45 |
| chain /250 | 181,02 | 106,24 | 182,22 |
| chain /500 | 1247,80 | 692,39 | 1490,94 |

Zvolen největší doložený hluboký přínos s nejmenším rozsahem (popisky).
Sdílené uzly zůstávají koncept; lokální časy mají i opačné směry. Nejde
o MariaDB ani produkční měření.

Akceptace stanovená před výsledky: chain250/500 ≥20% zisku nativního menu
i celého přehledového HTTP na obou MariaDB; ostatní ≤20%+5ms funkce/+20msHTTP.
9 nativních profilů/DB, 2+11 párů v čerstvých procesech, celé menu/hash,
SQL budget3 a checksumy. Reálné Apache/PHP na úplně migrované fixture,
36 scénářů/DB:20 matrix při30 kategoriích +4 při flat1000/chain250/chain500/
tree1000. Anonym/zákazník, filtry/hledání/řazení/prázdné/neplatné/CSRF,
4 warmup+17 párů pozitivů/1 pár negativů. 355 checksumů před/po každé fázi.
HTML/hlavičky porovnat celé, normalizace jen nonce/CSRF/checkout_key,
explicitní testovací origin a ověřený odvozený CSP hash handleru. Bez mocků.
Chrome:8 cold/warm × desktop/mobil případů při tree1000, formuláře/styly/
geometrie/CSS/pixely a skutečné síťové chyby. FCP/ready gate20%+20ms.
Light/dark jsou konfigurace localStorage, veřejný shell zůstává světlý.

Historická workflow CATMENU má zamknutý i nyní měněný helper; zachovaná
jako ruční replay na skutečně testovaném8a545bb8. Aktuální dva MariaDB joby
nahrazuje CATLABEL s novým přesným source kontraktem na30386ddb a stejnými
přísnými kontrolami. Všeobecné CI stejné. Změna lane je výslovně součástí
review, nikoli přeskočené testování.

Lokální hraniční sada: prázdné/chybějící cesty, mezery/Unicode/oddělovač,
číselné klíče a neřetězcový rodič, cykly podle počátečního bodu, skrytí/
virtuální uzly, malé/1000/hluboké/smíšené menu a změny/rollback/dvě spojení.
Dosavadní menu diferenciální testy zůstávají beze změny.
Lokálně27 testů/204 kontrol PASS. Přesný source kontrakt PASS; styl upraven
podle PSR-12, ostatní kód beze změny. Nativní přejímka zatím NOT RUN.

Původní WIP/HEAD zachovaný: `kis_rosters_admin.php` SHA256
`7a7afd75bfa34f736f158096ab46a671b7fee6c6faedc3701f989c0cf64733e5`.
Při úplném PASS chráněný deploy se zálohou/config/migracemi/VS/indexem/
HTTP/úklidem, UAT/importfalse. Produkční profil a přihlášené UAT NOT RUN.
