import fs from 'node:fs/promises';
import path from 'node:path';
import {execFileSync} from 'node:child_process';
import {Workbook, SpreadsheetFile} from '@oai/artifact-tool';

const root = 'C:/xampp/htdocs/evidencePavel';
const out = path.join(root, 'outputs/data-flow-audit-2026-10-19');
const wb = Workbook.create();
const navy = '#19345C';
const blue = '#216AC0';
const pale = '#EAF2FC';
const font = 'Aptos';

function sheet(name, title, note, headers, rows, widths) {
  const s = wb.worksheets.add(name);
  s.showGridLines = false;
  s.getRangeByIndexes(0,0,1,headers.length).merge();
  s.getCell(0,0).values = [[title]];
  s.getRangeByIndexes(1,0,1,headers.length).merge();
  s.getCell(1,0).values = [[note]];
  s.getRangeByIndexes(3,0,1,headers.length).values = [headers];
  if (rows.length) s.getRangeByIndexes(4,0,rows.length,headers.length).values = rows.map(r=>r.map(v=>v??''));
  s.getRangeByIndexes(0,0,rows.length+4,headers.length).format.font = {name:font,size:10,color:'#18304C'};
  s.getRangeByIndexes(0,0,1,headers.length).format = {fill:navy,font:{name:font,size:16,bold:true,color:'#FFFFFF'}};
  s.getRangeByIndexes(1,0,1,headers.length).format = {fill:pale,font:{name:font,size:10,color:'#375678'}};
  s.getRangeByIndexes(3,0,1,headers.length).format = {fill:blue,font:{name:font,size:10,bold:true,color:'#FFFFFF'}};
  s.getRangeByIndexes(0,0,rows.length+4,headers.length).format.wrapText = true;
  s.getRangeByIndexes(3,0,rows.length+1,headers.length).format.verticalAlignment = 'center';
  s.getRangeByIndexes(0,0,1,headers.length).format.rowHeight = 32;
  s.getRangeByIndexes(1,0,1,headers.length).format.rowHeight = 34;
  s.getRangeByIndexes(3,0,1,headers.length).format.rowHeight = 32;
  if (rows.length) s.getRangeByIndexes(4,0,rows.length,headers.length).format.rowHeight = name==='Vstupní body'?34:name==='Datové toky'?48:name==='Nálezy'?72:name==='Cesty'?54:38;
  for (let c=0;c<widths.length;c++) s.getRangeByIndexes(0,c,rows.length+4,1).format.columnWidth = widths[c];
  s.freezePanes.freezeRows(4);
  s.tabColor = blue;
  return s;
}

