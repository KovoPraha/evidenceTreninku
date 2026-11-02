# Autonomní funkční ověření 2. 11. 2026

Následné autorizované [sloučení PR #272 a nasazení](verification/functional-release-20261102/RESULTS.md) mají samostatný protokol.
Níže je zachován rozsah původního testovacího běhu před nasazením.

Pracovní kopie `evidencePavel-functional-uat-20261102`, větev
`codex/functional-uat-20261102`, výchozí main `e916f086`.
Vlastník povolil autonomní testování dalších funkcí, opravy a jejich testy.
Změnu hesla a T07 výslovně odložil. Nové nasazení není součástí tohoto běhu.

## Rozsah a postup

- Zachovat původní WIP v htdocs. Produkci procházet pouze veřejně a bez zápisů;
  pracovní relace je při vstupní kontrole odhlášená.
- Založit vlastní loopback MariaDB s prázdným verzovaným schématem, migracemi
  a syntetickým localhost demo seedem. Žádná kopie skutečných dat ani configu.
  Veškeré integrace a odesílání vypnout, session a soubory držet mimo webroot.
- Přes skutečný Chrome ověřit veřejnou navigaci, katalog a filtry, rodinné
  identity a košík, kalendáře, sportovní účet a pracovní trenérské postupy.
  Syntetické zápisy jsou povoleny pouze v této izolované místní instanci.
- Nálezy reprodukovat, opravit v jejich vlastním kódu, doplnit účelný regresní
  test a znovu ověřit v prohlížeči. Neprovedené kroky neoznačovat PASS.
- Spustit dotčené testy, úplnou sadu, dokumentační a kvalitativní kontroly;
  připravit PR a vyčkat CI. Doložit rozdíl lokálního a produkčního ověření.

## Průběžný stav

Vstupní dokumentace přečtena, oddělená větev vytvořena z aktuálního main.
Produkční přihlášení nebylo dostupné; heslo se nečetlo ani neobnovovalo.
Dokončeno 36 konkrétních browser kontrol a tři místní UI opravy; [výsledky](verification/functional-uat-20261102/RESULTS.md).
Finální Windows 1902/38196 (3 skipy), JavaScript 19/19, PHPStan a style PASS.
Implementace, browser a lokální ověření dokončeny. Stav finálního Linux/MariaDB
CI určuje navazující PR; žádný deploy. Vlastní servery zastaveny, diagnostická
syntetická DB zachována mimo Git. Při předání uvést přesný stav PR/CI.
