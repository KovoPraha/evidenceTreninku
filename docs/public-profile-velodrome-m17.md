# M1.7 – veřejný účastník a hodina velodromu

První slice znovu používá stávající `sportovci`, `sportovist`, `individualni_lekce` a `verejne_rezervace`. Nevytváří druhý rezervační ani platební systém.

## Veřejný profil

Přihlášený účet si v `booking/verejny_profil.php` založí právě jeden self profil. Povinné jsou jméno, příjmení, datum narození a platný kontaktní e-mail převzatý z účtu; telefon je doplňkový. Vazby `public_self_profiles.account_id` a `sportovec_id` jsou obě unikátní. Nový sportovec dostane deterministický interní hash odvozený z ID účtu a stav `cekajici`. Každé vytvoření, adopce již schválené self vazby nebo změna vytvoří auditní záznam.

Automatické párování podle jména, narození nebo e-mailu se záměrně neprovádí, protože by mohlo připojit cizí osobu. Pokud účet už má právě jednu schválenou roli `self` v K2, profil ji bezpečně převezme; více self rolí skončí fail-closed.

Každý profil zároveň vytváří nebo ověřuje právě jednu kanonickou aktivní vazbu `account_person_roles` s rolí `self`, takže funguje i v beneficiary a rodinných kontraktech. Audit používá samostatného neaktivního trenéra `Automat veřejných profilů`, vytvořeného migrací pouze jako systémovou auditní identitu. Nepředstírá zásah reálného trenéra a nelze se přes něj přihlásit.

## Velodrom

Správce vypíše hodinu v `verejny_velodrom_admin.php`. Záznam je běžná `individualni_lekce` na veřejném sportovišti s kódem `velodrom`. Může mít sdílenou kapacitu nebo výhradní režim, který má efektivní kapacitu jedna.

Rezervace v `booking/velodrom.php`:

- vyžaduje aktivní ověřený účet a dokončený self profil;
- zamkne profil/sportovce a řádek lekce, pak znovu ověří čas, překryv a kapacitu;
- ukládá konkrétní `sportovec_id` do existující `verejne_rezervace`;
- unikátní aktivní token chrání před duplicitou stejného účastníka ve slotu;
- odmítne i překrývající se aktivní rezervaci stejného sportovce;
- storno je account-scoped, uvolní aktivní token a vytvoří audit.

Starší stránka `moje_rezervace.php` pracuje nad stejnými řádky. Databázový trigger proto při jejím storno nebo zamítnutí veřejné rezervace rovněž uvolní aktivní token a zapíše `legacy_close`; starší průchod tak nemůže obejít audit ani zablokovat pozdější novou rezervaci.

## Finance

Bezplatná hodina se ihned uloží jako `potvrzena` a `zaplaceno=1`. Placená hodina použije stav `ceka`, `zaplaceno=0`. Administrátor ji ve `verejny_velodrom_admin.php` potvrdí pouze s CSRF, výslovným potvrzením a důvodem; jedna auditovaná transakce nastaví `zaplaceno=1` a `ceka → potvrzena`. Opakování je idempotentní a jiné stavy selžou. Tento slice nevytváří objednávku, QR platbu ani vlastní platební účetnictví.

Navazující integrace musí propojit rezervaci s variantou katalogu a `shop_order_items`/`shop_orders`. Vyžaduje změnu sdíleného checkoutu a doménový aktivační handler, proto je záměrně mimo M1.7. Do té doby je placený průchod vhodný jen pro localhost a ruční provozní ověření.