const flows = [
  ['A01','Identita','Trenér zadá přihlašovací údaje','login.php','trenér','heslo, rate limit, session/revokace','treneri; session','administrace / odmítnutí bez přístupu','login.php; includes/auth_session.php'],
  ['A02','Identita','Veřejný účet se přihlásí','booking/prihlaseni.php','veřejný účet','heslo, omezení pokusů, redirect allowlist','verejni_uzivatele; session','zamýšlená stránka / zůstat v loginu','booking/prihlaseni.php'],
  ['A03','Identita','Registrace veřejného účtu','booking/registrace.php','anonym','CSRF, duplicita, jednorázový token','verejni_uzivatele; případně public_profiles','ověřovací e-mail → booking/overeni.php / nedoručení není signalizováno','booking/registrace.php:84-142'],
  ['A04','Identita','Zapomenuté heslo','booking/zapomenute_heslo.php','anonym','rate limit, generická odpověď, hash tokenu','password_reset_tokens','e-mail → booking/nove_heslo.php / poštovní selhání skryto','booking/zapomenute_heslo.php:19-45; includes/password_reset.php:15-60'],
  ['A05','Identita','Nové heslo','booking/nove_heslo.php','token','token, expirace, heslo, revokace','verejni_uzivatele; password_reset_tokens','login / chyba tokenu','booking/nove_heslo.php'],
  ['A06','Osoby','Připojit dítě nebo vlastní profil','booking/moje_osoby.php','veřejný účet','CSRF, ověření vazby a identity','sportovci; family/account relations','schválená osoba do výběrů / čekající žádost','booking/moje_osoby.php; includes/child_access.php'],
  ['A07','Osoby','Sportovec se přihlásí','booking/sportovec_prihlaseni.php','sportovec','samostatné oprávnění/session','sportovec_pristup; session','booking/muj_sport.php / login chyba','booking/sportovec_prihlaseni.php'],
  ['A08','Osoby','Veřejná karta sportovce','public_profile_access.php; booking/verejny_profil.php','bearer token','náhodný token, vazba session podle operace','sportovci; profilové čtení','profil / odmítnutí; poznámka zvlášť autorizovaná','public_profile_access.php; includes/public_profile_token.php'],
  ['S01','Sportovní evidence','Vložit trénink','formular.php → ulozit_trenink.php','trenér','CSRF, měření, soupiska, obrázky, transakce','treninky; trenink_sportovec; mereni_zaznamy; audit','editace tréninku / formulář s chybou','formular.php; ulozit_trenink.php'],
  ['S02','Sportovní evidence','Upravit trénink','edit_trenink.php → update_trenink.php','trenér','oprávnění, parser měření, kompenzace souborů','treninky; vazby; mereni_zaznamy; audit','detail upraven / chyba bez částečného zápisu','edit_trenink.php; update_trenink.php'],
  ['S03','Sportovní evidence','Vložit závod','formular_zavod.php → ulozit_zavod.php','trenér','CSRF, měření, účast, soubory','zavody; zavod_sportovec; mereni_zaznamy','detail závodu / validace','formular_zavod.php; ulozit_zavod.php'],
  ['S04','Sportovní evidence','Upravit závod','edit_zavod_form.php → update_zavod.php','trenér','oprávnění, měření, transakce','zavody; účast; mereni_zaznamy; audit','detail závodu / bezpečné zamítnutí','edit_zavod_form.php; update_zavod.php'],
  ['S05','Sportovní evidence','Poznámka a tréninky na profilu','sportovec_treninky.php; ajax_sportovec_poznamka.php','oprávněný účet/sportovec','token pro čtení; session a vazba pro zápis','sportovci; poznámky; treninky','profil / odmítnutí zápisu bez identity','sportovec_treninky.php; ajax_sportovec_poznamka.php'],
  ['P01','Plánování','Naplánovat trénink','planovany_trenink_form.php; planovac.php','trenér','CSRF, tým, termín, oprávnění','planovane_treninky; soupiska','plán → RSVP a evidence / validace','planovany_trenink_form.php; planovac.php'],
  ['P02','Plánování','Odpovědět na účast','booking/muj_sport.php; booking/treninky.php','rodina/sportovec','vazba osoby, deadline, finanční rozhodnutí','training_rsvps; audit','stav účasti / důvod odmítnutí','booking/muj_sport.php; includes/club_finance.php:172-186'],
  ['P03','Plánování','Trenér řídí RSVP a náhradní místa','training_rsvps_admin.php','trenér s planovac','CSRF, role, kapacita a věk','training_rsvps; club_makeup_slots','soupiska a náhradníci / validace','training_rsvps_admin.php; includes/club_makeup.php:324-367'],
  ['P04','Náhrady','Omluvit plánovaný trénink','booking/muj_sport.php','rodina/sportovec','vazba, lhůta a zaplacený program','omluvy; následně club_makeup_credits','čeká na vyhodnocení účasti / zamítnutí','includes/club_makeup.php:152-259'],
  ['P05','Náhrady','Rezervovat náhradu','booking/nahrady.php','rodina/sportovec','vazba, platný kredit, vhodný termín, kapacita','club_makeup_reservations; club_makeup_credits; events','rezervace / bez místa či oprávnění','booking/nahrady.php:18-47; includes/club_makeup.php:411-446'],
  ['P06','Náhrady','Zrušit náhradu','booking/nahrady.php','rodina/sportovec','vazba, deadline','club_makeup_reservations; club_makeup_credits; events','uvolněný kredit / po lhůtě backend odmítá','booking/nahrady.php:51; includes/club_makeup.php:448-468'],
  ['P07','Rezervace','Individuální lekce','booking/kalendar.php → booking/rezervovat.php','anonym/účet dle cesty','CSRF, kapacita, termín','rezervace individuálních lekcí','potvrzení / čekací listina; přímý mail','booking/rezervovat.php; booking/waiting_list.php'],
  ['P08','Rezervace','Veřejná hodina velodromu','booking/velodrom.php','veřejný účet','vazba účastníka, kapacita, cena','public_velodrome_reservations nebo shop_cart','zdarma rezervace; placená do košíku / chyba','booking/velodrom.php:30-61'],
  ['E01','E-shop','Vyhledat a vybrat variantu','booking/eshop.php → booking/produkt.php','veřejnost','publikace a prodejní pravidla','shop_products; shop_variants; kategorie','detail a přihláška / bez nabídky','booking/eshop.php:100-130; booking/produkt.php'],
  ['E02','E-shop','Změnit košík','booking/produkt.php; booking/eshop.php','veřejný účet','CSRF, sklad, beneficiary, členství','shop_carts; shop_cart_items; service cart','košík / chyba, chybějící osoba','booking/eshop.php:23-56; includes/shop_checkout.php:151-224'],
  ['E03','E-shop','Vytvořit objednávku','booking/eshop.php → shopCheckoutPlace()','veřejný účet','idempotence, fingerprint, zámky, souhlasy, ceny, jedna finanční osoba','shop_orders; shop_order_items; payments; zásoby; events','booking/objednavka.php / server odmítne smíšené děti','booking/eshop.php:57-68; includes/shop_checkout.php:226-429'],
  ['E04','E-shop','Koupit zboží bez účtu','booking/rychly_nakup.php','anonym','varianta, kontakt, dostupnost, token přístupu','veřejný účet/objednávka podle rychlého toku','omezený detail objednávky / chyba','booking/rychly_nakup.php'],
  ['E05','E-shop','Rychle přihlásit program','booking/rychla_prihlaska.php','anonym','termín, osoba, souhlasy, cena','sportovci; žádost; shop_orders; payments','booking/objednavka.php / při loginu ztráta varianty','booking/rychla_prihlaska.php:17-53'],
  ['E06','E-shop','Zobrazit objednávku a QR','booking/objednavka.php','vlastník/token','vlastnictví nebo omezený token','shop_orders; payments','platba a stav / nepřístupná objednávka','booking/objednavka.php'],
  ['E07','Platby','Ruční bankovní potvrzení','eshop_orders_admin.php → shopOrderConfirmPayment()','správce financí','důvod, potvrzení, zámek a stav platby','payments; shop_orders; order events','processing + aktivace plnění / odmítnutí duplicity','eshop_orders_admin.php; includes/shop_checkout.php:760-824'],
  ['E08','Platby','SumUp webhook','booking/sumup_webhook.php','podepsaný poskytovatel','server-side checkout, stav, idempotence','payments; shop_orders; navázaná plnění','zaplaceno / odmítnutí neplatné události','booking/sumup_webhook.php'],
  ['E09','Platby','Stripe webhook','booking/stripe_webhook.php','podepsaný poskytovatel','podpis, checkout retrieval, idempotence','payments; shop_orders; navázaná plnění','zaplaceno / odmítnutí neplatné události','booking/stripe_webhook.php'],
  ['E10','Platby','Fio read-only kandidáti','bin/fio-import.php; includes/fio_readonly_import.php','CLI/finanční správce','read-only, párovací návrh, ruční potvrzení','bankovní import/staging; audit','kandidát na potvrzení / disabled či review','bin/fio-import.php; includes/fio_readonly_import.php'],
  ['E11','Objednávky','Správce potvrdí či stornuje objednávku','eshop_orders_admin.php','správce','role, důvod, potvrzení, stavový automat','shop_orders; payments; inventory; events','nový stav / konflikt či nemožná refundace','eshop_orders_admin.php; includes/shop_checkout.php:834-979'],
  ['E12','Objednávky','Definice zákaznického stavu','eshop_order_statuses_admin.php','správce','role, text e-mailu, důvod','shop_order_status_definitions; events','stav k výběru / validace','eshop_order_statuses_admin.php; includes/shop_order_customer_status.php:60-103'],
  ['E13','Objednávky','Změnit zákaznický stav','eshop_orders_admin.php','správce','aktivní definice, validní e-mail, transakce','shop_orders; shop_order_events; club_event_notifications','viditelný stav; případný e-mail čeká ve frontě / rollback','eshop_orders_admin.php; includes/shop_order_customer_status.php:115-162'],
  ['K01','Programy','Admin publikuje nabídku a termíny','club_programs_admin.php','správce programu','termín, kapacita, podmínky, cena','club_programs; offers; shop products/variants','publikovaný detail / preflight chyby','club_programs_admin.php'],
  ['K02','Akce','Admin naplánuje/potvrdí akci','club_calendar.php','trenér/organizátor','termín, cílová soupiska, kapacita, preflight','club_events; sessions; people; vehicles','veřejný kalendář / neotevřené přihlašování','club_calendar.php:20-88; includes/club_event_registration.php:212-384'],
  ['K03','Akce','Bezplatná účast','booking/klubovy_kalendar.php','rodina/sportovec','vazba, souhlas, okno, kapacita, finanční limit','club_event_registrations; events; planned participants','potvrzení/čekací listina / odmítnutí','booking/klubovy_kalendar.php:24-156; includes/club_event_registration.php:386-539'],
  ['K04','Akce','Placená účast přes produkty','booking/klubovy_kalendar.php → booking/krouzky.php → booking/eshop.php','rodina','vazba, souhlas, okno, limit, cena','club_event_cart_items; shop_orders; payments','objednávka → potvrzení po platbě / nedokončený košík','booking/klubovy_kalendar.php:100,130; includes/club_event_shop.php'],
  ['K04a','Akce','Účast s přímým předpisem','booking/klubovy_kalendar.php → clubCalendarFamilyRegister()','rodina','vazba, souhlas, kapacita, finanční limit','club_event_registrations; club_event_planned_participants; club_member_charges; payments','účast potvrzena; předpis ve sportovním profilu / bez přímých platebních instrukcí','booking/klubovy_kalendar.php:37-53; includes/club_calendar.php:412-425'],
  ['K05','Akce','Storno účasti a čekací listina','booking/klubovy_kalendar.php; club_calendar.php','účastník/správce','termín, oprávnění, podmínky','registrations; planned participants; charges; notifications','uvolněné místo/povýšení / chybový stav','includes/club_event_registration.php:541-806'],
  ['K06','Akce','Zpráva soupisce','roster_messages_admin.php','trenér','snapshot příjemců, fingerprint, potvrzení','roster message queue','worker/retry / stav fronty','roster_messages_admin.php; bin/roster-message-worker.php'],
  ['F01','Finance','Nastavit pravidla salda','club_finance_admin.php','správce financí','důvod, potvrzení, limity a lhůta','club_finance_settings; events','nové pravidlo / validace','club_finance_admin.php; includes/club_finance.php:30-69'],
  ['F02','Finance','Založit měsíční plán','member_fee_plans_admin.php','správce financí','soupiska, částka, platnost, důvod','member_fee_plans; events','náhled období / validace','member_fee_plans_admin.php; includes/member_fee_plan.php:24-50'],
  ['F03','Finance','Vygenerovat měsíční předpisy','member_fee_plans_admin.php','správce financí','náhled/fingerprint, potvrzení, idempotence','club_member_charges; payments; plan runs','předpisy a VS / přeskočené osoby','member_fee_plans_admin.php; includes/member_fee_plan.php:110-135'],
  ['F04','Finance','Roční úhrada příspěvků','member_fee_annual_admin.php','správce financí','12 období, náhled, sleva, částka, důvod, potvrzení','club_finance_receipts; 12 charges; allocations','měsíce zaplaceny / preview mismatch','member_fee_annual_admin.php; includes/member_fee_annual.php:8-83'],
  ['F05','Finance','Zapsat ověřenou platbu sportovce','club_finance_receipt_admin.php','správce financí','bankovní reference, částka, důvod, potvrzení','club_finance_receipts; allocations; payments','saldo/přeplatek / duplicitní reference','club_finance_receipt_admin.php; includes/club_finance.php:222-303'],
  ['F06','Finance','Vypočítat a zobrazit saldo','sportovec_detail.php; booking/muj_sport.php','trenér/rodina/sportovec','atribuce, grace_days, credit, expirace objednávky','charges; orders; receipts; allocations','otevřená/po lhůtě/přeplatek','includes/club_finance.php:121-169'],
  ['F07','Finance','Rozhodnout o přihlášení','program/event/training services','rodina/sportovec','enforcement a overdue limit','club_finance_settings; výsledné přihlášení','povolit / blokovat s důvodem','includes/club_finance.php:172-186'],
  ['I01','Importy','KIS archiv a Shoptet staging','bin/kis-archive-source.php; bin/shoptet-products-stage.php','CLI/správce importu','typ, hash, parse, staging','import runs; staging; archiv','náhled a review / zamítnutí','bin/kis-archive-source.php; bin/shoptet-products-stage.php'],
  ['I02','Importy','Explicitní promote','kis_sync_center.php; eshop_catalog_admin.php','správce importu','review, identity match, potvrzení','kanonické osoby/produkty/členství; audit','zveřejněná data / konflikt','kis_sync_center.php; includes/kis_import_sandbox_promotion.php; includes/shop_catalog_promotion.php'],
  ['I03','Importy','Sportovní export a soubory','export_uci.php; download endpointy','oprávněný uživatel','role, typ/ID, soukromá cesta','privátní přílohy; export','stažení / odmítnutí','export_uci.php; private_download.php'],
  ['N01','Komunikace','Zpracovat notifikace akcí/objednávek','bin/club-event-notifications.php','CLI worker','claim, retry, transport','club_event_notifications','sent/failed/pending; doručení nutno ověřit','bin/club-event-notifications.php; includes/club_event_notification.php'],
  ['N02','Komunikace','Hromadný e-mail','odeslat_emaily.php','trenér','výběr příjemců, náhled','přímo mail(); audit dle cesty','odesláno/částečný neúspěch','odeslat_emaily.php:195-225'],
  ['O01','Provoz','Migrace a deploy','bin/*; .github/workflows/deploy-production.yml','CLI/workflow','záloha, migrace, invarianty','schéma, archiv, release','nasazený release / stop při chybě','db.php; .github/workflows/deploy-production.yml'],
];

