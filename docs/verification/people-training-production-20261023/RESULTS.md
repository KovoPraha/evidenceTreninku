# Přihlášená produkční prohlídka osob a tréninků — 23. 10. 2026

Navazuje na [předchozí izolovanou přejímku a release](../people-training-20261023/RESULTS.md).
Produkce byla prohlížena v uživatelem přihlášeném Chrome od 19:27 SELČ.
Výchozí ověřený release: `ee6cff62f9b7628ec1dda7bb4c3571b7272a9b30`.
Soukromé úložiště: PASS při čtení aktuálního webového procesu.

## Rozsah a hranice

- Produkční doménová data pouze čtena. Přepínána vlastní pracovní pozice, GET filtry a záložky; nový formulář pouze otevřen a vyzkoušeny jeho neodeslané prvky.
- Žádné produkční založení osoby/tréninku, potvrzení docházky, schválení vztahu, odhalení RČ, import, úhrada ani aktivace integrace.
- Superadmin v pracovních kontextech registráře, vedoucího sportu a trenéra. Není to důkaz všech oprávnění skutečného omezeného trenéra nebo rodinného účtu.
- Zápis pouze výsledků technické kontroly v B1 `testovaci_scenare.php`; původní výsledky zachovány a doplněny, B05/B26 zůstávají PARTIAL. Žádné přepsání lidské akceptace.

## Produkční kontroly před opravou

| Kontrola | Výsledek a meze |
|---|---|
| Správa sportovců | Seznam 1504 osob načten; hledání TEST vrací 3, filtr Archiv 1. Žádná změna osoby. |
| Archivovaná syntetická karta TEST-PR137 | Přehled, KIS a platby, Sport, Poznámky, Historie funkční. Historická soupiska zůstává oddělená od aktuální. Kontakt nezakládá přístup účtu. |
| Editor osoby | Samostatné sekce profilu, rodičů, účtů, soupisek, plateb a chráněných údajů se načtou. RČ nebylo odhaleno ani upraveno. |
| Audit osoby | Historické akce včetně evidence osoby a soupisky; odkaz Zdroj otevře skutečně vybranou záložku Historie. Chybějící důvod se nedovozuje. |
| Všechny tréninky | Jeden evidovaný trénink, detail se rozbalí, editor načte jednoho účastníka a původního trenéra; bez zachycené JS chyby. |
| Karta účastníka | Stejný trénink na záložce Sport; neuzavřená docházka označena „účast nepotvrzena“. |
| Docházka a uzavření | Rozepsaná docházka, samostatný skutečný interval, účastníci, trenéři, zdroj a explicitní potvrzení konce; nic neodesláno. |
| Plánovač | Přepínání Moje/Vše a týdnů funkční. Tři staré nezapsané plány ve Vše; chybný odkaz do prázdného minulého týdne. |
| Nový trénink | Načten formulář se stabilním submission klíčem; přidání řádku měření funguje bez JS chyby. Archivovaná testovací osoba se v našeptávači nenabízí. Nic neuloženo. |
| Moje tréninky | Pohled přihlášeného trenéra načten, bez odpovídajících záznamů. |
| Účty a osoby | Bez filtru se načte; textové hledání způsobilo HTTP 500. Oprava níže. |

## Opravy z tohoto průchodu

1. **Hledání osoby vracelo HTTP 500.** B1 reprodukce v 19:32:45 prokázala MariaDB chybu 1267, mix kolací při `LIKE` nad spojeným jménem. Stránka nyní používá společné parametrizované hledání: sjednocení kolace pouze SQL výrazu, doslovné wildcardy, přesné ID (včetně jednociferného), oba směry celého jména, limit 100 a zachycená validace délky. Uložená jména ani schema se nemění. Opraven i stejný výraz v auditním hledání. Odstraněny také opakované pojmenované parametry nekompatibilní s nativními prepared statements.
2. **Upozornění na nezapsané lekce vedlo do nesprávného týdne.** Cíl se odvozuje z nejstaršího skutečně nalezeného plánu po všech filtrech, místo pevného „dnes minus 7 dní“. Filtry zůstávají. Text neprohlašuje plánovanou lekci automaticky za uskutečněnou.
3. **Náhled oznámení zobrazoval HTML entity a sekal je uprostřed.** HTML se převede na prostý text, entity se dekódují před zkrácením na 160 znaků; konečný výstup se stále HTML-escapuje.

