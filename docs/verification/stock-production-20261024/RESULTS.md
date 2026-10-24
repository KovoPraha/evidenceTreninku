# Nasazení skladových oprav a čtecí kontrola návazností

24. 10. 2026. Na výslovné zadání vlastníka nasazeny ověřené opravy PR #187.
Bez prohlížeče. Testovací obchodní a účetní zápisy pouze v izolovaných databázích
a dočasném soukromém úložišti. Žádné testovací objednávky, skladové pohyby,
doklady, platby, vratky ani exporty v produkci.

## Co bylo nasazeno

- Release **065ed2a6fb797e21928d0eb5d1d5b84af0c04d93**.
- [Chráněné nasazení 40085679569](https://github.com/KovoPraha/evidenceTreninku/actions/runs/40085679569): **SUCCESS**.
- Výdej respektuje úplné účetní vratky z příjmů propojených historií přídělů.
  Vratka s výslovným dokladem jiné objednávky neblokuje výdej jen kvůli společnému příjmu.
  [Rozbor oprav a původní testy](../stock-followup-20261024/RESULTS.md).
- Deploy: **1676 testů / 33403 assertions**, lint a kontrola závislostí PASS.
  Před aktivací ověřena záloha **330 tabulek**. Migrace **pending=[]**,
  zachování dosavadních variabilních symbolů **unchanged=true**.
- Soukromé úložiště a existující konfigurace zachovány; dočasná souborová sonda
  vytvořena a odstraněna. Žádné přesouvání příloh ani nové nastavení integrací.
- Import kroužků vypnut. HTTP smoke **200** přes standardní SSH/HTTP fallback
  po síťovém timeoutu runneru; nezávislý HTTP GET z pracovní stanice také **200**.
- Živý release vrátil stejné SHA i run ID. **8/8 otisků** skladových a účetních
  souborů odpovídá přesným bajtům Git blobů release: [důkaz](runtime-hash-proof.json).

## Co bylo zjištěno v produkci

[Databázový drill 40085999249](https://github.com/KovoPraha/evidenceTreninku/actions/runs/40085999249):
**16 základních kontrol PASS**, žádné přeskočené schema, [výstup](invariants.json).

[Skladový a účetní audit 40086062724](https://github.com/KovoPraha/evidenceTreninku/actions/runs/40086062724):
**20 skladových + 14 účetních kontrol PASS**, [úplné souhrnné výsledky](accounting-stock-audit.json).
Jedna konzistentní databázová transakce READ ONLY, bez aplikačního bootstrapu,
vytváření dokladů, exportů nebo událostí stažení. Pomocné skripty běžely mimo
webroot a jejich následné odstranění skončilo SUCCESS.

| Evidence | Skutečný počet |
| --- | ---: |
| Objednávky | 8 |
| Řízené skladové stavy, pohyby, rezervace a dávky | každá tabulka 0 |
| Výdejky a jejich položky | každá tabulka 0 |
| Vratkové případy a jejich finanční vazby | každá tabulka 0 |
| Skladové PDF doklady | 0 |
| Účetní doklady, peněžní pohyby a příděly | každá tabulka 0 |
| Účetní rozhodnutí a skutečné výplaty vratek | každá tabulka 0 |
| Účetní exportní balíky | 0 |
| Katalogové varianty zboží bez řízeného skladového stavu | 855 |

Poslední údaj zahrnuje všechny katalogové varianty zboží; neznamená 855 aktivně
prodávaných položek ani počet fyzických kusů. Starší katalogový údaj není
automaticky ověřený fyzický počáteční stav. Nic nebylo odhadem převáděno.

Kontroly zahrnují soulad stavů s pohyby, dávkami a rezervacemi, ocenění výdeje,
vazbu položek na objednávku, vrácení nejvýše vydaného množství, vazby vratky na
opravný doklad a skutečné výplaty před uzavřením, integritu PDF a exportů.
Prázdné tabulky ale **neprokazují skutečný produkční průchod**. V produkci nebylo
žádné skladové/účetní PDF ani ZIP k ověření; jejich testovací vytvoření nebylo provedeno.

Samostatné anonymní HTTP GET ověřily přístupové hranice: sklad, výdeje, vratky
a účetní exporty přesměrují na login; skladový doklad a soukromá metadata vracejí
403. [HTTP výsledky](http-checks.json). Žádné přihlášené stahování skutečných dokladů.

## Izolované ověření diagnostiky a celého procesu

[PR #188](https://github.com/KovoPraha/evidenceTreninku/pull/188) rozšířil opakovatelnou
čtecí diagnostiku; nejde o druhé nasazení aplikace. Diagnostický commit
`283c46db7b5d31dda26ae2faef63da944715aedc` je oddělený od výše uvedeného živého release.

- Lokálně **19 cílených testů / 480 assertions PASS**, PHP lint a diff check PASS.
- [CI 40085960624](https://github.com/KovoPraha/evidenceTreninku/actions/runs/40085960624):
  **1677 testů / 33411 assertions**, MariaDB **10.3 a 11.4 PASS**.
- [CodeQL 40085959013](https://github.com/KovoPraha/evidenceTreninku/actions/runs/40085959013): PASS.
- Nativní izolovaná MariaDB: rodinná objednávka se dvěma kurzy a zbožím → výdej
  → blokovaná fyzická vratka → posouzení → opravný doklad → skutečná výplata
  → skladové PDF v účetním ZIP. Účasti obou dětí zůstaly aktivní.
- Záměrně porušený skladový stav, množství výdeje, částka výplaty a obsah
  skladového PDF byly odhaleny. Po obnovení syntetických dat audit opět prošel.
  Počty vratkových událostí se čtecím auditem nezměnily.
- Testy odlišují chybějící schema od prázdné evidence; chybějící schema odmítnou.
  Nativní smoke stále vypisuje známé upozornění staré migrace na klíč `columns`,
  ale končí exit 0. Nasazení neměnilo tuto historickou migraci.

## Následující provozní krok

Technická oprava je nasazená a ověřená. Zprovoznění skutečného řízeného skladu
vyžaduje fyzickou inventuru, schválené počáteční stavy a převzetí otevřených
rezervací. Účetní metodika a lidské převzetí zůstávají samostatné kroky.
Po prvním skutečném výdeji/vratce lze zopakovat `overit-ucetni-vazby` a ověřit
už i existující provozní data a soubory. Žádná historická data ani počátky
nebyly kvůli této přejímce automaticky vytvořeny nebo opravovány.
