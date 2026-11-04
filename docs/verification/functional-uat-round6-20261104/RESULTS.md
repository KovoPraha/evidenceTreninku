# Šestý autonomní funkční průchod — 4. 11. 2026

**Vybrané scénáře registráře skutečně provedené v Chrome PASS; 16 závěrečných kontrol persistence PASS.**
Nová chyba aplikace se nepotvrdila, tato vlna přidává dokumentaci a důkazy.
Výchozí head PR #295: aba0b6bf2f7be2e416e81d4cbb9690020185523a.
Oprava původu papírové docházky F07 z předchozí vlny zůstává v otevřeném PR #295, bez nasazení.
Heslo, použitý obnovovací odkaz a T07 zůstávají SKIPPED.

## Produkční prohlížeč

Použitá přihlášená karta vlastníka, pracovní kontext Registrář členů a KIS, účet má superadmina.
Jde o ověření UI, nikoli důkaz oprávnění omezené role. Žádný produkční doménový zápis.
Živé nasazení zůstává 94982c44d7b7737bddcca46d3ebb9fb4f698abef, run 41835865547.

| Scénář | Pozorovaný výsledek |
|---|---|
| Nový vlastní souhlas | Samostatný koncept, před uložením chybí zveřejnění. Prázdný název blokuje nativní validace a zaměří pole. |
| Mobilní registrář 390 × 844 | Editor i papírová evidence bez vodorovného přetékání. Nabídka se otevře, další nástroje rozbalí a navigace do papírové evidence ji zavře. |
| Prázdný papírový podklad | Nativní validace vyžaduje osobu; žádný platný formulář neodeslán. |
| Vyřízení osoby | Hledání existujícího TEST-PR137 Ověření nasazení (#11782); všech sedm navazujících odkazů zachová osobu. Papírová evidence ji skutečně předvybere. Mobilní stránka bez přetékání, potvrzení samostatného posouzení povinné. |

Bezpečné pozorování: [production-browser.json](production-browser.json).
Karta nakonec vrácena na pracovní rozcestník, přihlášení a registrářský kontext ověřeny, viewport resetován.

## Místní browser s omezeným registrářem

Vlastní syntetická DB functional_round4, MariaDB 10.4.32 / 127.0.0.1:3322,
samostatný Apache/PHP 8.2.12 / 127.0.0.1:18422. Prázdné verzované schéma a syntetické seedování,
bez produkčního dumpu/configu. Transporty mail/cURL blokované, platební integrace vypnuté.
Účet round4-registrar (#3) má právě jedinou pozici registrar a nemá superadmina.
Nová fixture R6-ebfe92: osoba #20, program #9, vlastní souhlas #4. Zápisy provedla skutečná UI.

| Scénář | Pozorovaný výsledek |
|---|---|
| Koncept a publikace | Uložený koncept #13 nezveřejní dokument. Nezaškrtnuté potvrzení blokuje nativní validace; vzorové znění odmítne server. |
| Konkurenční koncepty | Finální A #14 a B #15 vycházejí ze stejného základu. A se výslovně zveřejní jako text #30; zveřejnění B server odmítne zprávou o starší verzi. Aktuální A nepřepíše a zastaralý koncept nemá tlačítko zveřejnění. |
| Skrytí | Samostatně potvrzené skrytí vytvoří text #31 / custom-draft-16. Historické znění #30 zůstane dostupné. |
| Papírový souhlas | Budoucí podpis blokuje nativní validace. Bez přílohy i umístění originálu odmítne server, zachová program a podepisujícího. Platný podklad #6 uloží k posouzení s historickým textem #30. |
| Samostatné ověření | Výslovné ověření #6 změní stav na „Podklad ověřen“, zachová historickou verzi; nevytvoří online odpověď ani účet. |
| Oprava po skrytí souhlasu | Formulář zachová program #9 a historický text #30. Nový podklad #7 má změněné umístění originálu, čeká na posouzení; #6 se označí jako nahrazený. Starý text i umístění zůstanou zachované. |
| Vyřízení osoby | Postup pro odchod ukazuje skutečné podklady a čekající členství. Přiřazení odpovědnosti vytvoří otevřený úkol; samostatné uzavření vytvoří revizi 2 a dva auditní záznamy. Členství zůstane čekající. |

[local-browser.json](local-browser.json) zaznamenává skutečné browser výsledky.
[persistence.json](persistence.json) obsahuje 16 kontrol: zachování historického textu a kontextu,
stavy podkladů, uzavřený úkol a audit, nezměněný hash celé osoby, počty účtů/online odpovědí,
prázdné rodinné vazby, soupisky, členské předpisy a plánované akce osoby; omezená role potvrzena.
DB kontrola je read-only a cíl hlídá podle jména DB, hostu a portu.

## Důkazy a hranice

- [Mobilní produkční editor](production-consent-mobile.png). Mobilní vyřízení osoby doložené pozorováním DOM; jeho zmenšený nečitelný snímek není zařazený jako vizuální důkaz.
- [Odmítnutý starý koncept](local-stale-consent.png), [ověřený podklad před opravou](local-paper-accepted.png), [uzavřený místní úkol](local-lifecycle-closed.png).
- Snímky vizuálně zkontrolované; neobsahují skutečné zdravotní údaje, kontakty, tokeny ani soukromé přílohy.
- Jde o vybrané browser scénáře navazující na [pátou vlnu](../functional-uat-round5-20261104/RESULTS.md), nikoli browser přehrání všech jejích 73 HTTP kontrol. Její původní NOT RUN zůstává historicky platné.
- Dvě karty sdílely relaci; není to T07 ani důkaz izolace dvou přihlášení. Úplné produkční UAT rolí, E8, poslední synchronizace a účetní přejímka zůstávají otevřené.
- Občasné timeouty ovládání po navigaci byly ověřeny dalším čtením a persistencí; nejde o potvrzenou chybu aplikace. Zápisy se neopakovaly bez kontroly výsledku.
- Vlastní místní služby vypnuté; MariaDB doložila Normal shutdown / Shutdown complete, porty 3322 a 18422 už neposlouchají. Syntetická data a důkazy zachované.
- Dokumentační kontrakt, kontrola odkazů a git diff --check ověřeny při dokončení PR. Tato vlna nemění runtime ani produkci.
