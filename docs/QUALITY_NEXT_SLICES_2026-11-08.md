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

Stav ROZPRACOVÁNO; vlastník vývoj, zadání vlastníka: chybný čas nesmí odeslat
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
Merge a nasazení touto žádostí neprovedeny.


Q2-TIME implementováno ve větvi: časová kontrola v novém zápisu i editaci,
koncepty nového zápisu v prohlížeči a obnova při odmítnutí serverem.
[Výsledky a omezení](verification/training-form-resilience-20261109/RESULTS.md):
31/108 PHP a 18 browser kontrol PASS; plné CI čeká. Nasazení NOT RUN.
