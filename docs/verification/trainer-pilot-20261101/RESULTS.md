# Oddělený trenérský pilot — založení a ověření

1. 11. 2026, Europe/Prague. **Samostatný účet a syntetická soupiska jsou založeny.**
Úplné interaktivní UAT ani doručení resetu na novou adresu zatím neproběhly.
Počáteční heslo je náhodné a neznámé; vlastník si jej nastaví samoobslužnou
[trenérskou obnovou](https://kis.kovopraha.cz/booking/zapomenute_heslo.php?pro=trener).
V tomto rozsahu nebyla odeslána žádná zpráva.

## Identita a oddělení

Dohledání přesných vlastníkem dodaných adres nalezlo pouze veřejné účty bez
explicitní pracovní vazby. Nebyly automaticky propojovány ani povyšovány.
Třetí schválená adresa byla volná; osobní výsledky zůstávají v soukromém
provozním dokladu, nikoli v Gitu. [Postup dohledání](../../TRAINER_OWNER_LOOKUP_2026-11-01.md).

Provozní režim používá chráněné main workflow a vlastní neaktivní technickou
auditní identitu, bez e-mailu, pozic, superadmina, přihlášení a obnovy hesla.
Autorizační marker obsahuje přesné workflow/ref, GitHub actor a run ID.
Stávajícím účtům se neměnila hesla, adresy ani oprávnění. Velocota se nepropojovala.

## Skutečné založení

- [Běh 41330611475](https://github.com/KovoPraha/evidenceTreninku/actions/runs/41330611475)
  SUCCESS, vytvoření i následné čtení PASS. [Doklad](pilot-created.json).
- Trenér **56**, soupiska **77**, jméno `TEST - Trenérský pilot 20261031`.
  Pouze pozice coach; osoby TEST Alfa a TEST Beta (11783, 11784) viditelné,
  TEST Mimo rozsah (11785) a všechny skutečné osoby mimo rozsah odmítnuty
  skutečným databázovým predikátem oprávnění. Nejde o ruční UI test pod novým účtem.
- Grant od `2026-11-01 18:17:31` do `2026-11-15 18:17:31` databázového času.
  Obnova hesla způsobilá, veřejný účet nevytvořen ani nepřipojen.
- [Opakování 41330782300](https://github.com/KovoPraha/evidenceTreninku/actions/runs/41330782300)
  SUCCESS: `changed=false`, `read_only=true`, stejné ID, rozsah a platnost,
  žádné prodloužení či obnova hesla. [Doklad](pilot-idempotence.json).
- Čerstvá záloha po založení obsahuje pilot, 337 tabulek a 2 triggery;
  [izolovaná obnova 41330986065](https://github.com/KovoPraha/evidenceTreninku/actions/runs/41330986065)
  PASS: skutečný import a porovnání počtů všech tabulek i obou triggerů.
  [Doklad obnovy a SHA](backup-restore.json).

## Vydání a kontroly

[PR #266](https://github.com/KovoPraha/evidenceTreninku/pull/266) sloučeno po všech
11 úspěšných kontrolách; ověřený head a squash mají shodný strom. Linux
**1895 testů / 38095 assertions**, POSIX 3/45 bez skipů, oba pilotní režimy
na migrované MariaDB 10.3/11.4 PASS. Lokálně 1895/38044 se 3 Windows skipy;
cílené lookup + pilot 17/92. [CI](ci.json).

[Deploy 41330031591](https://github.com/KovoPraha/evidenceTreninku/actions/runs/41330031591)
SUCCESS, zopakoval 1895/38095; [doklad](deployment.json). Následný audit potvrzuje
živou identitu, zachovanou konfiguraci a vypnuté integrace. Ledger 143, pending [],
žádná nová migrace. [Před](pre-verification.json), [po](post-verification.json),
[porovnání](release-comparison.json). 37 aktivních trenérských pozic, z toho
36 bez rozsahu. Identita doložena deployment metadata a ověřenými hashi pilotních
souborů, nikoli úplnou inventurou runtime.

HTTP [před](pre-http.json) i [po](post-http.json) 26/26, stejné katalogové produkty;
[trenérské trasy](trainer-http.json) 3/3, no-store a bez lokálního testovacího odkazu.
Pouze anonymní GET, bez odeslání formulářů. UAT false, import false.

[Dohled obnovy hesel](trainer-readiness.json), run 41331118594: automatický
hostingový heartbeat 1. 11. v 17:22:03 UTC úspěšný, správný zdroj instalovaný,
trenérská fronta prázdná. 29 z 37 aktivních pracovních účtů způsobilých k obnově;
původních 8 blokovaných beze změny. Tři staré klubové zprávy zůstaly čekající.
Hostingové `executable=false` je dříve doložená diagnostická odchylka; skutečný
heartbeat prokazuje běh. Obecné mailové příznaky nejsou potvrzením doručení
na adresu nového pilotu, které zde nebylo testováno.

Následuje [lidský testovací postup](../../TRAINER_PILOT_2026-10-31.md).
Konec grantu není automatické smazání účtu nebo dat. Další trenéři potřebují
vlastní identity a výslovné rozsahy; tento testovací účet se nesdílí.
