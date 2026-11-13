# KIS-ACT-01: podklad pro testerku — první přihlášení importovaných rodičů

Aktualizováno 13. 11. 2026. Tento soubor je aktuální pracovní scénář k nasazené změně [#463](https://github.com/KovoPraha/evidenceTreninku/pull/463). Technické vydání a jeho meze jsou v [release protokolu](verification/parent-activation-release-20261112/RESULTS.md); živý stav vydání vede [CURRENT_STATE](CURRENT_STATE.md). **Produkční pozvánky, aktivace rodiče a přihlášené UAT dosud nebyly provedeny.** Následující scénáře nejsou záznamem výsledku.

## Co se změnilo

- Existující profil dítěte se nezakládá znovu. Přístup rodiče tvoří samostatný veřejný účet a schválená vazba v `account_person_roles`; importovaný kontakt ani shoda jména vazbu neschvalují.
- Administrátor má přehled **Aktivace účtů KIS** na `https://kis.kovopraha.cz/kis_account_activation_admin.php`. Vidí importovanou osobu, kontakty, účet, stav e-mailu a vazby, případný samostatný účet sportovce, poslední stav pozvánky a doporučený další krok. Může hledat a filtrovat: Vše, Bez účtu, Čeká vazba, Neaktivní účet, Neověřený e-mail, Schválená vazba a Aktivní účet.
- Jen po individuální kontrole lze vybrat nejvýše 100 způsobilých účtů, uvést důvod, potvrdit kontrolu osoby, adresy a vazby a stisknout **Zařadit vybrané pozvánky**. Tento krok vytváří skutečný jednorázový odkaz ve frontě e-mailů.
- Neaktivní nebo neověřený importovaný účet nastaví první heslo na `https://kis.kovopraha.cz/booking/aktivace_uctu.php`. Odkaz platí 60 minut a lze jej použít jednou. Již aktivní ověřený účet dostává odkaz na běžnou obnovu hesla. Heslo se e-mailem neposílá.
- Pokud rodičovský účet neexistuje, rodič použije běžnou registraci, ověří e-mail a v **Moje osoby** požádá o vazbu k již evidovanému dítěti. Oprávněný administrátor žádost ověří v **Účty a osoby**. Samostatný účet sportovce je odlišný přístup.

## Předpoklady a bezpečná hranice

Testerka může nyní provést pouze čtecí kontrolu bez změny účtů. K pilotu s pozvánkou vedení předem schválí text zprávy, způsob ověření identity, přesné adresáty, rozsah malé dávky a řešení nedoručení. Správce připraví oddělené testovací účty a schránky se schválenými vazbami; jejich identifikátory předá testerce mimo veřejný Git. **Bez tohoto schválení nevybírat účty, nepotvrzovat kontrolu a neodesílat formulář pozvánek.** Neprovádět pokus na Alexandrovi ani jiném skutečném rodiči jen podle importního e-mailu.

Do repozitáře neukládat jména dětí, adresy, hesla, aktivační odkazy, tokeny ani snímky s osobními údaji. Výsledky zapisovat pod neosobními značkami `T01`–`T09`; citlivé důkazy zůstanou v klubem určeném soukromém úložišti. Stav `sent` znamená předání dopravci, nikoli potvrzené doručení do schránky.

## Scénáře pro čtecí kontrolu — dostupné nyní

| ID | Kroky | Očekávaný výsledek |
| --- | --- | --- |
| T01 | Bez přihlášení otevřít administrativní URL a veřejnou stránku aktivace bez tokenu. | Administrace odmítne přístup (HTTP 403); aktivační formulář se zobrazí, ale žádný účet se nezmění. |
| T02 | Přihlásit se oprávněným administrátorským účtem. Otevřít **Aktivace účtů KIS**, použít hledání a každý filtr, přejít na další stránku. | Přehled zůstává v administrativním rozsahu; u řádků jsou oddělené stavy účtu, e-mailu, vazby, sportovce a pozvánky. Není zobrazeno heslo ani token. |
| T03 | Na připravených neosobních datech prohlédnout stav **Bez účtu**, **Čeká vazba**, neaktivní/neověřený, aktivní/ověřený a duplicitní či sdílený e-mail. Neodesílat formulář. | Přehled ukazuje konkrétní další krok; účet bez schválené vazby nebo s nejednoznačnou adresou nelze vybrat k pozvání. Samostatný přístup sportovce se nezaměňuje s rodičovským účtem. |

## Scénáře schváleného pilotu — zatím NOT RUN

Pro každý zápis zaznamenat výchozí stav, přesný schválený testovací účet, čas, výsledek, audit a úklid mimo veřejný Git. Před odesláním znovu ověřit, že je schválený právě tento pilot.

| ID | Kroky | Očekávaný výsledek |
| --- | --- | --- |
| T04 | U jednoho neaktivního/neověřeného účtu se schválenou vazbou a ověřenou schránkou zaznamenat důvod kontroly, potvrdit kontrolu a zařadit jednu pozvánku. | Fronta zaznamená jeden požadavek; účet ještě není aktivní. Po skutečném doručení rodič otevře jednorázový odkaz a nastaví vlastní heslo. Aktivace ověří e-mail, zneplatní ostatní odkazy a staré relace. |
| T05 | Přihlásit aktivovaného testovacího rodiče přes `booking/prihlaseni.php`; otevřít **Moje osoby**, kroužky, soupisky a tréninky. | Vidí právě své dříve evidované a schválené děti včetně jejich historie; může použít rodičovské dostupné funkce, zejména potvrzení účasti a omluvu v připravených testovacích datech. Nevznikne nový profil dítěte. |
| T06 | Rodič se dvěma schválenými dětmi použije jeden aktivační odkaz. | Po přihlášení vidí obě správné děti; žádné cizí dítě. Čekající vazba kteréhokoli importovaného dítěte naopak brání pozvánce, dokud není individuálně vyřízena. |
| T07 | Aktivní ověřený testovací účet požádá přes administraci o obnovu hesla. | Dostane cestu stávající obnovy; původní vazby na děti se nemění. |
| T08 | Na odděleném testovacím účtu prověřit vypršelý a již použitý odkaz, změnu e-mailu či zrušenou vazbu před použitím. | Odkaz je odmítnut, účet se tím neaktivuje a dítě se nepřiřadí. Žádný token se nezobrazuje v administraci. |
| T09 | Pokud existuje jen profil dítěte, testovací rodič zaregistruje účet, ověří adresu a podá žádost v **Moje osoby**; správce ověří identitu v **Účty a osoby**. | Schválená vazba směřuje na původní ID sportovce a zachová členství, soupisku, docházku i historii; shoda jména sama propojení neprovede. |

## Záznam výsledku

U každého ID uvést `PASS`, `FAIL`, `BLOCKED` nebo `NOT RUN`, datum, roli a prostředí, neosobní značku účtu, očekávaný a skutečný výsledek, odkaz na soukromý důkaz a případný úklid. Oddělit stav fronty, předání dopravci, skutečné doručení, nastavení hesla a úspěšné přihlášení. Technický HTTP 200 ani stav `sent` samy o sobě neuzavírají rodičovské UAT. Výsledek pilotu navázat na [release protokol](verification/parent-activation-release-20261112/RESULTS.md) a [registr](WORK_PLAN_CURRENT.md#kis-act-01--první-heslo-importovaných-rodičů-12-11-2026).
