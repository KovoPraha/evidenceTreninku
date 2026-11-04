# Ověření implementace TV programů — 4. 11. 2026

Implementace vznikla ve větvi `codex/tv-programs-20261104` z `main`
`5eb64c95e4b199fc339076524578e9a44efb3414`. Původní checkout a jeho
rozpracované změny nebyly přepsány. Produkce ani její účty/data nebyly měněny.

## Automatické kontroly

| Kontrola | Výsledek |
|---|---|
| Celá sada PHPUnit | 1994 testů, 38574 kontrol; bez selhání, 3 přeskočení na Windows |
| TV a související cílené regrese | 54 testů, 2779 kontrol, PASS |
| Návaznosti pracovních pozic, společného UI a katalogu migrací | 20 testů, 200 kontrol, PASS |
| JavaScript TV přehrávače | 4 scénáře PASS: časování/pořadí/smyčka, jeden snímek, převzetí nové verze, prázdný program |
| MariaDB 10.4.32 | Nová migrace opakovatelná; publikování, odmítnutí starého editora a archivace PASS |
| Úplná místní migrace | 149 migrací, stav current, žádná čekající migrace |
| Anonymní HTTP inventura | 34 veřejných výjimek, 2 CLI trasy, 238 chráněných tras; 0 chyb |
| PHPStan level 5 | Bez nových chyb a bez rozšíření baseline |
| Kontrakt PHPStan | PASS; zachováno 10 negativních kontrol |
| Styl změněných PHP souborů | PSR-12, délky řádků a závorky PASS |

Nové TV testy ověřují oddělení konceptu a zveřejněné verze, vyřazení vypnutých
snímků včetně jejich textů a obrázků, obrázky použité více programy, obnovení
staršího zveřejnění pouze do konceptu, archivaci a nové zveřejnění po obnovení,
optimistický zámek, rollback při chybějícím obrázku, validaci rozložení a
dekódování/překódování obrázků.

## Skutečný browser a HTTP průchod

Playwright/Chromium, samostatný Apache na loopbacku a oddělená místní MariaDB
s výhradně syntetickým demo obsahem:

- Přihlášení a přepnutí na **Obsluha TV**, vytvoření programu číslo 1.
- Nahrání skutečného PNG z projektových assets a jeho výdej jako JPEG.
- Dva textové bloky s různými velikostmi a pozicemi, zarovnání a průsvitný
  podklad; uložení konceptu a přehrání náhledu.
- Skutečné přesunutí textu myší, změna šířky úchytem, upozornění na přesah.
- Zveřejnění ovládacím prvkem administrace a veřejné přehrávání přes `/tv/1`.
- Ověření stejných souřadnic plátna při rozlišení 1920 × 1080 a jejich
  poměrného přepočtu při 1280 × 720; vstup do fullscreen i jeho ukončení.
- Veřejný program a obrázek HTTP 200; anonymní administrace, nahrávání/změna
  a náhled obrázku přesměrují na přihlášení (302 bez následování redirectu).
- Aktivní jiná pracovní pozice: administrace, zápis i náhled obrázku HTTP 403.
- Neplatné CSRF HTTP 400; souběžný zápis se starou verzí HTTP 409.
- Neexistující program HTTP 404; query parametr nepřepíše číslo v `/tv/1`.
  Veřejný přehrávač nezakládá session a odpovědi mají `Cache-Control: no-store`.
- Změna textu ve zveřejněné verzi se v již otevřeném přehrávači projevila
  automaticky; následné obnovení původního textu také bez reloadu.
- Program číslo 2 publikován s odlišným obsahem bez změny programu číslo 1.
- Kontrolované stránky editoru/přehrávače: žádné JS chyby ani CSP varování.

Snímky obrazovky a běhové podklady zůstávají místně v `output/playwright/`;
session, konfigurace a databázové soubory nejsou součástí commitu.

## Hranice dokončení

Jde o dokončení a místní technické ověření implementace. Produkční deploy,
přidělení pozice konkrétní skutečné obsluze, skutečný obsah a ověření na
fyzické TV jsou samostatné provozní kroky. Autostart do fullscreen závisí na
prohlížeči TV. Offline režim ani kalendář vysílání nebyly požadovány.
