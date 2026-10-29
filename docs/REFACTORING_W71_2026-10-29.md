# W7.1a — cílené ověření rodinného přístupu

Zahájeno 29. 10. 2026 z `54013cdd25c0e77f6250c55e506e3e7dbaaafa36`,
větev `codex/refactoring-w71-family-reads-20261029`, izolované worktree mimo
htdocs. Vlastník autorizoval autonomní implementaci, merge a chráněný deploy
po úspěšném ověření. Cizí `kis_rosters_admin.php` a ostatní WIP zůstávají.

**Dokončeno a nasazeno 29. 10. 2026:** PR #225, release
`81544542bb40cb96e7141791ea22e4b382531d5e`,
[deploy 40930536923](https://github.com/KovoPraha/evidenceTreninku/actions/runs/40930536923).
[Produkční protokol](verification/refactoring-w71-release-20261029/RESULTS.md).
Linux 1 804 testů / 36 058 assertions, MariaDB 10.3/11.4, před/po 26/26 HTTP PASS.
U 20 osob SQL 24 → 4, nejvýše jeden datový řádek, stejné autorizační podmínky.
W7 jako celek zůstává otevřené; následuje W7.1b podle nálezu níže.

## První řez W7 a zjištění

`familyPortalCanViewPerson` volá seznam celé rodiny a hydratuje členství každé
osoby přes další SELECT. Boolean kontrola přitom potřebuje jedinou platnou
vazbu k požadované osobě. Používá ji rodinný přístup ke zdravotním údajům,
RSVP a náhradám. `familyPortalPersonOverview` a seznamy mají další kandidáty,
které tento malý bezpečnostně citlivý řez nebude slučovat do stejné změny.

Nejprve charakterizovat stávající přístup a změřit počet SQL/řádků/paměť/čas
pro 0/1/5/20 osob, oprávněný i cizí profil. Potom nahradit celé načítání
cíleným SELECT existence s LIMIT 1. Společný statický SQL fragment zachová
stejný JOIN a autorizační predikát pro seznam i kontrolu jedné osoby.
Bez cache, změn transakčních hranic, zápisových služeb nebo schématu.

## Bezpečnostní a výkonový kontrakt

- Aktivní a ověřený účet; pouze approved self/guardian; valid_from včetně
  hranice, valid_to výlučně; členství sportovce není oprávnění účtu.
- Cizí osoba, pending/revoked/odmítnuté vazby, payer/coach, neaktivní a
  neověřený účet, budoucí/ukončená vazba, chybějící tabulka a neplatné ID
  musí zachovat odmítnutí. Duplicity/kolace a osiřelé řádky zachovat podle JOIN.
- Každé volání čte DB znovu. Ověřit odvolání a změnu účtu na stejné PDO,
  rollback a revokaci z druhé connection před dalším čtením. Neměnit
  konzistenci již otevřeného DB snapshotu ani pořadí existujících zámků.
- Při existujícím schématu nejvýše čtyři SQL (tři metadata + jeden dotaz),
  nejvýše jeden datový řádek; bez SELECT členských/osobních polí.
- Seznam celé rodiny, role, pořadí a membership výstup beze změny.

## Brány a evidence

Charakterizace nejprve na původním kódu; rozpočet musí před úpravou selhat.
Syntetický profil použije všechny migrace, nativní prepared statements,
zapnuté FK a EXPLAIN; žádné produkční osobní údaje. Samostatné měření času
na běžném PDO, instrumentace SQL zvlášť. Testy SQLite a MariaDB 10.3/11.4,
plná sada, PHPStan bez nové baseline, styl, anonymous sweep, JS a dokumentace.
Teprve zelený PR a přesný main mohou být nasazeny. Před/po čtecí audit a HTTP
kontroly, záloha/migrační check a ověření živé identity. `uat_schvaleno=false`,
`importovat_krouzky=false`. Návrat kódem bez obnovy DB přes nové transakce.

Tento balík neuzavírá celé W7: plný rodinný přehled R02, žádosti R03 a seznamy
registráře R04 vyžadují další měření. Lidská přejímka ani aktivace služeb
neplyne z technického PASS.

## Navazující autorizační audit

Při čtení volajících byl nalezen kandidát pro samostatný race test:
`trainingRsvpSave` a rodinné rezervace/storna náhrad kontrolují oprávnění
před zahájením transakce a před čekáním na doménový zámek. Tento balík
zachovává stávající pořadí; ověření revokace mezi dvěma voláními není důkaz
revokace během čekání již běžícího writeru. W7.1b má nejprve reprodukovat
tuto situaci a podle výsledku upravit kontrolu uvnitř zápisové transakce.
Není to nově zavedená cache ani tvrzení o dokončeném auditu všech writerů.

[Měření a výsledky](verification/refactoring-w71-20261029/RESULTS.md).