## Izolované ověření

- B1 read-only probe 19:30:37 PASS: 326 tabulek, vypnuté externí transporty, soukromé cesty mimo webroot.
- Záloha před zápisem přejímkového protokolu: `evidence_2026-10-23_173536_b58e837f.sql.gz`, 326 tabulek, 2 triggery, SHA256 `f99404d0adf7539dbf8e554fd95d0e0cee12d57559a6c32eca57eb0b138b129c`.
- PHPUnit cíleně: **14 testů / 93 assertions PASS**, PHP syntax a diff check PASS.
- Nativní MariaDB read-only dotazy: `UAT-OSOBY23` a `92` vracejí pouze 92; doslovné `%` a neexistující jméno nic.
- B1 UI hledání vrací jednu archivovanou syntetickou osobu, bez HTTP 500. [Vizuální důkaz](local-identity-search.png).
- B1 UI plánovač: z prázdného týdne 26. 10. se opraveným odkazem otevře 22. 10. se skutečným plánem 2 a zachovanou soupiskou `team_id=3`.
- MariaDB CI smoke rozšířen o různé kolace jména/příjmení, nativní prepare a hledání celého jména oběma směry; MariaDB 10.3 a 11.4 PASS.
- [Lokální cílový týden a zachovaný filtr](local-pending-plan.png).

## Nasazení a opakovaná produkční kontrola

- [PR #177](https://github.com/KovoPraha/evidenceTreninku/pull/177), kód `7f2d5e0a`, merge/release **`313cd61ba5111f0d88e158b5107bb1160cb0d374`**.
- PR CI **39996659275** a CodeQL **39996654683** PASS. Linux PHPUnit **1 653 testů / 32 963 assertions**. Main CI **39996941822** a CodeQL **39996941776** PASS.
- [Chráněný deploy 39997113354](https://github.com/KovoPraha/evidenceTreninku/actions/runs/39997113354) **SUCCESS**, dokončen 19:46:45 SELČ. `uat_schvaleno=false`, `importovat_krouzky=false`. Ověřená záloha a ochrana soukromého klíče HTTP 403. Nové migrace tento balík nemá. Přímé spojení CI runneru na web timeoutovalo; standardní serverový fallback ověřil HTTP 200, následně také přihlášený browser načetl aplikaci.
- Browser readiness 19:46:31: přesný SHA `313cd61b`, soukromé úložiště PASS, celková lidská UAT brána nadále BLOCKED dle chybějících předpokladů.
- **Hledání osob PASS:** opakovaný původně chybující dotaz `TEST-PR137` vrací jednu správnou archivovanou syntetickou osobu. [Produkční důkaz](production-identity-search.png) je omezen na záhlaví a výsledek hledání; nezahrnuje osobní údaje žadatelů níže.
- **Auditní hledání PASS:** stejný text vrátí odkaz na správnou osobu a její historii.
- **Plánovač PASS:** filtry Vše, tři staré plány; opravený odkaz otevře skutečný plán v týdnu 25.–31. 5. 2026. Náhled „testovací oznámení“ obsahuje české znaky, nikoli entity. [Produkční důkaz](production-pending-plan.png).
- Další kontrola: historická testovací soupiska obsahuje stejné ukončené členství jako karta; obrazovka sportovní činnosti vysvětluje odvození z uzavřené docházky.
- [Export lokálního protokolu](acceptance-hub-export.md) byl stažen přes UI a zkontrolován před přidáním do Gitu. Nejde o účetní export ani potvrzení lidské přejímky.

## Nezaměňovat s dokončenou lidskou přejímkou

Produkční data neposkytují reprezentativní vzorek všech tréninkových disciplín a stavů.
Rodinné přihlášení, skutečná trenérská omezení a všechny zápisové varianty nebyly na produkci zkoušeny.
Platí předchozí izolované důkazy a jejich meze; lidských 43 scénářů a převzetí účetní zůstávají otevřené.
