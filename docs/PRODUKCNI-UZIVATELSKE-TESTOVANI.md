# Produkční uživatelské testování

Aktualizováno: 7. 10. 2026, Europe/Prague

Pro toto UAT je cílovou produkční aplikací <https://kis.kovopraha.cz/>. Staré
nasazení `data.kovopraha.cz/evidence` se stále používá, ale současný GitHub
deploy workflow na něj nemíří. Nepovažujte proto stav starého nasazení za důkaz
verze nebo funkčnosti KIS a během tohoto testu mezi adresami nepřecházejte.

Hlavním pracovním dokumentem je
[`JEDNODUCHY_MANUAL_PRODUKCNIHO_TESTOVANI_2026-09.pdf`](../output/pdf/JEDNODUCHY_MANUAL_PRODUKCNIHO_TESTOVANI_2026-09.pdf).
Obsahuje 20 navazujících příběhů pro všechny pracovní pozice, rodiče,
osmileté děti a veřejnost. Každý příběh propojuje přípravu dat, jejich použití,
kontrolu výsledku a jednoduchou kontrolu oprávnění.

Starší podrobný technický protokol
[`UZIVATELSKE_TESTOVANI_SCENARE_2026-09.pdf`](../output/pdf/UZIVATELSKE_TESTOVANI_SCENARE_2026-09.pdf)
zůstává pouze jako interní podklad; pro běžné testery není určen.

## Testovací osoby a reálné provozní kroky

- `Tester Karel` - rodič, `tester.karel@velocota.com`;
- `Ema Tester` - osmileté dítě Karla, omezené přihlášení `tester.ema`;
- `Tester Petra` - druhý rodič, `tester.petra@velocota.com`;
- `Adam Tester` - dítě Petry, omezené přihlášení `tester.adam`;
- `Tester Správce` - pracovní účet pro postupné přepnutí všech osmi pozic.

Přesné založení, bezpečné uložení hesla, fixture data a cílená deaktivace jsou
v [`PRODUKCNI-UAT-UCTY-A-DATA.md`](PRODUKCNI-UAT-UCTY-A-DATA.md).

Test zahrnuje skutečné doručení zpráv do e-mailového koše domény
`@velocota.com` a skutečné malé bankovní převody přes zobrazený QR kód.
Potvrzení platby se provádí až podle skutečného bankovního záznamu.

Stripe má v manuálu samostatnou cestu, která se provede až po bezpečném
zapnutí testovacího režimu na produkční doméně. Současný workflow přijímá jen
testovací `sk_test`/`pk_test` klíče a kontroluje nepřítomnost live páru. Nejde
tedy ještě o skutečné stržení z karty; live režim vyžaduje samostatné rozhodnutí.

## Povinná kontrola před zápisem

1. V GitHub Actions otevřete poslední úspěšný běh **Nasadit produkci**.
2. Zapište jeho run ID a commit.
3. V kroku kontroly Variables musí být:
   - `APP_HOST=kis.kovopraha.cz`;
   - `WEB_URL=https://kis.kovopraha.cz`;
   - `REMOTE_DIR=kis.kovopraha.cz`.
4. Pokud nasazený commit není schválenou verzí pro UAT, provádějte pouze
   read-only kontroly. Zapisovací scénáře označte `BLOCKED`.

