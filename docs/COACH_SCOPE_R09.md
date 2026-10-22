# R09: pracovní role a rozsah trenéra

Lokální implementace 22. 10. 2026. Produkce není změněna. Přidělení skutečným trenérům se neodvozuje z historie; před případným nasazením je musí vedoucí sportu výslovně připravit a ověřit v pilotu.

## Matice role × akce × rozsah

| Aktivní pozice / identita | Akce | Datový rozsah |
|---|---|---|
| Trenér | Hledání a sportovní karta osoby, měření a přílohy zátěžových testů | Osoba v aktuálně výslovně přidělené podskupině nebo přímé aktuální vazbě na přidělenou soupisku. |
| Trenér | Trénink, jeho kopie, úprava, smazání a měření | Vlastní trénink; všechny zaznamenané osoby stále v jeho aktuálním rozsahu. Nově podané osoby/měření i cílové soupisky jsou kontrolované na serveru. |
| Trenér | Plánování, příprava tréninku, odpovědi a náhradníci | Vlastní plán, výslovně přidělené cílové soupisky včetně všech potomků při skupinovém výběru; všechny zobrazené osoby v aktuálním rozsahu. Samotné vytvoření plánu právo nepřiděluje. |
| Trenér | Přidělení rozsahu sobě či druhému trenérovi | Zakázáno, i při podvrženém autorovi nebo přímém POST. |
| Trenér | Úprava identity / kontaktů, účetnictví, odměny, hromadný export | Kanonická úprava identity patří registráři; účetní agendy hospodáři. Starý neauditovaný zápis profilu ze sportovní karty je odmítnut. Sportovní karta ani pracovní náhled veřejného profilu nezobrazují finanční odměny. |
| Vedoucí sportu | Přidělit nebo okamžitě ukončit rozsah | Aktivní pracovník s přidělenou pozicí trenéra; konkrétní soupiska/podskupina, interval a povinný důvod. |
| Vedoucí sportu | Sportovní přehledy a kontrola | Celý sportovní provoz podle centrální matice tras. Oprávnění se neslévají s účetnictvím. |
| Registrář | Správa osob a audit identity | Kmenové osoby, vztahy, členství a zdrojové mapy. Sdílený pomocný predikát neobchází centrální oprávnění konkrétní trasy. |
| Hospodář | Účetní agendy | Centrálně přidělené finanční trasy. Pozice sama nezískává trenérský rozsah. |
| Rodič / vlastní účet / sportovní účet | Vlastní rodinné údaje | Stávající aktivní a ověřené vztahy nebo samostatný sportovní účet; pracovní oprávnění je nenahrazuje. Ukončení zaměstnanecké role neruší legitimní rodičovství. |
| Držitel odkazu `training_read` | Čtení omezeného profilu | Vybraná osoba a platnost odkazu. Odkaz nezakládá pracovní rozsah, možnost zápisu ani čtení odměn. |

## Přidělení a ukončení

Administrace `coach_scope_admin.php` zapisuje `coach_scope_grants` a neměnnou historii `coach_scope_events`. Přidělení obsahuje trenéra, typ a ID cíle, začátek, volitelný konec, autora a důvod. Konec intervalu je výlučný okamžik. Překrývající se přidělení stejného rozsahu je odmítnuto; navazující intervaly jsou možné. Okamžité ukončení se zapisuje odděleně, původní interval se nepřepisuje a opakovaný požadavek nevytvoří druhé ukončení.

`team` znamená přímé členy konkrétní soupisky, nikoli automaticky celý podstrom. Další soupisky se přidělují výslovně. `subgroup` odkazuje na již existující původní podskupinu. Ani jeden typ nezakládá novou evidenci osob. Historický trénink, příjmení, kontaktní e-mail a přítomnost na tréninku nepřidělují přístup.

Kontrola znovu posuzuje aktivitu pracovníka, přidělenou trenérskou pozici, platnost a ukončení rozsahu a současné zařazení osoby. Při chybějící tabulce přidělení nedostane trenér automaticky celý klub. Při chybě auditu se změna vrací transakcí.

