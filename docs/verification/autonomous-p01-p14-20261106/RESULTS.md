# Autonomní průchod P01–P14 — výsledek 6. 11. 2026

Aktuální pokyn: autonomně projít všechny P. Následně vlastník potvrdil P01 a odložil skutečný tok P05/P07 i export P06. Tyto skutečné operace se neprováděly. Heslo/T07 dál odložené. Větev `codex/autonomous-p01-p14-20261106`, izolovaná kopie `C:/Users/Marek/Documents/Codex/evidencePavel-operations-handoff-20261106`, výchozí main `646d9c7f`.

## Stav všech položek

| ID | Doložený výsledek a zbývající mez |
| --- | --- |
| P01 | HOTOVO: vlastník potvrdil doručení stávající zprávy do své schránky, bez nové rozesílky. |
| P02 | Regrese BAR PASS, checklist první směny připravený. Chybí skutečný login Lucky, fyzické zásoby a počátek kasy. |
| P03 | Publikace hotová. Syntetický Dompdf tisk 45 položek, 10 stran, 45/45 názvů a vizuálně čitelná první/poslední strana PASS. Produkční přihlášený tisk NOT RUN. |
| P04 | Kontrakt/export zrevidovaný a regrese PASS; účetní rozhodovací matice připravená. Ledger adaptér stále neimplementovaný, potřebuje účetní pravidla a mapování. |
| P05 | Automatický schedule i dry-run SUCCESS, časová odchylka viz níže. Skutečné sjednání/platba/PDF/doručení ODLOŽENO vlastníkem. |
| P06 | Export a sync ODLOŽENO vlastníkem; bez změny dat, E8 až po přejímce. |
| P07 | Reálný platební průchod ODLOŽENO vlastníkem. Nejasné bankovní pohyby nepřiřazené. |
| P08 | Testy pilotu a revokace relací PASS; lidské UAT otevřené, heslo/T07 neprovedené, grant neprodlužovaný. |
| P09 | Produkční čtecí kontrola 16 invariantů PASS / 0 porušení. Lidské D12–D14 a dvě původní chybějící přílohy otevřené. |
| P10 | 300 místních větví, cizí WIP zachované. Žádný plošný merge/reset/mazání; vlastní měřicí schéma uklizené. |
| P11 | Nový migrovaný profil kapacit a čistý evaluator PASS. Plošné K0.2 tím není dokončené; potřeba nové runtime optimalizace nebyla doložená. |
| P12 | Aktuální doplněk testerky, pravidlo zastupování a odložení P05/P07/P06; oficiální zdroje ověřené. Identita testerky, autorizace a skutečné sdílení neurčené/neprovedené. |
| P13 | Kandidáti a rozhodovací pole připravené; typy/příjemce musí vybrat vlastník. Bez nového rozesílání. |
| P14 | HOTOVO: konkrétní dnešní záloha a dvě předchozí generace obnovené v izolaci. Produkce se neobnovovala. |

[Provozní a účetní podklady](READINESS.md), [doplněk testerky](../../testing/chatgpt-tester-20261105/CURRENT_OVERLAY_20261106.md).

## Provozní důkazy

- [Obnova 42224275796](https://github.com/KovoPraha/evidenceTreninku/actions/runs/42224275796) SUCCESS, [názvy a počty](restore-evidence.txt). Zahrnuje přesně `evidence_2026-11-06_060929_014837d4.sql.gz`; každá generace 385 tabulek / 2 triggery, SHA souboru i manifestu a všechny počty ověřené v dočasné MariaDB Docker.
- [Schedule 42222661557](https://github.com/KovoPraha/evidenceTreninku/actions/runs/42222661557) event=schedule SUCCESS; [výsledek](membership-schedule.txt) apply=true, všechny počty 0, errors=[]. Začal 13:05:18 UTC místo nominálních 05:23 UTC: odchylka 7 h 42 min. Dokládá automatický běh, nikoli přesný ranní čas nebo nákup.
- [Dry-run 42224288772](https://github.com/KovoPraha/evidenceTreninku/actions/runs/42224288772) SUCCESS; [výsledek](membership-dry-run.txt) apply=false, nuly, bez chyb.
- [Invarianty 42224909992](https://github.com/KovoPraha/evidenceTreninku/actions/runs/42224909992) SUCCESS; [16 kontrol / 0 porušení](production-invariants.json). První pending běh zrušil produkční zámek, tento úspěšný běh se spustil následně.
- [Metadata běhů](runs.json). Žádné soukromé dumpy či konfigurace v Gitu.

## Místní ověření

[Cílená sada](targeted-tests.txt): 136 testů / 753 assertions PASS. Původní start bez vendor/autoload selhal; po Composer install dle locku celá sada zopakovaná PASS. Testy se neupravovaly.

[Kapacitní souhrn](capacity-summary.json), [plný profil](capacity-migrated-profile.json): vlastní loopback MariaDB 10.4.32 / port 33436, náhodné schéma, 159 migračních kroků a FK; 3 warmup a 15 vzorků na režim. Dense 10000: přírůstková paměť 5480168 → 1831240 B; duplicity 10000: 4537600 → 515024 B. Shoda výsledků/SQL i brána do 4 MiB a do 60 % baseline PASS. Časy nejsou plošné zrychlení: duplicity 219,6 → 240,2 ms. Nová optimalizace se nedělala.
[Čistý evaluator](capacity-benchmark.jsonl) prošel 3000/30000/300000 řádky při stálých 1000 identitách a celkovém PHP peaku 2 MiB; není to neomezený počet identit nebo celé HTTP/SQL SLA. [Metoda](capacity-benchmark.php.txt).

PDF [syntetické nabídky](bar-menu-synthetic.pdf) vzniklo přes skutečné barMenuHtml a Dompdf s vypnutými vzdálenými/PHP/JS obsahy. [Metoda](bar-menu-smoke.php.txt), [první strana](bar-menu-first.png), [poslední strana](bar-menu-last.png). Pypdf ověřilo 45 názvů / 10 stran, Poppler a vizuální kontrola první/poslední strany PASS. Produkční HTTP/autorizace tím nenahrazené.

Všechny položky jsou projité, nikoli všechny provozně hotové. P01/P14 uzavřené, P05 doložený automatický start. Skutečné P05/P07/P06 odložené. P02/P04/P08/P09/P12/P13 vyžadují skutečné podklady či lidské rozhodnutí. Plošné K0.2 otevřené. Nový runtime deploy není potřeba.
