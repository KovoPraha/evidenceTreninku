# Sdílený vzhled a chování aplikace

Aktualizováno: 5. 8. 2026

Evidence, KIS, e-shop a rezervační část používají jeden společný základ bez
frontendového build kroku:

- `includes/ui_shell.php` vytváří odkazy na společné assety a veřejnou navigaci,
- `assets/app-ui.css` sjednocuje pozadí, karty, formuláře, focus a stav odesílání,
- `assets/app-ui.js` zajišťuje indikaci načítání, ochranu proti dvojímu odeslání,
  bezpečné toast zprávy a kopírování,
- `hlavicka.php` zůstává společným obalem přihlášené trenérské administrace a
  načítá stejný UI základ,
- veřejné vstupy e-shopu, tréninků, kroužků, velodromu a přihlášení používají
  `publicShellNav()`; viditelné volby se přizpůsobí rodiči, sportovci a trenérovi.

## Jedna aplikace, nikoli tři oddělené moduly

Označení Evidence, e-shop a KIS popisuje funkční oblasti a historický původ
obrazovek. Technicky jde o jednu aplikaci v jednom repozitáři a webrootu. Používá
jedno `db.php`, společný migrační katalog, společnou session a tabulku
`sportovci`. KIS soupisky a programy přímo navazují na původní tréninky a
docházku; e-shopové objednávky mohou stejnému sportovci aktivovat program,
soupisku, událost nebo rezervaci. Trenérský účet zároveň dostává zákaznickou
identitu pro nákup.

Rozdělení adresářů a názvů souborů se zatím zachovává kvůli kompatibilitě a
srozumitelnosti, nikoli jako hranice samostatných systémů.

## Pravidlo pro novou stránku

Přihlášená administrační stránka má použít `hlavicka.php`. Samostatná nebo
veřejná HTML stránka má po načtení `db.php` zavolat v `<head>`:

```php
<?php appUiAssets(); ?>
```

Hlavní veřejná stránka navíc používá `publicShellNav()` a
`publicShellFooter()`. Aktivní sekce je `shop`, `training`, `clubs` nebo
`velodrome`; účtové stránky mohou navigaci volat bez aktivní sekce.

## Formuláře a načítání

Každý `POST` formulář získá po platném odeslání stav `aria-busy`, indikátor v
tlačítku a horní načítací linku. Druhé odeslání stejného formuláře je
zablokováno. Tlačítka se technicky nedeaktivují, takže se neztratí jejich
`name` a `value`. Po návratu historií prohlížeče se stav vyčistí.

Validace, CSRF a databázová transakce zůstávají odpovědností konkrétní funkce;
sdílený JavaScript je pouze jednotná uživatelská odezva.

## Automatická ochrana

`tests/Unit/SharedUiShellTest.php` kontroluje, že každá aktivní first-party PHP
stránka s `<head>` používá buď administrační hlavičku, nebo `appUiAssets()`.
Současně hlídá veřejné vstupy, bezpečné vykreslení toastu a jednotné chování
odesílaných formulářů.

Stránky mohou mít vlastní CSS pro specifický obsah (kalendář, časová osa,
tabulka nebo tisk). Společné pozadí, formulářové prvky, načítání, navigace a
obecné interakce se už nemají kopírovat do jednotlivých souborů.
