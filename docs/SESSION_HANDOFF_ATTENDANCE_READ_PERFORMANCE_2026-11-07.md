# Předání P11 / R06 — docházka, 7. 11. 2026

## Zadání a oprávnění

Pokyn „ok, pokračuj“ navazuje na dokončený R05 další položkou P11: dávkové
ověření účastníků docházky a prokázání přínosu na stejných syntetických datech.
**Bez produkčních změn, deploye a aktivací, zachovat chování i původní WIP.**

Větev `codex/attendance-read-performance-20261107`, vlastní pracovní kopie
`C:/Users/Marek/Documents/Codex/evidencePavel-attendance-performance-20261107`.
Base main `89edfdbee5fbddbe05e0134d94f79ba0cb4143d9` / #353.
Původní kopie `C:/xampp/htdocs/evidencePavel` zůstává na své větvi;
`kis_rosters_admin.php` při převzetí SHA256
`7a7afd75bfa34f736f158096ab46a671b7fee6c6faedc3701f989c0cf64733e5`.

## Implementace a dosud ověřený stav

`coachTrainingAssertPeople` mimo transakci čte pouze ID po dávkách 500.
Autorizační kontrola je vždy první a čerstvá. V transakci zůstávají původní
jednotlivé dotazy ve stejném pořadí; prázdný vstup zachovává i původní prepare.
Nejde o cache oprávnění nebo osob. Jde o sdílený validátor, takže dávky platí
pro jeho volání mimo transakci; writer ani samotný POST se nepřepisují.

[PR #354](https://github.com/KovoPraha/evidenceTreninku/pull/354), implementace
`b98961ae`; [výsledky a surové důkazy](verification/attendance-read-performance-20261107/RESULTS.md).
Místně **29 testů / 206 assertions PASS**, cílený PHPStan, baseline budget,
styl a dokumentace PASS. [Párové CI 42352255371](https://github.com/KovoPraha/evidenceTreninku/actions/runs/42352255371)
na měřicí revizi `b639c7e0` (syntetický merge `d96fa34e`): **všech 11 kontrol PASS**.
Finální dokumentační CI/merge ověřit u PR; nepředstavuje produkční release.

- Stejný migrovaný katalog 347 tabulek / 160 migrací, devět R06 případů
  10/50/200 účastníků × 1/12/52 revizí na MariaDB 10.3.39/11.4.13.
  Pět zahřátí, 30 vzorků/fázi/případ: **1080 časových vzorků R06** celkem.
- SQL **54/94/244 → 45**. Medián 200 osob **2,30–2,48× rychlejší**,
  50 osob 1,34–1,40× a 10 osob 1,07–1,10×; p95 není univerzálně lepší.
  Vrácené řádky a PHP paměť shodné. Měřeno GET tělo/šablona, nikoli celé HTTP.
- Všechna data i celé HTML ve všech párech shodné. Na každé MariaDB
  84 paritních kombinací vstupů/rolí, čerstvost, transakční SQL/parametry/pořadí
  a zámky PASS; validátor 200 osob 202 → 3 SQL, 1200 osob 5 SQL.
- Plné CI 2315 testů / 42 846 assertions, unixové hranice 3/45,
  integrační smoke obou verzí, PHPStan/styl/CodeQL/dokumentace PASS.
  R05/R13 párová parita/měření zachované, všech 24 aktuálních případů PASS.
- READ ONLY, čtyři autorizační negativa a cleanup všech vlastních schémat PASS.
  Místní MariaDB ani HTTP služba nevznikly, starší runtime adresáře se nemažou.

[Kalibrace](verification/attendance-read-performance-20261107/CALIBRATION.md)
odděluje dvě odmítnutá porovnání od výsledků: R06 závisí na předchozím R05 seedu
a HTML zobrazuje automatické `closed_at`. Obě fáze proto mají úplné pořadí
24 případů a stejný syntetický čas revizí. Nejde o normalizaci historie v HTML.
Zmrazený měřič se od `89edfdbe` liší jen dvěma řádky v seedu, SHA256 po LF
`847888e87e6a479cad6cf922f892021ef40528a4bd9f454ce728f4d9ebc5fde5`.
Měřené tělo zachované; nový měřič přidává ještě otisky služeb mimo interval.
Komparátor hlídá striktní paritu, nejvýše 45 SQL / 1000 řádků pro tento fixture,
snížení SQL a nezvýšení řádků; časový limit na runneru není SLA.

## Další postup

1. Finální CI a začlenění ověřit u PR #354. Platné párové výsledky a surové
   profily jsou již trvale uložené v uvedeném protokolu, kalibrace oddělená.
2. Původní WIP ověřené shodným SHA256; žádný reset/clean/přepnutí původní
   kopie. Sloučení po všech kontrolách je samostatné od produkčního nasazení.
3. Zůstává plné HTTP/bootstrap/navigace, další role a plánem propojená docházka,
   R05 historické upozornění s hierarchickým filtrem a další objemové profily.
4. Produkci ani R05/R13 nenasazovat. P05/P07, export P06, heslo/T07 a souběžný
   ranní audit mají své původní závislosti; neopakovat cizí provozní operace.

[Průběžný registr](WORK_PLAN_CURRENT.md), [aktuální předání](HANDOFF_CURRENT.md),
[produkční stav](CURRENT_STATE.md), [předchozí R05](SESSION_HANDOFF_PLANNER_READ_PERFORMANCE_2026-11-06.md).
