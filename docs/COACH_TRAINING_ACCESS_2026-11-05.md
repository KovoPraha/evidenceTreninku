# Tréninky a zastupování bez povinného přiřazení k soupisce

## Rozhodnutí vlastníka z 5. 11. 2026

Každý aktivní trenér může vypsat trénink a zadat skutečně proběhlý trénink i pro jiné než přidělené soupisky. Vlastní svěřenci a soupisky mají při výběru přednost. Přiřazení slouží v tréninkovém toku k řazení, nikoli jako podmínka zastupování.

Hláška „Pro vybrané datum nejsou dostupné skupiny ani soupisky.“ dříve vznikala i tehdy, když aktivní soupisky existovaly, ale všechny odfiltroval pracovní rozsah trenéra. Stejná kontrola následně blokovala i serverové uložení a výběr účastníků. Trénink bez předchozího plánu lze zapsat přímo přes „Zadat trénink“; předchozí vypsání termínu není podmínkou.

## Výsledné chování

- Plánování, přímý zápis, úprava vlastního záznamu a uzavření vlastní docházky používají všechny aktivní soupisky a existující účastníky. Kontrola platného pracovního účtu probíhá i na serveru a znovu v transakci. Změna data obnovuje stejný neomezující výběr.
- Výběr cíle má nejprve oddíl „Moje skupiny a soupisky“, potom „Další skupiny a soupisky“. Každý cíl má jediný checkbox, sdílené podskupiny zachovají popis rodičů. Členové přidělených soupisek se řadí první i v našeptávači účastníků a měření; ostatní lze vyhledat a vybrat.
- Bez jakéhokoli přiřazení se zobrazí všechny aktivní cíle a účastníci. Vypršení či odvolání přiřazení pouze odstraní přednostní řazení; nezabrání zastupování. Odebrání pracovní pozice nebo deaktivace účtu naopak zápis zastaví.
- Pro zastoupení trenéra se v plánovači přepne „Moje“ na „Vše“ a u dosud nezaevidovaného termínu se zvolí „Zadat evidenci“. Zapisující trenér smí otevřít i cizí naplánovaný termín; musí zachovat jeho datum. Po zápisu zůstává původní autor plánu a skutečný zapisující trenér je evidován u provedeného tréninku i v události `recorded_by_substitute`.
- Zachovává se ochrana před opakovaným odesláním a souběžným zaevidováním stejného plánu, kontrola skutečného času a kolizí. Přesuny, přejmenování nebo rušení cizího plánu a přepis již existující cizí evidence nadále podléhají dosavadním pravidlům vlastnictví; zastupující trenér může vytvořit zápis k dosud nezaevidovanému termínu.

Pracovní rozsahy zdravotních podkladů, soukromých profilů a jejich souborů zůstávají samostatnou autorizací. Nový kontext `training` v našeptávači poskytuje výběr a identifikaci účastníků pro trénink, nikoli obecný přístup k jejich soukromému profilu.

## Jak nastavit vlastní soupisky

Vlastní přiřazení spravuje **Vedoucí sportu → Svěřenci trenérů**, stránka `coach_scope_admin.php`. Rozbalí „Přidělit: Tým / soupiska“, vybere trenéra a konkrétní soupisku nebo skupinu, datum od, případný konec a důvod. Pro více soupisek přidá další přiřazení. Přidělená skupina zvýhodní v tréninkovém výběru také své platné podřízené soupisky a jejich členy.

Nová implementace sama nezaložila ani nezměnila žádné skutečné přiřazení. Přiřazení není nutné doplňovat, aby trenér mohl zapisovat nebo zastupovat.

## Implementace a ověření

Samostatná větev `codex/coach-training-without-roster-grants-20261105`, základ `97b85bb5`. Předchozí práce na měsíčních členstvích je oddělená ve větvi `codex/monthly-membership-flow-20261105`, commit `99716090`; tato oprava ji nevyžaduje.

Pravidla tréninkového přístupu a přednostního řazení jsou v `includes/coach_training_access.php`; přihlášení, role a autorství se znovu kontrolují při zápisu. Rozlišení zastupování v `coachScopeAssertPlan()` je explicitní argument, nikoli změna všech operací nad cizím plánem. Obecný výběr cíle klubových akcí se nemění.

Cílená sada zahrnuje trenéra bez grantů, přednost vlastních osob/soupisek, dostupnost ostatních, odebrání role, neaktivní účet, neplatné cíle, vlastní i cizí plán při zastupování, soukromý profil, plánovač, RSVP, docházku, měření a opakované odeslání. Prošlo **81 testů / 368 assertions**. Celá regresní sada prošla: **2 035 testů / 38 934 assertions**, 3 přeskočené testy prostředí, žádné selhání. Celý PHPStan PASS; dvě staré výjimky odstraněny skutečnou kontrolou DB připojení, baseline snížen o 58 hlášení. Kontrola stylu prošla pro 24 změněných PHP souborů; kontrola dokumentace a odkazů PASS. Místní prohlížeč zobrazil skutečný PHP výběr se syntetickými daty ve správném pořadí.

Nová migrace není potřeba. Produkční diagnostika v přihlášeném prohlížeči potvrdila stejnou prázdnou nabídku v trenérském kontextu vlastníka; přiřazení Václava Papíka nebylo samostatně měněno ani jeho úplnost ověřena. Přímá SSH diagnostika nebyla dostupná. Místní testy a náhled nejsou důkazem produkčního nasazení ani zápisu skutečného tréninku.
