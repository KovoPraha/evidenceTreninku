# Příprava ostrých plateb Fio a SumUp — 5. 11. 2026

Vlastník požaduje Fio párování a SumUp přímo na produkci s ostrými daty,
bez provider sandboxu, s využitím zkušeností z Velocoty. Jde o nové výslovné
zadání k přípravě a aktivaci těchto integrací; starší zákaz aktivace se na tento
schválený rozsah nevztahuje. Heslo a T07 se nadále vynechávají.

1. Aktuální main, vlastní worktree, aktuální produkční přepínače, metadata secrets
   a provozní konfigurace. Hodnoty klíčů ani transakční osobní údaje neexportovat.
2. Porovnat kanonické Fio/SumUp cesty s Velocotou a aktuálními dokumenty providerů.
   Regresní testy běží offline na syntetických datech; nejde o provider sandbox.
3. Fio: čtecí ověření účtu, nejprve omezený import bez automatického potvrzování,
   kontrola postupu a návrhů, poté přesná automatika a plán. Žádné bankovní příkazy.
4. SumUp: před aktivací ověřit nový/potvrzený API klíč, správného obchodníka,
   CZK/online kartu, stav a detail skutečné transakce v callbacku, idempotenci
   a odmítnutí vratky/chargebacku. Nový klíč se předává přes production Secret.
5. Případné opravy provést přes testy, review a chráněné vydání; produkční
   konfiguraci měnit přes existující chráněná workflow se zálohou a read-back.
   Evidovat odděleně aktivaci, plánovaný běh a skutečně doloženou zaplacenou úhradu.
6. Skutečnou testovací platbu nebo vratku nepředstírat. Bez určeného plátce,
   částky a provedení finančního kroku nelze tvrdit živé finanční E2E PASS.

Výchozí nasazené SHA bc627ab1; aktuální main f7bf825d. Fio účet/token nyní
ověřený chráněným během 42000593900. Import/auto-confirm i SumUp byly předtím false.
Nový SumUp klíč čeká na upřesnění vlastníka; samotná hodnota nepatří do chatu.