const findings = [
  ['F01','Vysoká','Identita','Registrace ignoruje výsledek přímého mailu; chybí zřejmý resend','Účet může zůstat neověřený bez použitelného odkazu','Trvalý outbox + generické opakované ověření + alarm','booking/registrace.php:100-142','Potvrzeno v kódu'],
  ['F02','Střední','Identita','Reset zneplatní starý token před nedoloženým doručením nového','Nedostupná pošta může zrušit fungující odkaz','Outbox; upravit revokaci až po bezpečném předání','includes/password_reset.php:29-59','Potvrzeno v kódu'],
  ['F03','Střední','E-shop','Více sportovců odmítnuto až při checkoutu','Rodina musí ručně rozdělit košík','Preflight v košíku; jedním krokem rozdělit podle dítěte','includes/shop_checkout.php:280-285; booking/eshop.php:158','Potvrzeno v kódu'],
  ['F04','Střední','E-shop','Prázdný výběr osoby bez přímého CTA','Uživatel neví jak pokračovat','Odkaz Moje osoby + návratový kontext','booking/produkt.php:275-276; booking/eshop.php:132','Potvrzeno v kódu'],
  ['F05','Střední','Programy','Přihlášení z rychlé přihlášky zahodí ID termínu','Opakovaný výběr, riziko jiného termínu','Zachovat validovanou variantu přes login','booking/rychla_prihlaska.php:21,43-44','Potvrzeno v kódu'],
  ['F06','Střední','Náhrady','Tlačítko storna i po deadline','Server zamítne nabízenou akci','Ukázat deadline; po něm kontakt trenéra','booking/nahrady.php:51; includes/club_makeup.php:448-459','Potvrzeno v kódu'],
  ['F07','Nízká','Náhrady','Zamčený kredit posílá do obecného e-shopu','Znovu hledat navazující pololetí','Deep-link podle carry_offer_id','booking/nahrady.php:47; includes/club_makeup.php:218-253','Potvrzeno v kódu'],
  ['F08','Nízká','E-shop','Falešná service_item jen pro neprázdný košík','Redundance a křehká větev event-only','Test prázdnosti ze skutečného počtu všech typů','booking/eshop.php:135,158','Potvrzeno v kódu'],
  ['F09','Prověřit','Komunikace','Zákaznický status vloží zprávu do fronty, běh workeru neověřen','Fronta může čekat bez doručení','Provozní kontrola CRON, backlogu, retry a testovací adresy','includes/shop_order_customer_status.php:115-156; bin/club-event-notifications.php','Neověřeno na produkci'],
  ['F10','Nízká','Komunikace','Více requestových cest obchází společnou frontu','Nedohledatelné doručení; možné tiché selhání','Oddělit requestové odeslání od legitimních worker transportů','booking/rezervovat.php:213; booking/waiting_list.php:120; odeslat_emaily.php:218','Potvrzeno v kódu'],
  ['F11','Střední','Akce a platby','Přímě placená akce po přihlášení jen oznámí potvrzení účasti','Vznikne předpis, ale v kalendáři ani přihláškách není platební CTA; rodič musí hledat profil','Po registraci ukázat částku, splatnost, VS a odkaz na platební detail / QR','booking/klubovy_kalendar.php:43-53; includes/club_calendar.php:369-410; booking/krouzky.php:235-257','Potvrzeno v kódu'],
];

