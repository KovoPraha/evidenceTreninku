# PR #305 — ověření a nasazení 5. 11. 2026

Vlastník výslovně povolil review PR #305, při úspěchu merge, chráněné nasazení,
ověření produkce a aktualizaci dokumentace. Heslo, obnovovací odkaz a T07 vynechat.

1. Ověřit přesný head, aktuální main, všechny kontroly, objednávkový kontext,
   hranice formulářů a úplné stránkování; zopakovat související regresní sadu.
2. Pořídit čtecí produkční audit a anonymní HTTP před nasazením. Sloučit jen
   ověřený head, zkontrolovat shodu Git stromu a spustit chráněný deploy na main
   s NASADIT, uat_schvaleno=false a importovat_krouzky=false.
3. Ověřit přesné živé SHA, změněné soubory, schéma, přepínače, zachování VS,
   HTTP a dostupný přihlášený browser. Produkční ověření pouze čtecí,
   nevytvářet skutečné vratky, finanční ani skladové pohyby nebo nové přístupy.
4. Čerstvou zálohu ověřit skutečnou obnovou výhradně v izolované MariaDB runneru.
   Výsledky a omezení zapsat do protokolu, CURRENT_STATE a HANDOFF přes docs PR.

Nové workflow či změny integrací nejsou součástí vydání. Cizí WIP zachovat.

Dokončeno: review bez blokujícího nálezu, merge a chráněný deploy SUCCESS,
produkční audit/HTTP a dostupný přihlášený čtecí Chrome PASS, přesná čerstvá
záloha skutečně obnovena v izolaci. [Výsledky a omezení](RESULTS.md).
