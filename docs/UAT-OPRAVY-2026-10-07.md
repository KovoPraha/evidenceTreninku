# Opravy podle zpětné vazby testerů z 7. 10. 2026

Stav první vlny: implementováno, sloučeno do `main` a technicky nasazeno na produkci.

Implementační pull request je
[#34](https://github.com/KovoPraha/evidenceTreninku/pull/34). Produkční commit
po následných opravách workeru je `623e161e0b2d7eefeffe6d87d4586926265ba02a`.

## Druhá vlna oprav podle dokumentu testera

Stav této části: implementováno ve zdrojovém kódu a automaticky ověřeno.
Přesný stav GitHubu a produkčního nasazení se eviduje v
[`CURRENT_STATE.md`](CURRENT_STATE.md), nikoli odhadem v tomto funkčním souhrnu.

- potvrzení akce a současné otevření přihlašování už nepřeteče přes délku
  auditní akce; nová migrace rozšiřuje sloupec na 64 znaků a aplikační vrstva
  délku před zápisem kontroluje;
- založení plánovaného tréninku používá vždy viditelnou volbu mezi soupiskami
  a interním tréninkem bez účastníků;
- výpis všech výkazů bezpečně pracuje s přísným SQL režimem, správně odkazuje
  na trénink, počítá kanonická měření a při chybě zobrazí referenční kód;
- měsíční plány příspěvků jsou přímo dostupné ze správy jednotlivých předpisů
  a výjimku lze přidat jen členovi s platným členstvím v soupisce plánu;
- kontrast záhlaví ve správě odměn a kreditních období už nezávisí na
  přepsání barev Bootstrapu;
- rychlá přihláška nejprve rozliší dítě a dospělého; dospělý zadává jméno jen
  jednou;
- košík před objednávkou vysvětlí, která událost nebo program už není
  dostupný, a umožní položku odebrat bez ztráty zbytku košíku;
- názvy v menu e-shopu obsahují cestu rodičovské kategorie, takže dvě různé
  kategorie „Dětské“ nejsou zaměnitelné.

Databázová migrace této vlny je
`20261007130000_club_event_admin_action_width.php`. Kompletní lokální sada po
změnách: 838 testů / 11 740 kontrol, bez chyby. Toto ověření samo o sobě není
důkazem nasazení ani produkčního UAT.

## Změny pro uživatele v první vlně

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
