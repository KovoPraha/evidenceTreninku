# Opravy podle zpětné vazby testerů z 7. 10. 2026

Stav: implementováno, sloučeno do `main` a technicky nasazeno na produkci.

Implementační pull request je
[#34](https://github.com/KovoPraha/evidenceTreninku/pull/34). Produkční commit
po následných opravách workeru je `623e161e0b2d7eefeffe6d87d4586926265ba02a`.

## Změny pro uživatele

- Registrace vysvětluje, že lze použít zapamatovatelnou delší frázi, a heslo je
  možné bezpečně zobrazit nebo skrýt.
- Správce při otevření přihlašování na klubovou akci dostane předletovou
  kontrolu chybějícího termínu a může akci potvrdit a otevřít řízeným postupem.
- Ruční založení sportovce je rozdělené do dvou jednodušších kroků.
- Při nedostupné platební bráně aplikace uvolní zámek relace, zobrazí bezpečnou
  chybu a umožní opakování nebo návrat k bankovní platbě.
- Administrátor může odebrat jednotlivou pracovní pozici a účet deaktivovat či
  znovu aktivovat; změna se zapisuje do auditu.
- Plánovaný trénink musí mít cílovou soupisku, nebo musí být výslovně označený
  jako interní trénink bez účastníků.
- Rodič nebo sportovec může potvrdit účast či neúčast na tréninku. Trenér má
  souhrn a detail odpovědí.
- Kopie týdne zachovává vazby plánů na soupisky.

## Databázová změna

Migrace `20261007120000_training_rsvps.php` přidává datový základ pro odpovědi
na účast na tréninku. Je součástí běžného migračního katalogu a byla aplikována
produkčním deploy workflow před aktivací release.

## Ověření

- CI nad výsledným `main`: 832 testů / 11 668 kontrol;
- MariaDB 10.3 a 11.4 integrační smoke: úspěch;
- produkční deploy: [běh 37605872735](https://github.com/KovoPraha/evidenceTreninku/actions/runs/37605872735), úspěch;
- produkční worker zpráv soupiskám po opravě hostingu:
  [běh 37606107788](https://github.com/KovoPraha/evidenceTreninku/actions/runs/37606107788), úspěch;
- veřejná domovská stránka a registrace: HTTP 200.

## Co tím není potvrzeno

Deploy byl uložen s `uat_schvaleno=false`. Automatické testy, migrace a HTTP
smoke proto nejsou vydávány za úplný přihlášený uživatelský průchod. Skutečné
e-maily, bankovní operace a všechny role se ověřují samostatně podle
[`PRODUKCNI-UZIVATELSKE-TESTOVANI.md`](PRODUKCNI-UZIVATELSKE-TESTOVANI.md).
