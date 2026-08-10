# Produkční UX a funkční audit KIS — 10. 8. 2026

## Rozsah a způsob ověření

Audit proběhl přímo na `https://kis.kovopraha.cz` v rolích nepřihlášeného návštěvníka, registrovaného zákazníka a testovacího administrátora. Zápisy byly po každém důležitém kroku kontrolovány v produkční databázi přes phpMyAdmin. Všechny vytvořené osoby, názvy a poznámky nesou označení `UXTEST`; nebyla změněna žádná objednávka, rezervace ani účet skutečného člena.

- HTTP průchod: 78 unikátních veřejných a administračních stránek.
- Před opravou: 77 stránek odpovědělo HTTP 200 bez fatální chyby; `kis_rollover_a06_admin.php` odpověděla 404, přestože je soubor verzovaný.
- Provedené scénáře: registrace, ověření e-mailu, přihlášení, zapomenuté heslo, veřejný e-shop a košík, administrace katalogu, trénink, sportoviště a rezervace, individuální lekce, skupina, trenér, rodinný přehled, týdenní souhrn a soukromý kalendář.
- Kontrola databáze: existence i výsledný stav účtů, tréninku, účasti, rezervace, individuálních lekcí, skupiny, trenéra, košíku, publikace produktu a zákaznických voleb.

## Ověřené funkční scénáře

### Účet zákazníka

- Registrace účtu a vlastní osoby proběhla úspěšně.
- Ověřovací zpráva dorazila do catch-all schránky `@velocota.com`; odkaz účet správně ověřil.
- Přihlášení ověřeného zákazníka funguje a sportovní přehled ukazuje pouze schválený vlastní profil.
- Žádost o obnovu hesla zobrazila neutrální potvrzení a vytvořila jednorázový DB token. Samotnou zprávu se kvůli opakovanému timeoutu webmailu nepodařilo vizuálně otevřít — bod pro ruční kontrolu.
- Zákaznický přehled správně zobrazil testovací trénink a délku 1,25 h.
- Zapnutí a následné vypnutí týdenního souhrnu se správně propsalo do `family_weekly_summary_preferences` (`enabled=0` po úklidu).
- Vytvoření soukromého kalendáře fungovalo a vydalo jednorázově zobrazený token. Po testu byl odkaz odvolán a auditní událost zachována.

### Tréninky

- Přes administrační formulář byl vytvořen trénink `treninky.id=1591` s datem 10. 8. 2026, délkou 1,25 h, kategorií silnice, testovací skupinou, účastníkem i trenérem.
- Databázové vazby účastník–trenér–tag–skupina odpovídaly formuláři.
- Stejný trénink se bez prodlevy objevil zákazníkovi v docházce.

### Sportoviště a rezervace

- Vytvořeno testovací sportoviště `sportoviste.id=7` a rezervace `rezervace_sportovist.id=7` na 11. 8. 2026 21:00–22:00.
- Rezervace se zobrazila v kalendáři a DB odpovídala kapacita, čas, trenér i poznámka.
- Rezervace byla přes UI zrušena a sportoviště přes UI odstraněno; výsledné počty obou testovacích záznamů jsou nula.

### Individuální lekce

- Formulář původně při nezaškrtnutém opakování vytvořil dva termíny (`id=9` a `id=10`) místo jednoho.
- Příčina: skrytý výběr počtu týdnů odesílal výchozí hodnotu 1, i když přepínač opakování nebyl zapnutý.
- Oba testovací termíny byly zrušeny. Oprava nově vyžaduje explicitní zaškrtnutí a vypnutý výběr se vůbec neodesílá.

### E-shop

- Veřejný produkt `id=93`, varianta `SKU=504`, lze vložit do košíku.
- Změna množství 1 → 2 se propsala správně a autoritativní součet byl 200 Kč.
- Neplatný slevový kód skončil srozumitelnou chybou.
- Administrační publikace produktu fungovala; nový veřejný popis je `Bidon KOVO PRAHA o objemu 500 ml.` a DB obsahuje tři auditní události publikace.
- Košík byl po testu vyprázdněn; testovací položky v košíku: 0.
- V databázi dosud není žádná e-shopová objednávka. Checkout správně selže bezpečně, protože produkce nemá kompletní `SHOP_BANK_*` konfiguraci.

## Nalezené a opravené problémy

