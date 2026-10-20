# Sjednocená administrace a správa e-shopu

Následné schválené produkční nasazení a browser ověření jsou dokončené; viz [produkční report](UNIFIED_ADMIN_SHOP_PRODUCTION_2026-10-20.md). Níže je původní lokální implementační předání.

Stav 20. 10. 2026: lokální implementace ve větvi `codex/unified-admin-shop-20261020`, základ `4991c91f`. Pracovní kopie: `C:\xampp\htdocs\evidencePavel-unified-admin-shop-20261020`. Produkční nasazení ani vzdálený Git nejsou součástí tohoto kroku.

## Výsledná obsluha

- Společná hlavička, postranní nabídka podle pracovní pozice, typografie, barvy, formuláře, tabulky a tlačítka používají jednotný styl. Mobilní nabídka se otevírá samostatně. Sdílené komponenty se uplatní napříč aplikací; jednotlivé starší formuláře nebyly všechny obsahově přestavovány.
- Produkty mají jednoduchý stránkovaný výpis. Detail obsahuje karty Hlavní údaje, Varianty a ceny, Sklad, Obrázky, Kategorie a Historie. Počet kusů je vidět u každé varianty.
- Přidání produktu a stažení z prodeje mají vlastní stránky. Stažení zachová produkt, objednávky, sklad a historii.
- Importované fyzické zboží přijímá nové ruční varianty, včetně generování kombinací barev a velikostí (nejvýše 64). Původní importní identity zůstávají zachované; přidané varianty mají původ manual, autora a vlastní KP- kód.
- Původní i nové varianty lze editovat. Cena se mění také v tabulce variant a v samostatném ceníku. Úprava ceny nemění sklad. Sklad se opravuje samostatně přes kanonický zápis pohybu s kontrolou původního zůstatku.
- Duplicitní kombinace zablokuje celou dávku. Programové nabídky zůstávají v jejich specializované správě kapacit a termínů.

## Ověření

`composer test`: **1 136 testů / 25 677 assertions**, vše úspěšné. Po drobných změnách obsluhy doménových chyb a kontrastu odhlášení navíc prošlo 19 cílených testů / 138 kontrol. PHP lint, kontrola JavaScriptu a Composer validate prošly. Nejsou přidány nové migrace; izolovaná MariaDB odpovídá 106 migracím.

Browser Chrome, localhost, syntetický administrátor a data bez skutečných zákazníků:

| Kontrola | Ověřený výsledek |
| --- | --- |
| Samostatné založení produktu | Produkt 5 a první varianta byly založeny přes formulář; přidány 4 další kombinace. |
| Samostatné stažení produktu | Produkt 5 se stáhl z prodeje, varianty a jejich sklad zůstaly dostupné v administraci. |
| Importovaný produkt | S výslovným souhlasem uživatele byl standardním CLI připraven nový syntetický import run 2 (2 produkty, 3 varianty). Kontrola a převod proběhly přes UI. |
| Přidání variant importovaného produktu 6 | Modrá/Růžová × 128/134: přidány 4 varianty, celkem 6. |
| Editace původní varianty 11 | S → XS, 1 290 → 1 190 Kč; SKU LOCALHOST-UI-S a sklad 5 zachovány. |
| Tabulková změna ceny | Varianta 10: 1 290 → 1 390 Kč, sklad 3 zachován. |
| Skladová inventura | Varianta 11: 5 → 7 kusů, právě jeden pohyb +2 s důvodem a auditní událostí. |
| Editace nové varianty 13 | Modrá → Tmavě modrá, 1 145 → 1 245 Kč, sklad 2 zachován. |
| Duplicitní kombinace | Růžová/128 odmítnuta, produkt stále obsahuje 6 variant. |
| Mobilní šířka 390 px | Navigace se přepne na otevírací nabídku, formuláře se skládají pod sebe, tabulka má vlastní horizontální posun. |
| Další pracovní pozice | Systémový rozcestník má stejnou hlavičku a boční menu se svými oprávněnými odkazy. |

Výsledek je uložen přes `testovaci_scenare.php` u B08 s přesným rozsahem. B08 je PARTIAL, protože širší scénář zahrnuje i veřejnou publikaci; nový syntetický import byl záměrně ponechán jako koncept. To není selhání uvedených kontrol správy variant.

Snímky místního ověření: `outputs/unified-admin-shop-20261020/importovane-varianty.jpg` a `historie-sklad.jpg` (lokální nezaverzované artefakty).

## Navázání

Lokální URL: `http://localhost/evidencePavel-unified-admin-shop-20261020/`. Ignorovaný `config.php` používá izolovanou DB `evidence_shop_ui_v2` na portu 33120, data v `C:\xampp\mysql\codex-unified-admin-20261020-data`. Nesdílí produkční databázi.

Před případným schváleným nasazením porovnat větev s aktuálním main a použít standardní chráněný PR/Actions postup. Samotná tato dokumentace nepotvrzuje nasazení ani produkční akceptaci.
