# Návrat vydání B04/B23

Připraveno pro produkční vydání `5df4b0278129a3d66b62a19657911567d7196dee` (B04/B23 z PR #205 a aktualizovaná čtecí diagnostika z PR #206). Tento dokument není záznam provedeného návratu ani obnovy produkční databáze.

## Ověřené podklady

- Předchozí živý release: `a23e393f64eeffafd75df834e6e5ba8c78213b72`, deploy `40198413027`.
- Předletová čtecí kontrola [40242345115](https://github.com/KovoPraha/evidenceTreninku/actions/runs/40242345115) potvrdila 44/44 otisků živých souborů a stejných 44/44 otisků v archivním release `data/.kis-deploy/releases/a23e393f64eeffafd75df834e6e5ba8c78213b72-40198413027`. Nové cesty byly správně nepřítomné.
- Návratová větev [codex/b04-b23-rollback-ready-20261025](https://github.com/KovoPraha/evidenceTreninku/tree/codex/b04-b23-rollback-ready-20261025), commit `61a88784a11672a3c6af81366168532d0ac5c45a`, má aktuální vydání z main jako předka. Nejde o opakovaný merge starého commitu.
- [CI návratové varianty](https://github.com/KovoPraha/evidenceTreninku/actions/runs/40242357929): 1719 testů / 34754 assertions, unixové kontroly bez přeskočení, MariaDB 10.3 i 11.4 PASS.

## Zachování dat a migrací

Návrat obnovuje předchozí chování aplikace, ale ponechává přesné dvě nové migrace, všech šest tabulek, migrační ledger a jejich vlastnictví v zálohách. Starý katalog migrací by existující nová ID nemusel přijmout; proto se nenasazuje prostý starý checkout. Výsledkové revize, KIS audit a případná data vzniklá po nasazení se nemažou.

Rsync nasazení nepoužívá `--delete`. Návratový manifest proto výslovně odstraňuje pouze šest nových PHP souborů, které předchozí aplikace nepoužívá: tři knihovny výsledků/KIS, `includes/shop_payment_presentation.php`, `kis_review_admin.php` a `zavod_vysledky.php`. Přílohy, konfigurace a databázové záznamy do tohoto seznamu nepatří. Čtecí diagnostika vydání zůstává dostupná.

## Postup při problému

1. Uchovat diagnostiku a rozlišit závadu kódu od poškození dat. Podle potřeby omezit dotčené zápisy; běžící objednávky a integrace tím nejsou automaticky zastavené.
2. Ověřit živé SHA a aktuální main. Přibyly-li další aplikační změny, nejprve návratovou větev znovu porovnat a ověřit.
3. Otevřít PR návratové větve do main, projít skutečný diff a povinné CI. Nepoužívat reset ani force push main.
4. Standardní `deploy-production.yml`: `potvrzeni=NASADIT`, `uat_schvaleno=false`, `importovat_krouzky=false`. Vznikne nová záloha aktuálního stavu; migrace a dosavadní VS zůstanou zachované.
5. Ověřit přesný release, čtecí databázové/účetní/skladové kontroly, přístupové hranice a nedostupnost odstraněných cest.

## Obnova databáze není běžný rollback kódu

Zálohu nejprve obnovit do izolace a zkontrolovat checksum, manifest, počty tabulek a triggery. Při skutečném poškození dat navrhnout opravu se zachováním nových plateb, objednávek a dalších zápisů vzniklých od zálohy. Automatické přepsání ostré databáze starým dumpem není součástí tohoto postupu. Izolovaná obnova není záruka RTO/RPO ani nácvik odstávky hostingu.
