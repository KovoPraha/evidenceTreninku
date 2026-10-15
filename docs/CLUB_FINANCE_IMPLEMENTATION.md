# Finanční saldo sportovce – implementace 2026-10-15

## Rozsah

- Saldo sportovce spojuje neuhrazené členské předpisy (včetně předpisů za účasti) a platné, nezaplacené objednávky e-shopu v CZK. Zrušená a expirovaná objednávka není vymahatelný dluh.
- Příchozí platbu zapisuje hospodář teprve po ověření bankovního výpisu. Jedinečná reference brání opakovanému zápisu. Částka se přiděluje nejstarším předpisům a bankovním objednávkám; zbytek je přeplatek. Při zrušení částečně uhrazené položky se její příděl znovu počítá jako dostupný přeplatek, zatímco položka vyžadující vratku zůstává vázaná do jejího vyřešení.
- Měsíční předpisy zůstávají samostatné. Roční úhrada je jedna potvrzená bankovní platba rozepsaná do 12 měsíčních předpisů. Volitelná sleva (0–20 % v administraci) snižuje každý jednotlivý předpis; nelze ji zpětně použít, pokud už některý měsíční předpis existuje.
- Nově vystavené měsíční předpisy čerpají již ověřený přeplatek. Úplně pokrytý předpis je rovnou uhrazen, částečně pokrytý zůstává čekající se sníženým otevřeným zůstatkem. Jednu bankovní platbu nesmí následně samostatný potvrzovací tok potvrdit podruhé.
- Při nákupu se objednávka přiřadí jedinému schválenému sportovci účtu automaticky. U účtu s více sportovci je nutný výběr. Košík s položkami pro různé sportovce se musí rozdělit na více objednávek; to brání přenesení dluhu sourozence na nesprávný profil. Věk není překážkou vlastního nákupu.

## Pravidla přihlášek

- Po migraci jsou omezení **vypnutá**. Hospodář je může zapnout a měnit až s auditovaným důvodem a výslovným potvrzením.
- Výchozí lhůta je 14 celých dní po splatnosti. Při splatnosti 1. října se částka započte do omezení 16. října. Výchozí limit je **více než 2 000 Kč** po lhůtě pro novou přihlášku; existující účast, odhlášení a historie zůstávají přístupné.
- Omezení se ověřuje na serveru při přihlášení na akci, potvrzení účasti v kalendáři, odpovědi „zúčastním se“ na trénink, rezervaci velodromu a při nákupu programů/placených účastí. U rodinných účtů se posuzuje konkrétní sportovec.
- Výchozí hranice 5 000 Kč slouží pro viditelné upozornění ve sportovním profilu. Termíny tréninků se neskrývají: skrytí by znemožnilo pochopit, proč přihláška chybí, a komplikovalo opravu platby.

## Bezpečnost a provozní podmínky

- Migrace: `20261015130000_club_financial_balance.php`. Nové tabulky a přiřazení e-shopové objednávky jsou zahrnuty v kontraktu zálohy.
- Fio import zůstává read-only/review-first. Tento modul nespouští bankovní synchronizaci ani automatické potvrzení pohybu. Reference již použitá pro hromadnou platbu nesmí projít druhým potvrzením ve Fio administraci; je-li pohyb ve výpisu znám, kontroluje se datum a částka.
- Staré objednávky bez explicitního přiřazení se počítají jen tehdy, má-li kupující dnes právě jednoho schváleného sportovce. Staré objednávky sdíleného účtu a anonymní nákupy se **nepřiřazují odhadem podle jména ani e-mailu**. Před zapnutím omezení je nutné je provozně projít. Nové objednávky mají přiřazení uložené napevno.
- Přeplatek je klubové saldo; u e-shopu se neproměňuje sám o sobě v potvrzenou objednávku. Je třeba ověřit a přiřadit platbu v hospodářském toku. E-shopová objednávka bez úhrady může mezitím expirovat a uvolnit rezervované zboží.
- Nasazení kódu a migrace jsou oddělené od zapnutí omezení i od potvrzování skutečných bankovních plateb. Před aktivací proveďte kontrolu sald, sourozeneckých objednávek a UAT s povolenými testovacími identitami. Lokální prohlížečový průchod je popsán v `CLUB_FINANCE_BROWSER_UAT_2026-10-15.md`.

## Ověření

`php vendor/bin/phpunit --configuration phpunit.xml.dist tests/Integration`

Nové integrační testy pokrývají lhůtu, zrušené/expirované položky, přiřazení společného účtu, částečnou úhradu, přeplatek, uvolnění zrušeného přídělu a zvýhodněnou roční platbu.
