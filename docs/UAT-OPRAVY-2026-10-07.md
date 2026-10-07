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
změnách a následné opravě kontrastu: 840 testů / 11 751 kontrol, bez chyby.

Produkční browserový průchod navíc odhalil a uzavřel matoucí zobrazení
neomezené kapacity jako `0` volných míst ve zrychlené přihlášce. Oprava je v
[#39](https://github.com/KovoPraha/evidenceTreninku/pull/39) a produkční
prohlížeč nyní zobrazuje „Kapacita není omezena.“ Toto ověření samo o sobě není
důkazem úplného přihlášeného produkčního UAT.

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

- CI nad výsledným `main`: 840 testů / 11 751 kontrol;
- MariaDB 10.3 a 11.4 integrační smoke: úspěch;
- produkční deploy: [běh 37636084395](https://github.com/KovoPraha/evidenceTreninku/actions/runs/37636084395), úspěch;
- produkční databázové invarianty: [běh 37636671525](https://github.com/KovoPraha/evidenceTreninku/actions/runs/37636671525), `ok=true`, 16 kontrol, 0 porušení;
- produkční worker zpráv soupiskám po opravě hostingu:
  [běh 37606107788](https://github.com/KovoPraha/evidenceTreninku/actions/runs/37606107788), úspěch;
- veřejná domovská stránka a registrace: HTTP 200.

## Aktuální produkční stav

Následný produkční průchod odhalil ještě přepsání bílé barvy textu na dvou
tmavých kartách. Oprava je v
[#43](https://github.com/KovoPraha/evidenceTreninku/pull/43) a schválený release
z commitu `49c38c72c6704826182aff7f59855f47b5818bb8` nasadil běh
[`37636084395`](https://github.com/KovoPraha/evidenceTreninku/actions/runs/37636084395).
Záloha, migrace, aktivace i HTTP smoke prošly a prohlížeč na obou stránkách
naměřil bílý text `rgb(255, 255, 255)`.

Podrobný stav všech deseti bodů je v
[`PRODUKCNI-UZIVATELSKE-TESTOVANI.md`](PRODUKCNI-UZIVATELSKE-TESTOVANI.md).
Za úplně uzavřený se nadále nepovažuje skutečný bankovní převod, skutečné
doručení e-mailu ani produkční cyklus zastaralé položky košíku; nebyly provedeny
jen proto, aby test nevytvářel finanční nebo komunikační dopady.
