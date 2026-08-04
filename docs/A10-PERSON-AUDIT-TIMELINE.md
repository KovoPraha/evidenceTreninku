# A10 – auditní časová osa osoby

`person_audit_admin.php` je administrační stránka pouze pro čtení. Přístup má jen přihlášený trenér s rolí `admin`.

Vyhledání probíhá podle přesného ID nebo části jména přímo v aktuálním databázovém spojení. Po zvolení sportovce helper `includes/person_audit_timeline.php` načte pouze jeho záznamy z existujících auditních proudů: vazby účtů, dětský přístup, objednávky s příjemcem položky, kroužkové programy, soupisky a jejich obnovy, přihlášky na události, veřejný profil a rezervace velodromu.

Časová osa nic nezapisuje a nepotřebuje migraci. Aktér se dohledává dávkově; neexistující jméno se zobrazí jako typ a ID. Důvod se ukáže jen tehdy, když jej zdroj skutečně uložil (u JSON pouze z bezpečně dekódovaných polí `reason`, `note` nebo `ended_reason`). Poškozený nebo příliš velký JSON se ignoruje. Stránkování dovoluje 25, 50 nebo 100 řádků a záměrně nejvýše 100 stran, aby ani administrátor nemohl jedním požadavkem vyvolat neomezené načítání historie.

Známé omezení: starší tabulky nemusí zaznamenávat důvod či aktéra všech změn. A10 tuto chybějící kauzalitu nedoplňuje odhadem. Událost nároku na osobu se objeví až po přiřazení ke sportovci; nevyřízené žádosti bez potvrzené osoby nelze pravdivě připojit ke konkrétní časové ose.

Localhost seed zapisuje obnovu hesla omezeného sportovního účtu jen tehdy, když
se uložené heslo skutečně liší od demo hesla. Opakovaný reset prostředí tak
nevytváří falešné změny a důležité auditní události nezapadnou v technickém šumu.
