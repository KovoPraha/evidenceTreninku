# W10.3–W15 — implementace a CI dokončeny, nasazení blokuje databázový prostor

30. 10. 2026, cílové vydání `2345b648a237973e96593daa5d8588be2a99c28a`.
[PR #248](https://github.com/KovoPraha/evidenceTreninku/pull/248),
[#249](https://github.com/KovoPraha/evidenceTreninku/pull/249),
[#252](https://github.com/KovoPraha/evidenceTreninku/pull/252),
[#254](https://github.com/KovoPraha/evidenceTreninku/pull/254) a
[#255](https://github.com/KovoPraha/evidenceTreninku/pull/255) jsou sloučené.
Předchozí W7.4–W10.2 mají samostatné úspěšné release protokoly.
[Uzavření oblastí a mezí přejímky](../../REFACTORING_CLOSURE_2026-10-30.md).

## Dokončené ověření

- [CI přesného main](github-ci-main.json): Testy 41094283333, PHP quality
  41094283244, Current documentation 41094283278 a CodeQL 41094283424 SUCCESS.
  Linux 1864 testů / 37843 assertions; POSIX kontroly bez skipů,
  MariaDB 10.3/11.4 včetně skutečných souběhů, PHPStan, styl, JS a anonymous sweep.
- [Konečný PR commit](github-ci-pr.json) prošel stejnými branami.
  W12 preview 10 000 řádků: MariaDB [10.3](pr-mariadb103/import-preview-profile.json)
  4,36 s / 40 194 postupů čtení, [11.4](pr-mariadb114/import-preview-profile.json)
  4,09 s / 42 394; limit 310 000. Stejný fingerprint, bez promotion.
  Jednotlivá instrumentovaná měření, nikoli produkční SLA.
- Skutečná obnova celého syntetického schématu, 290 tabulek / 1 trigger,
  celé tabulkové digesty, FK a soukromý archiv: [10.3](pr-mariadb103/backup-restore-profile.json),
  [11.4](pr-mariadb114/backup-restore-profile.json) PASS.
  Dvě spojení a negativní kontrola ověřily konzistentní účetní export:
  [10.3](pr-mariadb103/export-snapshot.json), [11.4](pr-mariadb114/export-snapshot.json).
- [Předletová kontrola](pre-verification.json), run 41094301278, byla úspěšná,
  read-only, bez doménových mutací. Živé metadata stále W10.2
  `7828758cfe832a99c11ddeafe916b8480e501feb`, run 41077903487.
  [HTTP před](pre-http.json) 16:56 UTC: 26/26, stejné tři veřejné produkty.
  Ověření identity je založeno na deployment metadata a workflow;
  nejde o novou inventuru hashů všech běžících souborů.

## Neúspěšné nasazení a aktuální dopad

[Deploy 41095272194](https://github.com/KovoPraha/evidenceTreninku/actions/runs/41095272194)
FAILURE. [Strukturovaný protokol](deployment.json): testy prošly,
ale vytvoření zálohy v 17:04 UTC selhalo na SQLSTATE HY000 / driver 1021,
MariaDB hlásila `/tmp/#sql_855_0.MAI`, errno 28. Ověřená záloha nevznikla.
Migrace, kopírování aplikačního release a aktivace byly přeskočeny;
dočasná konfigurace byla uklizena. Aplikace nebyla přepnuta na cílové SHA.
Počet migračních záznamů byl před pokusem 141; očekávaný přechod na 142
se tímto pokusem neprovedl. UAT i import false.

[Následná diagnostika](post-failure-diagnostics.json),
[run 41095685511](https://github.com/KovoPraha/evidenceTreninku/actions/runs/41095685511),
potvrdila chybu 1021 třikrát. Web i DB uvádějí host `replikant3544`,
DB datadir `/var/lib/mysql/`, tmpdir `/tmp`. PHP měřilo na dostupném
webovém úložišti přibližně 17,865 GB volných ze 40,960 GB. To neprokazuje
kapacitu, inody ani kvótu v mount namespace databázového procesu.
Inventura našla 184 release adresářů; jejich velikosti jsou pouze částečné
kvůli bezpečnému časovému limitu. 60 souborů záloh má 121 627 705 B.
Žádný release, záloha ani doménová data nebyly v rámci řešení incidentu smazány.

[HTTP po neúspěšném pokusu](post-http.json) v 17:05 UTC: 13/26.
Index a přihlašovací/chráněné cesty fungují, katalog a produktové detaily
vracejí 500 i na předchozí aplikaci. Jde o aktuální provozní incident,
nikoli úspěšné nasazení nebo regresi nově aktivovaného kódu.

Stejný problém se objevil již před tímto pokusem:
[15:38 UTC, 13/17](pre-http-disk-full.json), diagnostika 41084273868,
poté [16:04 UTC, 26/26](recovered-http.json) a
[16:32 UTC, úspěšná read-only kontrola](recovery-continuity.json).
Příčina dočasného zotavení není doložena. Význam chyby uvádí
[MariaDB ER_DISK_FULL](https://mariadb.com/docs/server/reference/error-codes/mariadb-error-codes-1000-to-1099/e1021).

## Konkrétní podmínky pokračování

Správce hostingu musí ověřit prostor a inody filesystemu skutečného DB `/tmp`,
kvóty a případné oddělené mount namespace/PrivateTmp procesu MariaDB.
Z webového chrootu nemáme důkaz ani oprávnění k bezpečnému vyčištění DB temp.
Nemažte automaticky rollback vydání nebo zálohy na základě jejich počtu.
Zadání hostingu je popsáno zde; žádná externí zpráva nebyla automaticky odeslána.

Po odstranění provozní příčiny: čerstvý read-only audit a HTTP 26/26,
CI aktuálního main, chráněný deploy se zálohou, `uat_schvaleno=false`,
`importovat_krouzky=false`; následně živé SHA/run, schéma current/pending [],
ledger 142, VS beze změny, config hash/flags/přílohy a HTTP 26/26.
Novou migraci a ledger zachovat i při případném dopředném opravném vydání.
Do splnění těchto bodů zůstává konečná produkční brána otevřená.

Lidská/účetní přejímka a integrace jsou oddělené. PHPStan má nadále
2350 historických potlačených nálezů, jejich růst nyní blokuje CI.
Testovací MariaDB na localhost:33426 a vlastní browser/server byly ukončeny;
původní rozpracovaný checkout zůstal zachován.
