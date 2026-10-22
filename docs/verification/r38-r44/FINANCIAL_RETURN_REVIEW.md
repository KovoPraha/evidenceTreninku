# Finanční návraty a historické podklady — závěrečná revize 23. 10. 2026

Opravené nezávisle reprodukované chyby:

- Účetní přehled porovnával objednávku `order` s kanonickým přídělem `shop_order`; nyní zobrazuje skutečně přiřazenou platbu (oprava vlastníka exportu).
- Platný vyplacený nárok dříve nepřijal vrácenou bankovní platbu, pokud se nezměnila schválená částka. Výdaje i odměny nyní mohou přiřadit doložený skutečný příjem ke konkrétní původní výplatě. Částečný i plný návrat obnoví závazek, který lze znovu vyplatit. Původní schválení ani historický výdaj se nepřepisují.
- Pozdě zaevidované výdaje, zálohy a verze odměn s dříve datovanou skutečnou úhradou se neztrácejí z podkladů exportu. Mají `late_recorded=true` a výjimku ke kontrole; pozdější schválení se nevydává za historické schválení.

Migrace `20261023100000_club_payment_returns` přidává pouze tabulku vazeb původního a vratkového přídělu. Peníze zůstávají výhradně v kanonické knize. Existující historické vratky přeplatků bez konkrétní původní výplaty konzervativně snižují dostupnou částku nového návratu; systém si původní výplatu nedomýšlí. Nové výplaty vyžadují aktuálně schválený podklad; skutečný návrat odměny lze doložit i při následně změněném sportovním podkladu. Původní uzavřená finanční evidence a uzavřené období zůstávají chráněné.

Původní výplata musí patřit témuž nároku a měně, být kladným přídělem skutečného výdaje a předcházet návratu. Příjem a datum musí souhlasit. Idempotentní opakování vrací původní výsledek; změna původního přídělu pod stejným klíčem odmítne. Součet návratů nikdy nepřesáhne nevrácenou část původní výplaty. Souběžné operace se serializují kanonickým finančním mutexem. Uvolnění přídělu není vrácení peněz.

## Ověření finálního kódu

- `ClubExpensesTest` + `ClubRewardsTest`: **30 testů / 251 assertions PASS**. Částečný a plný návrat, opakování, překročení, další výplata, chybný původ/směr, uzavřené období a pozdě evidované podklady bez zpětného schválení.
- `expense-return-http.json`: **47 skutečných HTTP scénářů PASS**, včetně návratů a nové výplaty, CSRF, práv a uzavřeného případu.
- `reward-return-http.json`: **25 skutečných HTTP scénářů PASS**, včetně návratů a nové výplaty, CSRF, odděleného schválení a odvolané role.
- `expense-return-race.json`: **29 kontrol PASS**, schéma `evidence_r31_expenses_5ab64eca`. Reálný `INNODB_TRX LOCK WAIT` také pro soupeřící návrat; překročení původní výplaty odmítnuto po uvolnění zámku.
- `reward-return-race.json`: **12 kontrol PASS**, schéma `evidence_reward_race_93c4ab49`. U výplaty, opakování, překryvu i návratu oba soupeři prokazatelně čekali na zámek. U dvou návratů po 70 Kč z původních 100 Kč uspěl právě jeden.
- `ClubMigrationFinanceTest`: **11 testů / 103 assertions PASS** po finálních změnách služeb.
- `finance-mariadb.json`: **21 kontrol PASS**, schéma `evidence_migration_test_1b2ff990`, včetně skutečných účetních a skladových počátků a dvou pozorovaných závodů `LOCK WAIT`.

Vše proběhlo v označeném syntetickém B1 na 127.0.0.1:33426 / HTTP 18086; žádná produkční změna, nasazení ani odeslání externí služby. Úspěšné technické ověření není potvrzením skutečných migračních dat ani převzetím účetní.
