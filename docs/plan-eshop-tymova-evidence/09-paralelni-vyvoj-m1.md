# Paralelní vývoj M1

Stav: pracovní protokol řídicího tasku

## Zásada

Paralelní práce probíhá jen v samostatných Git worktrees a větvích vytvořených
ze stejného integračního SHA. Pracovní task nesmí editovat hlavní worktree ani
sdílené řídicí dokumenty. Výsledek vždy končí lokálním commitem; push, merge do
`main`, produkční deploy a produkční DB změny provádí pouze řídicí task po
integrační kontrole.

## První paralelní kolo

Společný base: `87f3ce3f039c53d7df44f488e44f5716c42e4d12`

| Proud | Branch | Worktree | Vlastněné povrchy |
|---|---|---|---|
| rodinný portál | `codex/m1-family-portal` | `C:\xampp\htdocs\evidencePavel-m1-family` | nový family helper/page, odkaz z `booking/moje_osoby.php`, vlastní testy |
| politiky soupisek | `codex/m1-roster-policies` | `C:\xampp\htdocs\evidencePavel-m1-rosters` | vlastní migrace, `includes/kis_roster.php`, `kis_rosters_admin.php`, vlastní testy/doc |
| příjemce shop položky | `codex/m1-shop-beneficiary` | `C:\xampp\htdocs\evidencePavel-m1-shop` | vlastní migrace, shop beneficiary/checkout helper, vlastní testy/doc |
| integrace | `main` | `C:\xampp\htdocs\evidencePavel` | řídicí dokumentace, backup/seed kontrakt, cherry-pick, společné portály a plná verifikace |

Pracovní proudy nesmějí měnit `bin/db-backup.php`, `bin/seed-local-demo.php`,
program board ani session handoff. Tyto sdílené soubory upraví integrace jednou
po přijetí všech migrací.

## Povinný prompt pracovního tasku

Každé zadání obsahuje:

1. absolutní cestu worktree, branch a base SHA,
2. jediný funkční výstup a co výslovně není součástí,
3. přesný seznam vlastněných a zakázaných souborů,
4. datový kontrakt a pravidla kompatibility,
5. bezpečnostní invariantu a akceptační scénáře,
6. požadované SQLite/MariaDB/test/lint důkazy,
7. zákaz push/deploy/produkční DB,
8. požadavek na lokální commit, SHA, seznam souborů, rizika a integrační pokyn.

Pokud se ukáže nutnost změnit nevlastněný soubor, pracovní task zastaví tuto
část a vrátí integrační požadavek. Nesmí si sám rozšířit rozsah.

## Přijetí výsledku

Řídicí task u každé větve provede:

1. kontrolu stavu worktree a jediného očekávaného commitu,
2. `git diff --check` a kontrolu seznamu souborů proti vlastnictví,
3. source review migrace, autorizace, transakcí a kompatibility,
4. cílené testy v pracovním worktree,
5. cherry-pick do `main` v určeném pořadí,
6. doplnění společných backup/seed/UI povrchů jedním integračním commitem,
7. migration check/apply pouze na localhostu,
8. plný PHPUnit, lint, dependency audit, MariaDB a browser průchod,
9. aktualizaci M1 scénářů, boardu a handoffu.

První merge order je:

1. rodinný portál – nemění schéma,
2. politiky soupisek – první nová migrace,
3. příjemce shop položky – druhá nová migrace,
4. integrační úpravy seed/backup/portálu.

Pořadí migrací je určeno jejich ID, nikoliv okamžikem dokončení pracovního tasku.

## Stop podmínky

- větev nezačíná na deklarovaném base SHA,
- pracovní task změnil nevlastněný soubor,
- dvě větve přidaly stejné ID migrace,
- změna vyžaduje přepis již aplikované migrace místo nové forward migrace,
- test vyžaduje produkční osobní údaje nebo externí volání,
- autorizace je založena jen na ID z URL,
- finanční nebo členský stav se mění bez auditu/transakce,
- pracovní strom obsahuje nevysvětlené změny,
- vznikl požadavek na push, deploy nebo produkční DB bez nového výslovného souhlasu.

## Druhé paralelní kolo

Společný base: `0d5e89c2760d0e657bc1da17fb5f7b83deb90f35`

| Proud | Branch | Přijatý commit | Výsledek |
|---|---|---|---|
| M1.3 tréninkový most | `codex/m1-training-bridge` | `acb5883` → `9f7e531` v `main` | M:N vazba plánů na soupisky a deduplikovaný snapshot očekávaných osob |
| M1.4 kroužkové programy | `codex/m1-club-programs` | `3e670f9` → `e6fad8e` v `main` | program, nabídka, účast, audit, kapacita a aktivace uhrazené položky |
| M1.6 cílení událostí | `codex/m1-event-rosters` | `cd0e014` → `218bfd3` v `main` | M:N cíle, transakční kontrola oprávnění a snapshot důvodu |
| integrace | `main` | následující integrační commit | seed, backup kontrakt, navigace, plné testy, MariaDB a browser smoke |

Všechny tři pracovní větve skončily čisté, bez push/deploy a bez změny produkční
databáze. Číslované migrace `130000`, `140000` a `150000` byly aplikovány jen na
localhost.

## Třetí paralelní kolo

Společný base: `6c3324f`

| Proud | Branch | Přijatý commit | Výsledek |
|---|---|---|---|
| M1.4 platební lifecycle | `codex/m14-program-lifecycle` | `15cd57b`, oprava zámků `589d79b` | paid aktivace, storno/refund, audit a sjednocené transakční pořadí |
| M1.5 rollover execution | `codex/m15-rollover-execution` | `8cf6774`, concurrency `94ab4a2` | fingerprint, výjimky, skutečný přesun, historie a idempotentní souběh |
| M1.7 veřejný velodrom | `codex/m17-public-velodrome` | `9d8cee5` | veřejný self profil a rezervace nad existujícími booking tabulkami |
| integrace | `main` | následující integrační commit | seed, backup kontrakt `.4`, navigace, plné testy, MariaDB a browser smoke |

Všechny migrace třetího kola byly aplikovány pouze na localhost. Produkce, push,
Fio ani Stripe se v tomto kole neměnily.

## Další vhodné paralelní kolo

Po přijetí třetího kola lze opět oddělit:

- M1.8 jednotný localhost rozcestník a akceptační scénáře A01–A10,
- dětský login a jeho omezený pohled bez rodičovských oprávnění,
- placený velodrom napojený na shop objednávku, QR a bankovní lifecycle.

Placený velodrom musí navázat na už stabilní společný profil a příjemce služby;
nový proud proto smí rozšířit sdílený checkout až po samostatné integrační kontrole
pořadí zámků, storna a refundace.
