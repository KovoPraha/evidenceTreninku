# W11.2 — čerstvé ověření jednoho soupiskového příjemce

Worker pro každý řádek znovu sestavoval celé publikum a fingerprint náhledu.
Pro doručení však potřebuje rozhodnutí o jediném účtu a jeho přesném e-mailu.
Cílený helper zachovává validaci textu, aktivní skupinu/sezónu, aktuální
potomky, intervaly členství i rolí self/guardian, ověřený aktivní účet
a striktní PHP porovnání e-mailu. Nepoužívá cache oprávnění.

Náhled a enqueue zůstávají původní; čtecí rozhodnutí workeru používá EXISTS
nad vazbou konkrétního účtu a členstvím v aktuálních potomcích.
Původní chyby, hold, transport, claim a dokončení zůstávají stejné.

[Důkazy](verification/refactoring-w112-20261030/RESULTS.md) obsahují
RED rozpočet a porovnání s náhledem, změny mezi voláními, MariaDB profil
100/1000/10 000 osob a standardní kontroly. Po W11 pokračuje
[W12](REFACTORING_W12_2026-10-30.md), poté UI a závěrečná konsolidace.
