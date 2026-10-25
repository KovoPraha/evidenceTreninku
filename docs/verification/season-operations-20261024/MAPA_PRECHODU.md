# Pracovní mapa nahrazení původních postupů

24. 10. 2026. Podklad k registru `club_cutover_admin.php`, nikoli schválený
import či odstavení. Potvrzená původní Evidence je na
`https://data.kovopraha.cz/evidence/index.php`; přesné používané podstránky
nebyly v tomto balíku prohlíženy. Původní KIS a samostatné seznamy potřebují
konkrétní inventuru. Uživatel potvrdil všechny níže zmíněné papírové agendy.

U všech řádků je **konkrétní vlastník i datum přechodu dosud neurčené**.
Role v tabulce je navržená odpovědnost, nikoli přiřazení konkrétnímu pracovníkovi.
Historie je návrh rozsahu k potvrzení a porovnání s původním zdrojem.
Do potvrzeného přechodu se nemění současné místo skutečného zápisu.

| Původní obrazovka / seznam | Nový pracovní postup | Navržená odpovědná role | Historie a kontrola před přechodem |
|---|---|---|---|
| Osoby v Evidence / původním KIS; název obrazovky ověřit | `person_lifecycle_admin.php` → kmenová osoba, vztahy a další kroky | Registrář | Identifikátory, doložené změny členství a vazby rodiny; sporné shody individuálně, neslučovat podle e-mailu |
| Skupiny / soupisky v Evidence a KIS; obrazovky ověřit | `season_preparation_admin.php` → hierarchie, intervaly a samostatná historická obnova | Registrář + vedoucí sportu | Časové zařazení, rodičovství skupin a výjimky; určit význam starých sezon |
| Původní rozvrh a plánované tréninky; konkrétní obrazovku ověřit | `planovac.php` → konkrétní lekce a svěření trenérům | Vedoucí sportu / pověřený trenér | Budoucí termíny, místo, trenér, skupiny; kontrola duplicit a kolizí |
| Evidence tréninků + papírová docházka a omluvy | `training_closure_admin.php` → plán, omluvy, skutečná účast, uzavření | Trenér + vedoucí sportu | Staré ID tréninků, účast, opravy; plán nezaměnit za uskutečněnou lekci |
| Papírové přihlášky a souhlasy | `paper_registrations_admin.php` → ověření konkrétního podkladu osoby | Registrář | Originál, verze textu, podpis a datum; chybějící důkaz nepovažovat za souhlas |
| Papír / Excel zdravotních prohlídek | `medical_clearance_admin.php` → platnost a podklady před aktivitou | Registrář | Účel, platnost, stav ověření, soukromý originál; rozsah uchování D11/D18 |
| Osobní organizace akcí + původní Evidence; konkrétní obrazovky určit | `club_event_work_admin.php` → účast, doprava a související vyúčtování | Vedoucí sportu / trenér; finance hospodář | Otevřené akce, odpovědnosti, účastníci a doprava; rezervace vozidla není skutečná jízda |
| Papír / Excel výdajů a záloh na akce | `club_expenses_admin.php` → výdaj, originál, schválení, vypořádání | Hospodář | Nevyúčtované zálohy, otevřené případy, částky a originály; sporné doklady D08 |
| Papír / Excel odměn trenérů a sportovců | `club_rewards_admin.php` → podklady, schválený nárok, vypořádání | Hospodář; vlastní podklady trenér | Výkazy, doložené sazby a schválení, zbývající výplaty; žádný odhad D04/D06 |
| Osobní kontakt a používané komunikační kanály; inventuru doplnit | `club_communication_admin.php` → vlastní fronta pracovní role / osobní vyřízení | Správce; obsah registrář / obsluha účtů | Otevřené požadavky a nejasná doručení; skutečný transport a plánovač ověřit podle D12 |
| Hotovost a osobní výdeje oblečení | Existující finanční a skladový postup; společná přejímka v E | Hospodář + skladník / obsluha prodeje | Skutečná pokladna, inventura, rezervace a nevypořádané případy D02/D09; mimo implementaci D |
| Papír / Excel členských příspěvků | Existující evidence předpisů a úhrad; společná přejímka v E | Hospodář + registrář | Období, plátce, předpis, doložená úhrada a otevřené saldo; D01–D03, bez hromadného přepisu |

## Konkrétní postup doplnění každého řádku

1. Správce založí přípravu s přesným rozsahem a konkrétním zdrojem. Návrhy
   balíku D nejsou automaticky uložené deklarace skutečného provozu.
2. Vlastník dodá skutečný název obrazovky / seznamu, veřejnou adresu bez
   přístupových parametrů a ukázku běžného případu včetně otevřených výjimek.
3. V nové agendě se projde tentýž případ na povolených testovacích datech.
   Do mapy se zaznamená konkrétní cílový postup, odpovědná osoba, dokazatelný
   rozsah historie a umístění důkazu. Návrh se teprve potom označí jako ověřený.
4. Oddíl určí jediné zapisující místo, přesnou časovou hranici, dokončení
   rozpracovaných případů, režim starého webu a náhradní evidenci při výpadku.
5. Reálná migrační dávka musí mít vlastní porovnání a vyřešené výjimky.
   Stav mapy není povolením tuto dávku spustit.
6. Veřejné adresy, přihlášení, soukromé soubory a callbacky se posuzují
   samostatně v existujícím druhu položky „Mapa starých adres“. Neznámé staré
   podstránky se nevymýšlejí a žádné automatické přesměrování není nastavené.

Pokud některý z těchto podkladů chybí, daná agenda zůstane označena jako návrh
nebo k dořešení. Ostatní nezávislé agendy lze dál připravovat. Půjčované vybavení
je podle zadání mimo rozsah.
