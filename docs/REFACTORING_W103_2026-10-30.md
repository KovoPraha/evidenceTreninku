# W10.3 — klubové finanční projekce a posouzení čtení

Základ: W10.2, bez produkčních účetních operací. Před implementací zachovat
původní čistou projekci `accountingClubBalanceRows` jako kontrolní baseline.
Opakovaně prochází všechny příděly, spotřeby, převody a výplaty pro každý
případ. Prověřit indexování podle přesné identity případu při zachování
pořadí jednotlivých operací, datových hranic, typů a neověřených hodnot.

Měření po doménách 1000/10000/100000; baseline s omezeným časem, nedokončený
vzorek nesmí být extrapolovaný. Současný výsledek musí být totožný s baseline
tam, kde doběhne, a s nezávislým finančním očekáváním na velké fixture.
Dodatečná paměť nejvýše 128 MiB. Žádné agregování v jiném pořadí kolem
integer overflow, nové SQL či změna write cesty. PDF a XLSX použijí shodné řádky.

Dále změřit ledger 1k/10k/100k a osobní historii na migrované lokální MariaDB,
včetně cílení cizí osoby a limitů. Posoudit čekající účetní zdroje a zapsat,
zda vyžadují další samostatný řez. Cache se nesmí sdílet s finančním zápisem.

Gate: doménové baseline + cílené finance testy, plný PHPUnit, PHPStan,
MariaDB 10.3/11.4, styl, dokumentace a CI přesného headu. Release existujícím
chráněným workflow až po úspěchu; UAT/importy false. Rollback jen kód, bez DDL.
W11–W15 navazují. Skutečná účetní metodika a provozní přejímka zůstávají mimo.
