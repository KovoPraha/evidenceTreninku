# R33–R34: výkazy a odměny — lokální implementace

23. 10. 2026, izolovaný checkout a syntetický runtime B1. Bez nasazení, skutečných výplat a produkčních změn.

## Dokončený rozsah

- `club_rewards_admin.php` je společný pracovní postup: trenér předkládá vlastní výkaz; vedoucí sportu zadává doložená pravidla, předkládá sportovce a nezávisle posuzuje verze; hospodář přiřazuje již existující skutečný peněžní pohyb.
- Výslovná sazba, jednotka, časová účinnost, rozhodnutí/smluvní podklad a účetní metodika jsou povinné. Neexistuje implicitní sazba ani mzdový/daňový výpočet. Změna sazby znamená nové neměnné pravidlo a výslovné předložení; při změně účinnosti uvnitř období se období rozdělí.
- Tréninkový podklad pochází pouze z uzavřené skutečné docházky R26. Trenérské další činnosti jsou předložený podklad vyžadující věcné posouzení. Částky jsou celočíselné haléře; hodinové položky se zaokrouhlují na haléř u každé položky.
- Každá verze uchovává podklady, sazbu, příjemce a oprávnění převzetí, částku, autora a důvod. Překrývající se období jednoho člověka jsou zakázána. Příjemce se vybírá explicitním typovaným identifikátorem; u dítěte je povinný podklad oprávnění a způsob převzetí, rodič se nehádá.
- Schválení nesmí provést předkladatel, odměňovaný trenér ani trenérský příjemce. Shoda jména nebo e-mailu schvalovatele s osobou sportovce/příjemce vyžádá jiného vedoucího; neslučuje identity. Schválení je neměnné, další změna jde opravnou verzí.
- Výplata používá `accounting_movements` a `accounting_allocations` s případem `reward_claim`. Nevytváří nový bankovní pohyb, odeslání bance ani duplicitní peněžní deník. Podporuje částečné výplaty a více nároků v jednom doloženém pohybu. Skutečně vrácený přeplatek po nižší schválené opravě se přiřazuje ze skutečného kladného příjmu; pouhé uvolnění alokace není vratka.
- Změna zdrojové činnosti je vidět v pracovním přehledu a exportu a blokuje další výplatu do nové verze/schválení. Staré schválení ani již evidovaná výplata se nepřepočítají. Současné změny jsou serializovány účetním mutexem a zamykáním čtených zdrojů/oprávnění.
- Starší zápisy sazby/uzavření/příznaku `vyplaceno` po dostupnosti nové evidence odmítají finanční mutaci a odkazují na novou agendu. Historická uzavřená či vyplacená období blokují nové překrývající se nároky a vyžadují ověření migrace. Chybějící historický souhrn se nepřepočítává.
- Přidání/smazání další činnosti v předloženém období blokuje společný guard; po vrácení k doplnění lze podklad opravit a předložit novou verzi. Původní snapshot zůstane.
- `clubRewardExportRows()` poskytuje samostatné nároky/verze/rozhodnutí/vypořádání/pravidla. Schválení a stav verze mají časový cutoff; novější schválení se nevydává za již známé ve starém měsíci. Peníze zůstávají pouze v kanonické evidenci.

## Ověření

- `tests/Integration/ClubRewardsTest.php`: **15 testů, 104 assertions PASS** (retry, změna podkladů, částečná platba, přeplatek a skutečná vratka, historický cutoff, vlastní schválení, role, překryv, chybějící metodika, obcházení společného ledgeru, vrácení další činnosti).
- `tests/Support/ClubRewardsHttpAcceptance.php`: **20 HTTP kontrol PASS**, skutečný localhost 18086/MariaDB 33426. Zahrnuje formuláře s typovaným výběrem osoby, CSRF, omezení rolí, starý delete writer, částečnou výplatu, idempotenci, přeplacení a zrušenou roli.
- `tests/Support/ClubRewardsMariaDbRace.php`: **9 kontrol / 3 souběžné scénáře PASS**. Vždy pozorovány dvě skutečné MariaDB transakce v `LOCK WAIT` před uvolněním bariéry: dvě výplaty proti jednomu saldu, totožný retry a překrývající se nároky.
- Důkazy: `docs/verification/r31-r37/rewards-http.json`, `rewards-race.json`. Samostatná syntetická schémata se nemažou a neobsahují reálné údaje.

## Odložené věcné rozhodnutí

Vedení a účetní musí dodat skutečné smluvní režimy, sazby, metodiku a oprávnění příjemců. Technika jejich doložení vyžaduje a nic nepředvyplňuje jako schválené. Přejímka se skutečnou obsluhou/účetní, historické mapování a produkční přepnutí tímto nebyly provedeny.