const paths = [
  ['C01','Nový rodič','Registrace → ověření → přihlášení → propojení osoby → program','Vytvořený účet není totéž co ověřený a propojený profil','F01, F04','booking/registrace.php; booking/moje_osoby.php'],
  ['C02','Rodina se sourozenci','Dvě programové položky → košík → platba','Finanční atribuce je jen jedna; musí se rozdělit před checkoutem','F03','booking/eshop.php; includes/shop_checkout.php:280-285'],
  ['C03','Rychlý zájemce','Produkt/termín → rychlá přihláška → login nebo okamžitá objednávka','Login vrací na obecný detail, nikoli konkrétní termín','F05','booking/rychla_prihlaska.php:21,43-44'],
  ['C04','Nepropojená osoba','Program → výběr účastníka','Bez schválené osoby se nedá pokračovat ani kliknout na návod','F04','booking/produkt.php:275-276'],
  ['C05','Kupující','Košík → idempotentní objednávka → QR/SumUp/Stripe → potvrzení','Browser návrat není platba; až potvrzená platba aktivuje plnění','—','includes/shop_checkout.php:226-429; booking/objednavka.php'],
  ['C06','Účastník akce','Kalendář → zdarma / přímý předpis / produktový košík','Tři cenové politiky mají odlišné dokončení a platební cestu','F11','booking/klubovy_kalendar.php; includes/club_calendar.php:412-425'],
  ['C07','Sportovec','Trénink → RSVP → omluva → docházka → kredit → náhrada','Deadline storna musí odpovídat nabízené akci','F06, F07','booking/nahrady.php; includes/club_makeup.php'],
  ['C08','Správce financí','Plán → náhled měsíce → předpis → bankovní příjem → saldo','Bez potvrzení bankovního příjmu není předpis zaplacený','—','includes/member_fee_plan.php; includes/club_finance.php'],
  ['C09','Správce financí','Roční platba → náhled 12 měsíců → jedna úhrada → 12 alokací','Měsíční předpisy zůstávají samostatně dohledatelné','—','includes/member_fee_annual.php'],
  ['C10','Správce objednávek','Nový stav → změna objednávky → fronta → worker → zákazník','Zápis do fronty není důkaz doručení','F09','includes/shop_order_customer_status.php; bin/club-event-notifications.php'],
  ['C11','Importér','Zdroj → staging → náhled/match → explicitní promote','Nejednoznačná identita musí zůstat v review','—','kis_import_promote.php; includes'],
];

