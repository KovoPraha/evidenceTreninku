# P11-ASSET11 — průběžný výsledek

13. 11. 2026. [PR #470](https://github.com/KovoPraha/evidenceTreninku/pull/470), head fe11fb95.
[CSS párový běh 43383614934](https://github.com/KovoPraha/evidenceTreninku/actions/runs/43383614934):
assets profily MariaDB 10.3/11.4 PASS. 13 HTTP scénářů na databázi,
358 tabulek s nezměněnými kontrolními součty, úklid PASS. HTML shodné
po odstranění přesného tagu o 215 B a normalizaci nonce. Dvě pixelově
shodné dvojice snímků, 88 měřených a 24 zahřívacích navigací CSS profilu.

Studený Bootstrap CSS 53 957→31 248 B (−22 709 B / −42,09 %),
dekódované CSS 465 606→232 803 B, warm 0→0 B.
FCP desktop cold 68→64 ms, mobile cold 64→60 ms.
HTTP správce 10.3 11,868→11,918 ms; 11.4 10,711→10,760 ms.
Čas HTTP prakticky shodný, přínos je odstraněný přenos CSS.
Lokální cílené testy 21/155 PASS; syntax, styl a dokumentace PASS.

Celkové CI a poslední historický counts profil při tomto checkpointu
ještě běží. Merge/deploy NOT RUN. Původní WIP zachovaný.
