# Fio: automatický hostingový provoz a 45 posouzených pohybů

Ověřeno 5. 11. 2026, Europe/Prague. Vlastník výslovně zadal autonomní
ověření plánovače a 45 čekajících pohybů, včetně oprav. Heslo a T07 vynechané.
[Příprava a rozsah](PLAN.md), [souhrnné důkazy](evidence.json).

## Co se opravilo a skutečně spustilo

GitHub plán deklaroval deset minut, ale předchozí události měly
několikahodinové rozestupy; ani po aktivaci neměl doložený úspěšný běh. Poslední
pozorovaný starší schedule 41995203532 byl skipped před aktivací.
[PR #312](https://github.com/KovoPraha/evidenceTreninku/pull/312) přidal
samostatný hostingový spouštěč do již existujícího CRON.1minuta.
Nový soubor zz-evidence-fio-import.php je řazený za trenérským workerem;
jiné cron soubory, hostingový plán, hesla ani oprávnění se neměnily.

[Instalace 42020650727](https://github.com/KovoPraha/evidenceTreninku/actions/runs/42020650727)
ověřila přesný zdroj vlastního cronu a neveřejného workeru. Status ihned po
instalaci správně nepotvrdil žádný automatický běh. Do hostingu se nepřenášel
token z runneru; worker čte stávající privátní konfiguraci aplikace.

Hosting nyní každou minutu vyvolává samostatný tick. Import je povolený
nejvýše jednou za 600 sekund; minutová granularita a jitter mohou další běh
posunout na následující minutu. Stav uchovává zvlášť tick a poslední skutečný
import. Ruční status/audit ani ruční import tento hostingový důkaz nevytvářejí.

| Skutečný automatický import | Čas zahájení | Importní ID | Nové / duplicity | Potvrzené úhrady |
| --- | --- | ---: | --- | ---: |
| První | 12:40:07 CET | 14 | 0 / 4 | 0 |
| Druhý | 12:51:03 CET | 15 | 0 / 4 | 0 |

Odstup skutečných importů je **656 sekund**. Oba mají trigger hosting_cron,
ok=true a caught_up=true. Pozorované ticky vzrostly z 2 na 12.
První [čtecí status 42020937481](https://github.com/KovoPraha/evidenceTreninku/actions/runs/42020937481)
a [druhý čtecí stav / audit 42022348440](https://github.com/KovoPraha/evidenceTreninku/actions/runs/42022348440)
pouze načetly existující heartbeat; žádný ruční tick/process nebyl spuštěn.
Po prvním úspěchu byl KIS_FIO_SCHEDULE_ENABLED přepnut na false a přečten zpět:
redundantní GitHub plán je vypnutý, chráněný ruční workflow zůstává zálohou.

Worker používá stávající fioSynchronize, bezpečnost přesného párování a společný
databázový importní zámek s ručním CLI. Překryv, deduplikace a 30sekundový
provider limit jsou zachované. Po chybě/přerušení nevzniká minutová smyčka API;
starší úspěšný import nezakryje novější přerušený pokus. Neodesílají se
bankovní příkazy a nepoužívá se posun bankovní zarážky.

## Výsledek všech 45 pohybů

[Audit před instalací 42019910210](https://github.com/KovoPraha/evidenceTreninku/actions/runs/42019910210)
a [audit po automatických importech 42022348440](https://github.com/KovoPraha/evidenceTreninku/actions/runs/42022348440)
proběhly v READ ONLY transakci. Pro každý pohyb se znovu použilo kanonické
posouzení VS, částky, měny a stavu; doplnily se diagnostické počty shod jen
podle částky a časové hranice. Report neobsahuje VS, částky, účty, jména ani
raw provider data. V [důkazech](evidence.json) je samostatný výsledek každého technického ID.

| Zjištění | Počet |
| --- | ---: |
| Posouzené čekající pohyby | 45 |
| Aktuální klasifikace review_unknown_vs | 45 |
| Alespoň jedna evidovaná platba se stejným VS | 0 |
| Pouze shoda částky s některou čekající platbou | 3 |
| Změněné stavy nebo potvrzené úhrady z těchto pohybů | 0 |

Pouze částkou podobné jsou interní pohyby **11, 16 a 27**. Jejich VS nemá
protějšek v evidovaných platbách, takže částka nedokládá totožnost platby.
Všech 45 bylo prověřeno a správně zůstává v review_unknown_vs. Ke skutečnému
přiřazení je potřeba doložený podklad propojující konkrétní pohyb s předpisem;
bez něj se nevytváří úhrada, dluh ani odhadnutá vazba. Původní požadavek
prověřit všechny položky je uzavřený; účetní identifikace zůstává otevřená.

Před/po je shodný celý report 45 položek a všech osm plateb ve stavech:
pending 2, paid 1, cancelled 1, refund_required 3, refunded 1. Automatické
importy vložily nula pohybů a potvrdily nula úhrad; duplicity jsou idempotentní.

## Kontroly vydání a meze

Všech 11 kontrol PR PASS. [Linux CI 42018987204](https://github.com/KovoPraha/evidenceTreninku/actions/runs/42018987204)
i [chráněný deploy 42019877235](https://github.com/KovoPraha/evidenceTreninku/actions/runs/42019877235)
ověřily **2029 testů / 38953 assertions bez skipů**, včetně MariaDB 10.3/11.4.
Přesný ověřený head a sloučený strom jsou shodné. Cílené místní Fio 35/233,
PHPStan, styl a dokumentační kontrakt PASS. Testy zahrnují 599/600 s, souběh,
výpadek, přerušení procesu, cizí soubor, zdroj/čerstvost a neměnnost čtených dat.

Čerstvá záloha evidence_2026-11-05_113522_e27fc12c.sql.gz má 347 tabulek a 2 triggery.
[Izolovaná obnova 42020816687](https://github.com/KovoPraha/evidenceTreninku/actions/runs/42020816687)
ověřila checksum, všechny počty a triggery. Produkční DB se neobnovovala.
Schéma current/pending [], ledger 150, původní VS zachované.
[Čtecí audit 42021224333](https://github.com/KovoPraha/evidenceTreninku/actions/runs/42021224333)
potvrdil živé vydání a nezměněný config hash, integrační flags, privátní nálezy
i canonical-write režim: [porovnání](release-comparison.json).

Anonymní HTTP [před](pre-http.json) / [po](post-http.json): **57/57 PASS**,
včetně nové CLI/private/cron hranice. Zdroj cronu a workeru byl ověřen proti
chráněnému main, nikoli jen podle existence souboru. Nejde o úplnou inventuru
hashů celé aplikace; historické manifesty auditu mají jiný pevný rozsah.
Nová placená úhrada, živé přesné spárování či SumUp vypořádání se touto
kontrolou netvrdí. Přihlášené UI UAT ani heslo/T07 se v této vlně neprováděly.

## Provozní kontrola

Z main spustit fio-operations-production.yml, action=status,
potvrzeni=OVERIT-FIO-PROVOZ. Očekávat installed_expected_source,
recent_hosting_tick a recent_successful_import true. Čerstvost ticku je
180 s a úspěšného importu 900 s; novější nedokončený pokus zdraví odmítne.
Action=audit pouze znovu přečte čekající pohyby. Pro změnu zdroje instalátor
odmítá odlišný existující soubor; připravit novou auditovanou verzi, nepřepisovat
ostatní úlohy. Nouzové zastavení importu/automatiky zajišťuje stávající
configure-fio-production.yml / disable s potvrzením VYPNOUT-FIO.
