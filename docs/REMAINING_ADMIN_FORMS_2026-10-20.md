# Sjednocení zbývajících formulářů administrace

Datum: 20. 10. 2026. Navazuje na PR #140–143. Pracovní větev
`codex/remaining-admin-forms-20261020`, výchozí `42a66cab`.

## Změny

- Společná vrstva `assets/app-ui.css` sjednocuje karty, záhlaví sekcí,
  vstupy, rozestupy, měření a ukládací lištu starších administračních formulářů.
  Vybrané lekce, stavové štítky, upozornění a náhledy obsahu si zachovávají význam.
- Patnáct starších stránek již nemá vlastní kopie barevných karet, přechodů
  a zaoblených panelů. Změna pokrývá tréninky, závody, členy, sazby,
  období, kredity a další starší přehledy.
- Skupiny, podskupiny, sportoviště, bankovní účet a další činnost mají
  přehledné řádky popisek/pole; na mobilu se skládají pod sebe.
  Ukládací tlačítka používají společnou zelenou barvu.
- Společný pomocník propojí starší sousední popisky s formulářovými poli.
  Kliknutí na popisek skutečně zaměří ovládací prvek.
- Hlavička používá kanonické cesty i ve vnořených stránkách: přepnutí role,
  odhlášení, veřejný portál, zkratky, hledání a PWA. Opraveno také možné
  `//ajax_global_search.php` při instalaci do kořene domény.
- Audit log má správný název stránky a mobilní viewport.

## Ověření na localhostu

Oddělený checkout a databáze `evidence_shop_ui_v2`; pouze syntetická data.
Zdrojová inventura našla 112 přímých administračních stránek s formuláři.
V Chrome proběhl průchod 105 adresami napříč osmi pracovními rolemi,
kontrola titulku, obsahu, zachytitelných PHP chyb a šířky stránky.
Nešlo o 105 úplných funkčních scénářů: servis bez vozidla přesměrovává,
RSVP bez `plan_id` správně vrací nenalezený trénink a další editory vyžadují ID.
Následně byly z UI otevřeny editace uloženého vozidla i skutečný servisní formulář.

Vizuálně zkontrolovány trénink, závod, skupiny, sportoviště, bankovní účet,
činnost, jízda a servis. Chrome dále ověřil:

- uložení činnosti `LOCALHOST sjednocení formulářů 2026-10-20`, 1,5 h,
  zobrazení stejné položky ve výkazu;
- vytvoření syntetického vozidla `LOCALHOST test sjednocení NEPLATIT`,
  SPZ `LOCALHOST`, následné otevření editace s uloženou hodnotou;
- skutečné přepnutí pracovní pozice z `jizdy/formular.php`;
- zaměření názvu plánovaného tréninku kliknutím na popisek;
- tréninkový formulář při 390 × 844 px bez vodorovného přetékání,
  dostupnou přichycenou ukládací lištu;
- tmavý režim jednoduchého editoru, návrat do světlého režimu a reset viewportu.

Lokální výsledek B30 zapsán přes `testovaci_scenare.php` jako PARTIAL,
protože tato prohlídka neověřuje všechny mutace, navigaci bez JS ani úplné UAT.
Žádná produkční data ani zprávy nebyly touto lokální prohlídkou změněny.

Lokální důkazy jsou v ignorovaném `outputs/unified-admin-shop-20261020/`:
`remaining-forms-browser-checks.json`, `remaining-forms-training.jpg`,
`remaining-forms-mobile.jpg`, `remaining-forms-dark.jpg`.

PHPUnit: **1 138 testů / 25 707 kontrol, PASS**.
PHP lint změněných souborů, `node --check assets/app-ui.js`
a `git diff --check` prošly. Regresní test hlídá kanonické cesty hlavičky;
starší test kontrastu nyní ověřuje společné theme-aware záhlaví.

## Produkční stav

Nasazení této navazující změny bude doloženo samostatným záznamem
s přesným release SHA, výsledkem standardního CI/deploy a živou kontrolou.
Předchozí nasazený release `175f92c7a59294acff7e15fe09cbed258707fa2e`
je doložen v `UNIFIED_ADMIN_SHOP_PRODUCTION_2026-10-20.md`.
Plné UAT zůstává otevřené.
