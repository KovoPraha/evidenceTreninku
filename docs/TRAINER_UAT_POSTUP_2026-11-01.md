# Interní test trenérského účtu

Verze 1. 11. 2026. Délka přibližně 20–30 minut. Toto je testovací zadání,
nikoli potvrzení provedeného uživatelského testu.

- [Krátký checklist k přeposlání trenérům](TRAINER_UAT_CHECKLIST_2026-11-01.md)
- [Scénář pro automatické testování v jiném vlákně](TRAINER_UAT_BROWSER_2026-11-01.md)
- [Doklady skutečného založení pilotu](verification/trainer-pilot-20261101/RESULTS.md)

## Příprava

Produkce: <https://kis.kovopraha.cz/>. Použít pouze přidělený testovací účet,
nikoli běžný správní nebo rodinný účet. E-mail předá koordinátor soukromě;
hesla a obnovovací odkazy nepatří do chatu, dokumentace ani screenshotů.

Současný pilot: účet `TEST - Trenérský pilot 20261031`, trenér 56, soupiska 77.
Testovací osoby TEST Alfa (11783) a TEST Beta (11784). Kontrolní osoba
TEST Mimo rozsah (11785) musí být nepřístupná. Grant končí 15. 11. 2026
v 18:17:31 databázového času hostingu; po vypršení se test označí BLOCKED,
nikoli jako chyba oprávnění. Žádné automatické prodloužení.

Každý další trenér potřebuje vlastní přidělený účet a rozsah. Tento dokument
další účty nezakládá. Současný pilotní účet nesdílet a nespouštět současně
ruční a automatický běh pod stejnou identitou.

Pro běh zvolit označení `TEST-UAT-MANUAL-YYYYMMDD-HHMM`, například s místním
časem Europe/Prague. Zapsat jej do náplně nového tréninku. Vytvořit nejvýše
jeden trénink za běh; při nejasném výsledku nejprve dohledat záznam podle
označení, neopakovat uložení naslepo. Testovací záznamy ponechat pro diagnostiku.
Nepřidávat skutečné osoby, rezervace sportovišť, platby, odměny, přílohy ani
rozesílání. Nevytvářet kalendářní akce nebo veřejnou nabídku.

## Postup a očekávané výsledky

| ID | Co přesně projít | Očekávaný výsledek |
|---|---|---|
| T01 | Na [obnově trenérského hesla](https://kis.kovopraha.cz/booking/zapomenute_heslo.php?pro=trener) zadat přidělenou adresu. Vlastník otevře doručený odkaz a nastaví heslo. Použitý odkaz zkusí znovu. | Zpráva dorazí během několika minut (zkontrolovat spam), nové heslo se uloží. Použitý odkaz je odmítnut. Odkaz platí 60 minut; čekání na expiraci není povinnou součástí testu. |
| T02 | Na [přihlášení](https://kis.kovopraha.cz/login.php) zadat testovací adresu a nové heslo. | Pracovní prostředí má pozici Trenér. Nelze přepnout na správce nebo jinou nepřidělenou pozici. |
| T03 | V Moji sportovci vyhledat a otevřít TEST Alfa a TEST Beta. Vyhledat TEST Mimo rozsah. | Obě svěřené osoby jsou dostupné. Kontrolní osoba ani skuteční sportovci nejsou dostupní jako svěřenci. Soupiska není totéž co legacy skupina; prázdné Moje skupiny samo o sobě není selhání. |
| T04 | Přes Zadat trénink vyplnit dnešní datum v rámci platného grantu, délku 1 h, náplň s označením běhu a obě testovací osoby. Ostatní povinná pole vyplnit podle formuláře; nepřidávat spolutrenéry, rezervace nebo přílohy. Uložit jednou. | Uložení uspěje. V Moje tréninky je právě jeden odpovídající záznam se správným datem, délkou a dvěma účastníky. |
| T05 | Otevřít vlastní právě vytvořený trénink. Náplň změnit na stejné označení doplněné o „– upraveno“, uložit a obnovit stránku. | Úprava přetrvá. Zůstává stejné ID a počet účastníků; nevznikne druhý záznam. Pokud není úprava dostupná, uvést to jako nález, neobcházet UI přes API. |
| T06 | Otevřít [administraci plateb](https://kis.kovopraha.cz/eshop_payments_admin.php) a [detail kontrolní osoby](https://kis.kovopraha.cz/sportovec_karta.php?sportovec_id=11785). Nic neodesílat. | Přihlášenému trenérovi je přístup odmítnut bez zobrazení finančních/osobních dat. Chybějící položka menu sama není dostatečný důkaz. Po odhlášení místo toho požadavek vede na přihlášení. |
| T07 | Nechat relaci A přihlášenou. V oddělené relaci B provede vlastník další obnovu hesla. Potom v A znovu načíst Moji sportovci. Vlastník zkusí jednou přihlášení starým a poté novým heslem. | Původní relace je odvolaná a vyžaduje přihlášení. Staré heslo je odmítnuto, nové funguje. Použitý resetovací odkaz nejde znovu použít. |
| T08 | Na telefonu zopakovat přihlášení, otevření testovací osoby a detailu uloženého tréninku. | Text a ovládání jsou čitelné, klíčová tlačítka dostupná, obsah není useknutý. Nevytvářet další trénink. |

Veřejný kalendář, katalog cviků nebo názvy společných filtrů nejsou totéž jako
neoprávněný přístup ke svěřencům. Nález popsat podle konkrétně dostupných dat.
Pokud se zobrazí skuteční sportovci nebo finance, zastavit daný scénář, nic
neupravovat ani hromadně nekopírovat a nahlásit jako P1.

## Jak zaznamenat výsledek

Pro každý krok zvolit PASS / FAIL / BLOCKED / NOT RUN. Chybějící přihlášení,
nedostupná schránka nebo vypršený grant znamenají BLOCKED, nikoli PASS.
U T01/T07 rozlišit vlastníkem potvrzené doručení od pouhého přijetí formuláře.

```text
Běh: TEST-UAT-MANUAL-YYYYMMDD-HHMM
Krok: T04
Čas a pásmo: YYYY-MM-DD HH:MM Europe/Prague
Zařízení / prohlížeč:
Adresa stránky (bez tokenu):
Postup: 1) … 2) … 3) …
Očekávaný výsledek:
Skutečný výsledek / přesná hláška:
Opakovatelnost: vždy / někdy / jednou
ID vlastního testovacího tréninku, pokud vznikl:
Screenshot bez hesel, tokenů a údajů skutečných osob:
Závažnost: P1 přístup k cizím datům / P2 blokace funkce / P3 vzhled či text
```

Hlášení poslat koordinátorovi nebo do chatu s Codexem. Úspěšné kroky lze
shrnout „T01–T03 PASS, T04 FAIL…“. Heslo ani celý obnovovací odkaz neposílat.
Dokončení: všechny kroky mají výsledek, vzniklé testovací ID je zapsané,
nálezy jsou doložené a tester je odhlášený. PASS automatizace nenahrazuje lidské
posouzení použitelnosti ani obecnou přejímku celé aplikace.
