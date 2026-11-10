# P11-EVENTREAD — dávkové veřejné karty klubových akcí

10.11.2026, Europe/Prague. ROZPRACOVÁNO / výběrové měření před implementací.
Vlastník Vývoj/Codex, zadavatel Marek. Přímé zadání: další nejpřínosnější
optimalizace podle analýzy, přesná shoda/před-po; po všech PASS commit/merge/deploy.
Původní i souběžné WIP zachovat. Vlastní větev
codex/club-event-public-read-performance-20261110, worktree
C:/Users/Marek/Documents/Codex/evidencePavel-next-performance-20261110.
Base main966f7013, poslední ověřený deploy42863352343/9ef52268 SUCCESS.
Release/flags vlastní CURRENT; pro vlastní vydání UAT/importfalse.
Původní fio větev/d1221f35 a kis_rosters_admin.php SHA256
7a7afd75bfa34f736f158096ab46a671b7fee6c6faedc3701f989c0cf64733e5 beze změny.

## Nález a výběr

Navazuje na [analýzu, body1/2/6](verification/performance-analysis-20261107/RESULTS.md):
sdílené údaje načíst jednou pro výsledek, bez cache oprávnění a zásahu do writeru.
Dosavadní pricing/katalog/profily i ASSET1–5 jsou uzavřené. RIX indexové/SQL
varianty nesplnily gate; nevytvářet je znovu. HJS2 zůstává nevybraný prototyp,
bez převzetí WIP. Další CSS duplicity mají menší lokální přenosový přínos.

Čerstvý zdroj: clubEventOpenFreeList opakuje sessions, MIN kapacity, obsazenost
a waitlist pro každou událost; clubEventOpenPaidList první tři opakuje dokonce
pro každou prodejnou variantu téhož eventu. booking/krouzky.php volá oba seznamy.
Nejdřív změřit skutečný růst SQL a mezní scénáře, pak dávkovou projekci porovnat
s původními neměněnými API. Další N+1 eligibility pro osoby není tímto řezem měněné.

## Rozsah a akceptace před změnou

1. Samostatná čtecí projekce veřejné nabídky; dvě původní výběrová SQL s přesnými
filtry/řazením a PDO typy, dávkové termíny/kapacity/obsazenost/čekání podle ID.
Zachovat pořadí, více variant, nulové/negativní/null capacity override, všechny
statusy a sjednocení potvrzených osob mezi plánem a přihláškou. Fallback bez
planned tabulky i bez volitelných visibility sloupců zachovat. Bounded IN dávky.
2. Měnit pouze čtecí zapojení booking/krouzky.php a nový helper. Registrační,
cenové, refund/cancel/checkout writery, autorizace, role, CSRF, session,
podmínky a schéma zůstanou původní. Žádná persistentní cache nebo nové indexy.
Stávající scalar API budou neměnnou kompatibilní referencí a writer kontrolou.
3. SQLite výběrový probe a strict array/typy; MariaDB10.3/11.4 skutečná nativní
parita 0/12/100/500 událostí a více variant, všechny boundary stavy. SQL/rows/
PHP čas/paměť samostatně, 17 střídavých párů po4warmupech; nevydávat SQLite
čas za produkční latenci. Přijmout významné snížení SQL a měřený nativní přínos.
4. Celé HTTP před/po na stejném syntetickém plném migrovaném schématu. Public,
ověřená rodina, neověřený/revokovaný účet, prázdná nabídka; ceny/termíny/kapacity,
CSRF a anonimní POST denials. Přesná HTML shoda jen s request nonce/CSRF,
všechny skripty a data zachované; negativní mutace porovnávače. Domain checksumy.
5. Skutečný Apache/Chrome, desktop/mobile cold/warm,17 párů/4warmupy, pixel/
computed-style/role/form/select/shipping kontrakty, bez mocku sítě či latence.
Žádná produkční objednávka, rezervace, platba, e-mail ani změna oprávnění.
6. Všechno aktuální CI; main integrace bez ztráty souběžné práce, tested/merged
tree shodný; protected deploy pouze po přijetí. Backup/config/migrace/VS/indexy/
HTTP/cleanup a nové produkční GET před/po. Lidské UAT/fyzický telefon označit
NOT RUN, pokud se neprovedou. Výsledky/plán/handoff průběžně do repa.

Výběrové měření, nová implementace/native/browser/release zatím NOT RUN.
Další krok: základní diagnostický probe a bezpečná produkční veřejná baseline;
po prokázání růstu implementovat projekci a rozhodnout podle nového měření.
