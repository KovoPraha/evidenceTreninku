# Produkční uživatelské testování

Aktualizováno: 22. 9. 2026, Europe/Prague

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
- `KIS testovací superadministrátor` - pracovní účet pro postupné přepnutí
  všech osmi pozic (v manuálu označený jako Tester Správce).

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

Při kontrole 22. 9. 2026 nasadil běh `35729597409` commit
`0135e34e47243ba983ee9239ab67087a5b6f35f1`; záloha, migrace, aktivace i
serverový HTTP smoke byly zelené. Tento údaj je historický důkaz konkrétního
release. Před mutujícími testy vždy ověřte aktuální běh a `var/deployment.json`.

## Testovací identity

- Pro běžný test použijte přívětivé identity uvedené v manuálu: Tester Karel,
  Tester Petra, Ema Tester, Adam Tester a KIS testovací superadministrátor.
- Pracovní účet `Tester Správce` založte jako běžný auditovaný pracovní účet a
  přidělte mu všech osm pozic. Technický účet
  `kis-superadmin-test@velocota.com` může sloužit pouze k počátečnímu založení;
  jeho heslo se testerům nepředává v dokumentu.
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
