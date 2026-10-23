# Následná společná přejímka — 23. 10. 2026

Větev `codex/club-followup-review-20261023`, základ `7aff7884`.
Pouze izolovaný syntetický localhost. Bez skutečného účetního převzetí,
uživatelské přejímky, produkčních změn a nasazení.

## Regrese aplikačního kódu

- Celá PHPUnit sada: **1 545 testů / 31 583 assertions PASS**, bez přeskočení,
  chyb a selhání. Souhrn [phpunit.json](phpunit.json); úplný JUnit a konzole
  zůstávají v místním runtime jako `followup-junit.xml` a `followup-phpunit.log`.
- Členské a sportovní HTTP: **100 kontrol PASS**. Podrobnosti a jednotlivé
  JSON protokoly: [členská autorizace](MEMBERSHIP_AUTHORIZATION.md).
- Obchodní HTTP: **85 kontrol PASS**. [Obchodní protokol](COMMERCE.md).
- Router a soukromé reporty: **22 HTTP kontrol nad 21 požadavky PASS**,
  navíc 3 kontroly vytvoření/uložení reportu. [Provozní protokol](../R42_LOCAL_OPERATIONS_FOLLOWUP.md).
- Skutečné MariaDB souběhy: **62 kontrol PASS** (19 nových členských a
  finančních autorizačních scénářů + 12 původních sportovních kontrol +
  6 nových skladových autorizačních scénářů + 6 výdejových + 19 vratkových).
  Některé kontroly ověřují transakční guard, jiné celý doménový zápis;
  nejde o 62 samostatných uživatelských pracovních postupů.
- Souvislý syntetický měsíc: **13 kontrol PASS**, konečný zůstatek 4 800 Kč,
  bez duplicitních peněz, s vazbou výdaje, odměny, výdeje a exportu.
  [Protokol](operating-month.json). Běh proběhl před závěrečným restartem B1.
- Syntaxe **35 PHP a 5 PowerShell souborů PASS**; [protokol](php-lint.json).
  Procesní ochrany: 7 kontrol a 1 mock přejímka PASS. Katalog
  **136 migrací current**, žádná čekající [migrace](migrations.json).

První společný běh narazil na tři testy vyžadující přesné znění existujícího
Apache pravidla. Ochrana `reports` je nyní samostatné pravidlo, původní
zůstalo zachované. Cílená regrese a následně celá sada výše prošly.

## Prohlídka rozhraní

Skutečný Chrome na místním serveru: přihlášení syntetickým účtem, přepnutí
pozice trenéra a vedoucího sportu, přechod z nového menu na odměny a uzavření
docházky. Kontrola dostupnosti navigace neprovedla obchodní ani finanční zápis.
Snímek `followup-navigation.png` je v místním runtime
`%LOCALAPPDATA%\EvidencePavelB1\finance-b1-20261022`.
Nejde o lidskou uživatelskou přejímku.

## Obnova prostředí

Záloha po skutečném restartu odhalila chybu MariaDB 1932 na tabulce
`child_access_events`. Samotný úspěšný start a kontrola identity databáze
nedokládaly čitelnost všech tabulek. Příčina ani okamžik vzniku nejsou
prokázané; nelze chybu bez důkazu připsat poslednímu restartu.

Obnova do nového datadiru zachovala všech **338 čitelných tabulek** se
shodnými počty, obsahovými otisky, strukturou a 2 triggery. Nečitelná tabulka
pochází z poslední ověřené zálohy, kde měla 0 řádků. Její případné pozdější
události nelze potvrdit. Původní celý datadir včetně ostatních testovacích
schémat je zachovaný v `mysql-quarantine-recovery-20261023-075136-e4945a30`
uvnitř místního runtime. Soukromé soubory se nepřepisovaly.
[Souhrn obnovy](b1-recovery-summary.json) a
[podrobný postup](../R42_LOCAL_OPERATIONS_FOLLOWUP.md) zachovávají omezení důkazu.

Po obnově se zopakovalo **66 HTTP kontrol PASS**: docházka 25, vratky 33
a soukromý report 8 nad 7 požadavky (navíc 3 kontroly generování souboru).
Znovu prošel i souvislý měsíc se 13 kontrolami a zůstatkem 4 800 Kč.
Protokoly: [docházka](post-recovery-training.json),
[vratky](post-recovery-returns.json), [reporty](post-recovery-weekly.json),
[měsíc](post-recovery-month.json). Jde o opakování vybraných scénářů výše,
nikoli další nové uživatelské funkce.

Nová úplná [záloha](backup.json)
`evidence_2026-10-23_055943_98d3125c.sql.gz` má SHA-256
`9b6d42383966f9f20f0f8156743a308b673e89a4e1e16eab14c6de98b48bf564`.
Nezávislá [obnova](backup-restore.json) do nové DB
`evidence_b7_restore_8e0bfa24` ověřila **326 vlastněných aplikačních tabulek,
2 triggery, 175 soukromých souborů a 70 databázových odkazů na soubory**.
Rozdíl proti 339 tabulkám celého sandboxu tvoří tabulky mimo zálohovací
kontrakt aplikace; jsou zachované v nové DB i v původním offline datadiru.
Prošly rovněž skladové projekce 32 variant, 21 revizí uzavření, 19 výplat
odměn, 8 vazeb skutečných vratek, migrační mapy a kontrolní snímky.
**4 soukromé týdenní reporty** mají po obnově shodné PDF, HTML i manifesty.

Finální [read-only probe](readiness.json) v 08:00 CEST: **PASS**, všech 326
požadovaných přítomných tabulek čitelných, žádná chybějící požadovaná.
Tři výslovně volitelné staré `ucto_gs_*` tabulky jsou uvedené jako nepřítomné.
Externí transporty jsou zakázané a produkční autorizace je `false`.

## Omezení

Veškeré účetní částky, sazby, osoby a případy testů jsou syntetické. Testovací
zápisy ani jejich úspěch nepotvrzují skutečné počátky, správnost zdrojových
dat nebo účetní metodiku oddílu. [D01–D16](../../CLUB_COMPLETION_DECISIONS.md)
zůstávají odložené. Žádné oprávnění k produkčnímu přepnutí nevzniklo.
