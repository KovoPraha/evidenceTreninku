# Oddělený trenérský pilot — technická příprava

31. 10. 2026. Nástroj je nasazen; **testovací účet ani skupina zatím nevznikly**.
Čeká se na samostatný e-mail, který vlastník ovládá. Žádná zpráva nebyla v tomto
kroku odeslána. Vlastník již [potvrdil doručení](../trainer-recovery-20261031/mailbox-confirmation.json)
předchozího transportního testu; to není doklad skutečného resetu nového účtu.

## Vydání a ověření

- [PR #261](https://github.com/KovoPraha/evidenceTreninku/pull/261) sloučen po
  11 úspěšných kontrolách. Ověřený head a squash mají shodný strom.
  Linux **1885 testů / 38032 assertions**, samostatné POSIX **3/45** bez skipů;
  MariaDB 10.3/11.4 včetně pilotu PASS. [CI](ci.json).
- Lokálně 1885 testů / 37979 assertions, 3 očekávané Windows skipy. Cílený pilot
  7 testů / 37 assertions: omezení přístupu, kolize, rollback, idempotence,
  zachování vlastníka a odmítnutí změněného grantu. PHPStan, styl a anonymní
  průchod PASS. Rozhodující úplné ověření je Linux CI výše.
- [Deploy 41194869109](https://github.com/KovoPraha/evidenceTreninku/actions/runs/41194869109)
  SUCCESS, znovu provedl 1885/38032; [doklad](deployment.json).
  Nasazený commit `256744513ecc1201ee5ae3071f30cbd4a637be58`.
- Read-only audit potvrdil živou identitu, shodnou konfiguraci, integrační
  přepínače a kandidáty příloh. Ledger 143, pending [], žádná nová migrace,
  historické VS beze změny. [Před](pre-verification.json), [po](post-verification.json),
  [porovnání](release-comparison.json). Nejde o úplnou inventuru všech runtime souborů.
- HTTP [před](pre-http.json) i [po](post-http.json) **26/26**, stejné katalogové
  produkty. [Trenérské trasy](trainer-http.json) **3/3**, no-store, bez testovacího
  odkazu. Pouze anonymní GET, žádný formulář ani přihlášení.
- Čerstvá záloha 337 tabulek / 2 triggery, SHA a skutečná izolovaná obnova:
  [doklad](backup-restore.json). UAT false a import false; integrace neaktivovány.

## Kontrola hostingu a zbývající krok

[První read-only běh](https://github.com/KovoPraha/evidenceTreninku/actions/runs/41195264652)
se zastavil ještě před spuštěním nástroje: chroot nemá `sha256sum`.
[PR #262](https://github.com/KovoPraha/evidenceTreninku/pull/262) počítá vzdálené
SHA-256 přes PHP a stále porovnává oba nasazené soubory s ověřeným zdrojem.
Aplikační soubory nemění, opětovný deploy proto není potřebný.
Skutečný stav vlastníka musí potvrdit následný `status`; první pokus jej neověřil.

Po doplnění e-mailu provést chráněné vytvoření, které před zápisem vytvoří novou
zálohu, v jediné transakci založí coach-only účet a dvě syntetické osoby v jeho
soupisce, třetí mimo rozsah a čtrnáctidenní grant. Ověří zachování účtu vlastníka
i zákaz přístupu mimo pilot. Náhodné počáteční heslo se nezobrazuje; vlastník
si nastaví vlastní přes obnovu hesla. [Postup a lidské scénáře](../../TRAINER_PILOT_2026-10-31.md).
Bez adresy se nezakládá účet, neupravují se kontakty a nepřiřazují reálné skupiny.

EvidencePavel a Velocota zůstaly oddělené. Do Velocoty tento release nezasahuje.
