# Předání P11-R13-F — hledání a datum zdravotního přehledu

7. 11. 2026: ROZPRACOVÁNO, plán před implementací.
[Plán](MEDICAL_FILTER_READ_PERFORMANCE_2026-11-07.md), [výsledky](verification/medical-filter-read-performance-20261107/RESULTS.md).
Nové zadání dovoluje commit/merge/deploy po shodě, přínosu a zelených kontrolách.
Větev `codex/medical-filter-read-performance-20261107`, vlastní dřívější čistý worktree
`C:/Users/Marek/Documents/Codex/evidencePavel-medical-state-performance-20261107`.
Base35fdad44, dosavadní produkce938e9c50/#381; její stavový řez a důkazy zachovat.
Plán: bounded kandidáti, prázdné/vzácné výsledky původním čtením nad ID;
široké stavové hledání bez vnitřních JOIN lidí, datum omezí i historii.
DDL/cache/rekurze neplánované. Nezměnit přímou osobu/transakce/práva/writer/dokumenty.
Další krok: zmrazená reference, implementace, parita SQLite/nativní obě DB,
párové cache-off helper/HTTP/Chrome10k/100k, důkazy, kontroly, teprve pak release.
Původní WIP SHA256 `7a7afd75bfa34f736f158096ab46a671b7fee6c6faedc3701f989c0cf64733e5` shodný;
staré untracked výstupy původního checkoutu i tohoto worktree zachované.
Implementace/testy/měření/PR/merge/deploy nového řezu zatím NOT RUN.

## Průběh implementace

První implementace hotová lokálně, přesný současný baseline35fdad44 včetně jeho stavového readeru zmrazený nezávisle. Nová sada7/454 a společná regrese25/801 PASS, styl/lint PASS. Přímá osoba a transakce zachovávají SQL/bindingy; předchozí test požadující původní SQL i u q/data nahrazen novým kontraktem výsledků podle D15. Nativní profiler14případů×4objemy100/1k/10k/100k,17střídavých párů a4zahřátí s query cache OFF. Samostatné nativní joby nezvětšují rozpočet staré integrační sady. HTTP benchmark porovnává přesné dva původní aplikační soubory, všech30k/300k záznamů a rozložená data revizí na jednom seedu;15kladných/12záporných případů a6Chrome scénářů. Výkon zatím NOT RUN; implementace je kandidát k měření, nikoli k deployi.
