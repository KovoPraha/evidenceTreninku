# Q01: provozní přejímky a příprava další funkce

Stav 8. 11. 2026: **připravené scénáře, bez provozního provedení a bez aktivace nové funkce**.
Navazuje na [průběžný plán](WORK_PLAN_CURRENT.md#koncept-q01--další-vývoj-doladění-úklid-příprava-8-11-2026).
PASS technických testů neznamená přijetí skutečného provozu. U každého kroku zapsat
roli, datum, prostředí, zdroj podkladu, PASS/FAIL/NOT RUN a odkaz na důkaz.

## Q4: jeden navazující provozní průchod

| Oblast | Pořadí a pozorovaný výsledek | Vstup a rozhodnutí člověka |
| --- | --- | --- |
| BAR P02 | Obsluha se přihlásí vlastními údaji; správce předloží fyzickou inventuru a skutečný příjem; obsluha otevře vlastní kasu doloženou počáteční hotovostí, provede první skutečný prodej a porovná položky, zásobu, úhradu a uzávěrku. Čekající karta/QR zůstane čekající do prokázané úhrady. Zapsat rozdíl očekávané a skutečné hotovosti. | Přítomnost Lucky nebo jiné oprávněné obsluhy, ověřené kusy po umístěních, počáteční hotovost, skutečná platba. Údaje nevytvářet z katalogu. [Provozní handoff](SESSION_HANDOFF_BAR_2026-11-06.md), [technická přejímka](verification/bar-acceptance-20261106/RESULTS.md). |
| BAR P04 | Z první reálné události porovnat `source_key`, částky zboží, zálohu, způsob platby, neměnný JSON a návazné storno/vratku. Pro kartu/QR dohledat existující bankovní nebo terminálový pohyb; export neznamená účetní zápis. | Hospodář/účetní schválí účty, DPH, ocenění, číselné řady a pravidla souhrnného vypořádání. Do té doby adaptér nic nezaúčtuje. [Kontrakt](BAR_ACCOUNTING_CONTRACT_V1.md), [plán adaptéru](BAR_ACCOUNTING_ADAPTER_PLAN_2026-11-05.md). |
| P05/P07 | Pro jednu vlastníkem určenou novou objednávku/členství sledovat identitu a oprávnění plátce, vznik závazku, skutečný bankovní pohyb s VS, přiřazení právě jednou, vytvořené PDF a přijetí zprávy v cílové schránce. Čas a ID každé události uložit do neveřejného provozního důkazu bez hesel a citlivých údajů v Gitu. | Vlastník obnoví odloženou skutečnou zkoušku a dodá ověřeného sportovce/plátce, objednávku a možnost potvrdit doručení. `sent` v transportu není potvrzení schránky. [Runbook](MONTHLY_MEMBERSHIP_RUNBOOK.md), [doklady](SHOP_DOCUMENT_AUTOMATION_2026-11-07.md). |
| P06 | Po dodání nového exportu nejprve zhotovit rozdílový náhled identit, skupin, intervalů, kolizí a počtů. Každou nejednoznačnost předložit k rozhodnutí; teprve schválený rozdíl převést a ověřit role a obnovu. | Vlastník dodá skutečný nový export a obnoví pozastavenou synchronizaci. Legacy E8 se neodstraňuje před přejímkou. [Pracovní plán](MEMBER_EVIDENCE_WORK_PLAN_2026-11-02.md). |

Nejbližší samostatný krok vývoje: připravit prázdný přejímací protokol s těmito poli
pro konkrétní termín a roli, jakmile jsou skutečné vstupy k dispozici. Provozní
zápisy se nesmějí odvozovat ze syntetických testů.

## Q5: návrh jedné funkce — soukromý osobní progres

Vybraný záměr: **C01/V5, čtecí časová řada vlastních normalizovaných sportovních
měření pro rodiče s právě schválenou vazbou na dítě**. Současný `sports-measurement-v1`
normalizuje nové údaje, ale historické nejednoznačné hodnoty a zátěžové testy
nejsou schválený zdroj progresu. [Datový milník](plan-eshop-tymova-evidence/12-milnik-m3-clenska-hodnota.md#m35--datová-kvalita-sportovního-progresu),
[backlog V5](plan-eshop-tymova-evidence/11-backlog-hodnota-pro-cleny.md#zařazené-řezy).

| Kontrakt návrhu | Rozhodnutí pro první řez / otevřená brána |
| --- | --- |
| Uživatel a účel | Rodič v přihlášené rodinné části otevře jen měření dítěte, k němuž má právě platnou schválenou vazbu. Vidí datum, typ, jednotku a hodnotu v časové řadě; porovnávají se pouze stejné disciplíny a metriky. Žádné veřejné pořadí, zdravotní interpretace, automatický verdikt ani notifikace. |
| Vlastník dat | Klub určí odpovědnou osobu za sportovní měření, opravy a pravidla zveřejnění rodině. Zdroj je existující normalizované měření v KIS; neodhadovat jednotky ze starého textu. Sportovec/rodič mají čtecí pohled, nikoli právo měnit originální trenérský záznam. |
| Oprávnění | Na každém požadavku se znovu ověří session a vazba `self`/`guardian` ke konkrétní osobě; samotné ID v URL nesmí udělit přístup. Revokace vazby okamžitě odebere výstup; trenérský rozsah se nesmí rozšířit přes rodinnou stránku. |
| Data a retence | První řez nic nového trvale neukládá; graf je odvozený z povolených záznamů `sports-measurement-v1`. Před implementací musí klub potvrdit účel, dobu uchování a výmaz podkladových měření včetně nezletilých. Zátěžové testy, nejednoznačné historické řádky a volné poznámky se nezobrazují. |
| Chyby a migrace | Bez schválených normalizovaných dat ukázat srozumitelný prázdný stav. Chybějící zdroj označit jako chybu, ne jako nulový výkon. Žádný automatický backfill; případný jednorázový import je oddělené rozhodnutí s ručním vyřešením všech nejednoznačností. Pro odvozený první řez se nepředpokládá nová tabulka. |
| Měřitelné přijetí | Na syntetických datech ověřit dvě rodiny s oddělenými dětmi, revokaci vazby během relace, hodnoty v m/km a ms, zamezení srovnání neslučitelných metrik, prázdný stav a nepřístupnost historických nejasností a zátěžových testů. Ověřit mobilní čitelnost 390/360 px a žádný nový zápis při GET. |

Tento kontrakt je podklad k produktovému a soukromí týkajícímu se rozhodnutí,
ne povolení implementace ani zpřístupnění dat. P15 ČSC/Sportsoft má vlastní
otevřené PR a neslučuje se s tímto řezem.


## Q2-TIME — odolný zápis tréninku (9. 11. 2026)

Stav NASAZENO / technické ověření PASS / cílené přihlášené UAT 3/3 PASS; vlastník vývoj. Chybný čas nesmí odeslat
formulář ani vymazat text; uchovat koncept při přerušení. Větev
`codex/training-form-resilience-20261109`, vlastní worktree
`C:/Users/Marek/Documents/Codex/evidencePavel-training-resilience-20261109`, base `2c61df14`.
Plán: sjednotit kontrolu měřeného času před odesláním, doplnit obnovu konceptu
nového tréninku v prohlížeči a zálohu při serverové chybě. Zachovat ochranu
proti dvojímu zápisu; koncept nikdy automaticky neodesílat. Koncept oddělit
podle trenéra a formuláře, mazat až po potvrzeném úspěchu. Přílohy nelze obnovit.
Akceptace: chybný čas neodešle POST; text a měření přežijí reload/zavření,
serverové odmítnutí; správný čas projde; jiný účet koncept neuvidí; nejasné
odeslání vyžaduje kontrolu historie. Ověřit cíleně PHP i skutečný browser.
Původní implementační žádost neobsahovala nasazení; následné pověření a výsledek níže.


Q2-TIME implementováno: časová kontrola v novém zápisu i editaci,
koncepty nového zápisu v prohlížeči a obnova při odmítnutí serverem.
[Výsledky a omezení](verification/training-form-resilience-20261109/RESULTS.md):
34/131 cíleně, 18 browser kontrol a plné CI 2409/54943 PASS.

Původní předání: [PR #412](https://github.com/KovoPraha/evidenceTreninku/pull/412), kódový commit `9dad0dfc` měl 11/11 CI PASS. Finální head `3fb1af8b` znovu 11/11 PASS, sloučený tree přesně shodný.


9. 11. 2026 vlastník výslovně zadal sloučit #412, nasadit, ověřit funkčnost
v produkci a uložit důkazy. Postup: převzít souběžné release docs, aktuální CI,
merge, chráněný deploy přesného main s importem/UAT flagem false; poté ověřit
nasazený JS a přihlášený formulář (neplatný čas, zachování konceptu, reload).
Ostrý trénink kvůli validační zkoušce nevytvářet. Výsledek zapsat do release
protokolu, CURRENT a handoffu. Souhlas nevyžaduje novou aktivaci automatizace.

Výsledek: #412 sloučený a nasazený, [produkční protokol](verification/training-form-resilience-release-20261109/RESULTS.md).
Deploy SUCCESS; 6 HTTP a přesný JS PASS. Předání důkazů vede
`codex/training-resilience-release-proof-20261109` ve stejném vlastním worktree.
Při původním vydání přihlášené UAT NOT RUN: Computer Use ukončil ovládání, protože nedokázal
bezpečně určit URL Chrome. Další krok: testerka projde šest bodů v protokolu,
zejména chybný čas, reload a znovuotevření konceptu. Otevřené UAT není PASS.

### Q2-TIME-UAT — nový pokus 9. 11. 2026

Vlastník zadal znovu ověřit chybný čas, zachování textu při reloadu a zavření
okna. Vlastník práce vývoj; větev `codex/training-form-uat-20261109`, vlastní
worktree shodný s Q2-TIME, base `fa8315eb`. Rozsah: syntetický rozepsaný
formulář v přihlášeném Chrome, bez uložení skutečného tréninku a bez zásahu
do cizích konceptů. Zavření vlastního testovacího okna/karty nezavírá cizí práci.
Akceptace: blokace chybného času a přesné zachování textu v obou návratech;
výsledky a hranice budou doplněné do [protokolu](verification/training-form-resilience-release-20261109/RESULTS.md).
Výsledek: vlastník se přihlásil a všechny tři požadované scénáře prošly.
[Produkční UAT 3/3 PASS a hranice](verification/training-form-uat-20261109/RESULTS.md):
blokace neplatného času, přesná obnova textu/měření při reloadu i po zavření
samostatného testovacího okna. Správný čas prošel čtecí kontrolou validity.
Platný submit nebyl proveden. Po potvrzení odstranění vlastního konceptu
selhala závěrečná kontrola CDP a nástroj zastavil ovládání kvůli neověřené URL.
Další krok: ověřit úklid konkrétního TEST konceptu; úplný zápis a výpadek při POST
zůstávají mimo tento převzatý rozsah. Důkazy neobsahují session ani submission klíče.
