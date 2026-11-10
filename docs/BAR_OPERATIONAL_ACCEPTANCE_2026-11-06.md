# BAR: provozní přejímka a příprava nabídky

Stav 10. 11.: [původní TV obrazovky, převod zásob a oprava starých URL dokončené](verification/bar-display-stock-20261110/RESULTS.md). 68 počátečních zůstatků obou skladů převzato přesně včetně pěti záporných hodnot; fyzická inventura a lidské P02/P04 jsou nadále otevřené. Nabídka 1 / vydání 1 se znovu nepublikovala. Původní postup a nulové počátky níže jsou datovaná historie; další zásoby spravovat pouze v KIS, původní zdroj znovu nesynchronizovat.

Zadání vlastníka 6. 11. 2026: pokračovat provozní přejímkou a připravit zveřejnění nabídky. Předchozí převod a doména jsou dokončené podle [protokolu B7](verification/bar-cutover-20261106/RESULTS.md). Příprava nabídky znamená uložený neveřejný koncept; nezahrnuje automatické zveřejnění ani odhad skutečných počátečních zásob.

## Postup

1. Ověřit aktuální main, živý modul, dostupnou přihlášenou relaci a existující nabídky, aby nevznikl duplicitní návrh. Zachovat všechny dosavadní produkty, ceny, role a provozní operace.
2. V nové oddělené lokální databázi `evidence_bar_test_browser_*` připravit pouze syntetická data. Browser používá loopback:8895, vlastní MariaDB loopback:3327, žádný platební poskytovatel nebo pošta. Oddělit obsluhu bez vlastní spotřeby, správce s výslovnou spotřebou a systémového správce.
3. Skutečným browserem projít přihlášení obsluhy, omezení práv, vlastní kasu, hotovostní prodej, čekající kartu/QR a rezervaci, storno, vratku kelímků, vlastní spotřebu podle grantu a uzávěrku. U každého zápisu porovnat skutečně uložená data a částky, konečné zásoby, rezervace a stav kas. Jde o testovací doklady, ne ostré platby.
4. V produkci přes přihlášené rozhraní vytvořit nebo doplnit jeden návrh „Bar KOVO – základní nabídka“, s fotografiemi a pouze aktivními produkty povolenými v katalogu pro prodej. Zachovat jejich současné ceny a pořadí; žádné testovací nebo archivované produkty navíc. Před uložením ověřit konkrétní výběr.
5. Ověřit, že návrh je uložený a veřejná obrazovka ani výběr pokladny se dosud nezměnily. Připravit konkrétní přehled pro schválení publikace; zaznamenat nulové skladové počátky a potřebnou inventuru/příjem před skutečným prodejem.
6. Zapsat protokol PASS/FAIL/NOT RUN včetně snímků, uložených souhrnů, ID konceptu a otevřených lidských kroků. Obnovit původní pracovní kontext a místní konfiguraci, ukončit pouze vlastní testovací služby.

## Přístup a hranice

Produkční hesla se nečtou ani neposílají do chatu. Pokud je KIS odhlášený, vlastník obnoví svoji relaci; nezávislé místní kroky pokračují. Skutečné přihlášení Lucky jejím původním heslem vyžaduje vlastníka/obsluhu a zůstává samostatným bodem přejímky. Produkční prodeje, bankovní potvrzení, počáteční skladová množství a hotovost nelze nahrazovat syntetickou zkouškou. Produkční zásoby se neplní odhadem a veřejná nabídka se při přípravě nepublikuje.

## Dokončení původní přejímky

[Protokol a důkazy](verification/bar-acceptance-20261106/RESULTS.md): syntetická technická přejímka PASS, produkční návrh **1 / Bar KOVO – základní nabídka** uložený se 45 produkty a fotografiemi, dosud nezveřejněný. Původní kontext vlastníka a místní konfigurace obnovené, vlastní testovací služby vypnuté. Skutečné provozní kroky jsou výslovně vyjmenované v protokolu.

## Následné schválené zveřejnění

Vlastník následně 6. 11. výslovně schválil publikaci téhož návrhu. [Zveřejnění nabídky 1 / vydání 1](verification/bar-menu-publication-20261106/RESULTS.md) je dokončené: 45 produktů a 45 fotografií ověřených anonymně. Původní protokol výše zachovává stav před publikací. Další skutečné provozní kroky vede [session handoff](SESSION_HANDOFF_BAR_2026-11-06.md), pracovní postup [publikační plán](BAR_MENU_PUBLICATION_2026-11-06.md).
