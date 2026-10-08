# UX navigace, kalendáře a pracovní přehledy

Aktualizováno: 8. 10. 2026, Europe/Prague

Tento dokument popisuje sjednocení veřejné navigace, e-shopu, kalendářů a
prioritních přehledů. Datové modely, oprávnění a finanční životní cykly změna
nemění.

## Veřejná navigace

- Původní samostatné položky **Akce** a **Kalendář klubových akcí** jsou
  sloučené do položky **Akce a kalendář**.
- Stránka klubových akcí nabízí dvě zřetelné cesty: měsíční kalendář a seznam
  akcí k přihlášení.
- Přihlášení, registrace i návrat po odeslání formuláře zachovávají konkrétní
  akci pomocí kotvy `#akce-ID`.
- Veřejná data a časy používají jednotný český formát.

## E-shop

- Kategorie zveřejněné administrátorem pomocí `visible_in_menu` se zobrazují
  hierarchicky: kořenové kategorie jsou v hlavní liště a podkategorie v
  rozbalovací nabídce.
- Skrytý nadřazený uzel se znovu nezveřejní odkazem **Vše** jen proto, že je
  zveřejněná některá jeho podkategorie.
- Domovská stránka e-shopu nabízí dlaždice kořenových kategorií, vyhledávání,
  řazení a stránkování po 12 produktech.
- Nepřihlášenému zákazníkovi katalog nezmenšuje prázdný postranní košík. Nákup
  bez účtu a přihlášení jsou vysvětlené jedním stručným informačním blokem.
- Přihlášenému zákazníkovi zůstává košík viditelný vedle katalogu.
- Detail produktu používá drobečkovou navigaci a obrázek ukotvený u horního
  okraje obsahu. U programových variant se v kontaktním formuláři zobrazuje
  lidský název období a termín, ne interní SKU.

## Kalendáře

- `includes/calendar_ui.php` je společná přístupná měsíční mřížka pro veřejné
  tréninky a klubové akce.
- Tréninky lze zobrazit jako kalendář nebo jako seznam.
- Doplňující údaje jsou dostupné přes Bootstrap popover při najetí i zaměření
  klávesnicí; zásadní informace však zůstávají přímo v kartě nebo seznamu.
- `includes/ui_format.php` centralizuje české datumy, časy, rozsahy a názvy dnů
  a měsíců.

## Přehled rodiče a trenéra

- Sportovní přehled rodiny začíná počtem tréninků čekajících na odpověď,
  položek programu, schválených profilů a neuhrazených předpisů.
- Kotvová navigace vede přímo na odpovědi, program, profily, platby a nastavení.
- Podrobné profily, týdenní souhrn, roční přehled, soukromý kalendář a
  připomínky plateb jsou sbalitelné. Po chybě nebo úspěšném uložení se
  odpovídající část automaticky otevře.
- Pracovní rozcestník trenéra zobrazuje dnešní tréninky, rychlé založení
  evidence, plánovač, proběhlé tréninky bez evidence a rezervace čekající na
  potvrzení.

## Ověření a provozní hranice

- PHP syntaxe změněných souborů: bez chyby.
- `git diff --check`: bez chyby.
- Lokální PHPUnit: **875 testů / 11 913 kontrol**.
- Lokální vizuální průchod nad reálnými daty nebylo možné dokončit, protože
  nakonfigurovaná vývojová MariaDB na portu 3308 nebyla spuštěná. Databáze ani
  migrace se kvůli vizuální kontrole svévolně neměnily.
- Pull request [#53](https://github.com/KovoPraha/evidenceTreninku/pull/53) byl
  sloučen jako `134fcb037bbfc62c13ca5df36695407d8d783f65`; CI
  [37778040472](https://github.com/KovoPraha/evidenceTreninku/actions/runs/37778040472)
  a CodeQL
  [37778041269](https://github.com/KovoPraha/evidenceTreninku/actions/runs/37778041269)
  prošly.
- Produkční deploy
  [37778058404](https://github.com/KovoPraha/evidenceTreninku/actions/runs/37778058404)
  skončil úspěšně a nasadil přesně commit `134fcb0`.
- Následný průchod v prohlížeči ověřil veřejný e-shop, detail programu 247,
  veřejný rozvrh tréninků a klubový kalendář. Nová navigace, mřížky, termíny a
  názvy variant jsou zobrazené a konzole na kontrolovaných stránkách nehlásila
  chyby ani varování.
- Přihlášené rodičovské a trenérské přehledy nebyly po tomto deployi zapisově
  retestované, protože release byl nasazen s `uat_schvaleno=false`.
