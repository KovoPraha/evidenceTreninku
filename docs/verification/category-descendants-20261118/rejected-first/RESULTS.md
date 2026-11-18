# Samostatný index — NEPŘIJATO

PR#496/head7b0e0bc758945c190182279f75e11ed60a8f2b19,
[běh44362076843](https://github.com/KovoPraha/evidenceTreninku/actions/runs/44362076843).
Nativní stav/hash/6SQL a přínos stromů PASS, ale celé HTTP tree500 nesplnilo
předem určených≥20%: MariaDB10.3 category-anonymous17,821→14,486ms (18,7%),
MariaDB11.4 category-customer30,779→26,006ms (15,5%). Brána správně zastavila
obě lane před tree1000 a Chromem. Merge/deploy NOT RUN; žádná změna tolerance.
Uložené bezpečné artefakty obsahují dokončené fáze/native/cleanup; nezahrnují
úplné raw vzorky posledního tree500, protože brána zastavila jejich publikaci.
Čísla posledního kroku jsou z přesné AssertionError ve workflow.

Další kandidát kombinuje index s odstraněním duplicitního čtení uzlů uvnitř
jediného Select. Původní tree500/tree1000 nativní/HTTP≥20% a ostatní tolerance
se nemění. Nový rozsah/SQL kontrakt je výslovně zaznamenaný v hlavním plánu.
