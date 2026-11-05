# Ostré platby Fio a SumUp — aktivace 5. 11. 2026

Vlastník výslovně schválil produkční Fio/SumUp bez provider sandboxu,
použití stávajícího KIS_SUMUP_API_KEY a kartu pro všech **223 aktivních produktů
pro nové objednávky**. Změna hesla a T07 jsou nadále vynechané.
Jde o ověřenou aktivaci a import; skutečná zaplacená úhrada se zde netvrdí.

## Opravy, kontrola a vydání

[PR #308](https://github.com/KovoPraha/evidenceTreninku/pull/308) ověřuje před
prvním potvrzením SumUp autoritativní transakční detail, identitu, částku,
měnu, obchodníka a historii vratky/chargebacku. Fio automatika odmítá pohyb
zaúčtovaný před vznikem platby; ruční zdůvodněné posouzení je zachované.
Podrobnosti a červené regrese obsahuje [implementační příprava](IMPLEMENTATION.md).

Všech 11 kontrol PR PASS. [Linux CI 42005606415](https://github.com/KovoPraha/evidenceTreninku/actions/runs/42005606415)
i [chráněný deploy 42006918844](https://github.com/KovoPraha/evidenceTreninku/actions/runs/42006918844)
prošly: **2014 testů / 38866 assertions**, bez skipů. Místní cílená sada
[57/413](targeted.xml), PHPStan a změněný styl PASS; MariaDB 10.3/11.4 PASS.
Sloučený strom byl porovnán s ověřeným headem. Živé SHA určuje CURRENT_STATE;
log aktivace potvrzuje nasazení tří změněných integračních souborů a CLI konfigurátoru.

Čerstvá záloha má 347 tabulek / 2 triggery. [Izolovaná obnova 42008819858](https://github.com/KovoPraha/evidenceTreninku/actions/runs/42008819858)
ověřila checksum, všechny počty tabulek i triggery proti manifestu.
Produkční databáze se neobnovovala. Schéma current / pending [], ledger 150,
dosavadní variabilní symboly beze změny. Checksum je v [souhrnných důkazech](activation-evidence.json).

## Fio

[Čtecí kontrola 42000593900](https://github.com/KovoPraha/evidenceTreninku/actions/runs/42000593900)
ověřila token a správný bankovní účet. Import byl zapnutý samostatně,
nejprve bez automatického potvrzování. Jedenáct omezených oken pokrylo
**21. 9.–5. 11. 2026**: 75 načtených záznamů, **46 jedinečných uložených pohybů**,
29 duplicit, 45 ke kontrole a jeden ignorovaný. Žádný nebyl potvrzen jako úhrada.
Nejednoznačné pohyby vyžadují účetní posouzení; staré pohyby se nevynucují do nových plateb.

Po novém vydání byla [zapnuta automatika 42008096371](https://github.com/KovoPraha/evidenceTreninku/actions/runs/42008096371).
[Následující živý worker 42008496784](https://github.com/KovoPraha/evidenceTreninku/actions/runs/42008496784)
prošel: čtyři načtené duplicity, nula nových zápisů, nula potvrzených úhrad,
caught_up=true. Používá se čtecí periods API bez posunu bankovní zarážky a bez
platebních příkazů. Dotazy dodržely minimální rozestup Fio.

Workflow Synchronizovat Fio pohyby je active; KIS_FIO_SCHEDULE_ENABLED=true
a cron `*/10 * * * *`. **První přirozeně vyvolaný běh po aktivaci zatím nebyl
pozorován.** Ruční workflow dokládá funkční živý worker, nikoli vykonání plánovače.
Poslední pozorovaný starší schedule 41995203532 byl skipped před aktivací.
Následně zkontrolovat event=schedule a úspěšné dokončení; bez toho neoznačovat
pravidelný provoz za dlouhodobě ověřený.

## SumUp a přesný rozsah katalogu

[Čtecí ověření 42006624742](https://github.com/KovoPraha/evidenceTreninku/actions/runs/42006624742)
potvrdilo ostrou online kartu CZK a právo číst transakce.
[Aktivace 42007599345](https://github.com/KovoPraha/evidenceTreninku/actions/runs/42007599345)
vytvořila zálohu konfigurace a potvrdila SumUp enabled, správného obchodníka a URL.
Klíče ani obsah bankovních/SumUp transakcí se neukládají do dokumentace.

[Provozní nástroj #309](https://github.com/KovoPraha/evidenceTreninku/pull/309)
použil přesný čtecí manifest a existující kanonickou službu s auditem.
První preview pod dodanou adresou správně odmítlo účet bez finančního oprávnění;
nevznikl žádný zápis ani nové oprávnění. Následné preview pod existujícím
testovacím správcem [42010374715](https://github.com/KovoPraha/evidenceTreninku/actions/runs/42010374715)
ověřilo přesných 223 produktů. [Schválená dávka 42010503021](https://github.com/KovoPraha/evidenceTreninku/actions/runs/42010503021)
změnila **223/223** přes kanonické transakce a audit; všech osm starých plateb
si zachovalo původní režim. Nešlo o vytváření nových objednávek či úhradu.

[Doplnění #310](https://github.com/KovoPraha/evidenceTreninku/pull/310) sjednocuje
oprávnění pomocníka se stránkou: existující finance_manager nebo superadmin.
Syntetická sada **5 testů / 35 assertions PASS** ověřuje rozsah, staré platby,
ceny, přerušení/bezpečné pokračování, audit bez duplicit a odmítnutí ostatních pozic.
Provozní nástroje nevyžadují nové nasazení runtime. [Pravidla dávky](PRODUCT-POLICY-PLAN.md).
Oba provozní PR jsou sloučené; #310 prošel všemi 11 kontrolami,
[Linux CI 42010440381](https://github.com/KovoPraha/evidenceTreninku/actions/runs/42010440381)
ověřilo 2019 testů / 38901 assertions bez skipů, včetně MariaDB 10.3/11.4.

[Nezávislé čtecí ověření 42010706578](https://github.com/KovoPraha/evidenceTreninku/actions/runs/42010706578)
potvrdilo následující stav i opakovaný přístup k ostrému API:

| Rozsah | Počet | Platební režim |
| --- | ---: | --- |
| Aktivní produkty | 223 | Převod + SumUp |
| Koncepty | 2 | Pouze převod |
| Neaktivní produkty | 67 | Pouze převod |
| Dosavadní platby ve všech stavech | 8 | Pouze převod, původní snapshot |

Karta platí pro nové způsobilé objednávky přihlášeného zákazníka, pokud ji
povolují všechny položky košíku. Současný runtime nenabízí tuto kartu hostům
ani samostatným členským předpisům. Samostatné termíny velodromu se touto dávkou neměnily.

## Závěrečné ověření a otevřené meze

[Čtecí audit 42010713390](https://github.com/KovoPraha/evidenceTreninku/actions/runs/42010713390)
potvrdil živé vydání, Fio import/auto-confirm a SumUp true. Stripe/Velocota false,
Packeta nedefinovaná, KIS canonical writes false. Soukromé migrační nálezy a
ledger 150 se nezměnily. Konfigurace se změnila výslovně schválenou aktivací.
Historické old_source/new_source manifesty nejsou úplnou inventurou tohoto vydání.

Anonymní GET [před](pre-http.json) a [po](post-http.json): **54/54 PASS**,
včetně katalogu, produktů, přístupových hranic a GET webhooku 405.
Skutečný Chrome přesměroval nastavení plateb na login; přihlášené platební UAT
se proto netvrdí. Žádné heslo se nečetlo ani neměnilo.

Skutečná platba kartou včetně finálního webhooku, zaúčtování a následného
vypořádání nebyla provedena. Ani Fio se bez vhodné jednoznačné platby
neoznačuje jako živé E2E párování PASS. Tyto body a první přirozený plánovaný
běh zůstávají k doložení běžným provozem nebo konkrétní schválenou testovací úhradou.
Pro okamžité zastavení karty slouží chráněné configure-sumup-production.yml / disable;
Fio import a automatika mají vlastní konfigurační přepínače. Již zapsané úhrady
se vypnutím integrace automaticky nevracejí.