const storage = [
  ['Identita','verejni_uzivatele, treneri, sportovci, password_reset_tokens','web formuláře / token','session, ověření, autorizace','osobní údaje; odeslání zprávy'],
  ['Osoby a vztahy','sportovci, family/account relation tables','veřejný účet, žádost','výběr účastníka, schválená vazba','správnost oprávnění'],
  ['Evidence','treninky, zavody, mereni_zaznamy, vazby','trenérské formuláře','profil, export','měření, přílohy'],
  ['Plány a náhrady','plánované tréninky, RSVP, club_makeup_*','trenér, rodina, sportovec','docházka, kredity a rezervace','čas, kapacita, oprávnění'],
  ['Katalog','shop_products, shop_variants, shop_carts, shop_cart_items','správce a nakupující','detail, košík','cena/kapacita/beneficiary'],
  ['Objednávka','shop_orders, shop_order_items, shop_order_events, payments','checkout, admin, webhook','platební stav, QR, aktivace plnění','idempotence, transakce'],
  ['Stavy objednávek','shop_order_status_definitions, shop_order_status_definition_events','administrátor','zákaznický stav','šablony a notifikační fronta'],
  ['Akce','club_events, club_event_registrations, club_event_planned_participants','organizátor, rodič','kalendář, přihlášky','kapacita a souhlas'],
  ['Finance','member_fee_plans, club_member_charges, club_finance_receipts, club_finance_allocations, club_finance_settings','správce, banka','saldo, limit přihlášení','grace a přiřazení platby'],
  ['Komunikace','club_event_notifications, roster message queue','doménové události','e-mail mimo transakci','worker, retry, monitoring'],
  ['Importy','archive/hash, staging, import runs, promotion audit','upload/CLI','kanonický zápis až po promote','review identit a oprávnění'],
  ['Soubory','privátní úložiště a řízené downloady','upload/export','příloha/export','path validation a přístup'],
];

