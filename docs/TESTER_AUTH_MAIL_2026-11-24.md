# TESTER — nová správa e-mailů KIS (AUTH-MAIL-05)

**Vydáno 24. 11. 2026. Stav přihlášené přejímky: NOT RUN.**
Nová funkce je v produkci po [PR #524](https://github.com/KovoPraha/evidenceTreninku/pull/524) a [nasazení 45396452344](https://github.com/KovoPraha/evidenceTreninku/actions/runs/45396452344). Tento návod popisuje, co má testerka ověřit; není záznamem, že e-mail skutečně dorazil.

## Co je nové

- Administrátor má stránku [Veškerá pošta](https://kis.kovopraha.cz/mail_admin.php). Původní `auth_mail_admin.php` na ni přesměruje. Stránka po 50 záznamech ukazuje ověření a obnovu hesla, klubové události, hromadné zprávy, týdenní souhrny, upomínky a členské zprávy. Nově zahrnuje i žádosti o registraci, nové ověření a obnovu, které se do fronty nezařadily.
- Filtrovat lze podle oblasti, stavu, přesné e-mailové adresy a reference žádosti. U zprávy je vidět počet pokusů, čas dalšího pokusu nebo přijetí a důvod chyby. Bezpečnostní odkaz ani celé tělo zprávy se v přehledu nezobrazuje.
- `pending` čeká na worker, `processing` se zpracovává, `failed` vyžaduje kontrolu důvodu, `cancelled` se již nemá odeslat. `sent` je na stránce označeno **přijato transportem**: PHP `mail()` zprávu přijalo, ale samo to nedokazuje doručení do schránky.
- Administrátor může u jednotlivé vhodné zprávy zadat důvod, potvrdit opakování a podle potřeby připojit skutečnou skrytou kopii (BCC). BCC je výchozně vypnutá, po zapnutí nejvýše na 7 dní a platí jen pro výslovně vybranou dosud neodeslanou zprávu. Kopie obsahuje i jednorázový odkaz.
- Nový ověřovací odkaz a rodičovský či dětský odkaz pro obnovu platí 3 dny. Odkaz pro trenérský reset platí 60 minut. Opakování starého nebo již přijatého autentizačního e-mailu vydá nový odkaz; starý se nemá dále používat.

## Před testem

Použijte pouze vlastní kontrolní rodičovský účet, unikátní testovací adresu a schránku, kterou můžete přečíst včetně spamu. Pro BCC použijte druhou kontrolní schránku pod dohledem administrátora. Má-li testerka jen běžnou roli, provede veřejnou část a referenci předá administrátorovi; přehled, opakování a BCC jsou pouze pro administrátora. Neopakujte skutečné rodičovské ani klubové zprávy kvůli testu. Nynějších 87 selhaných klubových zpráv se nesmí hromadně vrátit do fronty bez rozboru chyby a aktuálnosti.

## Scénáře

1. **Přehled bez zásahu.** Přihlaste se jako administrátor a otevřete [Veškerou poštu](https://kis.kovopraha.cz/mail_admin.php). Ověřte stránkování, filtry `Ověření / heslo` a `Klubové události`, dále stavy `sent` a `failed`. U `failed` zkontrolujte, zda je vidět důvod. U `sent` ověřte popisek „přijato transportem“, nikoli „doručeno“.
2. **Nová registrace.** Na [registraci](https://kis.kovopraha.cz/booking/registrace.php) založte pouze nový kontrolní účet. Uložte zobrazenou referenci požadavku. Administrátor ji vyhledá a ověří přechod žádosti do fronty a stav `pending` → `sent`, případně konkrétní chybu. V kontrolní schránce ověřte přijetí zprávy včetně spamu. Jednorázový odkaz případně použijte jen v hlavní kontrolní schránce.
3. **Obnova hesla.** U existujícího aktivního a ověřeného kontrolního účtu odešlete [žádost o obnovu](https://kis.kovopraha.cz/booking/zapomenute_heslo.php). Uložte referenci. Administrátor musí najít výsledek žádosti i odpovídající zprávu ve frontě; ověřte skutečné přijetí v kontrolní schránce. Veřejný formulář nesmí prozradit, zda účet existuje. Jeden pokus s neexistující kontrolní adresou může ověřit obecnou odpověď a administrátorsky dohledatelný důvod bez zprávy ve frontě; neopakujte jej kvůli limitům.
4. **Skrytá kopie, jen administrátor.** Zapněte testovací režim na 1 hodinu s důvodem a potvrzením. K právě čekající vlastní testovací zprávě přiřaďte druhou kontrolní adresu. Ověřte přijetí hlavní zprávy i BCC a že BCC adresa není vidět hlavnímu příjemci. **Odkaz z BCC neotevírejte:** jeho použití by mohlo spotřebovat jednorázový token. Poté režim vypněte. Pokud worker zprávu stihl zpracovat před přidáním BCC, zapište BCC test jako NOT RUN; nevracejte kvůli němu cizí zprávu do fronty.
5. **Opakování, jen řízený test.** Použijte výhradně vlastní kontrolní zprávu po domluvě se správcem testu. Akce vyžaduje důvod a potvrzení; zkontrolujte novou auditní stopu a u autentizační zprávy nový odkaz. Neopakujte `delivery_unknown` bez prověření doručení a neprovádějte hromadné opakování 87 klubových selhání.

## Záznam výsledku

U každého scénáře zapište `PASS`, `FAIL` nebo `NOT RUN`, místní čas, typ zprávy, referenci žádosti, stav/pokusy v administraci a zda dorazila do hlavní i kontrolní schránky. U neúspěchu přidejte zobrazený strojový důvod a informaci, zda byla zkontrolována složka spam. Do GitHubu ani snímků obrazovky nevkládejte celé e-mailové adresy, tokeny, odkazy pro obnovu hesla, obsah zprávy ani přihlašovací údaje; citlivý snímek před sdílením začerněte.

Technické důkazy a otevřené meze jsou ve [výsledku AUTH-MAIL-05](verification/auth-mail-unified-20261124/RESULTS.md) a [tematickém plánu](identity-email-queue.md#auth-mail-05--společný-přehled-pošty-kontrolní-kopie-a-bezpečné-opakování-24-11-2026).