Aktuální schválený release z 7. 10. 2026 nasadil běh
[`37636084395`](https://github.com/KovoPraha/evidenceTreninku/actions/runs/37636084395)
z commitu `49c38c72c6704826182aff7f59855f47b5818bb8`. Záloha, migrace, aktivace i
serverový HTTP smoke byly zelené a release má `uat_schvaleno=true`.
`var/deployment.json` je z veřejného webu záměrně nedostupný (HTTP 403);
kontroluje se přes chráněný workflow a oprávněnou serverovou diagnostiku.

Kontrola připravenosti po konečném nasazení
[`37636447561`](https://github.com/KovoPraha/evidenceTreninku/actions/runs/37636447561)
vrátila `ready=true`: oba rodiče i děti, správce s osmi pozicemi, TEST produkty,
programová nabídka, placená i bezplatná akce, lekce, trénink, kalendář, testovací
Stripe, bankovní vlastník a inbox byly připravené. Testovací okno končí
8. 10. 2026 ve 20:00 Europe/Prague.

## Produkční průchod oprav z dokumentu testera

Průchod byl proveden 7. 10. 2026 v přihlášeném produkčním prohlížeči. Nešlo jen
o kontrolu HTTP 200. Výsledky jednotlivých bodů:

1. potvrzená klubová akce má otevřené přihlašování; plánovaná akce nabízí
   společnou volbu „Potvrdit akci a otevřít přihlašování“ - **PASS**;
2. interní plánovaný trénink lze uložit bez KIS soupisky; běžná evidenční
   skupina zůstává povinná - **PASS**, ověřeno TEST tréninkem na 16. 10. 2026;
3. přehled všech výkazů zobrazuje skutečná data i výslovný prázdný stav,
   nikoli prázdnou stránku - **PASS**;
4. měsíční plány jsou přímo dostupné ze správy členských předpisů - **PASS**;
5. výjimka plánu při prázdné soupisce zobrazí srozumitelný prázdný stav a odkaz
   ke kontrole soupisky - **PASS pro prázdný stav**; pozitivní výběr člena nebyl
   proveden, aby nevznikl trvalý finanční předpis;
6. tmavá karta hromadných sazeb má po opravě nadpis, popis i kód v bílé barvě
   `rgb(255, 255, 255)` - **PASS**;
7. tmavá karta kreditních období má nadpis i popis v bílé barvě
   `rgb(255, 255, 255)` - **PASS**;
8. produkt 247 má v každé ze dvou nabídek právě jedno pole účastníka - **PASS**;
9. odstranění zastaralé položky košíku je automaticky pokryté, ale celý
   produkční cyklus zavřít registraci → košík → znovu otevřít nebyl proveden -
   **PARTIAL**;
10. cesta kategorie „Kroužky › Dětské“ se zobrazuje jednou a není zaměněná s
    jinou kategorií Dětské - **PASS**.

Oprava kontrastu je v
[#43](https://github.com/KovoPraha/evidenceTreninku/pull/43). Lokálně i v CI
prošlo 840 testů / 11 751 kontrol a oba integrační běhy MariaDB 10.3 a 11.4.

Změny určené k novému průchodu testerů jsou shrnuté v
[`UAT-OPRAVY-2026-10-07.md`](UAT-OPRAVY-2026-10-07.md).

## Testovací identity

- Pro běžný test použijte přívětivé identity uvedené v manuálu: Tester Karel,
  Tester Petra, Ema Tester, Adam Tester a Tester Správce.
- Pracovní účet `Tester Správce` (`tester.spravce@velocota.com`) založte jako
  běžný auditovaný pracovní účet a přidělte mu všech osm pozic. Technický účet
  `kis-superadmin-test@velocota.com` se pro nové testovací běhy nepoužívá,
  protože jeho adresa koliduje s existujícím veřejným účtem; hesla se testerům
  nepředávají v dokumentu.
- Záznamy označujte krátkým a čitelným prefixem `TEST -`, například
  `TEST - Cyklistický kroužek pro děti`. Nepoužívejte náhodné kódy ani skutečná
  osobní data členů.
- Přátelské účty z manuálu se po testu deaktivují jednotlivě v administraci.
  Automatický cleanup drillu rozpoznává jen technické účty tvaru
  `kis-e2e-<číslo>@velocota.com`, nikoli `tester.karel@velocota.com`.

## Schvalovací brány

- `G1` - běžné reverzibilní produkční zápisy s prefixem TEST a popsaným úklidem.
- `G2` - pouze syntetický importní náhled bez propagace, cutoveru a zápisu do
  kanonických osob.
- `G3` - skutečné e-maily do koše `@velocota.com`, malé bankovní úhrady a
  případné skutečné vratky podle manuálu. Potvrzení probíhá až podle banky.

Veřejné testovací nabídky musí začínat `TEST -`, být zveřejněné jen během
dohodnutého okna a po pořízení důkazu ihned skryté.

## Bezpečný úklid

1. Deaktivujte dočasné pracovní a sportovní účty podporovanou administrací.
2. Skryjte TEST produkty a programy, vypněte kupóny, zrušte otevřené rezervace a
   uzavřete testovací termíny.
3. Účty Tester Karel, Tester Petra a Tester Správce deaktivujte jednotlivě v
   administraci. Dětské přístupy Tester Ema a Tester Adam rovněž ukončete
   podporovanou správou přístupů.
4. Dokončené objednávky, platby, vratky a auditní události nemažte. Musí zůstat
   dohledatelné a účetně vypořádané.
5. Na produkci nikdy nespouštějte localhost seed/reset, ostrý KIS cutover,
   hromadný import, CRON ani deploy jako vedlejší krok uživatelského testu.