Při zápisu tréninku a testu se právo ověřuje před zpracováním souborů a znovu uvnitř transakce. MariaDB zamyká řádek trenéra; přidělení a odvolání používají stejný zámek. Tím se změna rozsahu a následný zápis seřadí; sekvenční HTTP test sám není důkaz souběhu.

## Historie a přístupy

Ukončení rozsahu nemaže tréninky, docházku, měření, účty ani finanční podklady. Historický vlastní trénink s osobou mimo aktuální rozsah se trenérovi nezobrazí v přehledu a nelze jej otevřít/kopírovat/upravit/smazat přes přímé ID. Vedoucí sportu může historii zkontrolovat v příslušné pozici. Odvolání se znovu ověřuje také při každém použití již vytvořené pracovní profilové session a při stažení přílohy.

Starší celoklubové reporty jsou ve společné matici přesunuté pod vedoucího sportu. Našeptávač výběru soupisek vrací názvy struktur, nikoli seznam osob, a trenérovi nabízí pouze přidělené cíle; následný zápis cíle má samostatnou kontrolu rozsahu. Výběr celé skupiny vyžaduje přidělení každé zahrnuté soupisky k datu každého termínu série. „Moje skupiny“ ukazují výslovná přidělení, ne skupiny odhadnuté z počtu historických tréninků.

Plánování i změny náhradnických slotů ověřují rozsah uvnitř transakce se stejným zámkem trenéra. Přímý GET odpovědí, editace plánu a předvyplnění docházky kontroluje také historický snímek účastníků, aktuální výběr k datu plánu, odpovědi a náhradníky. Plán s osobou, která do aktuálního rozsahu dosud nevstoupila nebo jej už opustila, musí obsloužit vedoucí sportu. Starý plán s pouhou skupinou bez konkrétní podskupiny/soupisky vyžaduje jeho ověření; vlastnictví plánu ani znalost ID tuto kontrolu neobchází.

## Důkazy

- `CoachScopeTest`: chybějící/future/ukončené přidělení, podskupina i soupiska, datum členství, odebraná pracovní pozice, deaktivace, oddělení finanční pozice, podvržený autor, překryvy, auditní rollback, pracovní profilová session a měření cizí osoby.
- `PublicProfileAccessTokenTest`: platný explicitní trenérský rozsah a skutečná syntetická osoba před změnou pracovní identity; session po změně zaniká.
- Společná HTTP přejímka: žádné přidělení → 403; přidělení vedoucím → vlastní osoba 200/cizí 403; omezený AJAX výsledek; zákaz finančních/exportních a starých celoklubových tras; podvržený trénink bez zápisu; odvolaný rozsah zneplatní soubor i uložený profilový náhled.
- `tests/Support/CoachScopeMariaDbRace.php`: skutečné souběžné PHP procesy a MariaDB na výslovně ověřeném izolovaném serveru `127.0.0.1:33426`, vlastní syntetická databáze `evidence_b2_coach_scope`. Devět kontrol prošlo: odvolání po prvotním ověření odmítne zápis uvnitř transakce bez vedlejšího účinku; při opačném pořadí byl přímo pozorován stav `LOCK WAIT` odvolávající transakce, povolený zápis se dokončil jako první, poté odvolání a audit. Další požadavek již přístup nemá.

Při závěrečném opakování testu souběhu byla opravena synchronizace testovacích procesů: bariéry nyní zveřejňují kompletní obsah atomicky, ověřují ID spojení a pozorování respektuje obnovu diagnostického snímku InnoDB. Následně prošlo šest po sobě jdoucích běhů se skutečně pozorovaným `LOCK WAIT`; požadavek na důkaz zámku zůstal zachován.

Změna není migrací reálných přidělení ani potvrzením produkčního provozu. Rozsáhlé úpravy pravidel nominací, sportovní uzávěrky a samotné odměny zůstávají v navazujících úkolech realizačního plánu.
