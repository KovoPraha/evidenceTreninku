# BAR → KIS: schválené produkční vydání

5. 11. 2026 vlastník výslovně schválil nasazení („ok, tedy nasadit“). **Modul BAR je nasazený a technicky ověřený v KIS.** [PR #306](https://github.com/KovoPraha/evidenceTreninku/pull/306) je sloučený; živé SHA **76bf0370a06e0ab80bab5ee9af1815835ac24efc**, [deploy 42084130988](https://github.com/KovoPraha/evidenceTreninku/actions/runs/42084130988) SUCCESS.

Toto je vydání kódu B0–B6, nikoli dokončený převod skutečného baru. Skutečný katalog, fotografie a účet lucka dosud nebyly exportované ani importované; cílový katalog je prázdný. Starý bar.kovopraha.cz nebyl přesměrovaný ani měněný. Přidělení konkrétních dalších pracovníků, skutečný převod a lidská provozní přejímka jsou další část B7. Nové účetní zápisy nebo bankovní propojení BAR se neaktivovaly; dosavadní globální Fio a SumUp zůstávají zapnuté.

## Ověřený obsah vydání

- Finální přezkoušený head **07e69f9a59c73383c7c5845983fb78e0da6319e5** má všech **11 kontrol SUCCESS**. [Linux CI 42079599811](https://github.com/KovoPraha/evidenceTreninku/actions/runs/42079599811): **2203 testů / 41286 assertions bez skipů**, samostatné unixové hranice 3/45; MariaDB 10.3/11.4, PHPStan, styl, CodeQL, JavaScript a dokumentace PASS.
- Sloučený strom je přesně shodný s tímto headem. [CI main](main-ci.json) i nasazovací testy mají stejný technický výsledek. Vydání zahrnuje aktuální členství #316 i předání #317; jejich změny nebyly vrácené starší větví baru.
- Společný zálohovací ownership kontrakt **2026-11-05.8** zachovává všechny dosavadní oblasti i 28 BAR tabulek. Aplikovalo se sedm BAR migrací; [nasazovací kontrola](deploy-database.json) hlásí schema current / pending [], [čtecí audit](release-comparison.json) ledger **152 → 159**. Nasazovací kontrola zachování dosavadních variabilních symbolů PASS.
- Před vydáním byla doplněna chráněná operace úplné zálohy souborů a DB. Test hostovacího wrapperu na izolované fixture ověřil skutečné spuštění, obsah konfigurace i přílohy v archivu, stejné otisky a odmítnutí přepsání. Do výstupu se nedostal obsah konfigurace. Jediná chyba posledního spojení větví byla překročená délka CURRENT_STATE; dokument byl upraven bez ztráty údajů a finální kontroly výše jsou zelené.

## Bod návratu a skutečná obnova

[Úplný přednasazovací drill 42080871410](https://github.com/KovoPraha/evidenceTreninku/actions/runs/42080871410) SUCCESS z chráněného main. Všechny operace zachovaly běžící aplikaci a zapisovaly pouze její soukromé zálohy a dočasné pomocné nástroje mimo webroot. Dřívější pokus z pracovní větve GitHub odmítl před vykonáním skriptů; ochrana production/main nebyla obcházená.

| Podklad | Skutečně ověřený výsledek |
| --- | --- |
| DB `evidence_2026-11-05_180540_2776742c.sql.gz` | **357 tabulek / 2 triggery**; SHA-256 `24fecf4113e570533448321386e99b8de1c5b56c9230a38bdc4b53541fbe231b` |
| Soukromý archiv `kis-files-42080871410.zip` | **4341 souborů**, 657834269 zdrojových bajtů, ZIP 613288913 bajtů; SHA-256 `3102a91ab4ec5ed16b53612bb7b2fbe9262bdd963680aea29d2c00f2a7a63060` |
| Obnova SQL | Skutečná izolovaná MariaDB, všechny počty řádků i seznam triggerů souhlasí |
| Obnova souborů | Každý soubor skutečně obnoven do oddělené složky runneru a ověřen podle velikosti a SHA-256 |

[Metadata a otisky](pre-release-backup.json), [běh](pre-release-backup-run.json). Archiv zahrnuje tehdejší aplikaci včetně konfigurace a celé soukromé úložiště. Konfigurace, SQL a přílohy nebyly zveřejněné jako GitHub artefakty ani vložené do Gitu; po nácviku se privátní pracovní kopie runneru uklidily. Zálohy zůstávají soukromě na hostingu.

Nasazovací workflow ještě těsně před migracemi vytvořilo novou [DB zálohu](deploy-database.json) `evidence_2026-11-05_182807_acb1e566.sql.gz`, **357 tabulek / 2 triggery**, SHA-256 `4a4e0f5f0ef6024a79eb0a29fd73fc9dc6976d2c66f2b92c36ad7c360f2ca6dc`. Následný samostatný drill této nejnovější zálohy: [42085710492](https://github.com/KovoPraha/evidenceTreninku/actions/runs/42085710492) **PASS**, stejný konkrétní soubor a checksum, všech 357 tabulek / 2 triggery. Produkční DB se neobnovovala.

## Živá přejímka

[Anonymní HTTP před](pre-http.json) **71/71**, [po](post-http.json) **77/77 PASS**. Zachovaná dostupnost katalogu e-shopu a stejných tří veřejných produktů. Všechny nové chráněné BAR cesty odmítají anonymní přístup přesměrováním k přihlášení, veřejné menu funguje a neplatná fotografie je 404. Interní PHP, CLI a archivní klíč jsou nedostupné. Jde pouze o GET, bez session a finančních POSTů. [Obě skutečně podávaná JavaScriptová aktiva](live-assets.json) mají přesný obsah přezkoušeného release.

[Čtecí audit před/po](release-comparison.json) potvrdil přesné živé SHA a běh, stejný hash konfigurace a stejné integrační flags: Fio import/auto-confirm a SumUp true, Stripe/Velocota false, Packeta nedefinovaná. KIS canonical writes false. Historický source manifest tohoto auditního nástroje není úplnou hash inventurou BAR souborů.

[Skutečný přihlášený Chrome](browser.json) ověřil existujícího superadmina v kontextech Finance, Administrátor baru a Obsluha baru:

- Obě nové pozice jsou dostupné v rozcestníku. Administrace načte katalog, sklad, faktury, nabídky a vypořádání kas bez runtime chyby; před převodem jsou prázdné.
- Pokladna vyžaduje vlastní kasu. Formulář otevření je dostupný, během přejímky se neodesílal a žádná kasa ani objednávka tímto testem nevznikla.
- Vlastní spotřeba je bez výslovného grantu odmítnutá, také u superadmina v barovém kontextu. Žádné oprávnění se při ověření nepřidělovalo.
- Účetní přehled ukazuje nulu a žádné podklady/exporty. Veřejná obrazovka hlásí přípravu nabídky. Žádné PDF se nenahrávalo ani nabídka nezveřejňovala.
- Původní pracovní kontext Finance byl na konci obnoven. Měnila se pouze pracovní session, nikoli doménová data. Místní doklad obrazovky je `output/playwright/bar-production-catalog-20261105.png`.

Toto je čtecí technická produkční přejímka. Běžná lucka v produkci ještě není převedená, skutečný prodej, karta/QR, hotovostní předání, platební poskytovatel a lidské účetní UAT se tímto vydáním netestovaly. Jejich bezpečné transakční/souběhové chování pokrývá izolovaná B4–B6 přejímka a CI; nesměšovat tyto doklady s reálnou platbou.

## Další krok

Dokončit skutečný převod podle [B7](../../BAR_PRODUCTION_READINESS_2026-11-05.md) a [runbooku katalogu](../../BAR_TRANSFER_RUNBOOK_2026-11-05.md): konkrétní balíček/manifest a nácvik, nulové výchozí zásoby, pouze lucka s technickou adresou info1@kovopraha.cz, explicitní přidělení dalších pracovních práv a vlastní spotřeby a lidská přejímka. Teprve po ověření cíle řešit přesměrování starého BAR. [Budoucí účetní adaptér](../../BAR_ACCOUNTING_ADAPTER_PLAN_2026-11-05.md) nadále vyžaduje schválení účtů, daní, ocenění a bankovní vazby.
