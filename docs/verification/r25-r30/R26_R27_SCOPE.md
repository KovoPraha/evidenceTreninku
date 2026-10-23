# R26–R27: skutečná docházka, plánování a náhrady

Ověřeno 23. 10. 2026 pouze v izolovaném prostředí `127.0.0.1:18086`, MariaDB `127.0.0.1:33426`. Produkce, nasazení, odesílání zpráv a plánovače nebyly aktivovány.

## Implementace

- `training_closure_admin.php`: uzavření po skutečném konci, potvrzení skutečných osob a trenérů, skutečná délka, podklad trenér/papír, zdůvodněná verzovaná oprava a zneplatnění. RSVP ani očekávaná soupiska nevytváří docházku. Neuzavřená lekce nevytváří hromadnou absenci.
- `training_closure_revisions`: neměnná verze času, účastníků, trenérů, původu, důvodu a autora. Aktuální ukazatel s kontrolou očekávané verze zabraňuje přepsání souběžného zápisu. Původní verze jsou dostupné historii a podkladům R30.
- Oprava spadající do uzavřeného či vyplaceného období sportovce vytváří `training_closure_reviews`; původní sazby, počty, částky a příznaky výplaty se nepřepisují. Platí i pro první uzavření, které odstraní původní raw účastníka.
- Legacy úprava/smazání a inline poznámka nemohou obejít uzavření. Navázaný nebo měřený trénink nelze smazat bez historie. Přímá URL i změna docházky kontroluje aktuální pracovní roli, soupisky a osoby v docházce i měřeních.
- Plánovač, opakování, kopie týdne a AJAX přesun sdílejí serializovanou kontrolu kolizí trenéra, sportoviště a průniku skupin/soupisek. Společná lekce se eviduje jedním termínem s více soupiskami. Vlastní doložená rezervace sportoviště neblokuje změnu názvu; změna jejího termínu vyžaduje storno a propojení nového termínu.
- Zrušení/přesun zachovává původní plán, důvod, autora a explicitní odkaz na náhradu. Rezervované náhrady se uvolní. Oznámení se pouze zařadí do existující fronty s kontrolou platné rodinné vazby před doručením; chybějící elektronický kontakt vytvoří viditelnou položku k osobnímu předání.
- Samostatná správa sportoviště nemůže vedlejší cestou měnit termín navázané lekce. Nová lekce se zakládá kanonickým plánovačem; samostatné rezervace nadále spravuje dosavadní oprávněná pozice.
- Nárok i spotřebování náhrady vyžaduje aktuální uzavřenou lekci po skutečném konci. Zámky mají pořadí skutečný trénink → plán; stav uzavření, účast a rezervace se po čekání čtou z aktuálních uzamčených řádků. Oprava nároku současně rezervovaného do jiné lekce nemůže přepsat rezervaci na revoked.
- Osobní omluva uchovává skutečný čas přijetí, způsob, autora a samostatný čas přepisu. Lze přepsat doloženou včasnou omluvu po proběhlé lekci. Přijetí se nedá přepsat opakováním formuláře a pozdní omluva neobchází lhůtu programu. Bez uzavřené docházky nevznikne kredit.

## Kontrakt pro R30

`trainingClosedActivitiesForPerson(PDO, personId, from, to)` vrací pouze aktuální uzavřené verze: `training_id`, `revision_id`, `version`, `actual_date`, `actual_started_at`, `actual_ended_at`, `duration_minutes`, `participant_ids`, `coach_ids`, `closed_at`, `closed_by` a původ podkladu. Oprávnění k osobě kontroluje volající. Rozepsané a zneplatněné lekce se nepočítají. Samotné uzavření nepředstavuje schválení odměny ani její úhradu.

## Důkazy

- `r26-r27-http.json`: 25 HTTP přejímacích kontrol, včetně CSRF, pracovních pozic, cizího měření na přímé URL, dvojího zápisu, zachování zaplaceného podkladu, AJAX přesunu, historie náhradního termínu a nedoručené osobní zprávy.
- `r26-r27-mariadb-race.json`: 12 kontrol ve čtyřech scénářích se dvěma skutečnými PHP procesy. Před uvolněním bariéry bylo u obou procesů ověřeno `INNODB_TRX LOCK WAIT`. Současné uzavření, kolize v rozvrhu a poslední náhradní místo mají právě jednoho vítěze. Čekající finalizace po zneplatnění lekce nespotřebuje nárok ani nepřepíše zrušenou rezervaci.
- Cílená sada `TrainingClosureTest`, `ClubMakeupTest`, `TrainingRsvpTest`, `SharedUiShellTest`: **44 testů / 257 assertions PASS**.

## Provozní hranice

Nebyly hromadně uzavřeny staré tréninky ani aktivovány náhrady pro skutečné programy. Platná kapacita nabídky, kapacita náhradní lekce a rezervace sportoviště zůstávají samostatné údaje. Konkrétní programy používající náhrady, jejich lhůty, případné výjimky a odpovědné osoby musí potvrdit oddíl před provozním pilotem. Automatické připsání sportovní odměny, sazby a schválení/výplata patří do R31–R34; tato etapa dodává neměnný skutečný podklad a upozornění na jeho opravy.
