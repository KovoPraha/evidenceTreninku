# Produkční nasazení a čtecí browser přejímka — 23. 10. 2026

**Kód R01–R44 je nasazený; plná provozní přejímka není dokončená.**
Aktuálně živé SHA `0b03b564dfa6b20817478848fa8721fea09ca082`, ověřené přímo přihlášeným prohlížečem. Závěrečný [deploy 39922198281](https://github.com/KovoPraha/evidenceTreninku/actions/runs/39922198281) SUCCESS. PR [#167](https://github.com/KovoPraha/evidenceTreninku/pull/167), [#168](https://github.com/KovoPraha/evidenceTreninku/pull/168), [#169](https://github.com/KovoPraha/evidenceTreninku/pull/169).

## Co bylo skutečně ověřené

- 1 572 testů / 31 989 assertions PASS na přesném aplikačním vydání; PHP 8.2, MariaDB 10.3 a 11.4 a bezpečnostní kontroly zelené. CI také skutečně obnovil 281 tabulek čisté testovací databáze; nejde o obnovení živé produkce.
- První produkční záloha 246 tabulek, druhá 329 tabulek po nových migracích; obě ověřené před aktivací. Migrace current; původní variabilní symboly zachované; import kroužků vypnutý. Druhé provisionování úložiště beze změny konfigurace, ověřená idempotence.
- 64 zaznamenaných browser pozorování nad 59 různými cestami; přihlášený vlastník se všemi pozicemi, přepnutí všech osmi pracovních kontextů. Jde hlavně o vykreslení, navigaci, čtení a konkrétní níže uvedené interakce, nikoli o 64 úplných obchodních scénářů.
- Účetnictví, doklady, opravy, exporty, výdaje, odměny, příspěvky, sklady, vratky, identity, souhlasy, zdravotní prohlídky, soupisky, sportovní evidence, programy a veřejné nabídky načtené bez nalezené fatální PHP/SQL chyby.
- Vyhledání „Bunda ACTIVE 23“ vrátilo jediný odpovídající produkt. Detail produktu 67 ukázal dostupnou velikost 128; přepnutí na vyprodanou 158 odstranilo akci přidání do košíku. Existující košík zůstal nedotčený.
- Přímý vstup do peněžní evidence z trenérské pozice byl odmítnut. Toto je test hranice aktivní pozice superadmina, nikoli důkaz všech oprávnění běžného omezeného účtu.
- Detail výdeje existující objednávky 13 se vykreslil včetně položek/historie. Chybný základní odkaz bez objednávky byl opraven a znovu přejat: nyní vede přes „Vybrat objednávku“ do seznamu s osmi odkazy na konkrétní výdeje.
- Přímý browser přístup ke klíči archivu vrací 403 Forbidden bez obsahu souboru. Běžné stránky katalogu, kroužků, tréninků, kalendáře a velodromu fungují v přihlášené relaci; samostatná anonymní relace nebyla součástí tohoto průchodu.

## Otevřené body podle priority

| Priorita | Bod | Co je doložené / další krok |
|---|---|---|
| P0 | Webové soukromé úložiště | CLI adresář existuje a zapisuje; webové `open_basedir` jej nepovoluje. Nastavení pochází z konstanty, nejde o env konflikt. [Přesný postup pro hosting](../../CLUB_STORAGE_HOSTING_BLOCKER.md). Do vyřešení a ověření nezahajovat vystavování PDF/archivů. |
| P0 před běžnou prací trenérů | Rozsahy svěřenců | 36 aktivních trenérů bez nových výslovných grantů. Věcně určit odpovědné trenéry a rozsah; nic neodvozovat automaticky z historie. |
| P0 před ostrým skladem | Počáteční sklad | 799 publikovaných variant bez nových skladových zůstatků; žádná nemá NULL původní množství. Neznamená to automatickou blokaci celého starého katalogu; nová evidence vyžaduje doloženou inventuru a ocenění. |
| P0 před pravidelnými předpisy | Členské příspěvky | Jeden současný plán bez nové dohody a rozhodnutí o částce. Vlastník musí potvrdit podklady, ne automaticky přepsat historii. |
| P1 | Dvě chybějící historické přílohy | Deploy hlásí candidates=0, already_private=0, missing=2, errors=0. Nedošlo k přesunu ani opravě chybějících originálů. Dohledat původní soubory. |
| P1 | Skutečná lidská a účetní přejímka | 43 připravených scénářů zůstává NEPROVEDENO člověkem, účetní ukázka NEPŘEVZATO. Tento browser smoke tyto stavy nepřepisuje. |
| P1 | Zápisová produkční UAT | Není oddělená testovací účetní/skladová evidence. Chybí také nastavení staršího UAT přehledu (dětské účty/vazby, termíny, inbox, kontrolor banky a okno). Nevytvářet fiktivní peníze ani doklady. |

Produkční finanční záznamy, skladové pohyby, objednávky, registrace a role osob nebyly při browser testu vytvářené ani upravované. Měnil se pouze aktivní pracovní kontext relace a lokální výběr/filtr. Nasazení samo přidalo schéma, kód a nastavení soukromého úložiště. Shoptet ani původní Evidence nebyly odstavené; žádné zprávy nebyly odeslané účetní, integrace či plánované importy nebyly zapnuté.

## Důkazy

[Strojově čitelný průchod](browser-checks.json), [stav produkční přejímky](readiness.jpg), [vyprodaná varianta](sold-out-variant.jpg). Záznam neobsahuje osoby, adresy, tajné hodnoty ani obsah finančních dokladů.

Browser občas vrátil časový limit při navigaci; aktuální stav byl ověřen novou observací nebo novou kartou stejného prohlížeče. V konzoli byly zprávy o zavřeném asynchronním message channelu, bez doložené vazby na aplikační chybu. Tyto problémy ovládání nebyly vydávané za chybu webu ani za úspěšný test bez následného ověření.

## Jednotlivá pozorování

| Cesta / kontext | Zjištění |
|---|---|
| `/uat_readiness_admin.php` | release PASS; private storage BLOCKED (directory/writable false) |
| `/club_cutover_admin.php` | GET form and scope notice rendered; no submission |
| `/provozni_prehled_admin.php` | GET rendered |
| `/sprava_treneru.php` | Načteno: bez nadpisu; bez nalezené fatální chyby |
| `/sprava_pracovnich_pozic.php` | Načteno: Pracovní pozice a rozcestníky, Odebrat konkrétní pracovní pozici, Ukončit přístup do systému, Superadmin; bez nalezené fatální chyby |
| `/eshop_payments_admin.php` | Načteno: Platby a vratky; bez nalezené fatální chyby |
| `/member_charges_admin.php` | Načteno: Členské předpisy; bez nalezené fatální chyby |
| `/member_fee_plans_admin.php` | Načteno: Plány členských příspěvků; bez nalezené fatální chyby |
| `/accounting_ledger_admin.php` | Načteno: Peněžní pohyby a pokladny; bez nalezené fatální chyby |
| `/accounting_documents_admin.php` | Načteno: Účetní doklady; bez nalezené fatální chyby |
| `/accounting_adjustments_admin.php` | Načteno: Opravy a vratky; bez nalezené fatální chyby |
| `/accounting_exports_admin.php` | Načteno: Účetní podklady; bez nalezené fatální chyby |
| `/club_expenses_admin.php` | Načteno: Výdaje, zálohy a vyúčtování; bez nalezené fatální chyby |
| `/club_rewards_admin.php` | Načteno: Výkazy a odměny; bez nalezené fatální chyby |
| `/financial_decisions_admin.php` | Načteno: Finanční rozhodnutí a podklady; bez nalezené fatální chyby |
| `/eshop_produkt_admin.php` | Načteno: Přehled produktů; bez nalezené fatální chyby |
| `/eshop_ceny_admin.php` | Načteno: Ceny produktů; bez nalezené fatální chyby |
| `/eshop_sklad_admin.php` | Načteno: Skladové zásoby; bez nalezené fatální chyby |
| `/eshop_inventory_admin.php` | Načteno: Fyzický sklad; bez nalezené fatální chyby |
| `/shop_returns_admin.php` | Načteno: Vratky, výměny a reklamace; bez nalezené fatální chyby |
| `/eshop_orders_admin.php` | Načteno: Objednávky; bez nalezené fatální chyby |
| `/eshop_assisted_sale_admin.php` | Načteno: Osobní prodej; bez nalezené fatální chyby |
| `/eshop_fulfillment_admin.php` | Původní vstup bez ID: holá chyba; opraveno a opakovaně ověřeno v závěrečném vydání níže. |
| `/shop_returns_admin.php` | Načteno: Vratky, výměny a reklamace; bez nalezené fatální chyby |
| `/eshop_notifications_admin.php` | Načteno: Fronta transakčních e-mailů; bez nalezené fatální chyby |
| `/family_weekly_summaries_admin.php` | Načteno: Týdenní rodinné souhrny; bez nalezené fatální chyby |
| `/sprava_sportovcu.php` | Načteno: Správa sportovců; bez nalezené fatální chyby |
| `/eshop_identity_admin.php` | Načteno: Účty, rodiče a sportovci; bez nalezené fatální chyby |
| `/registration_texts_admin.php` | Načteno: Registrační texty a souhlasy; bez nalezené fatální chyby |
| `/kis_rosters_admin.php` | Načteno: KIS – historické sezony a soupisky; bez nalezené fatální chyby |
| `/identity_reviews_admin.php` | Načteno: Ověření identity a zdrojové vazby; bez nalezené fatální chyby |
| `/paper_registrations_admin.php` | Načteno: Papírové přihlášky a souhlasy; bez nalezené fatální chyby |
| `/medical_clearance_admin.php` | Načteno: Zdravotní prohlídky; bez nalezené fatální chyby |
| `/club_program_admissions_admin.php` | Načteno: Osobní přihlášky do programů; bez nalezené fatální chyby |
| `/member_engagement_admin.php` | Načteno: Úrovně zapojení osob; bez nalezené fatální chyby |
| `/kis_sync_center.php` | Načteno: KIS synchronizační centrum; bez nalezené fatální chyby |
| `/sprava_vsech_treninku.php` | Načteno: Správa tréninků; bez nalezené fatální chyby |
| `/sprava_zavodu.php` | Načteno: Správa závodů; bez nalezené fatální chyby |
| `/prehled_vsech_vykazu.php` | Načteno: Výpis všech výkazů; bez nalezené fatální chyby |
| `/coach_scope_admin.php` | Načteno: Svěřenci trenérů; bez nalezené fatální chyby |
| `/sports_data_quality_admin.php` | Načteno: Kvalita sportovních dat; bez nalezené fatální chyby |
| `/planovac.php` | Načteno: Plánovač tréninků; bez nalezené fatální chyby |
| `/training_closure_admin.php` | Načteno: Docházka a uzavření; bez nalezené fatální chyby |
| `/sports_activity_admin.php` | Načteno: Ověřená sportovní činnost; bez nalezené fatální chyby |
| `/club_program_offers_admin.php` | Načteno: Kroužky; bez nalezené fatální chyby |
| `/eshop_events_admin.php` | Načteno: Klubové akce K3; bez nalezené fatální chyby |
| `/verejny_velodrom_admin.php` | Načteno: Veřejné hodiny velodromu; bez nalezené fatální chyby |
| `/club_programs_admin.php` | Načteno: Kroužkové programy a podmínky; bez nalezené fatální chyby |
| `/club_makeup_policies_admin.php` | Načteno: Omluvy a náhradní lekce; bez nalezené fatální chyby |
| `/bookable_services_admin.php` | Načteno: Kurzy a rezervační okna; bez nalezené fatální chyby |
| `/sprava_sportovist.php` | Načteno: Správa sportovišť; bez nalezené fatální chyby |
| `/formular.php` | coach form rendered; no submission |
| `/booking/eshop.php` | search Bunda ACTIVE 23 returns one matching product; existing cart untouched |
| `/booking/produkt.php?id=67` | variant128 in stock; selecting158 shows sold out and removes add-to-cart action; no cart mutation |
| `/booking/cyklisticke_krouzky.php` | Načteno: Kroužek vyberete podle dítěte, dne a místa.; bez nalezené fatální chyby |
| `/booking/treninky.php` | Načteno: Rozvrh tréninků; bez nalezené fatální chyby |
| `/booking/klubovy_kalendar.php` | Načteno: Akce a klubový kalendář; bez nalezené fatální chyby |
| `/booking/kalendar.php` | Načteno: Individuální lekce; bez nalezené fatální chyby |
| `/booking/velodrom.php` | Načteno: Veřejné hodiny velodromu; bez nalezené fatální chyby |
| `/accounting_ledger_admin.php (coach context)` | denied with required finance position; no ledger visible |
| `/eshop_fulfillment_admin.php?order_id=13` | existing production order detail rendered; no handover mutation |
| `/var/private/kis-person-import.php` | 403 Forbidden in browser; no file content exposed |
| `/eshop_fulfillment_admin.php (0b03b564)` | fixed landing PASS; order-selection link opens orders with8 fulfillment links |
| `/uat_readiness_admin.php (0b03b564)` | final release PASS; storage BLOCKED by web open_basedir, constant source, no environment override |
