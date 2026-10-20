# Sjednocení zbývajících formulářů administrace

Datum: 20. 10. 2026. Navazuje na PR #140–143. Pracovní větev
`codex/remaining-admin-forms-20261020`, výchozí `42a66cab`.

## Změny

- Společná vrstva `assets/app-ui.css` sjednocuje karty, záhlaví sekcí,
  vstupy, rozestupy, měření a ukládací lištu starších administračních formulářů.
  Vybrané lekce, stavové štítky, upozornění a náhledy obsahu si zachovávají význam.
- Šestnáct starších stránek již nemá vlastní kopie barevných karet, přechodů
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
- Při zadání tréninku bez cílové soupisky se chyba zobrazí před odesláním,
  takže se neztratí vyplněné datum a náplň. Serverová kontrola zůstává zachovaná;
  evidence z existujícího plánu zachovává svoji výjimku. Blokovaný submit
  také nevypíná upozornění na neuložené změny.

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
- zachování vyplněného tréninku při chybějící soupisce, následné doplnění
  `LOCALHOST Dráha 2026`, úspěšné uložení a načtení editoru tréninku #1
  s totožnou náplní a sjednoceným bannerem; následné uložení editované náplně
  a kontrolu jejího načtení;
- kartu člena, starší detail sportovce a program místní skupiny;
- editor skupiny při 390 × 844 px: popisky nad poli, tabulka má vlastní
  vodorovný posun, celá stránka nepřetéká;
- tmavý režim jednoduchého editoru, návrat do světlého režimu a reset viewportu.

Lokální výsledek B30 zapsán přes `testovaci_scenare.php` jako PARTIAL,
protože tato prohlídka neověřuje všechny mutace, navigaci bez JS ani úplné UAT.
Žádná produkční data ani zprávy nebyly touto lokální prohlídkou změněny.

Lokální důkazy jsou v ignorovaném `outputs/unified-admin-shop-20261020/`:
`remaining-forms-browser-checks.json`, `remaining-forms-training.jpg`,
`remaining-forms-mobile.jpg`, `remaining-forms-dark.jpg`.

Konečný PHPUnit: **1 138 testů / 25 708 kontrol, PASS**.
PHP lint změněných souborů, `node --check assets/app-ui.js`
a `git diff --check` prošly. Regresní test hlídá kanonické cesty hlavičky;
starší test kontrastu nyní ověřuje společné theme-aware záhlaví.

## Produkční stav

PR [#144](https://github.com/KovoPraha/evidenceTreninku/pull/144) je sloučený.
Aplikační release **`246dee2a4df5dc3152c1a769a9af128b87a91092`** nasadil
[běh 39496342888](https://github.com/KovoPraha/evidenceTreninku/actions/runs/39496342888).
Záloha, 106 migrací, aktivace release, kontrola zákazu přístupu k soukromému
klíči a HTTP smoke test prošly. Diagnostika `uat_readiness_admin.php`
v Chrome nezávisle potvrdila totožný SHA a deploy `2026-10-20T20:59:25+00:00`.

[CI 39496114603](https://github.com/KovoPraha/evidenceTreninku/actions/runs/39496114603)
prošlo: PHPUnit v Linuxu **1 138 testů / 25 700 kontrol**, MariaDB 10.3 i 11.4.
CodeQL prošel v
[běhu 39496109424](https://github.com/KovoPraha/evidenceTreninku/actions/runs/39496109424).
Lokální počet kontrol se liší kvůli podmíněným kontrolám prostředí.

Produkční Chrome po nasazení načetl osm stránek: zadání tréninku,
další činnost, plánovaný trénink, záznam jízdy, skupiny, podskupiny,
správu sportovců a audit log. Nebyly zachyceny PHP chyby ani přetékání celé
stránky. Trénink má bílé panely, modré záhlaví a zelené ukládání.
Přepnutí Finance → Registrář ze záznamu jízdy skutečně funguje;
akce odhlášení i přepnutí mají kořenové cesty. Audit log má správný titulek
a mobilní viewport. Produkční data nebyla editována; měnil se pouze aktivní
pracovní kontext relace a na konci byl vrácen katalog.

Produkční důkazy jsou v místním ignorovaném podadresáři `production/`:
`remaining-forms-training.jpg`, `remaining-forms-release.jpg`,
`remaining-forms-checks.json`. Pozdější aktualizace tohoto reportu a aktuálních
předání je pouze dokumentace; aplikační SHA výše zůstává identitou release.
Plné UAT zůstává otevřené, `uat_schvaleno=false`, `importovat_krouzky=false`.
