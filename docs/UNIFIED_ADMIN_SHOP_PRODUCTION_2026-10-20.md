# Sjednocená administrace – produkční ověření

20. 10. 2026, Europe/Prague. Vlastník výslovně schválil commit, push, standardní nasazení a prohlížečovou kontrolu s opravou chyb.

## Release

- [PR #140](https://github.com/KovoPraha/evidenceTreninku/pull/140): společný vzhled, samostatné stránky produktu, správa importovaných variant, cen a skladu.
- [PR #141](https://github.com/KovoPraha/evidenceTreninku/pull/141): české jednotky, archivace konceptu bez publikace, kontrast tmavého tématu a ověření zákazu HTTP přístupu při síťovém timeoutu runneru.
- [PR #142](https://github.com/KovoPraha/evidenceTreninku/pull/142): čitelné akce v bílém záhlaví starších formulářů.
- Cílový release: `175f92c7a59294acff7e15fe09cbed258707fa2e`.
- Závěrečné nasazení: [39483597831](https://github.com/KovoPraha/evidenceTreninku/actions/runs/39483597831), **success**. Vytvoření a ověření zálohy, migrace, aktivace release, ochrana soukromé konfigurace i HTTP smoke prošly.
- Přesný SHA byl nezávisle potvrzen v přihlášeném produkčním `uat_readiness_admin.php`: `175f92c7a59294acff7e15fe09cbed258707fa2e`, deployment `2026-10-20T19:29:57+00:00`. Celková UAT brána zůstává červená kvůli samostatným předpokladům plné sady a neschválenému plnému UAT; tím se nepotvrzuje její uzavření.

První běh [39481745765](https://github.com/KovoPraha/evidenceTreninku/actions/runs/39481745765) aktivoval kód, ale skončil síťovým timeoutem kontroly soukromé konfigurace. Oprava nezměkčuje požadavek HTTP 403/404: pouze při nulovém HTTP statusu z runneru ověří stejnou URL ze serveru přes již ověřený SSH kanál. Skutečná chyba HTTP ani veřejně dostupná konfigurace neprojde. Následný běh [39483198464](https://github.com/KovoPraha/evidenceTreninku/actions/runs/39483198464) už úspěšně dokončil všechny kroky včetně zálohy, migrací, ochrany soukromé konfigurace a HTTP smoke.

CI oprav: **1 137 testů / 25 679 kontrol**, MariaDB 10.3 a 11.4 i CodeQL prošly. Nové migrace tato změna nepřidává. Nasazení používá `uat_schvaleno=false` a `importovat_krouzky=false`; nové kroužky se nepřipravovaly.

## Prohlížečové ověření

Chrome, přihlášená produkční administrace `kis.kovopraha.cz`. Zápisy byly omezeny na výslovně označená testovací data. Původní prodejní varianty se neměnily.

| Oblast | Skutečný výsledek |
| --- | --- |
| Produkty a detail | Jednoduchý výpis, karty detailu a samostatné stránky se načítají v nové společné navigaci. |
| Importované zboží | Na produktu 66 byla vytvořena skrytá ruční testovací varianta 948, SKU `KP-5EBCA198D68E`. |
| Parametry a cena | Testovací barva změněna na `TEST UI 20261020 OVĚŘENO`; cena 100 → 125 Kč v editoru, následně 125 → 150 Kč v tabulce. Skrytí i sklad 0 zachovány. |
| Sklad | Testovací varianta 0 → 2 → 0. Historie obsahuje přesně dva pohyby +2 a −2, odpovídající nové stavy a důvody. |
| Duplicity | Opětovné přidání stejné kombinace odmítnuto hláškou „Varianta se stejnými parametry už existuje.“ |
| Původní import | Editor původní varianty 471 se načetl, kód 370 je pouze pro čtení; cena 2 481 Kč a sklad 1 se nezměnily. |
| Veřejný produkt | `/booking/produkt.php?id=66` stále nabízí pouze původní variantu 471 za 2 481 Kč. Testovací varianta chybí i ve výběru zájmu. |
| Založení produktu | Přes samostatnou stránku vytvořen koncept 295 `TEST UI 20261020 – NEPRODÁVAT`. Nebyl zveřejněn. |
| Archivace konceptu | První pokus odhalil omezení na aktivní produkty. Po PR #141 prošla archivace přes vlastní stránku bez publikace. Veřejný detail 295 vrací „Produkt není dostupný nebo už není v aktivní nabídce.“ |
| Mobil | Šířka 390 px, funkční otevření/zavření nabídky, skládání karet a horizontální posun tabulky. Původní viewport byl obnoven. |
| Celá aplikace | Společný vzhled potvrzen také na rozcestníku jiné role a formuláři zadání tréninku. Data tréninku se neukládala. |
| Tmavý režim | Formulář i tabulka jsou čitelné; upraven kontrast primárních nadpisů a tlačítek. Původní světlé téma obnoveno. |

## Konečný stav testovacích dat

Produkt 295 je archivovaný a veřejně nedostupný. Varianta 948 zůstává skrytá, jasně označená NEPRODAVAT, se skladem **0**; není součástí veřejné nabídky. Audit se nemaže. Nebyla vytvořena objednávka, platba ani odeslán e-mail.

Po cílovém release byly znovu potvrzeny české jednotky (`1 jedn.` místo `1 other`), původní cena a sklad, skrytí testovací varianty a čitelný odkaz Rozcestník ve formuláři tréninku. Stabilní tmavé téma má na primárním tlačítku text RGB 16/33/45 na pozadí 105/203/234. Světlé téma a původní velikost prohlížeče byly obnoveny.

Místní snímky jsou v `outputs/unified-admin-shop-20261020/production/`: `varianty-ceny-sklad.jpg`, `tmave-tema.jpg` a `trenersky-formular.jpg`. Nejsou součástí veřejného deploye ani Gitu.

Tento report potvrzuje uvedené scénáře správy e-shopu a vzhledu. Neuzavírá plnou akceptační sadu aplikace ani aktivaci platebních bran, Fio, hromadných importů nebo rozesílek. Původní místní implementační důkazy jsou v [předání](UNIFIED_ADMIN_SHOP_HANDOFF_2026-10-20.md).
