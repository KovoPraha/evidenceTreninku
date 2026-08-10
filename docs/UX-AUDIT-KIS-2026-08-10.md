# Produkční UX a funkční audit KIS — 10. 8. 2026

## Závěrečný stav po rozšířeném testování

- Implementační commit `34c185b64643e531ced4ac53004b23aa11593cbb` byl pushnut na `main` a úspěšně nasazen workflow `Nasadit produkci`, run `31409725853`.
- Produkční nasazení před změnou vytvořilo ověřenou zálohu `evidence_2026-08-10_163614_62fd5e47.sql.gz`: 154 tabulek, 2 triggery, SHA-256 `08f7f32aa94847f5e77ee78f09f19913bdfe93a9127396d66cba503abf6e51c2`.
- Přihlášený produkční smoke po nasazení ověřil HTTP 200 pro administrátorský dashboard, správu katalogu, objednávky, zákaznický kalendář a správu osob. Nové H1, popisky formulářů a responzivní navigace jsou v produkčním HTML přítomné.
- Webhook na nepovolený GET vrací HTTP 405 a `Allow: POST`.
- Produkce zůstává bezpečně fail-closed pro bankovní checkout, protože nejsou kompletně nastaveny skutečné `SHOP_BANK_*` údaje.

## Sestava oprav provedených v závěrečné vlně

1. **Přetékající administrátorská navigace.** Navbar se nyní skládá dříve (`navbar-expand-xxl`) a v úzkém pásmu 1400–1439 px se zmenší globální vyhledávání. Ověřené šířky 390, 1366, 1400, 1440, 1536 a 1920 px mají nulový horizontální přesah.
2. **Chybějící hlavní nadpisy.** Dashboard, formulář tréninku, správa sportovců a zákaznický kalendář používají skutečné H1 při zachování původní vizuální velikosti.
3. **Nepropojené popisky zákaznických formulářů.** Doplněny vazby `for`/`id` v kalendáři, žádosti o propojení osoby a přihlášení sportovce.
4. **Ikonová tlačítka bez čitelného názvu.** Doplněny přístupné názvy navigace měsíc/týden, správy sportovišť, editace/smazání skupiny, zrušení lekce a zamítnutí rezervace.
5. **Nejasná pole v hustých administrátorských tabulkách.** Doplněny názvy filtrů a řádkových voleb ve správě sportovců, katalogu, objednávkách a dynamických měřeních tréninku.
6. **Mobilní záhlaví e-shop administrace.** Akční tlačítka se nyní zalamují a nevytvářejí vodorovný přesah.
7. **Regresní ochrana.** Přidán `UxAccessibilityWiringTest` se 4 testy a 35 kontrolami pro klíčové nadpisy, popisky, názvy akcí a responzivní navigaci.

## Rozsah a způsob ověření

Audit proběhl přímo na `https://kis.kovopraha.cz` v rolích nepřihlášeného návštěvníka, registrovaného zákazníka a testovacího administrátora. Zápisy byly po každém důležitém kroku kontrolovány v produkční databázi přes phpMyAdmin. Všechny vytvořené osoby, názvy a poznámky nesou označení `UXTEST`; nebyla změněna žádná objednávka, rezervace ani účet skutečného člena.

- HTTP průchod: 78 unikátních veřejných a administračních stránek.
- 77 běžných stránek odpovědělo HTTP 200 bez fatální chyby. `kis_rollover_a06_admin.php` odpověděla 404 správně: jde o lokální akceptační scénář, který je na produkci záměrně skrytý kontrolou loopback prostředí.
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
6. **Upřesnění kontroly A06.** Soubor je po deployi přítomen, ale produkční HTTP 404 je očekávaná bezpečnostní vlastnost lokálního akceptačního hubu, nikoli provozní chyba. Deploy kontroluje přítomnost souboru; jeho obsah zůstává mimo loopback nepřístupný.
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
- Závěrečný automatický úklid lokálních mutačních scénářů skončil s nulou vlastněných trenérů, tréninků, lekcí, rezervací a žádostí o propojení. Bankovní testovací objednávka byla kanonicky stornována a označena `refunded`; Stripe sandbox platba byla skutečně refundována u Stripe a lokální objednávka zůstala v očekávaném stavu `refund_required`.
- Produkční testovací účty nelze bezpečně deaktivovat v aktuálně otevřené kartě phpMyAdmin: karta je přihlášena do databáze `velocotacom` se 202 tabulkami, zatímco deploy záloha potvrzuje, že KIS používá databázi `evidence` se 154 tabulkami. V `velocotacom` nejsou legacy KIS tabulky `verejni_uzivatele`, `treneri`, `sportovci`, `treninky` ani `individualni_lekce`; kvůli ochraně cizích dat tam nebyl proveden žádný zápis. Ruční úklid v databázi `evidence`: deaktivovat účty `verejni_uzivatele.id=5` a `treneri.id=47`, zvýšit jejich `session_version` a zkontrolovat případnou lekci `UXTEST postdeploy jediný termín 20260810`.

## Automatizované ověření

Regresní testy pokrývají opakování individuální lekce, více kandidátů trenérského loginu, blokování duplicitního e-mailu, kanonické odkazy, bankovní varování, deploy kontrakt a přístupnost upravených formulářů.

- Celá lokální sada bezprostředně před commitem: **515 testů, 4 533 kontrol, bez chyby**.
- Playwright funkční acceptance: **8/8 scénářů**; zákazník/admin, bankovní objednávka, kupón, členská cena, Stripe sandbox Checkout, idempotentní webhook, program, klubová událost, velodrom, storno, expirace a rate limit.
- Akceptační katalog: **40/40 scénářů A01–A10 a B01–B30**.
- Široký HTTP smoke: **161 endpointů**, žádná 5xx ani runtime chyba; očekávané ochranné odpovědi 400/403/404/405 zůstaly zachovány.
- UX/responzivní vlna: **49 kombinací stránky a viewportu**, nula chybějících H1, nula nepojmenovaných kontrol a nula horizontálních přesahů.
- Mutační CRUD vlna: **4/4 scénářů**; trenér, skupina, trénink a vazby, sportoviště, rezervace, individuální lekce, žádost o osobu, týdenní souhrn a soukromý kalendář. Všechny zápisy byly ověřeny SQL a vlastněná data uklizena.
- Databázové invarianty po bězích: 0 osiřelých položek objednávky, 0 osiřelých plateb, 0 duplicitních kódů objednávek, 0 duplicitních plateb k objednávce, 0 záporných součtů, 0 neplatných oken rezervací, 0 duplicitních účastníků a 0 aktivních expirovaných objednávek.
