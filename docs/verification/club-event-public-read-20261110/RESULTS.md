# P11-EVENTREAD — výběrové měření, nevybráno

**ODLOŽENO / žádný produkční release.**
[Plán a podmínka pokračování](../../CLUB_EVENT_PUBLIC_READ_PERFORMANCE_2026-11-10.md).
[SQLite data](sqlite-comparison.json):17 střídavých párů po4warmupech,
všechny ordered array/PDO typy shodné.0/1/12/100/201/500 free a stejný počet
paid eventů,2paid varianty/event.500+500:6506→17 SQL,301,319→62,068 ms;
100+100:1306→9 SQL,57,383→10,577 ms. Nejde o MariaDB nebo produkční latenci.
Fyzické indexy obou variant stejné; dřívější neindexovaný časový probe
nepoužíváme pro přijetí. Žádné umělé síťové zpoždění ani cache oprávnění.

[Produkční veřejný GET](production-public-probe.json):0otevřených karet,
200/200. Současná prázdná projekce pouze6→4 SQL. Veřejné [checkout formuláře](checkout-candidates.json)
298/952 a218/784 mají2CSS; vybrán P11-ASSET6 se samostatnou přejímkou.
Nový helper je nevolaný prototyp pouze na codex/club-event-public-read-performance-20261110.
Writery a produkce neměněné. Native2DB/celéHTTP/Chrome/CI/merge/deploy NOT RUN.

Reprodukce prototypu z jeho větve: oba soubory .php.txt z tohoto adresáře
zkopírovat pod stejnými jmény bez .txt do output/, spustit
`php output/event-read-compare.php`. Pouze sqlite::memory:, bez konfigurace
nebo produkčních dat. Veřejné raw HTML/headers zůstávají privátní mimo Git.
