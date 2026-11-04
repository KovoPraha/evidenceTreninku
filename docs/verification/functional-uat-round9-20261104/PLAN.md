# Devátý autonomní funkční průchod

Výchozí main `5eb64c95e4b199fc339076524578e9a44efb3414`, větev
`codex/functional-uat-round9-20261104`, oddělené worktree stejného názvu.
Vlastník povolil další testování a opravy; merge a další nasazení nejsou součástí této vlny.
Heslo, použitý obnovovací odkaz a T07 vynechat.

1. Ověřit syntetickou MariaDB na 3322, vlastní Apache na 18422 a blokované externí transporty.
2. Prověřit rodinné týdenní souhrny: oddělené role, filtry/datum/stránkování,
   neměnný náhled, escapování, CSRF, idempotenci a odmítnutí neoprávněných akcí.
3. Na vlastních místních datech ověřit odhlášení odběru, změnu kontaktu/oprávnění,
   nejistý výsledek transportu a opakování bez automatického duplicitního odeslání.
4. Vybrané cesty skutečně projít dostupným prohlížečem; případnou chybu reprodukovat
   selhávajícím testem, opravit a znovu ověřit. Výsledky HTTP a browseru oddělit.
5. Uložit důkazy, doplnit stav dokumentace, spustit příslušné testy a předat PR se zeleným CI.
   Vypnout pouze vlastní pomocné služby; zachovat cizí WIP i syntetické důkazy.

Produkční přihlášení v dostupném IAB 4. 11. chybí (přesměrování na login).
Produkční přihlášené UAT se proto netvrdí. Žádná rozesílka, import ani změna integrací.

Stav: místní průchod dokončen, F09 reprodukována a opravena. [Výsledky](RESULTS.md)
obsahují 37 HTTP/model/persistence kontrol, 49 kontrol MariaDB kontraktu, cílené testy
34/365 a úplnou místní sadu 1986/38417 (3 Windows skipy). Prohlížeč ověřil vybrané
scénáře; závěrečných 16 tabulek beze změny. Vlastní pomocné procesy zastavené,
MariaDB doložila úplné vypnutí a pomocná karta je zavřená; [úklid](cleanup.json).
Výsledek CI se posuzuje pro přesný head předaného PR. Další krok je jeho samostatný
review a rozhodnutí vlastníka o merge/deploy; E8, lidská přejímka a ostré transporty
se touto vlnou neuzavírají.
