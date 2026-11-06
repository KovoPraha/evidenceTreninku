# BAR: protokol zveřejnění nabídky 6. 11. 2026

## Rozsah

Výslovný pokyn vlastníka: zveřejnit uloženou nabídku **1 / Bar KOVO – základní nabídka**, 45 produktů, fotografie. Navazuje na [původní neveřejný návrh](../bar-acceptance-20261106/RESULTS.md) a [publikační postup](../../BAR_MENU_PUBLICATION_2026-11-06.md).

## Výsledek

**ZVEŘEJNĚNO / PASS.** Přihlášené rozhraní po jednom úspěšném potvrzení zobrazilo
„Zveřejněno: Bar KOVO – základní nabídka (vydání 1)“. Byla publikována existující nabídka 1,
nevznikl duplicitní návrh ani další vydání. Publikace proběhla běžným formulářem Administrátor baru
v obnovené relaci vlastníka s důvodem jeho výslovného schválení dne 6. 11. 2026.
Původní pracovní kontext této session byl Správa baru a nebyl přepínán; existující uživatelské záložky nebyly měněny.

Veřejná adresa: <https://kis.kovopraha.cz/bar/menu.php>. Administrace:
<https://kis.kovopraha.cz/bar/offers.php?id=1>. Staré <https://bar.kovopraha.cz/menu/>
se přesměrovává na stejnou novou nabídku.

## Živé ověření

| Kontrola | Skutečný výsledek | Stav |
| --- | --- | --- |
| Uložená publikace | Nabídka 1, vydání 1, název Bar KOVO – základní nabídka, fotografie; formulář zachoval 45 vybraných produktů | PASS |
| Veřejná obrazovka ve skutečném Chrome | Název, aktuální popisy/ceny a fotografie; obrazovka již nehlásí přípravu | PASS |
| Nezávislé anonymní HTTP bez session/cookies | HTTP 200, správný název, přesně 45 produktových článků a 45 fotografií | PASS |
| Všechny fotografie této nabídky | 45/45 HTTP 200, neprázdný obrazový obsah, velikosti a SHA-256 zaznamenané | PASS |
| Stará adresa menu | Přesměrování na KIS, HTTP 200, stejný název nabídky | PASS |
| Ochrana administrace, pokladny a tiskového PDF | Každá bez přihlášení HTTP 302 na login; veřejné zpřístupnění těchto cest nevzniklo | PASS |
| Přihlášená pokladna pouze čtením | „Před prodejem otevřete vlastní kasu.“; nevznikla testovací kasa/prodej | PASS v tomto rozsahu |
| Skutečné vytvoření tiskového PDF v přihlášené relaci | V této publikační etapě neprováděno; ověřena pouze jeho anonymní ochrana | NOT RUN |

[Strojový souhrn](public-http.json) je nezávislé čtení bez přihlášení ze **6. 11. 2026 11:53:48 CET**
(10:53:48 UTC); nejde o přesný databázový čas zápisu publikace. Veřejné menu zachovalo
`Cache-Control: no-store` a `X-Robots-Tag: noindex, nofollow`.

- [Uložené vydání 1 v administraci](admin-published.png).
- [Skutečně zobrazené veřejné menu s fotografiemi](public-menu.png).

## Rozsah změny a pokračování

Zveřejnění mění aktivní výběr menu a pokladny. Ceny, popisy a příznak vyprodání se dále čtou
z aktuálního katalogu; nejde o zmrazený ceník. Změna nevytváří skladový příjem, hotovostní vklad,
prodej, vlastní spotřebu, platební potvrzení ani účetní zápis. Aplikační kód se zde nenasazoval.

Skutečný login Lucky, fyzické zásoby, vlastní kasa a první reálný prodej/uzávěrka,
reálné bezhotovostní platby a účetní propojení zůstávají podle konkrétního pořadí
v [session handoffu](../../SESSION_HANDOFF_BAR_2026-11-06.md). Původní technická
přejímka na syntetických datech není nahrazena tvrzením o ostrém finančním UAT.

## Souborová dokumentace a kontrola

Aktualizované jsou kanonický provozní stav, hlavní i datovaný session handoff, plán začlenění,
provozní připravenost/přejímka, přenosový runbook, návaznost účetního adaptéru a dokumentační rozcestník.
Starší výsledky zachovávají historický stav a odkazují na tuto publikaci.
[Trvalé pravidlo vlastníka](../../WORK_DOCUMENTATION_POLICY.md) je zapsané přímo v repozitáři
a propojené s kořenovým AGENTS.md a CLAUDE.md. Průběžná práce i plánované etapy mají zůstat v souborech s odkazy.

`php tools/check-current-docs.php` PASS: CURRENT_STATE 80 řádků, archivované bloky beze změny,
žádný chybějící odkaz. Dodatečně ověřené místní odkazy všech nových/změněných Markdown souborů bez chyby.
`CurrentDocumentationContractTest`: **2 testy / 14 assertions PASS**, `git diff --check` PASS.
Větev neobsahuje změnu aplikačního PHP/JS, migrace ani workflow. Plné automatické brány ověřuje související PR;
výsledky provozního prohlížeče a anonymního HTTP jsou samostatné důkazy výše. Předání a aktuální CI:
[PR #335](https://github.com/KovoPraha/evidenceTreninku/pull/335). Souběžný handoff upozornění #333,
jeho aktuální vydání i všech 104 historických bloků zůstaly při zapracování main zachované.