1. **Nechtěné opakování individuální lekce.** Opraveno na straně formuláře i serveru; přidán regresní test.
2. **Nejednoznačné přihlášení trenérů se stejným e-mailem.** Produkce obsahuje dvě skupiny historických duplicitních aktivních e-mailů. Přihlášení dříve vybíralo libovolný první řádek. Nyní ověří všechny přesné kandidáty a přijme pouze právě jednu shodu hesla; více shod skončí bezpečným odmítnutím.
3. **Vznik dalších duplicitních trenérských e-mailů.** Administrace nyní normalizuje a validuje e-mail a blokuje nový/změněný e-mail používaný jiným aktivním trenérem. Existující historický profil lze upravit bez změny e-mailu.
4. **Staré odkazy `data.kovopraha.cz/evidence`.** Náhled týdenního souhrnu, soukromý kalendář, připomínky plateb a výchozí push notifikace používaly starou doménu. Všechny klikatelné odkazy nyní čerpají z jediné kanonické `APP_BASE_URL`; kalendářové UID zůstalo beze změny kvůli stabilitě identity událostí.
5. **Chybějící upozornění na nefunkční checkout.** Administrace e-shopu nyní výrazně ukáže neúplné bankovní nastavení a deploy preflight vypíše totéž jako varování.
6. **404 administrační stránky A06 po deployi.** Deploy nově po aktivaci explicitně ověří přítomnost `kis_rollover_a06_admin.php`.
7. **Přístupnost a hierarchie formulářů.** Klíčové testované stránky dostaly skutečný nadpis H1, vazby popisků na pole a čitelné názvy ikonových tlačítek. Upraveny byly přihlášení, registrace, obnova hesla, individuální lekce, sportoviště a rezervace.
8. **Zavádějící text katalogu.** Administrace už netvrdí, že veřejný obchod a checkout neexistují; text odpovídá skutečnému provozu.

## Body pro ruční kontrolu / rozhodnutí

### Blokátor objednávkového procesu

Pro kompletní test `objednávka → platební údaje → admin stav objednávky → DB` je nutné dodat skutečný klubový IBAN, BIC, název účtu a počet dnů splatnosti. Testovací či vymyšlený IBAN nebyl do produkce záměrně vložen. Dokud údaje chybí, checkout zůstává bezpečně vypnutý.

### Historické duplicitní trenérské účty

- `vzidek98@gmail.com`: 5 aktivních profilů, 5 různých uložených hashů, 142 vazeb na tréninky.
- `n.andrlova7@gmail.com`: 3 aktivní profily, 3 různé uložené hashe, 206 vazeb na tréninky.

Kód už nepoužije náhodný řádek, ale samotné slučování účtů vyžaduje rozhodnutí správce podle identity skutečných osob. Záznamy nebyly automaticky měněny.

### UX backlog

- Seznam sportovců má přibližně 10 096 řádků a 101 stran po 100; pro běžnou práci je vhodné doplnit rychlé filtrování a užší výchozí pohled.
- Správa všech tréninků vykresluje přibližně 1 463 formulářů v jediné odpovědi; vhodné je stránkování nebo načítání detailu až po rozkliknutí.
- Některé starší administrační stránky stále nemají hlavní H1. Kritické formuláře z tohoto průchodu byly opraveny, zbytek je vhodný jako samostatný přístupnostní úkol.
- Produkční transport týdenního souhrnu je v UI výslovně označen jako neaktivní; zapnutí preference tedy zatím neznamená skutečné odeslání zprávy.
- Doručení e-mailu pro obnovu hesla je třeba jednou ručně potvrdit ve webmailu; DB token a registrační e-mail byly ověřeny.

## Testovací data a úklid

- Zachováno pro dohledání: zákaznický profil `sportovci.id=10262`, trénink `treninky.id=1591`, dvě zrušené individuální lekce `id=9,10` a auditní události.
- Odstraněno: dočasné sportoviště, rezervace, skupina, trenérský CRUD profil a položky košíku.
- Odvoláno/vypnuto: soukromý kalendář a týdenní souhrn testovacího zákazníka.
- Testovací přihlašovací účty budou po nasazení a produkčním ověření deaktivovány a jejich relace zneplatněny.

## Automatizované ověření

Regresní testy pokrývají opakování individuální lekce, více kandidátů trenérského loginu, blokování duplicitního e-mailu, kanonické odkazy, bankovní varování, deploy kontrakt a přístupnost upravených formulářů. Celá lokální sada před nasazením: **511 testů, 4 498 kontrol, bez chyby**. Produkční SHA bude doplněna po úspěšném nasazení.