const tracked = execFileSync('git',['ls-files','-z','*.php'],{cwd:root,encoding:'utf8'}).split('\0').filter(Boolean);
const firstParty = tracked.filter(p=>! /^(vendor|tests|migrations|outputs|docs|var|\.agents)\//.test(p));
const inventory=[];
for(const rel of firstParty){
  const source=await fs.readFile(path.join(root,rel),'utf8');
  const forms=(source.match(/<form\b/gi)||[]).length;
  const pick=(name)=>[...new Set([...source.matchAll(new RegExp('\\$_'+name+'\\s*\\[\\s*[\\\"\\\']([^\\\"\\\']+)[\\\"\\\']','g'))].map(m=>m[1]))].sort();
  const get=pick('GET'),post=pick('POST'),files=pick('FILES');
  const request= /REQUEST_METHOD|php:\/\/input|HTTP_/.test(source);
  if(!forms && !get.length && !post.length && !files.length && !request)continue;
  const writes=[...new Set([...source.matchAll(/\b(?:INSERT\s+INTO|UPDATE|DELETE\s+FROM|REPLACE\s+INTO)\s+[`\"]?([a-z_][a-z0-9_]*)/gi)].map(m=>m[1].toLowerCase()))].sort();
  const side=[];
  if(/(?:@)?mail\s*\(/.test(source))side.push('mail() text/call');
  if(/header\s*\(\s*['\"]Location:/i.test(source))side.push('redirect');
  if(/move_uploaded_file|file_put_contents|fopen\s*\(/.test(source))side.push('file');
  if(/notifications|outbox|enqueue|queue/i.test(source))side.push('queue mention');
  const access=/canAccess\s*\(/.test(source)?'canAccess()':/trener_id/.test(source)?'trainer session':/verejny_uzivatel_id/.test(source)?'public account':/sportovec_pristup_id/.test(source)?'athlete session':'inspect';
  const channel=rel.startsWith('bin/')?'CLI':forms?'HTML form':request?'HTTP request':'GET/POST';
  const match=source.match(/<form\b|\$_(?:GET|POST|FILES)|REQUEST_METHOD|php:\/\/input/i);
  const evidence=match?`${rel}:${source.slice(0,match.index).split('\n').length}`:rel;
  inventory.push([rel,channel,forms,get.join(', '),post.join(', '),files.join(', '),access,writes.join(', '),side.join(', '),evidence]);
}
inventory.sort((a,b)=>a[0].localeCompare(b[0],'cs'));

sheet('Přehled','Datové toky aplikace — 19. 10. 2026',
  'Auditní základ 76ae9eb; lokální změny sleduje list Implementace. Produkční UAT ani doručení nebyly ověřeny.',
  ['Ukazatel','Hodnota','Význam'],[
    ['Ručně sledované toky',flows.length,'Řádky v listu Datové toky; není to počet všech route.'],
    ['Uživatelské cesty',paths.length,'Sekvence člověka přes více formulářů.'],
    ['Prvostranové PHP soubory',firstParty.length,'Tracked soubory mimo vendor/tests/migrations/outputs/docs/var/.agents.'],
    ['Heuristické vstupní body',inventory.length,'Soubor s formulářem, GET/POST/FILES nebo HTTP signálem.'],
    ['Nálezy',findings.length,'Potvrzené kódové vady i oddělený provozní bod k ověření.'],
    ['Platnost','lokální změny','Výchozí audit + místní implementace. Bez produkčního UAT, CRONu a doručení.'],
  ],[27,22,70]);
sheet('Cesty','Cesty uživatele a slepé konce','Ověřená návaznost vstupu, mezikroků a konečného stavu; Fxx odkazují na list Nálezy.',
  ['ID','Role','Kroky','Kritický přechod','Nález','Zdrojový důkaz'],paths,[9,24,58,66,14,50]);
sheet('Datové toky','Ručně ověřené datové toky','V každém řádku sledujte zdroj, hranici, úložiště a co člověk uvidí po úspěchu nebo chybě.',
  ['ID','Doména','Vstup / trigger','Endpoint / služba','Přístup','Validace / zpracování','Úložiště','Další krok / chyba','Zdrojový důkaz'],flows,[9,21,39,43,24,52,50,65,56]);
sheet('Vstupní body','Inventář formulářů a requestů','Heuristika ze zdrojových PHP: přímé SQL a klíče v souboru; volání do includes/ je nutné domapovat v Datových tocích.',
  ['Soubor','Kanál','Počet form','GET klíče','POST klíče','FILES klíče','Přístup heuristic','Přímé SQL tabulky','Vedlejší efekt heuristic','První důkaz'],inventory,[44,18,12,41,58,24,22,44,25,44]);
sheet('Úložiště','Kanonická úložiště a konzumenti','Skupiny tabulek; přesné DB schéma vyžaduje samostatné ověření migrací a provozní databáze.',
  ['Oblast','Tabulky / soubory','Původ','Konzument / výstup','Kontrolní riziko'],storage,[22,65,39,49,45]);
sheet('Nálezy','Potvrzené problémy a ověřovací body','Priorita není potvrzená produkční incidentnost. F09 vyžaduje provozní důkaz.',
  ['ID','Priorita','Oblast','Nález','Dopad','Doporučení','Zdrojový důkaz','Jistota'],findings,[9,17,19,65,61,68,62,23]);
sheet('Kontrola','Metodika a hranice důkazu','Dokumentace byla revidována proti zdrojovému commitu; nezaměňovat s produkčním UAT.',
  ['Kontrola','Výsledek','Poznámka'],[
    ['Zdrojový commit','76ae9eb','git rev-parse --short HEAD při kontrole'],
    ['Inventář','automatický','Tracked PHP, form/request regex, bez runtime routingu a volaných služeb'],
    ['Ručně ověřené trasy',flows.length,'Kritické cesty od vstupu po persistenci a konečný stav'],
    ['Kontrola navigace','statická','Vybrané cesty a slepé konce potvrzené v kódu'],
    ['Produkční browser UAT','neprovedeno','Tato revize nebyla požadavkem na produkční zápisy'],
    ['Produkční e-mail a CRON','neověřeno','Fronta neprokazuje doručení'],
    ['DB schéma a data','neověřeno','Zdrojová mapa, nikoli live DB audit'],
    ['Historie','zachována','outputs/data-flow-audit-2026-08-24 a -2026-10-07'],
  ],[29,25,74]);

sheet('Implementace','Stav návrhů v lokální pracovní kopii','Auditní řádky v listu Nálezy popisují původní commit; zde je stav změn před nasazením.',
  ['Nález','Lokální stav','Změna / kontrola','Zbývá'],[
    ['F01–F02','Opraveno lokálně','Šifrovaný outbox v transakci, resend, reset bez předčasné revokace, admin retry','Migrace, CRON, produkční doručení a browser UAT'],
    ['F03','Částečně','Konflikt viditelný v košíku; checkout je vypnutý; jedna osoba se předvolí','Jednoklikové rozdělení vyžaduje účetní a idempotentní kontrakt'],
    ['F04–F05','Opraveno lokálně','Moje osoby s návratem, vybraný termín po přihlášení','Browser UAT'],
    ['F06–F08','Opraveno lokálně','Lhůta storna, konkrétní nabídka, skutečný počet položek','Browser UAT'],
    ['F09','Neověřeno','Worker nově zahrnuje i identitní zprávy','Produkční CRON, backlog a test doručení'],
    ['F10','Otevřeno','Ostatní synchronní e-mailové cesty beze změny','Inventář šablon a idempotence před migrací'],
    ['F11','Opraveno lokálně','Přímý odkaz na předpis, platební údaje a QR','Browser UAT a produkční bankovní údaje'],
  ],[15,25,70,60]);

wb.recalculate();
const snapshot=await wb.inspect({kind:'workbook,sheet',maxChars:3000});
console.log(JSON.stringify({flows:flows.length,paths:paths.length,firstParty:firstParty.length,inventory:inventory.length,findings:findings.length,inspect:snapshot.ndjson??snapshot}));
await fs.mkdir(out,{recursive:true});
const xlsx=await SpreadsheetFile.exportXlsx(wb);
await xlsx.save(path.join(out,'DATOVE_TOKY_APLIKACE.xlsx'));
for(const name of ['Přehled','Cesty','Datové toky','Vstupní body','Úložiště','Nálezy','Kontrola','Implementace']){
  try {
    const p=await wb.render({sheetName:name,range:name==='Vstupní body'?'A1:F12':name==='Datové toky'?'A1:F12':name==='Nálezy'?'A1:E12':'A1:E12',scale:1,format:'png'});
    await fs.writeFile(path.join(out,`preview-${name.replace(/[^a-z0-9]/gi,'_')}.png`),new Uint8Array(await p.arrayBuffer()));
  } catch(e) { console.warn(`Render ${name}: ${e.message}`); }
}
