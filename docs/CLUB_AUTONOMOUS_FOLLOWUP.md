# Navazující kontrola společného systému oddílu

23. 10. 2026, větev `codex/club-followup-review-20261023`, základ `7aff7884`.
Pouze izolovaná kopie `C:\xampp\htdocs\evidencePavel-finance-b1-20261022`.
Navazuje na [předání R01–R44](CLUB_IMPLEMENTATION_HANDOFF.md).
Žádný push, merge, nasazení, skutečný import ani produkční změna.

## Co se změnilo

| Návaznost | Potvrzená mezera | Výsledek |
|---|---|---|
| R09, R13–R17, R25–R30 | Souběžné odebrání role mohlo projít přes starý transakční snímek | Dotčené finanční, členské a sportovní operace zamykají aktuální účet i konkrétní oprávnění. Skutečné MariaDB souběhy ověřují odmítnutí zápisu po odebrání role. |
| R18–R24 | Storno výměny přehlíželo nepřidělený nebo pouze uvolněný příjem | Přijaté peníze vyžadují finanční vypořádání. Obsluha je neschová stornem případu. |
| R21–R24 | Převzetí jednoho kusu k výměně umožnilo výdej jiného, již dobropisovaného kusu | Nárok rozlišuje převzetí k výměně a konečnou vratku, včetně doloženého vrácení náhradní varianty. |
| R26, R33, R18–R24 | Oprávněné navazující obrazovky nebyly dosažitelné z pracovního menu | Trenér, vedoucí sportu, katalog a finance mají odkazy na své existující navazující úkony. Vlastnictví obrazovek ani oprávnění se nerozšiřují. |
| R40, R42 | Týdenní report ukládal osobní údaje veřejně a odkazoval na chybějící PDF knihovnu | Nové HTML/PDF jsou soukromé, s kontrolními součty; vedoucí sportu získá ověřené PDF přes autorizovaný endpoint. Používá se existující Dompdf. Starý archiv se automaticky nepřevádí. |
| R04, R42 | Vestavěný server nečetl Apache ochrany; zastavení DB nebylo šetrné | Místní router chrání interní cesty, kontrola ověřuje účinné omezení přenosů a vlastní prostředí; stop používá řízené ukončení MariaDB. |

Podrobné reprodukce a meze testů obsahují
[členská a finanční autorizace](verification/autonomous-followup/MEMBERSHIP_AUTHORIZATION.md),
[obchodní opravy](verification/autonomous-followup/COMMERCE.md) a
[místní provoz](verification/R42_LOCAL_OPERATIONS_FOLLOWUP.md).

## Ověření a předání

Společné důkazy a stav obnovy jsou v
[protokolu](verification/autonomous-followup/RESULTS.md).
Finální regrese: **1 545 testů / 31 583 assertions PASS**. Nová záloha byla
obnovena do samostatné databáze: 326 aplikačních tabulek a 175 soukromých
souborů. Nalezená lokální chyba engine byla řešena obnovou do nové kopie se
zachováním původního datadiru; nejistota historie jediné nečitelné tabulky
je výslovně uvedena v protokolu.
Automatizovaná přejímka a syntetické příklady nenahrazují převzetí účetní
ani skutečný pilot. [Návod k prohlídce](CLUB_LOCAL_REVIEW.md) používá výhradně
místní kontrolní účty; přístupové údaje zůstávají mimo Git.

## Co se neuzavírá automaticky

Nadále platí [D01–D16](CLUB_COMPLETION_DECISIONS.md): skutečné zdroje, účetní
metodika a řady, fyzický sklad, schvalovatelé, klubová pravidla a převzetí
provozu. Nasazení, aktivace integrací, pilot, ostrý převod a odstavení starých
webů vyžadují samostatné rozhodnutí. Půjčované vybavení zůstává mimo rozsah.
