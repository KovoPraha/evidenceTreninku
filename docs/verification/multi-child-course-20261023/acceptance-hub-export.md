# Výsledky localhost akceptace A01–A10

Exportováno: 2026-10-23T21:03:08+02:00

| Scénář | Výsledek | Důležitost | Pozorováno | Očekáváno |
|---|---|---|---|---|
| A01 | NOT TESTED | — | — | — |
| A02 | NOT TESTED | — | — | — |
| A03 | NOT TESTED | — | — | — |
| A04 | NOT TESTED | — | — | — |
| A05 | NOT TESTED | — | — | — |
| A06 | NOT TESTED | — | — | — |
| A07 | NOT TESTED | — | — | — |
| A08 | NOT TESTED | — | — | — |
| A09 | NOT TESTED | — | — | — |
| A10 | NOT TESTED | — | — | — |
| B01 | PARTIAL | — | TECHNICKÁ KONTROLA AGENTEM 2026-10-23, B1 LOCALHOST NEPLATIT UAT-OSOBY23. Trénink 31: Anna 91, Běh 1000 m, 05:12.345; uložení/načtení PASS. Browser Zpět odhalil nový GET a duplicitu 32/33; opraven stabilní formulářový klíč. Opakovaná zkouška 34 po opravě: jediný trénink, Zpět odmítnuto s hláškou. Nejde o lidskou přejímku ani úplné pokrytí disciplín. | — |
| B02 | PARTIAL | — | TECHNICKÁ KONTROLA AGENTEM 2026-10-23. Editace31: oprava05:10.125, osoba91, zachován spolutrenér327; klávesnicový výběr PASS. Uzavření v1 a oprava75min vev2; přímá editace odmítnuta s viditelným důvodem. Neplatný interval zachovává rozepsanou docházku. Fotografie a souběh ověřeny automatickými testy. Lidská přejímka neprovedena. | — |
| B03 | PARTIAL | — | TECHNICKÁ KONTROLA AGENTEM. LOCALHOST NEPLATIT UAT-BROWSER23: trénink30, uzavření34v1 60min, odměna14 300Kč. Vlastní schválení326 odmítnuto, druhývedoucí327 schválil300, vyplaceno0. Není účetní ani lidské převzetí. Účetní export23 commerce části zvlášť ověřen. | Dílčí technické ověření propojeného toku; A01–A10 ani 43 scénářů lidské přejímky tím nejsou převzaty. |
| B04 | NOT TESTED | — | — | — |
| B05 | PARTIAL | — | TECHNICKÁ KONTROLA AGENTEM 2026-10-23. Plán15 -> trénink31; soupiska20, očekávaná osoba91; před evidencí 1bezodpovědi, potom skutečná účast na kartě91. Ověřeno jen založení a převod plánu, kopie a rušení sérií v tomto průchodu neprovedeny.<br>Navazující technická kontrola 23.10 večer: produkční Moje/Vše a týdny načteny. Nalezen chybný odkaz upozornění na minulý týden. B1 oprava otevírá skutečný nejstarší nezapsaný plán a zachovává team_id=3; ověřen přechod z prázdného budoucího týdne. Náhled oznámení dekóduje české entity před zkrácením a zůstává escapovaný.<br>Produkce po release313cd61b: odkaz otevřel týden25.–31.5.2026 s reálným nezapsaným plánem; HTML entity zobrazené správně. Pouze čtení. | — |
| B06 | NOT TESTED | — | — | — |
| B07 | NOT TESTED | — | — | — |
| B08 | NOT TESTED | — | — | — |
| B09 | PARTIAL | — | TECHNICKÁ KONTROLA AGENTEM, nikoli lidská přejímka. LOCALHOST NEPLATIT UAT-BROWSER23: objednávka30 2x500, platba30, pohyb79, částečný výdej10, faktura11. Navazující vratky a export viz docs/verification/browser-acceptance-20261023/RESULTS.md. Celý původní scénář a zákaznická role nepřevzaty. | Dílčí technické ověření propojeného toku; A01–A10 ani 43 scénářů lidské přejímky tím nejsou převzaty. |
| B10 | PARTIAL | — | TECHNICKÁ KONTROLA AGENTEM. LOCALHOST NEPLATIT UAT-BROWSER23: opravy12/13 po-500, vratky5/6, case25 uzavřen, sklad50=13/0/2/11. Peněžní čistý obrat prodeje0. Export23 ověřen20kontrol. Zákaznické zobrazení refunded zde neověřováno. | Dílčí technické ověření propojeného toku; A01–A10 ani 43 scénářů lidské přejímky tím nejsou převzaty. |
| B11 | NOT TESTED | — | — | — |
| B12 | NOT TESTED | — | — | — |
| B13 | NOT TESTED | — | — | — |
| B14 | NOT TESTED | — | — | — |
| B15 | PARTIAL | — | TECHNICKÁ KONTROLA AGENTEM. LOCALHOST NEPLATIT UAT-BROWSER23: osoba91, podklady106–108, nabídka15/program16, objednávka31, platba31, pohyb82+1200. Jedna aktivní účast a automatická soupiska20 od16.10 do22.12. Storno této účasti v tomto průchodu neprováděno.<br>23.10.2026 rodinný browser B1: přes košík vytvořena syntetická objednávka32 KP261023678C565F1E, osoba91, varianta31, 125 Kč. Před úhradou žádná nová účast, po simulovaném potvrzení banky ve finanční agendě účast a soupiska aktivní. Odvolání vazby86 skrylo kurzy a náhrady; vlastní objednávka plátci zůstala. Vazba poté znovu schválena. Nové opravy brání tichému přepsání dítěte a přiřazení příjemce neověřeným účtem. Vratka tohoto kurzu neprovedena, proto PARTIAL; plná přejímka není uzavřena.<br>23.10.2026 sourozenci B1: nový kurz produkt48/varianta55/kapacita2, Anna91 a Bára93. Jedna objednávka33 KP2610238684A82F7F za300Kč, dvě položky33/34, samostatné souhlasy; společná simulovaná platba aktivovala účasti12/13. Rozpis salda150+150Kč, účetní zdroj zachoval obě děti. Browser ověřil blokaci změny na duplicitní dítě a odebrání pouze jedné položky. Automatické testy: kapacita/rollback, opakování, odvolání vazby, souběh, jednotlivé finanční podíly, úplné storno a vratka. Částečné storno zaplacené objednávky tento balík neřeší. Technický důkaz, nikoli lidská přejímka; PARTIAL. | Dílčí technické ověření propojeného toku; A01–A10 ani 43 scénářů lidské přejímky tím nejsou převzaty. |
| B16 | NOT TESTED | — | — | — |
| B17 | NOT TESTED | — | — | — |
| B18 | PARTIAL | — | 23.10.2026 technická přejímka: B1 účet72, žádost1, syntetická osoba91 a vazba86. Žádost před schválením nedává přístup; schválení ukáže pouze tuto osobu, odvolání skryje rodinné/kurzové údaje a přímé náhrady odmítne. Vlastní zaplacená objednávka zůstane plátci dostupná. Opětovné schválení auditované. Produkce pouze čtení: účet bez vazeb, návrat z kurzu produkt247/varianta813 zachován. Nová registrace, doručení a sportovní přihlášení neprovedeny; v B1 není sportovní účet. Regresní sada 229/2596 PASS. Protokol docs/verification/family-course-20261023/RESULTS.md. Nejde o lidské přijetí. | — |
| B19 | NOT TESTED | — | — | — |
| B20 | NOT TESTED | — | — | — |
| B21 | NOT TESTED | — | — | — |
| B22 | NOT TESTED | — | — | — |
| B23 | NOT TESTED | — | — | — |
| B24 | NOT TESTED | — | — | — |
| B25 | NOT TESTED | — | — | — |
| B26 | PARTIAL | — | TECHNICKÁ KONTROLA AGENTEM 2026-10-23. Syntetická osoba92: kontrola duplicity91 zabránila nové kartě; společný kontakt91/92 aktualizován a odpojen pouze92 bez vzniku účtu; ochrana starého formuláře, interní poznámka a archivace92 zachovaly historii. Hromadné změny nebyly v prohlížeči opakovány.<br>Navazující produkční read-only kontrola 23.10 večer: hledání/archiv, všechny záložky karty, editor a zdroj auditní historie načteny. Účty a osoby při hledání vracely HTTP500; B1 reprodukce prokázala rozdílné kolace jmen. Oprava společným hledáním ověřena na MariaDB bez změny osob; jméno i přesné ID vrací osobu92, prázdný výsledek a doslovné procento bezpečné. Produkční potvrzení opravy následuje po CI.<br>Produkce po release313cd61b: stejný dotaz TEST-PR137 vrací jednu správnou archivovanou osobu bez HTTP500; stejné hledání v auditní ose také PASS. Produkční data pouze čtena. | — |
| B27 | NOT TESTED | — | — | — |
| B28 | NOT TESTED | — | — | — |
| B29 | PARTIAL | — | TECHNICKÁ KONTROLA AGENTEM 2026-10-23. Auditní osa92 nyní obsahuje skutečnou interní poznámku, editaci profilu i odpojení rodičovského kontaktu s odkazy na historii. Izolace osob, rollback poznámky a aktuální oprávnění doplněny automatickými testy. Lidské schválení neprovedeno. | — |
| B30 | NOT TESTED | — | — | — |

> Soubor neobsahuje hesla ani automaticky načtená osobní data. Před commitem zkontrolujte ručně zadané poznámky.
