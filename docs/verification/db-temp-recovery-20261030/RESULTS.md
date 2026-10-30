# Obnova DB kapacity a dokončení W10.3–W15

30. 10. 2026. Uživatel výslovně schválil dočasné odstavení globálního hledání
Velocoty, ověřenou zálohu/obnovu, bezpečné uvolnění místa a nasazení EvidencePavel.
Požadavek podpoře zůstal na jeho pokyn neodeslaný. Placená změna se neobjednávala.

## Příčina blokace a skutečná kapacita

Host replikant3544, MariaDB 10.3.39, datadir `/var/lib/mysql/`, tmpdir `/tmp`.
Aktualizovaná správa hostingu v 19:30:07 CET ukázala systémový `/dev/sdb1` na
100 %, dostupné 0, zatímco webový `/home` měl 17 GB volných. Inody systémového
oddílu byly využité jen z 8 %. [Sanitizovaný přepis měření](hosting-filesystem.json); originální snímek zůstává lokálně.
Předchozí [deploy 41095272194](https://github.com/KovoPraha/evidenceTreninku/actions/runs/41095272194)
selhal na HY000/1021 při záloze před migrací/aktivací; poslední živé vydání
zůstalo W10.2. Úklid webových release adresářů by tento problém neřešil.

Před jakoukoli změnou v této obnově ukázalo nové čtení 20:51:43 UTC již
2 370 215 936 B volných na stejném systémovém filesystemu velikosti
21 661 515 776 B. Příčinu tohoto dočasného zotavení jsme neurčili; nepřisuzujeme
ji svému zásahu. Dřívější opakované návraty chyby brání tvrzení o trvalé opravě
hostingu. Povolená cesta `/etc/ssl/certs` je ověřený proxy bod systémového
filesystemu; webový prostor je měřen odděleně.

## Záloha, obnova a odstavení hledání

[Stav odstavení](velocota-pause.json): existujícím nástrojem SearchRelease byl
v current/shared konfiguraci 31 cílů vypnut pouze `VELOCOTA_SEARCH_ENABLED`.
Cron `/CRON.1minuta/stats-search-index.php` je nahrazen maintenance stubem;
původní bajty a SHA jsou zachované. První pokus zastavil cron a odmítl pokračovat,
protože ještě běžel indexer. Po jeho dokončení byl získán běžný advisory lock;
žádný proces ani cizí zámek nebyl násilně ukončen.

Vznikl konzistentní READ ONLY snapshot pod stejným writer lockem, přesně pro
pět Search tabulek. [Manifest](backup-manifest.json): 141 073 875 B gzip,
20 062 988 řádků celkem, SHA-256 každého archivu a všech řádků každé tabulky.
Všechny archivy jsou na privátním webovém oddílu i mimo server na lokálním disku.
Raw SQL ani osobní údaje nejsou součástí tohoto repozitáře.

[Skutečná izolovaná obnova](restore-verification.json), MariaDB 10.3.39:

- 37 běhů, 1 stav, 48 011 položek slovníku;
- 1 323 147 dokumentů a 18 691 792 vazeb indexu;
- shodné počty a SHA-256 všech normalizovaných řádků pěti tabulek;
- shodné SHA-256 externích archivů, gzip kompletně přečten;
- nulové osiřelé vazby na dokumenty nebo slovník.

[Zkouška zásahu na obnovené kopii](local-reclaim-rehearsal.json) ověřila skutečný
TRUNCATE téže tabulky i zachování rodičů; lokální `.ibd` klesl z 2 583 691 264 B
na 98 304 B. Tento údaj není zaměňován s produkční úsporou. Lokální instance
na portu 33430 byla po ověření řádně ukončena, archivy zůstaly zachované.

## Produkční uvolnění prostoru

[Protokol](reclaim-result.json), 21:10 UTC: před mutací byly znovu ověřeny
archivy, úspěšná obnova, lokální zkouška, všechny vypnuté flags, cron hash,
release, přesné schéma, počty řádků, checkpointy a absence příchozích FK.
Pod writer lockem byla odstraněna aktivní reference hledání, zneplatněny
příznaky tokenizace a ukončen jeden rozpracovaný běh, aby se nemohl zveřejnit
neúplný index. Vyprázdněna byla pouze tabulka `stats_search_postings`.

| Měření | Bajty |
| --- | ---: |
| Systémový prostor před TRUNCATE | 2 280 534 016 |
| Systémový prostor po TRUNCATE | 5 631 791 104 |
| Pozorovaný nárůst volného místa | **3 351 257 088** |

Dokumenty a slovník zůstaly se stejnými počty. Kanonické záznamy, importer staging,
auditní historie, zálohy a data EvidencePavel tento zásah neměnil. Hledání a cron
zůstávají dočasně vypnuté; základní původní hledání Stats funguje přes vlastní
fallback. [HTTP kontroly Velocoty](velocota-paused-http.json) 6/6.
Následný READ ONLY metadata dotaz vytvořil dvě diskové dočasné tabulky bez chyby;
průběžné kapacitní kontroly jsou v [pozorováních](observations.json).

## EvidencePavel — brány nasazení

Cíl je main `d3f507bc2d21e4e25cfab4c3ad867a9aadf33b5d`, aplikační kód zahrnuje
W10.3–W15 sloučené do `2345b648a237973e96593daa5d8588be2a99c28a`.
Testy 41097723229, PHP quality 41097723222, docs 41097723233 a CodeQL 41097722410
pro tento přesný main prošly. Implementační důkaz: 1864 testů / 37843 assertions,
MariaDB 10.3/11.4, souběhy, rozpočty, typy/styl, JS a úplná syntetická obnova.

- [Audit před uvolněním](pre-verification.json), run 41127010904: PASS.
- [Audit po uvolnění](pre-deploy-verification.json), run 41129157799: PASS.
- [Veřejné HTTP před](pre-http.json): 26/26.
- [Obnova poslední zálohy před nasazením](https://github.com/KovoPraha/evidenceTreninku/actions/runs/41127231881):
  PASS, skutečně obnoveno 336 tabulek / 2 triggery do izolované MariaDB 11.4.
- [Chráněný deploy 41129311692](https://github.com/KovoPraha/evidenceTreninku/actions/runs/41129311692):
  **SUCCESS**, UAT false a import false. [Strukturovaný výsledek](deployment.json):
  nová záloha 336 tabulek / 2 triggery, 1864 testů / 37843 assertions,
  schéma current / pending [], VS unchanged true, aktivace a HTTP smoke PASS.
- [Audit po nasazení](post-verification.json), run 41130072050: živé SHA/run
  odpovídají cíli; [porovnání](release-comparison.json) zachovává config hash,
  flags, stav příloh a canonical-write false. Ledger 141 → 142.
  Identita je doložena deployment metadata a workflow, ne novou úplnou inventurou souborů.
- [HTTP po nasazení](post-http.json): **26/26**, stejné tři produkty.
- [Obnova právě vytvořené zálohy](https://github.com/KovoPraha/evidenceTreninku/actions/runs/41130077458):
  **SUCCESS**, 336 tabulek / 2 triggery, SHA a manifest ověřeny, skutečný import
  a porovnání počtů. [Identita obou ověřených záloh](evidence-backup-restore.json).

Technická produkční brána W10.3–W15 je uzavřena. Lidská/účetní přejímka,
dvě chybějící přílohy a aktivace integrací zůstávají oddělené. Pozorování po
nasazení ukazuje 5 598 212 096 B volných; nejde o garanci budoucí spotřeby.
Předání odstaveného hledání je sloučené v [Velocota docs PR #93](https://github.com/velocota/velocota-docs/pull/93).

## Provozní předání hledání

Privátní archivy, původní cron, provedené skripty a podrobný postup obnovy jsou
v `stats.velocota.com/_ops/evidencepavel-db-recovery-20261030` a v lokálním
`C:/Users/Marek/Documents/Codex/_ops/evidencepavel-db-recovery-20261030/HANDOFF.md`.
Bez trvalé kapacitní opravy nebo samostatně ověřeného úspornějšího indexu
hledání automaticky nezapínat: původní index by znovu spotřeboval uvolněné místo.
Obnova musí ověřit shodu zachovaných rodičovských dat, řádkové hashe, oprávněnost
zveřejnění a běžnou čerstvost. Starý snapshot se nesmí označit za čerstvý pouhou
změnou času. Provozní přejímka uživatelů a aktivace integrací jsou oddělené.
