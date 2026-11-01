# Nasazení opravy obnovovacího odkazu — 1. 11. 2026

Vlastník výslovně schválil kontrolu a případné sloučení PR #269, produkční
nasazení, následné ověření a aktualizaci dokumentace. Toto oprávnění navazuje
na původní implementaci bez nasazení; nemění oprávnění ke skutečným heslům,
účtům, rozesílkám, importům nebo finančním operacím.

## Plán a průběžný stav

1. Review PR #269: head `3d4d40dd3d3b9495db5277dbb931f31550e51f15`, základ
   `97a0ff213a871639f7708d99613282f73652302e`, všech 11 CI kontrol SUCCESS,
   Linux 1900/38192 a POSIX 3/45 bez skipů. Kontroly obsahují obě MariaDB
   verze 10.3/11.4. Žádné review komentáře nebo čekající požadované schválení.
   Main vyžaduje aktuální větev a úspěšné PHPUnit/MariaDB; pravidla se neobcházejí.
2. Provedeno review formy, CSRF, předběžného čtení, opakované zamykající
   kontroly při zápisu, zachování tokenu při opravě hesel a oddělení účelů.
   Bez blokujícího nálezu. Předprodukční čtecí audit spuštěn, výsledek se doplní.
3. Sloučit přesný ověřený head metodou squash; ověřit shodný strom.
4. Spustit `deploy-production.yml` z main, `potvrzeni=NASADIT`,
   `uat_schvaleno=false`, `importovat_krouzky=false`. Zachovat standardní
   preflight, zálohu, migrace/check, aktivaci a HTTP kontrolu.
5. Po nasazení provést čtecí audit a anonymní browser/HTTP ověření: nový
   úvodní stav, odmítnutí chybějícího a syntetického neplatného odkazu před
   heslovými poli, CSRF a základní dostupnost. Žádné skutečné heslo nebo
   obnovovací token se nepoužije; T07 a nové lidské replay UAT zůstávají oddělené.
6. Zapsat skutečné SHA/run, zálohu, schéma, rozsah ověření a omezení do
   protokolu, CURRENT_STATE a HANDOFF_CURRENT; dokumentaci předat přes PR.

Pracovní kopie: `evidencePavel-trainer-reset-replay-20261101`; původní UAT
artefakty i jiné pracovní kopie zůstávají zachované. Datum a časy jsou Europe/Prague,
pokud konkrétní strojový doklad výslovně neuvádí UTC.

## Skutečný výsledek

- PR #269 sloučen jako `acce3c1351cddbcad12fb5fea11afe6ae8936001`, shodný strom
  s ověřeným headem, bez změny pravidel ochrany main.
- Deploy 41349120615 SUCCESS, znovu 1900/38192, nová ověřená záloha 337/2,
  current/pending [], ledger 143 beze změny. UAT i import false.
- Čtecí audit 41349042948 před a 41349491104 po PASS, správná živá identita,
  konfigurace, flags integrací a stav příloh zachovány. HTTP před/po 26/26.
- Chrome potvrdil odmítnutí chybějícího a synteticky neplatného odkazu před
  heslovými poli; automatický check POST ověřen v obou režimech.
- CURRENT_STATE, HANDOFF_CURRENT, rozcestník, historické UAT a implementační
  protokoly jsou propojeny s novými doklady. Předchozí stav uložen do archivu.
- [Protokol vydání a limity](verification/trainer-reset-release-20261101/RESULTS.md).
  Skutečný použitý odkaz a T07 zůstávají odložené; nejsou vydávány za PASS.
