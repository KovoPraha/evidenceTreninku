# Skupiny, soupisky a časové členství – implementační předání

Stav: implementace sloučena z PR #138 a nasazena na `kis.kovopraha.cz` jako `4991c91f966eb65852876fd0084dd6ad041e3b69` v [běhu 39472709660](https://github.com/KovoPraha/evidenceTreninku/actions/runs/39472709660). Migrace jsou aktuální (`catalog_count=106`, `pending=[]`); deploy neprováděl přiřazení živých soupisek ani zapnutí plateb či plánovaného opakování nabídek. Původní požadavky a rozhodnutí jsou v `ROSTER_GROUPS_TRAINING_SEASON_IMPLEMENTATION_PLAN_2026-10-20.md`.

## Co je připraveno

- `club_teams` rozlišuje nadřazenou **skupinu** a konkrétní **soupisku**; stávající sezonní cizí klíče zůstávají pro kompatibilitu. Časové vazby `club_team_edges` a intervaly `club_roster_members` umožňují zjistit členství k datu a odstraňují duplicity při více soupiskách. Nové přímé členství ve skupině se nepovoluje.
- Administrace hierarchie vytváří skupiny a před uložením hromadného zařazení soupisek ukazuje překryvy a dopady. Změnu blokuje, pokud by zasáhla budoucí trénink s odpověďmi či náhradami nebo akci s přihláškami. Samostatný přehled navrhuje pouze přesné, jednoznačné mapování starých skupin a podskupin na nové záznamy. Nejasné a nenalezené položky ponechává ke kontrole, nic automaticky nepřevádí.
- Plánování, záznam, editace, duplikace a správa tréninků používají společný výběr „skupina → soupiska“. Výběr skupiny zahrnuje potomky, výběr jedné nebo více soupisek jen jejich sjednocené členy. Staré záznamy zůstávají čitelné. Odpovědi, náhrady, kalendáře a rozklikávací roční přehled na kartě sportovce i v rodinném portálu používají datum tréninku; skutečnou účast nadále zadává trenér.
- Cílení akcí a jejich viditelnost pro sportovce a rodinu používají stejný výpočet členství. Jedna akce se při více cestách členství zobrazí jednou; stávající kontroly kapacity, uzávěrky a přihlášky zůstávají.
- Kroužkový produkt a soupiska mají stálou identitu. Datované nabídky pololetí nebo celého roku lze opakovat podle konfigurace. Objednávková položka uchová přesné ID nabídky pro pozdější aktivaci; staré objednávky mají kompatibilní cestu. Generátor má náhled, idempotentní zápis a vypnuté produkční spínače; podrobnosti jsou v `CLUB_PROGRAM_RECURRENCE_OPERATIONS.md`.
- Měsíční plán nad skupinou vyhodnocuje efektivní členy za příslušný měsíc a vytváří nejvýše jeden předpis na sportovce. Variabilní symbol je stálý pro účet plátce. Rodinnou bankovní platbu lze po ruční kontrole rozdělit mezi předpisy dětí, ponechat evidovaný zbytek a později jej přidělit. Fio pohyby se společným VS zůstávají ke schválení správcem; automatické párování není zapnuto.

## Neprovedené kroky a bezpečné uvedení do provozu

1. Produkční workflow vytvořilo a ověřilo zálohu a aplikovalo migrace. Zpětné obnovení této konkrétní zálohy a úplný uživatelský průchod na kopii produkčních dat dosud ověřeny nebyly; před hromadným datovým zařazením je vhodné je provést. CI ověřilo MariaDB 10.3 i 11.4.
2. V náhledu starých skupin zkontrolovat nejasné vazby a rozhodnout konkrétní zařazení současných soupisek pod Třebešín, Točnou a závodní tým. Před zápisem projít náhled překryvů a dopadu. Historické tréninky, odpovědi, přihlášky a objednávky nepřepisovat plošně.
3. Na kopii dat ověřit případy z původního plánu: dvě soupisky v jedné skupině, listopad versus březen, trénink celé skupiny a jedné soupisky, editace a duplikace, akce, pozdní úhrada staré nabídky, jeden měsíční předpis a společná rodinná platba. Zkontrolovat paritu počtů před/po migraci.
4. Pro používané kroužky po kontrole ceny, kapacity a podmínek jednou nastavit opakování nabídky. Plánovaný generátor a jeho zápis zapnout až po produkčním dry-runu s očekávanými počty. Měsíční předpisy i rozdělení bankovních plateb spouštět nejprve v malém ručně kontrolovaném rozsahu.

Živá administrace ukázala 43 soupisek bez skupiny a read-only porovnání starých názvů 0 přesných shod a 46 položek bez vazby. Nejsou převedeny nejednoznačné staré skupiny, nejsou automaticky vytvořeny rodičovské vazby pro produkční soupisky a nebyla spuštěna nová fakturace ani generátor nabídek.
